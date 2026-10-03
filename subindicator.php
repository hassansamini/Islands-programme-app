<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/subindicator_link_repair.php';
$u=require_login(); $pdo=db();
ensure_project_subindicator_links();
$id=(int)($_GET['id']??0); $editing=$id>0; $errors=[]; $flashes=get_flashes();
$projects=$pdo->query("SELECT p.* FROM projects p WHERE p.active=1 AND EXISTS (SELECT 1 FROM submissions s WHERE s.project_id=p.id) ORDER BY p.project_code")->fetchAll();
$components=$pdo->query("SELECT * FROM components WHERE active=1 ORDER BY sort_order")->fetchAll();
$subs=$pdo->query("SELECT si.*,c.code component_code,c.name component_name FROM sub_indicators si JOIN components c ON c.id=si.component_id WHERE si.active=1 ORDER BY c.sort_order,si.sort_order,si.code")->fetchAll();
$gebs=$pdo->query("SELECT * FROM indicators WHERE active=1 ORDER BY id")->fetchAll();
$users=$pdo->query("SELECT id,name,role,email FROM users WHERE active=1 ORDER BY name")->fetchAll();
$links=$pdo->query("SELECT l.project_id,l.sub_indicator_id,l.geb_indicator_id,l.project_logframe_reference,si.code sub_code,si.component_id,i.code geb_code,i.name geb_name FROM project_sub_indicator_links l JOIN sub_indicators si ON si.id=l.sub_indicator_id LEFT JOIN indicators i ON i.id=l.geb_indicator_id WHERE l.active=1 ORDER BY l.project_id,si.sort_order,i.id")->fetchAll();
$existing=null;
if($editing){$st=$pdo->prepare("SELECT s.*,p.project_code,p.project_name,si.code sub_code,si.name sub_name,c.name component_name,i.code geb_code,u.name submitter,r.name reviewer,r.email reviewer_email,a.name approver,a.email approver_email FROM subindicator_submissions s JOIN projects p ON p.id=s.project_id JOIN sub_indicators si ON si.id=s.sub_indicator_id JOIN components c ON c.id=si.component_id LEFT JOIN indicators i ON i.id=s.geb_indicator_id JOIN users u ON u.id=s.submitted_by LEFT JOIN users r ON r.id=s.reviewer_id LEFT JOIN users a ON a.id=s.approver_id WHERE s.id=?");$st->execute([$id]);$existing=$st->fetch(); if(!$existing){flash('danger','Sub-indicator record not found.');redirect('subindicators.php');}}
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $project=(int)($_POST['project_id']??0);$subid=(int)($_POST['sub_indicator_id']??0);$geb=(int)($_POST['geb_indicator_id']??0);$year=(int)($_POST['reporting_year']??0);$country=trim((string)($_POST['country']??''));$value=trim((string)($_POST['reported_value']??''));$unit=trim((string)($_POST['unit']??''));$result=trim((string)($_POST['result_text']??''));$dis=trim((string)($_POST['disaggregation']??''));$source=trim((string)($_POST['data_source']??''));$method=trim((string)($_POST['methodology']??''));$notes=trim((string)($_POST['notes']??''));$reviewer=(int)($_POST['reviewer_id']??0);$approver=(int)($_POST['approver_id']??0);$policyRows=$_POST['policy_rows']??[];$beneficiaryRows=$_POST['beneficiary_rows']??[];
  if(!$project||!$subid||!$year||$year<2000||$year>2100||!$country||!$source||!$method)$errors[]='Project, component/sub-indicator, year, country, data source and methodology are required.';
  if($value!=='' && !is_numeric($value))$errors[]='Reported value must be numeric or blank.';
  $structured_type=''; $structured_payload=[];
  if($subid){
    $sx=$pdo->prepare("SELECT code FROM sub_indicators WHERE id=? LIMIT 1");$sx->execute([$subid]);$subcode=(string)($sx->fetchColumn()??'');
    if($subcode==='1.1'){
      $structured_type='policy_instruments';
      foreach((array)$policyRows as $row){
        $category=trim((string)($row['category']??''));$name=trim((string)($row['name']??''));$stage=trim((string)($row['stage']??''));$description=trim((string)($row['description']??''));
        if($category!==''||$name!==''||$stage!==''||$description!==''){
          if($category===''||$name===''||$stage==='')$errors[]='For Sub-indicator 1.1, each instrument row requires category, instrument/policy name and stage.';
          $structured_payload[]=['category'=>$category,'name'=>$name,'stage'=>$stage,'description'=>$description];
        }
      }
    } elseif($subcode==='1.2'){
      $structured_type='beneficiaries';
      foreach((array)$beneficiaryRows as $row){
        $category=trim((string)($row['category']??''));$intensity=trim((string)($row['intensity']??''));$male=(int)($row['male']??0);$female=(int)($row['female']??0);$activity=trim((string)($row['activity']??''));
        if($category!==''||$intensity!==''||$male||$female||$activity!==''){
          if($category===''||$intensity==='')$errors[]='For Sub-indicator 1.2, each beneficiary row requires beneficiary category and intensity.';
          if($male<0||$female<0)$errors[]='Beneficiary counts cannot be negative.';
          $structured_payload[]=['category'=>$category,'intensity'=>$intensity,'male'=>$male,'female'=>$female,'total'=>$male+$female,'activity'=>$activity];
        }
      }
    }
  }
  if($structured_type!=='')$dis=json_encode(['structured_type'=>$structured_type,'rows'=>$structured_payload],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if($value!=='' && (float)$value<0)$errors[]='Reported value cannot be negative.';
  $validLink=false;
  if($project&&$subid){$x=$pdo->prepare("SELECT 1 FROM project_sub_indicator_links WHERE project_id=? AND sub_indicator_id=? AND active=1 LIMIT 1");$x->execute([$project,$subid]);$validLink=(bool)$x->fetchColumn();if(!$validLink)$errors[]='The selected sub-indicator is not mapped to this child project in the programme framework.';}
  if($geb && $validLink){$x=$pdo->prepare("SELECT 1 FROM project_sub_indicator_links WHERE project_id=? AND sub_indicator_id=? AND geb_indicator_id=? AND active=1 LIMIT 1");$x->execute([$project,$subid,$geb]);if(!$x->fetchColumn())$errors[]='The selected GEB is not one of the configured project/sub-indicator links.';}
  if(!$errors){
    if($editing){$oldReviewer=(int)($existing['reviewer_id']??0);$oldApprover=(int)($existing['approver_id']??0);$st=$pdo->prepare("UPDATE subindicator_submissions SET project_id=?,sub_indicator_id=?,geb_indicator_id=?,reporting_year=?,country=?,reported_value=?,unit=?,result_text=?,disaggregation=?,data_source=?,methodology=?,notes=?,reviewer_id=?,approver_id=?,updated_at=NOW() WHERE id=?");$st->execute([$project,$subid,$geb?:null,$year,$country,$value!==''?(float)$value:null,$unit?:null,$result?:null,$dis?:null,$source,$method,$notes?:null,$reviewer?:null,$approver?:null,$id]);
      $fresh=$pdo->prepare("SELECT s.*,p.project_code,si.code sub_code,i.code geb_code,r.email reviewer_email,a.email approver_email,r.name reviewer_name,a.name approver_name FROM subindicator_submissions s JOIN projects p ON p.id=s.project_id JOIN sub_indicators si ON si.id=s.sub_indicator_id LEFT JOIN indicators i ON i.id=s.geb_indicator_id LEFT JOIN users r ON r.id=s.reviewer_id LEFT JOIN users a ON a.id=s.approver_id WHERE s.id=?");$fresh->execute([$id]);$rec=$fresh->fetch();
      if($reviewer && $reviewer!==$oldReviewer){ notify_subindicator_assignment($rec,'reviewer',$rec['reviewer_email']??null); }
      if($approver && $approver!==$oldApprover){ notify_subindicator_assignment($rec,'approver',$rec['approver_email']??null); }
      flash('success','Sub-indicator record updated.');redirect('subindicator.php?id='.$id);
    } else {
      $st=$pdo->prepare("INSERT INTO subindicator_submissions (project_id,sub_indicator_id,geb_indicator_id,reporting_year,country,reported_value,unit,result_text,disaggregation,data_source,methodology,notes,state,submitted_by,reviewer_id,approver_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");$st->execute([$project,$subid,$geb?:null,$year,$country,$value!==''?(float)$value:null,$unit?:null,$result?:null,$dis?:null,$source,$method,$notes?:null,'draft',$u['id'],$reviewer?:null,$approver?:null]);$new=(int)$pdo->lastInsertId();subindicator_audit($new,(int)$u['id'],'Created','', 'draft','Sub-indicator record created.');
      $fresh=$pdo->prepare("SELECT s.*,p.project_code,si.code sub_code,i.code geb_code,r.email reviewer_email,a.email approver_email,r.name reviewer_name,a.name approver_name FROM subindicator_submissions s JOIN projects p ON p.id=s.project_id JOIN sub_indicators si ON si.id=s.sub_indicator_id LEFT JOIN indicators i ON i.id=s.geb_indicator_id LEFT JOIN users r ON r.id=s.reviewer_id LEFT JOIN users a ON a.id=s.approver_id WHERE s.id=?");$fresh->execute([$new]);$rec=$fresh->fetch();
      if($reviewer)notify_subindicator_assignment($rec,'reviewer',$rec['reviewer_email']??null);if($approver)notify_subindicator_assignment($rec,'approver',$rec['approver_email']??null);
      flash('success','Sub-indicator record saved as Draft.');redirect('subindicator.php?id='.$new);
    }
  }
}
if($editing){$st=$pdo->prepare("SELECT s.*,p.project_code,p.project_name,si.code sub_code,si.name sub_name,c.name component_name,i.code geb_code,u.name submitter,r.name reviewer,r.email reviewer_email,a.name approver,a.email approver_email FROM subindicator_submissions s JOIN projects p ON p.id=s.project_id JOIN sub_indicators si ON si.id=s.sub_indicator_id JOIN components c ON c.id=si.component_id LEFT JOIN indicators i ON i.id=s.geb_indicator_id JOIN users u ON u.id=s.submitted_by LEFT JOIN users r ON r.id=s.reviewer_id LEFT JOIN users a ON a.id=s.approver_id WHERE s.id=?");$st->execute([$id]);$existing=$st->fetch();}
$events=[];if($editing){$st=$pdo->prepare("SELECT e.*,u.name FROM subindicator_workflow_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.subindicator_submission_id=? ORDER BY e.created_at DESC");$st->execute([$id]);$events=$st->fetchAll();}
$linkJson=[];foreach($links as $l){$linkJson[]=['project_id'=>(int)$l['project_id'],'sub_indicator_id'=>(int)$l['sub_indicator_id'],'component_id'=>(int)$l['component_id'],'geb_indicator_id'=>$l['geb_indicator_id']?(int)$l['geb_indicator_id']:null,'geb_code'=>$l['geb_code'],'geb_name'=>$l['geb_name'],'reference'=>$l['project_logframe_reference']];}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $editing?'Sub-indicator #'.str_pad((string)$id,5,'0',STR_PAD_LEFT):'New sub-indicator'?> | ISLANDS</title><link rel="stylesheet" href="assets/app.css"></head><body><div class="app-shell"><?php include __DIR__.'/partials/sidebar.php'; ?><main class="main">
<header class="topbar"><div><div class="eyebrow">RESULTS-BASED INDICATORS</div><h1><?= $editing?'Sub-indicator record #'.str_pad((string)$id,5,'0',STR_PAD_LEFT):'Create sub-indicator report'?></h1><p class="muted">Capture project-level outcomes and outputs, linked to the programme GEB/impact layer.</p></div><?php if($editing):?><span class="status large <?=e($existing['state'])?>"><?=e(workflow_label($existing['state']))?></span><?php endif;?></header>
<?php foreach($flashes as $f):?><div class="alert <?=e($f[0])?>"><?=e($f[1])?></div><?php endforeach;?><?php foreach($errors as $er):?><div class="alert danger"><?=e($er)?></div><?php endforeach;?>
<?php if($editing):?><section class="workflow-panel"><div class="workflow-head"><div><div class="eyebrow">WORKFLOW</div><h3>Sub-indicator lifecycle</h3></div><div class="workflow-note">The same Draft → Review → Approval → Completed governance is used for sub-indicator records.</div></div><div class="workflow"><div class="wf-step <?=in_array($existing['state'],['review','approval','approved'],true)?'done':($existing['state']==='draft'?'current':'')?>"><span>1</span><strong>Draft</strong><small>Data capture</small></div><i></i><div class="wf-step <?=$existing['state']==='review'?'current':(in_array($existing['state'],['approval','approved'],true)?'done':'')?>"><span>2</span><strong>Review</strong><small>Quality assurance</small></div><i></i><div class="wf-step <?=$existing['state']==='approval'?'current':($existing['state']==='approved'?'done':'')?>"><span>3</span><strong>Approval</strong><small>Final verification</small></div><i></i><div class="wf-step <?=$existing['state']==='approved'?'current':''?>"><span>4</span><strong>Completed</strong><small>Verified record</small></div></div><div class="workflow-actions"><?php foreach(['draft'=>'Move to Draft','review'=>'Move to Review','approval'=>'Move to Approval','approved'=>'Move to Completed'] as $target=>$label):if($target===$existing['state'])continue;?><form method="POST" action="subindicator_workflow_action.php"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="move_to"><input type="hidden" name="target_state" value="<?=e($target)?>"><input type="hidden" name="submission_id" value="<?=$id?>"><button class="btn <?= $target==='approved'?'success':($target==='review'?'primary':'secondary')?>" type="submit"><?=e($label)?></button></form><?php endforeach;?></div></section><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="form-layout"><div>
<section class="panel form-section"><div class="section-title"><span>01</span><div><div class="eyebrow">PROJECT LINKAGE</div><h3>Project and framework position</h3></div></div><div class="form-grid"><label>Child project*<select name="project_id" id="project_id" required autocomplete="off"><option value="">Select project</option><?php foreach($projects as $p):?><option value="<?=$p['id']?>" <?=($existing['project_id']??0)==$p['id']?'selected':''?>><?=e($p['project_code'].' — '.$p['project_name'])?></option><?php endforeach;?></select></label><label>Reporting year*<input type="number" name="reporting_year" min="2000" max="2100" value="<?=e((string)($existing['reporting_year']??date('Y')))?>" required></label><label>Country*<input name="country" value="<?=e($existing['country']??'')?>" required></label></div></section>
<section class="panel form-section"><div class="section-title"><span>02</span><div><div class="eyebrow">COMPONENT & SUB-INDICATOR</div><h3>Results-based indicator</h3></div></div><div class="form-grid"><label>Component*<select id="component_id" required disabled autocomplete="off"><option value="">Select project first</option><?php foreach($components as $c):?><option value="<?=$c['id']?>"><?=e($c['code'].' — '.$c['name'])?></option><?php endforeach;?></select></label><label>Sub-indicator*<select name="sub_indicator_id" id="sub_indicator_id" required disabled autocomplete="off"><option value="">Select project first</option></select></label></div><label>Linked GEB / impact indicator<select name="geb_indicator_id" id="geb_indicator_id"><option value="">Select configured GEB linkage</option></select></label><div id="mapping_help" class="mapping-help"></div><div id="structured_reporting_panel" class="structured-reporting" hidden>
  <div class="structured-heading"><div><div class="eyebrow">FRAMEWORK-BASED DATA COLLECTION</div><h3 id="structured_title"></h3></div><div id="structured_help" class="mapping-help"></div></div>
  <div id="policy_reporting" hidden>
    <p class="muted">Report each qualifying policy, regulation, standard or mechanism separately. Do not count the same instrument twice.</p>
    <div class="table-wrap"><table class="data-table structured-table"><thead><tr><th>Policy / instrument category</th><th>Instrument / policy name</th><th>Stage</th><th>Short description</th><th></th></tr></thead><tbody id="policy_rows"></tbody></table></div>
    <button type="button" class="btn secondary" id="add_policy_row">+ Add another instrument</button>
    <div class="structured-summary" id="policy_summary"></div>
  </div>
  <div id="beneficiary_reporting" hidden>
    <p class="muted">Report people capacitated by beneficiary category and intensity. Capture sex at registration where available; do not estimate sex-disaggregated values.</p>
    <div class="table-wrap"><table class="data-table structured-table"><thead><tr><th>Beneficiary category</th><th>Intensity</th><th>Male</th><th>Female</th><th>Total</th><th>Activity / intervention</th></tr></thead><tbody id="beneficiary_rows"></tbody></table></div>
    <div class="structured-summary" id="beneficiary_summary"></div>
  </div>
