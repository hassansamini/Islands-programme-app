<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function audit_setup(PDO $pdo, int $submissionId, ?int $userId, string $action, ?string $from, ?string $to, string $note=''): void {
    $s = $pdo->prepare("INSERT INTO workflow_events (submission_id,user_id,action,from_state,to_state,note,created_at) VALUES (?,?,?,?,?,?,NOW())");
    $s->execute([$submissionId,$userId,$action,$from,$to,$note]);
}

$message = '';
$error = '';
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(80) NOT NULL,
        organization VARCHAR(120) DEFAULT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_code VARCHAR(30) NOT NULL UNIQUE,
        project_name VARCHAR(220) NOT NULL,
        region VARCHAR(80) NOT NULL,
        countries VARCHAR(500) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS indicators (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(30) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        level VARCHAR(40) NOT NULL,
        unit VARCHAR(100) DEFAULT NULL,
        description TEXT,
        active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        reporting_year SMALLINT UNSIGNED NOT NULL,
        indicator_id INT UNSIGNED NOT NULL,
        country VARCHAR(120) NOT NULL,
        reported_value DECIMAL(20,4) NOT NULL,
        unit VARCHAR(100) NOT NULL,
        disaggregation TEXT,
        data_source TEXT NOT NULL,
        methodology TEXT NOT NULL,
        notes TEXT,
        evidence_path VARCHAR(500),
        state VARCHAR(20) NOT NULL DEFAULT 'draft',
        submitted_by INT UNSIGNED NOT NULL,
        reviewer_id INT UNSIGNED DEFAULT NULL,
        approver_id INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        approved_at DATETIME DEFAULT NULL,
        CONSTRAINT fk_sub_project FOREIGN KEY (project_id) REFERENCES projects(id),
        CONSTRAINT fk_sub_indicator FOREIGN KEY (indicator_id) REFERENCES indicators(id),
        CONSTRAINT fk_sub_submitter FOREIGN KEY (submitted_by) REFERENCES users(id),
        CONSTRAINT fk_sub_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id),
        CONSTRAINT fk_sub_approver FOREIGN KEY (approver_id) REFERENCES users(id)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS workflow_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        submission_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        from_state VARCHAR(20),
        to_state VARCHAR(20),
        note TEXT,
        created_at DATETIME NOT NULL,
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        submission_id INT UNSIGNED NOT NULL,
        recipients TEXT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

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

    // Local/API integration token. The token is stored hashed in the database.
    $apiToken = 'ISLANDS-GEB-API-2026';
    $apiHash = hash('sha256', $apiToken);
    $tokenUser = (int)$pdo->query("SELECT id FROM users WHERE username='GMalil_UNEP' LIMIT 1")->fetchColumn();
    $api = $pdo->prepare("INSERT IGNORE INTO api_tokens (name,token_hash,user_id,active,created_at) VALUES (?,?,?,?,NOW())");
    $api->execute(['ISLANDS GEB Portal API', $apiHash, $tokenUser ?: null, 1]);

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
    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='notification_log'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $cols, true)) $pdo->exec("ALTER TABLE notification_log ADD status VARCHAR(20) NOT NULL DEFAULT 'logged' AFTER body");
    if (!in_array('error_message', $cols, true)) $pdo->exec("ALTER TABLE notification_log ADD error_message TEXT NULL AFTER status");
    if (!in_array('sent_at', $cols, true)) $pdo->exec("ALTER TABLE notification_log ADD sent_at DATETIME NULL AFTER error_message");

    $users = [
        ['Samini Ngala','samini@example.org','samini','ISLANDS2026!','GEB Reviewer','GGKP/UNEP'],
        ['Hassan Sammy','hassan@example.org','hassan','ISLANDS2026!','NWG Member','NWG'],
        ['Hassan Ngala','samini099@gmail.com','GMalil_UNEP','ISLANDS-NWG-2026!','NWG Member','NWG'],
        ['Hassan Ngala','saminingala099@yahoo.com','YahMalil_FAO','ISLANDS-FAO-2026!','FAO Task Manager','FAO'],
        ['Hassan Ngala','hassan.ngala@un.org','UNMalil_UNEP','ISLANDS-UNEP-2026!','UNEP Task Manager','UNEP'],
        ['Fabienne Pierre','fabienne.pierre@un.org','Fabienne_Pier_UNEP','ISLANDS-FABIENNE-2026!','UNEP Task Manager','UNEP'],
        ['Jana Thuaud','jana.thuaud@un.org','Jana_Thuaud_UNEP','ISLANDS-JANA-2026!','UNEP Task Manager','UNEP'],
        ['Demo Reviewer','reviewer@example.org','reviewer','ISLANDS2026!','SPREP','SPREP'],
        ['Demo Approver','approver@example.org','approver','ISLANDS2026!','UNEP Task Manager','UNEP'],
    ];
    // Existing installations may already have the users table without username.
    try { $pdo->exec("ALTER TABLE users ADD username VARCHAR(80) NULL AFTER name"); } catch (Throwable $ignored) {}
    try { $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)"); } catch (Throwable $ignored) {}
    $q = $pdo->prepare("INSERT IGNORE INTO users (name,username,email,password_hash,role,organization,created_at) VALUES (?,?,?,?,?,?,NOW())");
    foreach ($users as $u) $q->execute([$u[0],$u[2],$u[1],password_hash($u[3],PASSWORD_DEFAULT),$u[4],$u[5]]);
    // Update the requested named accounts on an existing installation.
    $up = $pdo->prepare("UPDATE users SET name=?, username=?, role=?, organization=?, active=1 WHERE email=?");
    foreach ($users as $u) $up->execute([$u[0],$u[2],$u[4],$u[5],$u[1]]);

    $projects = [
        ['10267','Pacific Child Project','Pacific','Fiji, Kiribati, Marshall Islands, Micronesia, Nauru, Palau, Papua New Guinea, Samoa, Solomon Islands, Tonga, Tuvalu, Vanuatu'],
        ['10279','Caribbean I Child Project','Caribbean','Antigua and Barbuda, Bahamas, Barbados, Belize, Dominica, Grenada, Guyana, Haiti, Jamaica, Saint Lucia, Saint Kitts and Nevis, Saint Vincent and the Grenadines, Trinidad and Tobago'],
        ['10472','Caribbean II Child Project','Caribbean','Barbados, Belize, Jamaica, Trinidad and Tobago'],
        ['10848','Atlantic Child Project','Atlantic','Cabo Verde, Guinea-Bissau, Sao Tome and Principe'],
        ['10261','Indian Ocean Child Project','Indian Ocean','Comoros, Maldives, Mauritius, Seychelles'],
        ['10258','Caribbean Incubator Facility','Caribbean','Dominican Republic, Jamaica, Trinidad and Tobago'],
    ];
    $q = $pdo->prepare("INSERT IGNORE INTO projects (project_code,project_name,region,countries) VALUES (?,?,?,?)");
    foreach ($projects as $p) $q->execute($p);

    $inds = [
        ['GEF #9','Materials/products containing hazardous chemicals avoided or disposed of','IMPACT','Metric tonnes'],
        ['GEF #9.1','POPs removed or disposed of','SUB-INDICATOR','Metric tonnes'],
        ['GEF #9.2','Mercury reduced','SUB-INDICATOR','Metric tonnes'],
        ['GEF #9.4','Countries with legislation and policy on chemicals & waste implemented','SUB-INDICATOR','Countries'],
        ['GEF #9.5','Low-chemical/non-chemical systems implemented','SUB-INDICATOR','Systems'],
        ['GEF #9.6','POPs/mercury-containing materials directly avoided','SUB-INDICATOR','Metric tonnes'],
        ['GEF #10','Persistent organic pollutants to air reduced','IMPACT','Metric tonnes'],
        ['GEF #10.1','Countries with legislation/policy to control emissions of POPs to air','SUB-INDICATOR','Countries'],
        ['GEF #10.2','Emission control technologies/practices implemented','SUB-INDICATOR','Technologies'],
        ['GEF #11','People benefiting from GEF-financed investments','IMPACT','People'],
        ['GEF #11.1','People benefiting, of whom male','SUB-INDICATOR','People'],
        ['GEF #11.2','People benefiting, of whom female','SUB-INDICATOR','People'],
        ['GEF #5.3','Marine litter avoided','SUB-INDICATOR','Metric tonnes'],
    ];
    $q = $pdo->prepare("INSERT IGNORE INTO indicators (code,name,level,unit,description) VALUES (?,?,?,?,?)");
    foreach ($inds as $i) $q->execute([$i[0],$i[1],$i[2],$i[3],'ISLANDS Programme monitoring indicator.']);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
    if ($count === 0) {
        $samini = (int)$pdo->query("SELECT id FROM users WHERE email='samini@example.org'")->fetchColumn();
        $hassan = (int)$pdo->query("SELECT id FROM users WHERE email='hassan@example.org'")->fetchColumn();
        $project = (int)$pdo->query("SELECT id FROM projects WHERE project_code='10258'")->fetchColumn();
        $indicator = (int)$pdo->query("SELECT id FROM indicators WHERE code='GEF #9.2'")->fetchColumn();
        $s = $pdo->prepare("INSERT INTO submissions (project_id,reporting_year,indicator_id,country,reported_value,unit,disaggregation,data_source,methodology,notes,state,submitted_by,reviewer_id,approver_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $s->execute([$project,2026,$indicator,'Jamaica',17.8,'Metric tonnes','Illustrative programme record','Destruction certificates; programme reporting records','Aggregated from verified project records.','Demo record for interface testing.','review',$hassan,$samini,$samini,date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);
        $sid = (int)$pdo->lastInsertId();
        audit_setup($pdo,$sid,$hassan,'Submitted for Review','draft','review','Demo record created by setup.');
    }
    // Install/upgrade the component + sub-indicator layer and GEF #11/#10.1 structured fields.
    require_once __DIR__ . '/subindicator_schema.php';
    ensure_subindicator_schema();
    $message = 'Installation completed successfully. GEB and component/sub-indicator layers are ready.';
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>ISLANDS Portal Setup</title>
<link rel="stylesheet" href="assets/app.css"></head><body class="setup-page">
<div class="setup-card"><div class="brand-lockup"><img class="login-logo-dark" src="assets/islands-logo.png" alt="ISLANDS"><div><span>GEB Portal</span></div></div>
<h1>Portal installation</h1>
<?php if ($message): ?><div class="alert success"><?=e($message)?></div><p>Your database, programme projects, indicators and demo users are ready.</p><a class="btn primary" href="<?=e(APP_URL)?>/login.php">Open portal</a><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?=e($error)?></div><p>Check that MySQL is running in XAMPP and that the database credentials in <code>config.php</code> match your local environment.</p><?php endif; ?>
<div class="setup-note"><strong>Demo accounts</strong><br>samini@example.org / ISLANDS2026!<br>hassan@example.org / ISLANDS2026!</div>
</div></body></html>
