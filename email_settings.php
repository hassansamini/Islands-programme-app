<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
if (!has_role('GEB Reviewer', 'FAO Task Manager', 'UNEP Task Manager')) {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $host = trim((string)($_POST['host'] ?? ''));
    $port = (int)($_POST['port'] ?? 587);
    $encryption = (string)($_POST['encryption'] ?? 'tls');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $from = trim((string)($_POST['from_email'] ?? ''));
    $fromName = trim((string)($_POST['from_name'] ?? APP_NAME));
    $publicUrl = rtrim(trim((string)($_POST['public_url'] ?? '')), '/');

    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) $encryption = 'tls';
    if ($port < 1 || $port > 65535) $port = 587;
    if ($password === '') {
        $password = (string)$pdo->query("SELECT password FROM email_settings WHERE id=1")->fetchColumn();
    }

    $q = $pdo->prepare("UPDATE email_settings SET enabled=?,host=?,port=?,encryption=?,username=?,password=?,from_email=?,from_name=?,public_url=? WHERE id=1");
    $q->execute([$enabled, $host, $port, $encryption, $username, $password, $from, $fromName, $publicUrl]);
    flash('success', 'Email notification settings saved.');
    redirect('email_settings.php');
}

$s = email_settings();
$logs = $pdo->query("SELECT n.*,s.project_id FROM notification_log n JOIN submissions s ON s.id=n.submission_id ORDER BY n.id DESC LIMIT 10")->fetchAll();
$flashes = get_flashes();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Email notifications | ISLANDS</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="main">
<header class="topbar">
    <div>
        <div class="eyebrow">GOVERNANCE</div>
        <h1>Email notifications</h1>
        <p class="muted">Configure SMTP and send workflow notifications automatically.</p>
    </div>
    <span class="role-pill"><?= email_configured() ? 'SMTP configured' : 'SMTP not configured' ?></span>
</header>

<?php foreach ($flashes as $flash): [$type, $msg] = $flash; ?>
<div class="alert <?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach; ?>

<section class="panel">
<div class="section-title"><span>01</span><div><div class="eyebrow">SMTP CONFIGURATION</div><h3>Outgoing email</h3></div></div>
<form method="post" class="form-grid">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<label class="check-row"><input type="checkbox" name="enabled" <?= $s['enabled'] ? 'checked' : '' ?>> <span>Enable email notifications</span></label>
<label>SMTP host<input name="host" value="<?= e((string)$s['host']) ?>" required></label>
<label>SMTP port<input type="number" name="port" value="<?= e((string)$s['port']) ?>" required></label>
<label>Encryption<select name="encryption">
<option value="tls" <?= $s['encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (recommended)</option>
<option value="ssl" <?= $s['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS</option>
<option value="none" <?= $s['encryption'] === 'none' ? 'selected' : '' ?>>None</option>
</select></label>
<label>SMTP username<input type="email" name="username" value="<?= e((string)$s['username']) ?>" placeholder="your-account@example.com" required></label>
<label>SMTP password / app password<input type="password" name="password" placeholder="Leave blank to keep existing password"></label>
<label>From email<input type="email" name="from_email" value="<?= e((string)$s['from_email']) ?>" placeholder="notifications@example.com" required></label>
<label>From name<input name="from_name" value="<?= e((string)$s['from_name']) ?>" required></label>
<label class="full">Portal public URL<input name="public_url" value="<?= e((string)$s['public_url']) ?>" required><small>Use a URL reachable by email recipients. Do not use localhost if recipients are on another computer.</small></label>
<div class="full form-actions"><button class="btn primary" type="submit">Save email settings</button></div>
</form>

<form method="post" action="email_test.php" class="form-actions" style="margin-top:10px">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<button class="btn secondary" type="submit">Send test email to my profile address</button>
</form>
</section>

<section class="panel">
<div class="panel-head"><div><div class="eyebrow">DELIVERY LOG</div><h3>Recent notifications</h3></div></div>
<div class="table-wrap"><table>
<thead><tr><th>Date</th><th>Recipients</th><th>Subject</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($logs as $log): ?>
<tr>
<td><?= e($log['created_at']) ?></td>
<td><?= e($log['recipients']) ?></td>
<td><?= e($log['subject']) ?></td>
<td><span class="status <?= e($log['status'] ?? 'logged') ?>"><?= e($log['status'] ?? 'logged') ?></span><?php if (!empty($log['error_message'])): ?><small><?= e($log['error_message']) ?></small><?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="4" class="empty">No notifications yet.</td></tr><?php endif; ?>
</tbody></table></div>
</section>

<div class="setup-note"><strong>Gmail</strong><br>Use <code>smtp.gmail.com</code>, port <code>587</code>, STARTTLS, your Gmail address as the username, and a Gmail <strong>App Password</strong> as the SMTP password. Do not use your normal Gmail password.</div>
</main>
</div>
</body>
</html>
