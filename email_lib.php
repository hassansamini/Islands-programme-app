<?php
declare(strict_types=1);

function email_settings(): array {
    static $settings = null;
    if ($settings !== null) return $settings;
    $defaults = [
        'enabled' => 0,
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'from_email' => '',
        'from_name' => APP_NAME,
        'public_url' => 'http://localhost' . APP_URL,
    ];
    try {
        $row = db()->query("SELECT * FROM email_settings WHERE id=1 LIMIT 1")->fetch();
        $settings = array_merge($defaults, $row ?: []);
    } catch (Throwable $e) {
        $settings = $defaults;
    }
    return $settings;
}

function email_configured(): bool {
    $s = email_settings();
    return !empty($s['enabled']) && $s['host'] !== '' && $s['username'] !== '' && $s['password'] !== '' && filter_var($s['from_email'], FILTER_VALIDATE_EMAIL);
}

function smtp_read($fp): string {
    $data = '';
    while (!feof($fp)) {
        $line = fgets($fp, 515);
        if ($line === false) break;
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
}

function smtp_expect($fp, array $codes): string {
    $response = smtp_read($fp);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP error ' . $code . ': ' . trim($response));
    }
    return $response;
}

function smtp_cmd($fp, string $command, array $codes): string {
    fwrite($fp, $command . "\r\n");
    return smtp_expect($fp, $codes);
}

function smtp_send(array $to, string $subject, string $body): array {
    $s = email_settings();
    if (!email_configured()) return ['ok' => false, 'error' => 'SMTP email is not configured.'];
    $host = (string)$s['host'];
    $port = (int)$s['port'];
    $encryption = (string)$s['encryption'];
    $transport = $encryption === 'ssl' ? 'ssl://' . $host : 'tcp://' . $host;
    $errno = 0; $errstr = '';
    $context = stream_context_create(['ssl' => ['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);
    $fp = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) return ['ok'=>false,'error'=>'SMTP connection failed: ' . $errstr . ' (' . $errno . ')'];
    stream_set_timeout($fp, 20);
    try {
        smtp_expect($fp, [220]);
        smtp_cmd($fp, 'EHLO localhost', [250]);
        if ($encryption === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to establish TLS encryption.');
            }
            smtp_cmd($fp, 'EHLO localhost', [250]);
        }
        smtp_cmd($fp, 'AUTH LOGIN', [334]);
        smtp_cmd($fp, base64_encode((string)$s['username']), [334]);
        smtp_cmd($fp, base64_encode((string)$s['password']), [235]);
        $from = (string)$s['from_email'];
        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        $validRecipients = [];
        foreach ($to as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                smtp_cmd($fp, 'RCPT TO:<' . $address . '>', [250,251]);
                $validRecipients[] = $address;
            }
        }
        if (!$validRecipients) throw new RuntimeException('No valid email recipients.');
        smtp_cmd($fp, 'DATA', [354]);
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromName = (string)$s['from_name'];
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedFromName . ' <' . $from . '>',
            'To: ' . implode(', ', $validRecipients),
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: ISLANDS GEB Portal',
        ];
        $safeBody = str_replace(["\r\n", "\r"], "\n", $body);
        $safeBody = preg_replace('/^\./m', '..', $safeBody) ?? $safeBody;
        fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $safeBody) . "\r\n.\r\n");
        smtp_expect($fp, [250]);
        smtp_cmd($fp, 'QUIT', [221]);
        fclose($fp);
        return ['ok'=>true,'error'=>''];
    } catch (Throwable $e) {
        fclose($fp);
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}
