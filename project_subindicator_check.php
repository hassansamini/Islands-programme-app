<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/subindicator_link_repair.php';
$u=require_login(); $pdo=db(); ensure_project_subindicator_links();

$projects=$pdo->query("SELECT p.project_code,p.project_name,COUNT(DISTINCT l.sub_indicator_id) sub_count,COUNT(l.id) link_count
FROM projects p LEFT JOIN project_sub_indicator_links l ON l.project_id=p.id AND l.active=1
GROUP BY p.id ORDER BY p.project_code")->fetchAll();

$rows=$pdo->query("SELECT p.project_code,c.code component_code,si.code sub_code,si.name sub_name,
COALESCE(GROUP_CONCAT(DISTINCT i.code ORDER BY i.code SEPARATOR ', '),'No direct GEB linkage') gebs,
MAX(l.project_logframe_reference) reference
FROM project_sub_indicator_links l
JOIN projects p ON p.id=l.project_id
JOIN sub_indicators si ON si.id=l.sub_indicator_id
JOIN components c ON c.id=si.component_id
LEFT JOIN indicators i ON i.id=l.geb_indicator_id
WHERE l.active=1
GROUP BY p.id,c.id,si.id ORDER BY p.project_code,c.sort_order,si.sort_order,si.code")->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Project linkage check | ISLANDS</title><link rel="stylesheet" href="assets/app.css"></head>
<body><div class="app-shell"><?php include __DIR__.'/partials/sidebar.php'; ?><main class="main">
<header class="topbar"><div><div class="eyebrow">PROGRAMME FRAMEWORK</div><h1>Project / sub-indicator linkage check</h1><p class="muted">This page verifies the actual database attribution used by the reporting form.</p></div></header>
<section class="panel"><div class="panel-head"><h3>Project coverage</h3></div>
<div class="table-wrap"><table class="wide"><thead><tr><th>Project</th><th>Mapped sub-indicators</th><th>Link rows</th></tr></thead><tbody>
<?php foreach($projects as $r):?><tr><td><strong><?=e($r['project_code'])?></strong> — <?=e($r['project_name'])?></td><td><?=e((string)$r['sub_count'])?></td><td><?=e((string)$r['link_count'])?></td></tr><?php endforeach;?>
</tbody></table></div></section>
<section class="panel"><div class="panel-head"><h3>Actual project → component → sub-indicator mapping</h3></div>
<div class="table-wrap"><table class="wide"><thead><tr><th>Project</th><th>Component</th><th>Sub-indicator</th><th>GEB linkage</th><th>Project reference</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=e($r['project_code'])?></td><td><?=e($r['component_code'])?></td><td><strong><?=e($r['sub_code'])?></strong> — <?=e($r['sub_name'])?></td><td><?=e($r['gebs'])?></td><td><?=e($r['reference'])?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5">No project mappings exist in the database.</td></tr><?php endif;?>
</tbody></table></div></section>
</main></div></body></html>
