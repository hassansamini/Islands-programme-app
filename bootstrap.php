<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_lib.php';
require_once __DIR__ . '/subindicator_schema.php';
session_start();

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function redirect(string $path): never {
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}
function current_user(): ?array {
    return $_SESSION['islands_user'] ?? null;
}
function require_login(): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}
function has_role(string ...$roles): bool {
    $u = current_user();
    if (!$u) return false;
    return in_array($u['role'], $roles, true);
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Invalid security token.');
    }
}
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = [$type, $message];
}
function get_flashes(): array {
    $x = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $x;
}
function workflow_label(string $state): string {
    return ['draft'=>'Draft','review'=>'Review','approval'=>'Approval','approved'=>'Completed'][$state] ?? ucfirst($state);
}

/*
 * Workflow routing policy:
 * Every authenticated portal user may route a record to any of the four
 * workflow phases. The route is always recorded in the audit trail.
 */
function can_route(string $role): bool {
    return current_user() !== null;
}
function can_transition(string $state, string $action, string $role): bool {
    if (!can_route($role)) return false;
    return in_array($action, [
        'submit_review','submit_approval','approve',
        'return_review','return_draft','reopen_draft','move_to'
    ], true);
}

/*
 * Upgrade the existing database in-place. This makes the application tolerant
 * of databases created by earlier portal builds.
 */
function ensure_portal_schema(): void {
    try {
        $pdo = db();

        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_id INT UNSIGNED NOT NULL,
            recipients TEXT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'logged',
            error_message TEXT NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=" . $pdo->quote(DB_NAME) . "
            AND TABLE_NAME='notification_log'")->fetchAll(PDO::FETCH_COLUMN);

        $definitions = [
            'status' => "ALTER TABLE notification_log ADD status VARCHAR(20) NOT NULL DEFAULT 'logged' AFTER body",
            'error_message' => "ALTER TABLE notification_log ADD error_message TEXT NULL AFTER status",
            'sent_at' => "ALTER TABLE notification_log ADD sent_at DATETIME NULL AFTER error_message",
        ];
        foreach ($definitions as $column => $sql) {
            if (!in_array($column, $cols, true)) {
                try { $pdo->exec($sql); } catch (Throwable $ignored) {}
            }
        }

        // API authentication tokens for machine-to-machine JSON access.
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            user_id INT UNSIGNED NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS email_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            host VARCHAR(190) NOT NULL DEFAULT 'smtp.gmail.com',
            port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
            encryption VARCHAR(10) NOT NULL DEFAULT 'tls',
            username VARCHAR(190) NOT NULL DEFAULT '',
            password TEXT NOT NULL,
            from_email VARCHAR(190) NOT NULL DEFAULT '',
            from_name VARCHAR(190) NOT NULL DEFAULT 'ISLANDS GEB Portal',
            public_url VARCHAR(500) NOT NULL DEFAULT 'http://localhost/islands_geb_portal'
        ) ENGINE=InnoDB");
        $pdo->exec("INSERT IGNORE INTO email_settings (id) VALUES (1)");

        // Upgrade users table for username-based portal login.
        $userCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=" . $pdo->quote(DB_NAME) . "
            AND TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('username', $userCols, true)) {
            try {
                $pdo->exec("ALTER TABLE users ADD username VARCHAR(80) NULL AFTER name");
                $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)");
            } catch (Throwable $ignored) {}
        }

    } catch (Throwable $ignored) {
        // Workflow actions still work if notification infrastructure is unavailable.
    }
}
ensure_portal_schema();
ensure_subindicator_schema();

function audit(int $submissionId, ?int $userId, string $action, ?string $from, ?string $to, string $note=''): void {
    $s = db()->prepare("INSERT INTO workflow_events (submission_id,user_id,action,from_state,to_state,note,created_at) VALUES (?,?,?,?,?,?,NOW())");
    $s->execute([$submissionId,$userId,$action,$from,$to,$note]);
}