</div><div class="form-grid"><label>Reported value<input type="number" step="any" min="0" name="reported_value" value="<?=e((string)($existing['reported_value']??''))?>"></label><label>Unit<input name="unit" id="unit" value="<?=e($existing['unit']??'')?>" placeholder="Use the framework unit"></label></div><label>Result / achievement narrative<textarea name="result_text" rows="4" placeholder="Describe the project-level result achieved. Avoid activity-only reporting."><?=e($existing['result_text']??'')?></textarea></label><label>Disaggregation / details<textarea name="disaggregation" rows="4" placeholder="Country, sex, beneficiary category, facility, waste stream, product type, stage or other relevant breakdown."><?=e($existing['disaggregation']??'')?></textarea></label></section>
<section class="panel form-section"><div class="section-title"><span>03</span><div><div class="eyebrow">EVIDENCE</div><h3>Source & methodology</h3></div></div><label>Data source*<textarea name="data_source" rows="3" required><?=e($existing['data_source']??'')?></textarea></label><label>Calculation / assessment methodology*<textarea name="methodology" rows="4" required><?=e($existing['methodology']??'')?></textarea></label><label>Notes<textarea name="notes" rows="3"><?=e($existing['notes']??'')?></textarea></label></section>
</div><aside><section class="panel side-panel"><div class="section-title compact"><span>04</span><div><div class="eyebrow">GOVERNANCE</div><h3>Assignment</h3></div></div><label>Reviewer<select name="reviewer_id"><option value="">Select reviewer</option><?php foreach($users as $x):?><option value="<?=$x['id']?>" <?=($existing['reviewer_id']??0)==$x['id']?'selected':''?>><?=e($x['name'].' — '.$x['role'])?></option><?php endforeach;?></select></label><label>Approver<select name="approver_id"><option value="">Select approver</option><?php foreach($users as $x):?><option value="<?=$x['id']?>" <?=($existing['approver_id']??0)==$x['id']?'selected':''?>><?=e($x['name'].' — '.$x['role'])?></option><?php endforeach;?></select></label></section><section class="panel side-panel"><div class="eyebrow">FRAMEWORK NOTE</div><p class="muted">The project/sub-indicator relationship is stored explicitly, including the child-project logframe reference. The linked GEB field uses the configured programme linkage and remains traceable for review.</p></section></aside></div><div class="form-actions"><a class="btn ghost" href="subindicators.php">Cancel</a><button class="btn primary" type="submit"><?= $editing?'Save changes':'Save as Draft'?></button></div></form>
<?php if($editing):?><section class="panel audit"><div class="panel-head"><div><div class="eyebrow">AUDIT TRAIL</div><h3>Workflow history</h3></div></div><?php foreach($events as $ev):?><div class="audit-row"><span class="audit-dot"></span><div><strong><?=e($ev['action'])?></strong><span><?=e($ev['name']??'System')?> · <?=e($ev['created_at'])?></span><?php if($ev['note']):?><small><?=e($ev['note'])?></small><?php endif;?></div><span class="audit-state"><?=e(workflow_label($ev['to_state']??''))?></span></div><?php endforeach;if(!$events):?><div class="empty">No workflow events yet.</div><?php endif;?></section><?php endif;?>
<script>
const LINKS=<?=json_encode($linkJson,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const SUBS=<?=json_encode($subs,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const currentSub=<?=json_encode((int)($existing['sub_indicator_id']??0))?>;
const currentGeb=<?=json_encode((int)($existing['geb_indicator_id']??0))?>;
const isEditing=<?=json_encode($editing)?>;
const comp=document.getElementById('component_id'),sub=document.getElementById('sub_indicator_id'),geb=document.getElementById('geb_indicator_id'),proj=document.getElementById('project_id'),help=document.getElementById('mapping_help'),unit=document.getElementById('unit');
function setDisabled(el,disabled){el.disabled=disabled;}
function refreshComponents(){
  const pid=Number(proj.value);
  comp.innerHTML='<option value="">'+(pid?'Select component':'Select project first')+'</option>';
  setDisabled(comp,!pid);
  sub.innerHTML='<option value="">'+(pid?'Select component first':'Select project first')+'</option>';
  setDisabled(sub,true);
  refreshGeb();
  if(!pid) return;
  const ids=[...new Set(LINKS.filter(x=>Number(x.project_id)===pid).map(x=>Number(x.component_id)))];
  const seen=new Set();
  SUBS.filter(x=>ids.includes(Number(x.component_id))).sort((a,b)=>Number(a.component_id)-Number(b.component_id)||String(a.code).localeCompare(String(b.code),undefined,{numeric:true})).forEach(x=>{
    const cid=Number(x.component_id); if(seen.has(cid)) return; seen.add(cid);
    const o=document.createElement('option');o.value=cid;o.textContent=x.component_code+' — '+x.component_name;comp.appendChild(o);
  });
  if(currentSub){const cs=SUBS.find(x=>Number(x.id)===currentSub);if(cs&&ids.includes(Number(cs.component_id)))comp.value=String(cs.component_id);}
  refreshSubIndicators();
}
function refreshSubIndicators(){
  const pid=Number(proj.value),cid=Number(comp.value);
  sub.innerHTML='<option value="">'+(pid?(cid?'Select sub-indicator':'Select component first'):'Select project first')+'</option>';
  setDisabled(sub,!pid||!cid);
  if(!pid||!cid){refreshGeb();return;}
  const allowed=[...new Set(LINKS.filter(x=>Number(x.project_id)===pid&&Number(x.component_id)===cid).map(x=>x.sub_indicator_id))];
  SUBS.filter(x=>allowed.includes(Number(x.id))).forEach(x=>{const o=document.createElement('option');o.value=x.id;o.textContent=x.code+' — '+x.name;o.dataset.unit=x.unit||'';o.selected=Number(x.id)===currentSub;sub.appendChild(o)});
  refreshGeb();
}
function refreshGeb(){
  const pid=Number(proj.value),sid=Number(sub.value);
  geb.innerHTML='<option value="">Select configured GEB linkage</option>';
  const rows=LINKS.filter(x=>Number(x.project_id)===pid&&Number(x.sub_indicator_id)===sid);const seen=new Set();
  rows.forEach(x=>{if(x.geb_indicator_id&&!seen.has(x.geb_indicator_id)){seen.add(x.geb_indicator_id);const o=document.createElement('option');o.value=x.geb_indicator_id;o.textContent=x.geb_code+' — '+x.geb_name;o.selected=Number(x.geb_indicator_id)===currentGeb;geb.appendChild(o)}});
  help.textContent=rows.length?'Framework logframe reference(s): '+[...new Set(rows.map(x=>x.reference).filter(Boolean))].join('; '):(pid&&sid?'No direct GEB linkage is configured for this project/sub-indicator.':'Select a project, component and mapped sub-indicator.');
  const selected=sub.options[sub.selectedIndex];if(selected&&selected.dataset.unit&&!unit.value)unit.value=selected.dataset.unit;
}
proj.addEventListener('change',()=>{if(!isEditing){comp.value='';sub.value='';}refreshComponents();});
comp.addEventListener('change',refreshSubIndicators);sub.addEventListener('change',function(){refreshGeb();setTimeout(startStructuredCountWatcher,50);});
if(!isEditing){proj.value='';}
refreshComponents();
(function(){
const STRUCTURED_EXISTING = <?=json_encode((function() use ($existing){
  if(!$existing)return ['type'=>'','rows'=>[]];
  $j=json_decode((string)($existing['disaggregation']??''),true);
  return is_array($j)&&isset($j['structured_type'])?['type'=>(string)$j['structured_type'],'rows'=>is_array($j['rows']??null)?$j['rows']:[]]:['type'=>'','rows'=>[]];
})(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
function updateStructuredDisplay(){
  const selectedOption=sub.options[sub.selectedIndex];
  const code=selectedOption ? String(selectedOption.textContent||'').trim().split(/\s+/)[0] : '';
  const value=document.querySelector('input[name="reported_value"]');
  const unitField=document.getElementById('unit');
  const dis=document.querySelector('textarea[name="disaggregation"]');
  const disLabel=dis ? dis.closest('label') : null;

  const structured=(code==='1.1'||code==='1.2');

  if(disLabel){
    disLabel.style.display=structured?'none':'';
  }

  if(!value) return;

  if(code==='1.1'){
    const rows=document.querySelectorAll('#policy_rows tr');
    value.value=String(rows.length);
    value.readOnly=true;
    value.setAttribute('readonly','readonly');

    if(unitField){
      unitField.value='Countries';
      unitField.readOnly=true;
      unitField.setAttribute('readonly','readonly');
    }
  }else{
    value.readOnly=false;
    value.removeAttribute('readonly');

    if(unitField){
      unitField.readOnly=false;
      unitField.removeAttribute('readonly');
    }
  }
}

function startStructuredCountWatcher(){
  const target=document.getElementById('policy_rows');

  if(target && !target.dataset.countWatcher){
    const observer=new MutationObserver(function(){
      updateStructuredDisplay();
    });

    observer.observe(target,{childList:true,subtree:true});
    target.dataset.countWatcher='1';
  }

  updateStructuredDisplay();
}const CFG={
'1.1':{type:'policy',title:'Sub-indicator 1.1 â€” Policy & Regulatory Instruments',help:'Record each qualifying policy, regulation, standard or mechanism and its current stage.',cats:['Legal framework / import-related regulations','Chemical classification & labelling standards','Specific policy instruments'],stages:['Consultation','Draft','Technical review','1st reading','2nd reading','Approval','Adopted','Under implementation','Evaluation']},
'1.2':{type:'beneficiary',title:'Sub-indicator 1.2 â€” Beneficiaries & Capacity Building',help:'Record people capacitated by beneficiary category and intensity.',cats:['Policy, legal and standards professionals','Enforcement, customs and border-control personnel','Chemicals and waste-management practitioners','Private-sector and value-chain actors','Technical professionals and trainers','Workers and occupationally exposed groups','Community, consumer and youth participants'],ints:['High','Medium','Low']}};
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
function code(){const o=document.getElementById('sub_indicator_id')?.selectedOptions?.[0];return o?String(o.textContent).trim().split(/\s+/)[0]:'';}
function policyRow(r={},i=0){let c=CFG['1.1'];return `<tr><td><select name="policy_rows[${i}][category]"><option value="">Select category</option>${c.cats.map(x=>`<option value="${esc(x)}" ${x===r.category?'selected':''}>${esc(x)}</option>`).join('')}</select></td><td><input name="policy_rows[${i}][name]" value="${esc(r.name)}" placeholder="Instrument / policy name"></td><td><select name="policy_rows[${i}][stage]"><option value="">Select stage</option>${c.stages.map(x=>`<option value="${x}" ${x===r.stage?'selected':''}>${x}</option>`).join('')}</select></td><td><textarea name="policy_rows[${i}][description]" rows="2" placeholder="Brief description">${esc(r.description)}</textarea></td><td><button type="button" class="btn ghost remove-policy">Remove</button></td></tr>`;}
function policySummary(){let b=document.getElementById('policy_rows'),s=document.getElementById('policy_summary');if(!b||!s)return;let n=b.querySelectorAll('tr').length;s.textContent='Instrument rows reported: '+n;}
function renderPolicy(){let b=document.getElementById('policy_rows');if(!b)return;let r=STRUCTURED_EXISTING.type==='policy_instruments'?STRUCTURED_EXISTING.rows:[];if(!r.length)r=[{}];b.innerHTML=r.map(policyRow).join('');b.querySelectorAll('.remove-policy').forEach(x=>x.onclick=()=>{x.closest('tr').remove();policySummary()});policySummary();}
function beneficiaryRow(r={},i=0){let c=CFG['1.2'];return `<tr><td><select name="beneficiary_rows[${i}][category]"><option value="">Select category</option>${c.cats.map(x=>`<option value="${esc(x)}" ${x===r.category?'selected':''}>${esc(x)}</option>`).join('')}</select></td><td><select name="beneficiary_rows[${i}][intensity]"><option value="">Select intensity</option>${c.ints.map(x=>`<option value="${x}" ${x===r.intensity?'selected':''}>${x}</option>`).join('')}</select></td><td><input type="number" min="0" step="1" name="beneficiary_rows[${i}][male]" value="${Number(r.male||0)}"></td><td><input type="number" min="0" step="1" name="beneficiary_rows[${i}][female]" value="${Number(r.female||0)}"></td><td class="beneficiary-total">0</td><td><textarea name="beneficiary_rows[${i}][activity]" rows="2" placeholder="Training / capacity-building intervention">${esc(r.activity)}</textarea></td></tr>`;}
function beneficiarySummary(){let b=document.getElementById('beneficiary_rows'),s=document.getElementById('beneficiary_summary');if(!b||!s)return;let m=0,f=0,t=0;b.querySelectorAll('tr').forEach(x=>{let a=Number(x.querySelector('input[name$="[male]"]')?.value||0),q=Number(x.querySelector('input[name$="[female]"]')?.value||0);m+=a;f+=q;t+=a+q;let z=x.querySelector('.beneficiary-total');if(z)z.textContent=a+q});s.textContent=`Total beneficiaries: ${t} | Male: ${m} | Female: ${f}`;}
function renderBeneficiary(){let b=document.getElementById('beneficiary_rows');if(!b)return;let r=STRUCTURED_EXISTING.type==='beneficiaries'?STRUCTURED_EXISTING.rows:[];if(!r.length)r=CFG['1.2'].cats.map(category=>({category,intensity:'',male:0,female:0,activity:''}));b.innerHTML=r.map(beneficiaryRow).join('');beneficiarySummary();}
function update(){let c=code(),cfg=CFG[c],p=document.getElementById('structured_reporting_panel');if(!p)return;p.hidden=!cfg;document.getElementById('policy_reporting').hidden=!cfg||cfg.type!=='policy';document.getElementById('beneficiary_reporting').hidden=!cfg||cfg.type!=='beneficiary';if(!cfg)return;document.getElementById('structured_title').textContent=cfg.title;document.getElementById('structured_help').textContent=cfg.help;if(cfg.type==='policy')renderPolicy();else renderBeneficiary();}
document.getElementById('add_policy_row')?.addEventListener('click',()=>{let b=document.getElementById('policy_rows');let i=b.querySelectorAll('tr').length;b.insertAdjacentHTML('beforeend',policyRow({},i));b.querySelectorAll('.remove-policy').forEach(x=>x.onclick=()=>{x.closest('tr').remove();policySummary()});});
document.getElementById('sub_indicator_id')?.addEventListener('change',update);
document.addEventListener('input',e=>{if(e.target.matches('input[name$="[male]"],input[name$="[female]"]'))beneficiarySummary()});
update();
})();

(function(){
  function syncStructuredFields(){
    const sub = document.getElementById('sub_indicator_id');
    const value = document.querySelector('input[name="reported_value"]');
    const unit = document.getElementById('unit');
    const dis = document.querySelector('textarea[name="disaggregation"]');

    if(!sub || !value) return;

    const opt = sub.options[sub.selectedIndex];
    const code = opt ? String(opt.textContent || '').trim().split(/\s+/)[0] : '';
    const structured = (code === '1.1' || code === '1.2');

    if(code === '1.1'){
      const rows = document.querySelectorAll('#policy_rows tr');
      value.value = String(rows.length);
      value.readOnly = true;
      value.setAttribute('readonly','readonly');

      if(unit){
        unit.value = 'Countries';
        unit.readOnly = true;
        unit.setAttribute('readonly','readonly');
      }
    } else {
      value.readOnly = false;
      value.removeAttribute('readonly');

      if(unit){
        unit.readOnly = false;
        unit.removeAttribute('readonly');
      }
    }

    if(dis){
      if(structured){
        dis.value = '';
        dis.style.display = 'none';
        dis.setAttribute('aria-hidden','true');

        const label = dis.closest('label');
        if(label) label.style.display = 'none';

        const parent = dis.parentElement;
        if(parent){
          const parentLabel = parent.querySelector('label');
          if(parentLabel) parentLabel.style.display = 'none';
        }
      } else {
        dis.style.display = '';
        dis.removeAttribute('aria-hidden');

        const label = dis.closest('label');
        if(label) label.style.display = '';

        const parent = dis.parentElement;
        if(parent){
          const parentLabel = parent.querySelector('label');
          if(parentLabel) parentLabel.style.display = '';
        }
      }
    }
  }

  const observer = new MutationObserver(function(){
    syncStructuredFields();
  });

  observer.observe(document.body, {childList:true, subtree:true});

  document.addEventListener('change', function(){
    setTimeout(syncStructuredFields, 20);
  }, true);

  document.addEventListener('click', function(){
    setTimeout(syncStructuredFields, 50);
  }, true);

  setInterval(syncStructuredFields, 250);

  setTimeout(syncStructuredFields, 100);
document.querySelector('form[method="post"]')?.addEventListener('submit',function(e){
  if(code()==='1.2'){
    let rows=document.querySelectorAll('#beneficiary_rows tr');
    let valid=[...rows].some(r =>
      Number(r.querySelector('input[name$="[male]"]')?.value||0)>0 ||
      Number(r.querySelector('input[name$="[female]"]')?.value||0)>0
    );
    if(!valid){
      e.preventDefault();
      alert('Sub-indicator 1.2 requires at least one beneficiary with a male or female count greater than zero.');
    }
  }
});})();
</script>
</main></div></body></html>
