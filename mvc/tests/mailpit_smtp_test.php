<?php

declare(strict_types=1);

// Sends only a fixed, token-free message through the production mailer.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/../service/PasswordRecoveryMailer.php';

try {
    (new PasswordRecoveryMailer(''))->sendTestMessage();
    fwrite(STDOUT, "PASS: Mailpit accepted the token-free Vietnamese HTML/plain-text email.\n");
    fwrite(STDOUT, 'PASS: Composer autoload and PHPMailer ' . PHPMailer\PHPMailer\PHPMailer::VERSION . " loaded.\n");
    fwrite(STDOUT, "Inbox: http://127.0.0.1:8025\n");
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: Local email delivery failed. Check the local Mailpit service and configuration.\n");
    exit(1);
}
