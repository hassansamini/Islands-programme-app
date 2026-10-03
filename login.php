<?php
require_once __DIR__ . '/bootstrap.php';
if (current_user()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $s = db()->prepare("SELECT * FROM users WHERE (email=? OR username=?) AND active=1 LIMIT 1");
    $login = trim((string)($_POST['login'] ?? $_POST['email'] ?? ''));
    $s->execute([$login, $login]);
    $u = $s->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        unset($u['password_hash']);
        $_SESSION['islands_user'] = $u;
        redirect('index.php');
    }
    $error = 'The username/email address or password is not correct.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | ISLANDS</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="login-page"><div class="login-shell">
<div class="login-visual"><div class="brand-lockup light"><img class="login-logo" src="assets/islands-logo.png" alt="ISLANDS"><div><span>Programme</span></div></div><div class="visual-copy"><div class="eyebrow">GLOBAL ENVIRONMENTAL BENEFITS</div><h1>Data that turns programme results into evidence.</h1><p>Secure submission, review and approval of ISLANDS monitoring data across child projects and countries.</p></div><div class="visual-footer">Implementing Sustainable Low and Non-Chemical Development in Small Island States</div></div>
<div class="login-panel"><div class="mobile-brand brand-lockup"><img class="login-logo-dark" src="assets/islands-logo.png" alt="ISLANDS"><div><span>GEB Portal</span></div></div><div class="login-card"><div class="eyebrow">WELCOME BACK</div><h2>Sign in to the portal</h2><p class="muted">Use your programme account to continue.</p>
<?php if ($error): ?><div class="alert danger"><?=e($error)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label>Username or email<input name="login" required autocomplete="username" placeholder="Username or email address"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Enter your password"></label>
<button class="btn primary full" type="submit">Sign in <span>→</span></button></form>
<div class="demo-box"><strong>Demo access</strong><br>samini@example.org · ISLANDS2026!</div>
</div><div class="login-foot">ISLANDS Programme Monitoring & Reporting Portal</div></div></div></body></html>
