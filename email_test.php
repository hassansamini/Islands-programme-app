<?php
require_once __DIR__.'/bootstrap.php';
$u=require_login();
if(!has_role('GEB Reviewer','FAO Task Manager','UNEP Task Manager')) { http_response_code(403); exit('Access denied.'); }
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('email_settings.php');
verify_csrf();
$to=trim($_POST['to']??$u['email']);
if(!filter_var($to,FILTER_VALIDATE_EMAIL)){flash('danger','Enter a valid test email address.');redirect('email_settings.php');}
$result=smtp_send([$to],APP_NAME.' — SMTP test email',"This is a test email from the ISLANDS GEB Portal.\n\nSMTP configuration is working correctly.\n\nSent: ".date(DATE_RFC2822));
flash($result['ok']?'success':'danger',$result['ok']?'Test email sent to '.$to.'.':'Test email failed: '.$result['error']);
redirect('email_settings.php');