function notify_assignment(array $submission, string $assignmentType, ?string $recipient): array {
    if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return ['sent'=>false,'error'=>'No valid recipient email address is assigned.','recipients'=>[]];
    }
    $recipient = strtolower(trim($recipient));
    $projectCode = $submission['project_code'] ?? $submission['project_id'] ?? 'Project';
    $indicatorCode = $submission['indicator_code'] ?? 'Indicator';
    $label = $assignmentType === 'reviewer' ? 'Reviewer' : 'Approver';
    $assignedName = $assignmentType === 'reviewer'
        ? ($submission['reviewer_name'] ?? $submission['reviewer'] ?? $recipient)
        : ($submission['approver_name'] ?? $submission['approver'] ?? $recipient);
    $subject = APP_NAME . ': You have been assigned as ' . $label . ' — ' . $projectCode . ' / ' . $indicatorCode;
    $publicUrl = rtrim((string)email_settings()['public_url'], '/') . '/submission.php?id=' . (int)$submission['id'];
    $body = "Dear ISLANDS Programme colleague,\n\n"
        . "You have been assigned as the " . $label . " for a GEB reporting record.\n\n"
        . $label . ": " . $assignedName . " <" . $recipient . ">\n"
        . "Project: " . $projectCode . "\n"
        . "Indicator: " . $indicatorCode . "\n"
        . "Country: " . ($submission['country'] ?? '—') . "\n"
        . "Reporting year: " . ($submission['reporting_year'] ?? '—') . "\n"
        . "Current status: " . workflow_label((string)($submission['state'] ?? 'draft')) . "\n\n"
        . "Open the record: " . $publicUrl . "\n\n"
        . "This is an automated notification from the ISLANDS GEB Portal.\n";
    $mail = smtp_send([$recipient], $subject, $body);
    $status = $mail['ok'] ? 'sent' : 'failed';
    try {
        $q = db()->prepare("INSERT INTO notification_log
            (submission_id,recipients,subject,body,status,error_message,sent_at,created_at)
            VALUES (?,?,?,?,?,?,?,NOW())");
        $q->execute([
            (int)$submission['id'], $recipient, $subject, $body, $status,
            $mail['error'] ?: null, $mail['ok'] ? date('Y-m-d H:i:s') : null
        ]);
    } catch (Throwable $ignored) {}
    return ['sent'=>$mail['ok'],'error'=>$mail['error'] ?? '','recipients'=>[$recipient]];
}

function notify_event(array $submission, string $action, string $toState): array {
    $pdo = db();
    $recipients = [];
    $add = static function (?string $email) use (&$recipients): void {
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array($email, $recipients, true)) {
            $recipients[] = $email;
        }
    };

    // Route notifications by destination stage.
    if ($toState === 'review') {
        $add($submission['reviewer_email'] ?? null);
    } elseif ($toState === 'approval') {
        $add($submission['approver_email'] ?? null);
    } elseif ($toState === 'approved') {
        $add($submission['submitter_email'] ?? null);
        $add($submission['reviewer_email'] ?? null);
        $add($submission['approver_email'] ?? null);
    } elseif ($toState === 'draft') {
        $add($submission['submitter_email'] ?? null);
        $add($submission['reviewer_email'] ?? null);
        $add($submission['approver_email'] ?? null);
    }

    if (!$recipients) {
        return ['sent'=>false,'error'=>'No recipient email address is assigned for this workflow action.'];
    }

    $projectCode = $submission['project_code'] ?? $submission['project_id'] ?? 'Project';
    $indicatorCode = $submission['indicator_code'] ?? 'Indicator';
    $subject = APP_NAME . ': ' . workflow_label($toState) . ' — ' . $projectCode . ' / ' . $indicatorCode;
    $publicUrl = rtrim((string)email_settings()['public_url'], '/') . '/submission.php?id=' . (int)$submission['id'];

    $recipientRole = $toState === 'review' ? 'Reviewer' : ($toState === 'approval' ? 'Approver' : 'Workflow participant');
    $recipientName = $toState === 'review' ? ($submission['reviewer_name'] ?? 'Assigned reviewer') : ($toState === 'approval' ? ($submission['approver_name'] ?? 'Assigned approver') : 'Programme team');
    $body = "Dear ISLANDS Programme colleague,\n\n"
        . "A GEB reporting record has been routed to a new workflow stage.\n\n"
        . "Previous status: " . workflow_label((string)($submission['previous_state'] ?? '')) . "\n"
        . "New status: " . workflow_label($toState) . "\n"
        . "Action: " . $action . "\n"
        . "Assigned " . $recipientRole . ": " . $recipientName . "\n"
        . "Project: " . $projectCode . "\n"
        . "Indicator: " . $indicatorCode . "\n"
        . "Country: " . ($submission['country'] ?? '—') . "\n"
        . "Reporting year: " . ($submission['reporting_year'] ?? '—') . "\n"
        . "Routed by: " . ($submission['routed_by_name'] ?? 'Portal user') . "\n\n"
        . "Open the record: " . $publicUrl . "\n\n"
        . "This is an automated notification from the ISLANDS GEB Portal.\n";

    $mail = smtp_send($recipients, $subject, $body);
    $status = $mail['ok'] ? 'sent' : 'failed';
    $err = $mail['error'] ?? '';

    // Ensure an old database can receive the new logging fields.
    try {
        $s = $pdo->prepare("INSERT INTO notification_log
            (submission_id,recipients,subject,body,status,error_message,sent_at,created_at)
            VALUES (?,?,?,?,?,?,?,NOW())");
        $s->execute([
            (int)$submission['id'],
            implode(', ', $recipients),
            $subject,
            $body,
            $status,
            $err ?: null,
            $mail['ok'] ? date('Y-m-d H:i:s') : null
        ]);
    } catch (Throwable $ignored) {
        // Never make a successful workflow transition fail because logging failed.
    }

    return ['sent'=>$mail['ok'],'error'=>$err,'recipients'=>$recipients];
}
