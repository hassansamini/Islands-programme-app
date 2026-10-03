<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('workflow.php');
}
verify_csrf();

$id = (int)($_POST['submission_id'] ?? $_GET['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
$pdo = db();

if ($id <= 0) {
    flash('danger', 'No submission was specified.');
    redirect('workflow.php');
}

$s = $pdo->prepare("
    SELECT s.*, p.project_code, i.code indicator_code,
           u.email submitter_email,
           u.name submitter_name,
           r.name reviewer_name,
           r.email reviewer_email,
           a.name approver_name,
           a.email approver_email
    FROM submissions s
    JOIN projects p ON p.id=s.project_id
    JOIN indicators i ON i.id=s.indicator_id
    JOIN users u ON u.id=s.submitted_by
    LEFT JOIN users r ON r.id=s.reviewer_id
    LEFT JOIN users a ON a.id=s.approver_id
    WHERE s.id=?
");
$s->execute([$id]);
$sub = $s->fetch();

if (!$sub) {
    flash('danger', 'Submission not found.');
    redirect('workflow.php');
}

$from = (string)$sub['state'];

/* New universal routing action. */
if ($action === 'move_to') {
    $target = (string)($_POST['target_state'] ?? '');
    $allowed = ['draft','review','approval','approved'];

    if (!in_array($target, $allowed, true)) {
        flash('danger', 'Invalid workflow destination.');
        redirect('submission.php?id='.$id);
    }

    if (!can_route($u['role'])) {
        flash('danger', 'You are not authorized to route workflow records.');
        redirect('submission.php?id='.$id);
    }

    if ($target === $from) {
        flash('warning', 'The submission is already in '.workflow_label($target).'.');
        redirect('submission.php?id='.$id);
    }

    $to = $target;

    $actionLabel = 'Moved to ' . workflow_label($to);
    if ($from === 'draft' && $to === 'review') {
        $notifyAction = 'submit_review';
    } elseif ($from === 'review' && $to === 'approval') {
        $notifyAction = 'submit_approval';
    } elseif ($to === 'approved') {
        $notifyAction = 'approve';
    } elseif ($to === 'draft') {
        $notifyAction = 'return_draft';
    } else {
        $notifyAction = 'return_review';
    }
} else {
    /* Backward compatibility with older buttons/links. */
    $legacy = [
        'submit_review'   => 'review',
        'submit_approval' => 'approval',
        'approve'         => 'approved',
        'return_review'   => 'review',
        'return_draft'   => 'draft',
        'reopen_draft'    => 'draft',
    ];

    if (!isset($legacy[$action])) {
        flash('danger', 'Unknown workflow action.');
        redirect('submission.php?id='.$id);
    }

    if (!can_route($u['role'])) {
        flash('danger', 'You are not authorized to route workflow records.');
        redirect('submission.php?id='.$id);
    }

    $to = $legacy[$action];
    $actionLabel = [
        'submit_review'=>'Submitted for Review',
        'submit_approval'=>'Submitted for Approval',
        'approve'=>'Approved & Completed',
        'return_review'=>'Returned to Review',
        'return_draft'=>'Returned to Draft',
        'reopen_draft'=>'Reopened as Draft'
    ][$action] ?? 'Workflow updated';
    $notifyAction = $action;
}

$s = $pdo->prepare("
    UPDATE submissions
    SET state=?, updated_at=NOW(), approved_at=?
    WHERE id=?
");
$s->execute([
    $to,
    $to === 'approved' ? date('Y-m-d H:i:s') : null,
    $id
]);

// Keep GEF #11.1 and #11.2 derived records synchronized with the parent GEF #11 workflow state.
try { sync_geb11_derived_records($id); } catch (Throwable $ignored) {}

audit(
    $id,
    (int)$u['id'],
    $actionLabel,
    $from,
    $to,
    'Workflow transition completed by '.$u['name'].'.'
);

$sub['previous_state'] = $from;
$sub['state'] = $to;
$sub['routed_by_name'] = $u['name'];
$sub['routed_by_email'] = $u['email'] ?? '';

/* Email/logging failure can never undo a successful workflow transition. */
$mailResult = notify_event($sub, $notifyAction, $to);

flash('success', 'Workflow action completed. The submission is now '.workflow_label($to).'.');

if (!$mailResult['sent'] && !empty($mailResult['error'])) {
    flash('warning', 'Workflow completed, but email notification was not sent: '.$mailResult['error']);
}

redirect('submission.php?id='.$id);
