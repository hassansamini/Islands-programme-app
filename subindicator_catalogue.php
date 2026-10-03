<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_login();
$pdo=db();
$rows=$pdo->query("SELECT c.*,si.id sub_id,si.code sub_code,si.name sub_name,si.indicator_type,si.unit,si.description sub_description FROM components c LEFT JOIN sub_indicators si ON si.component_id=c.id AND si.active=1 WHERE c.active=1 ORDER BY c.sort_order,si.sort_order,si.code")->fetchAll();
$groups=[];
foreach($rows as $r){
    if(!isset($groups[$r['id']])) $groups[$r['id']]=['component'=>$r,'subs'=>[]];
    if($r['sub_id']) $groups[$r['id']]['subs'][]=$r;
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Components & Sub-indicators | ISLANDS</title>
<link rel="stylesheet" href="assets/app.css">
<style>
.framework-page{max-width:1480px}
.framework-intro{display:flex;justify-content:space-between;align-items:flex-end;gap:28px;margin-bottom:24px}
.framework-intro h1{margin:4px 0 0;font-size:30px;color:#123c5a}
.framework-intro p{max-width:760px;line-height:1.6;margin:7px 0 0;color:#6d7e8e}
.framework-meta{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}
.framework-meta span{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eef6fa;color:#27627d;font-size:10px;font-weight:800}
.framework-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
.framework-card{background:#fff;border:1px solid #dbe6ed;border-radius:16px;box-shadow:0 7px 24px rgba(15,43,65,.055);overflow:hidden;display:flex;flex-direction:column;min-width:0}
.framework-card-head{padding:22px 24px 18px;border-bottom:1px solid #e8eef2;background:linear-gradient(180deg,#fbfdfe,#f7fafc)}
.framework-card-kicker{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:12px}
.framework-number{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:28px;padding:0 10px;border-radius:8px;background:#e7f3fa;color:#0969b5;font-size:10px;font-weight:900;letter-spacing:.5px}
.framework-count{font-size:10px;font-weight:800;color:#81909b}
.framework-card h2{font-size:17px;line-height:1.35;color:#123c5a;margin:0 0 9px}
.framework-outcome{font-size:11px;line-height:1.65;color:#657987;margin:0}
.indicator-list{padding:8px 14px 14px}
.indicator-row{display:grid;grid-template-columns:58px minmax(0,1fr) auto;gap:13px;align-items:center;padding:14px 10px;border-bottom:1px solid #edf1f3;text-decoration:none;color:#17324d;border-radius:9px}
.indicator-row:last-child{border-bottom:0}
.indicator-row:hover{background:#f4f9fb}
.indicator-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;font-weight:900;color:#0969b5;background:#edf6fa;border-radius:6px;padding:6px 7px;text-align:center}
.indicator-main strong{display:block;font-size:12px;line-height:1.45;color:#1c4864}
.indicator-main small{display:block;margin-top:4px;color:#8a98a2;font-size:10px}
.indicator-arrow{font-size:16px;color:#9aaab4;padding:0 4px}
.framework-empty{padding:22px;color:#82919b;font-size:11px}
.framework-footer{margin-top:22px;padding:16px 19px;border:1px solid #dbe8ed;border-left:3px solid #12a4b4;border-radius:10px;background:#f6fbfc;color:#5c7381;font-size:11px;line-height:1.6}
@media(max-width:1050px){.framework-grid{grid-template-columns:1fr}.framework-intro{align-items:flex-start;flex-direction:column}}
@media(max-width:650px){.indicator-row{grid-template-columns:48px minmax(0,1fr)}.indicator-arrow{display:none}}
</style></head>
<body><div class="app-shell"><?php include __DIR__.'/partials/sidebar.php';?>
<main class="main framework-page">
<header class="framework-intro"><div><div class="eyebrow">PROGRAMME FRAMEWORK</div><h1>Components &amp; Sub-indicators</h1><p>Programme Results Based Indicators are organised under the four ISLANDS components. Select a sub-indicator to view its reporting records, or create a new project-level report using the governed workflow.</p><div class="framework-meta"><span>4 Components</span><span><?=array_sum(array_map(fn($g)=>count($g['subs']),$groups))?> Sub-indicators</span><span>Project-level results</span><span>M&amp;R Framework</span></div></div><a class="btn primary" href="subindicator.php">+ New sub-indicator report</a></header>
<section class="framework-grid">
<?php foreach($groups as $g): $c=$g['component']; ?>
<article class="framework-card">
<div class="framework-card-head"><div class="framework-card-kicker"><span class="framework-number"><?=e($c['code'])?></span><span class="framework-count"><?=count($g['subs'])?> indicator<?=count($g['subs'])===1?'':'s'?></span></div>
<h2><?=e($c['name'])?></h2><p class="framework-outcome"><?=e($c['description'])?></p></div>
<div class="indicator-list">
<?php if(!$g['subs']): ?><div class="framework-empty">No active sub-indicators configured.</div>
<?php else: foreach($g['subs'] as $si): ?>
<a class="indicator-row" href="subindicators.php?q=<?=urlencode($si['sub_code'])?>">
<span class="indicator-code"><?=e($si['sub_code'])?></span>
<span class="indicator-main"><strong><?=e($si['sub_name'])?></strong><small><?=e(ucfirst($si['indicator_type']?:'Result'))?><?=($si['unit']??'')?' · '.e($si['unit']):''?></small></span><span class="indicator-arrow">›</span>
</a>
<?php endforeach; endif; ?>
</div></article>
<?php endforeach; ?>
</section>
<div class="framework-footer"><strong>Reporting logic:</strong> A project-level report is created by selecting a child project first, then a component applicable to that project, and finally a sub-indicator mapped to the selected project and component. This catalogue is the programme framework view; it does not imply that every project contributes to every sub-indicator.</div>
</main></div></body></html>
