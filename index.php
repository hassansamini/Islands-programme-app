<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$pdo = db();
$total = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$draft = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE state='draft'")->fetchColumn();
$review = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE state='review'")->fetchColumn();
$approval = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE state='approval'")->fetchColumn();
$approved = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE state='approved'")->fetchColumn();
$projects = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE active=1")->fetchColumn();
$recent = $pdo->query("SELECT s.*,p.project_code,p.project_name,i.code indicator_code,i.name indicator_name,u.name submitter FROM submissions s JOIN projects p ON p.id=s.project_id JOIN indicators i ON i.id=s.indicator_id LEFT JOIN users u ON u.id=s.submitted_by ORDER BY s.updated_at DESC LIMIT 8")->fetchAll();
$flashes = get_flashes();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | ISLANDS GEB Portal</title><link rel="stylesheet" href="assets/app.css"></head>
<body><div class="app-shell">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="main"><header class="topbar"><div><div class="eyebrow">PROGRAMME OVERVIEW</div><h1>Good to see you, <?=e(explode(' ',$u['name'])[0])?></h1></div><div class="top-actions"><span class="role-pill"><?=e($u['role'])?></span><a class="avatar" href="profile.php"><?=e(strtoupper(substr($u['name'],0,1)))?></a></div></header>
<?php foreach ($flashes as $f): ?><div class="alert <?=e($f[0])?>"><?=e($f[1])?></div><?php endforeach; ?>
<section class="hero"><div><span class="hero-kicker">ISLANDS GEB MONITORING</span><h2>From reporting to verified impact.</h2><p>Manage Global Environmental Benefits data across the ISLANDS Programme with a clear, auditable workflow.</p><a class="btn white" href="submission.php">+ New GEB submission</a> <a class="btn white" href="export.php?format=xlsx">↓ Export Excel</a></div><div class="hero-art"><div class="ring r1"></div><div class="ring r2"></div><div class="hero-stat"><strong><?=$approved?></strong><span>approved records</span></div></div></section>
<section class="stats"><div class="stat-card"><span>Total submissions</span><strong><?=$total?></strong><small>Programme records</small></div><div class="stat-card warning"><span>Drafts</span><strong><?=$draft?></strong><small><a href="submissions.php?state=draft" style="color:inherit;text-decoration:none">Awaiting submission →</a></small></div><div class="stat-card review"><span>In review</span><strong><?=$review?></strong><small>Requires attention</small></div><div class="stat-card warning"><span>Awaiting approval</span><strong><?=$approval?></strong><small>Ready for final approval</small></div><div class="stat-card success"><span>Completed</span><strong><?=$approved?></strong><small>Verified records</small></div></section>
<div class="grid-2"><section class="panel"><div class="panel-head"><div><div class="eyebrow">RECENT ACTIVITY</div><h3>Latest submissions</h3></div><a href="submissions.php">View all →</a></div><div class="table-wrap"><table><thead><tr><th>Project</th><th>Indicator</th><th>Year</th><th>Status</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><a class="table-link" href="submission.php?id=<?=$r['id']?>"><?=e($r['project_code'])?></a><small><?=e($r['project_name'])?></small></td><td><span class="code"><?=e($r['indicator_code'])?></span><small><?=e($r['indicator_name'])?></small></td><td><?=$r['reporting_year']?></td><td><span class="status <?=e($r['state'])?>"><?=e(workflow_label($r['state']))?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><div class="panel-head"><div><div class="eyebrow">PROGRAMME SNAPSHOT</div><h3>Coverage</h3></div></div><div class="coverage"><div><strong><?=$projects?></strong><span>Active child projects</span></div><div><strong>13</strong><span>GEB indicators</span></div><div><strong>6</strong><span>Programme regions</span></div></div><div class="callout"><strong>Workflow</strong><p>Draft → Review → Approval. Every transition creates an audit event.</p></div></section></div>
</main></div></body></html>
