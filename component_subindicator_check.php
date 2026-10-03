<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$pdo = db();

$counts = [
    'components' => (int)$pdo->query("SELECT COUNT(*) FROM components WHERE active=1")->fetchColumn(),
    'sub_indicators' => (int)$pdo->query("SELECT COUNT(*) FROM sub_indicators WHERE active=1")->fetchColumn(),
    'project_links' => (int)$pdo->query("SELECT COUNT(*) FROM project_sub_indicator_links WHERE active=1")->fetchColumn(),
    'projects_with_geb_submissions' => (int)$pdo->query("SELECT COUNT(*) FROM projects p WHERE p.active=1 AND EXISTS (SELECT 1 FROM submissions s WHERE s.project_id=p.id)")->fetchColumn(),
];

?><!doctype html><html><head><meta charset="utf-8"><title>ISLANDS linkage check</title><link rel="stylesheet" href="assets/app.css"></head>
<body><div class="app-shell"><?php include __DIR__.'/partials/sidebar.php'; ?><main class="main">
<header class="topbar"><div><div class="eyebrow">SYSTEM CHECK</div><h1>Component / Sub-indicator linkage</h1><p class="muted">The fixed build has re-run the catalogue and attribution seeding.</p></div></header>
<section class="panel"><div class="table-wrap"><table class="wide"><thead><tr><th>Check</th><th>Count</th></tr></thead><tbody>
<?php foreach($counts as $k=>$v): ?><tr><td><?=e(ucwords(str_replace('_',' ',$k)))?></td><td><strong><?=e((string)$v)?></strong></td></tr><?php endforeach; ?>
</tbody></table></div>
<p style="margin-top:18px"><a class="btn primary" href="subindicator.php">Open New Sub-indicator Report</a>
<a class="btn secondary" href="subindicator_catalogue.php">Open Catalogue</a></p>
</section></main></div></body></html>
