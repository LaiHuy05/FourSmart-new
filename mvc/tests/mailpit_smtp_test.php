<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// Run: php mvc/tests/mailpit_smtp_test.php
// Local SMTP smoke test only; do not load the application or its database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "FAIL: vendor/autoload.php is missing.\n");
    exit(1);
}

try {
    require_once $autoload;

    $mail = new PHPMailer(true);
    $mail->isSMTP();

    // Fixed loopback endpoint: no external SMTP hosts or fallback transport.
    $mail->Host = '127.0.0.1';
    $mail->Port = 1025;
    $mail->SMTPAuth = false;
    $mail->SMTPSecure = '';
    $mail->SMTPAutoTLS = false;
    $mail->Timeout = 5;
    $mail->Hostname = 'foursmart.test';
    $mail->Helo = 'localhost';
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = 'echo';

    $mail->setFrom('noreply@foursmart.test', 'FourSmart Local Test');
    $mail->addAddress('developer@foursmart.test', 'Local Developer');
    $mail->Subject = 'FourSmart - Test Email';
    $mail->isHTML(false);
    $mail->Body = "Local SMTP connection test from FourSmart to Mailpit.\n"
        . "SMTP: 127.0.0.1:1025\n"
        . "Authentication: disabled. TLS and automatic STARTTLS: disabled.\n";

    $mail->send();

    fwrite(STDOUT, 'PASS: Composer autoload and PHPMailer ' . PHPMailer::VERSION . " loaded.\n");
    fwrite(STDOUT, "PASS: Mailpit accepted the email at 127.0.0.1:1025.\n");
    fwrite(STDOUT, 'Subject: ' . $mail->Subject . "\n");
    fwrite(STDOUT, 'Message-ID: ' . $mail->getLastMessageID() . "\n");
    fwrite(STDOUT, 'SMTP transaction ID: ' . $mail->getSMTPInstance()->getLastTransactionID() . "\n");
    fwrite(STDOUT, "Inbox: http://127.0.0.1:8025\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
