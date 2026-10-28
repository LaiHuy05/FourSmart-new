<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/../service/PasswordRecoveryMailer.php';

$names = ['FOURSMART_MAIL_TRANSPORT', 'FOURSMART_SMTP_HOST', 'FOURSMART_SMTP_PORT',
    'FOURSMART_SMTP_TIMEOUT', 'FOURSMART_MAIL_FROM', 'FOURSMART_MAIL_FROM_NAME', 'FOURSMART_MAIL_REPLY_TO'];
$original = [];
foreach ($names as $name) { $original[$name] = getenv($name); putenv($name); }
$log = tempnam(sys_get_temp_dir(), 'foursmart-mail-log-');
$oldLog = ini_set('error_log', $log);
$passed = 0;
$failed = 0;
$check = static function (bool $value): void { if (!$value) throw new RuntimeException('Assertion failed.'); };
$test = static function (string $name, callable $run) use (&$passed, &$failed): void {
    try { $run(); $passed++; echo 'PASS: ', $name, "\n"; }
    catch (Throwable $error) { $failed++; echo 'FAIL: ', $name, "\n"; }
};
$expectFailure = static function () use ($check): float {
    ob_start();
    $start = microtime(true);
    $caught = null;
    try { (new PasswordRecoveryMailer(''))->sendTestMessage(); }
    catch (Throwable $error) { $caught = $error; }
    $output = ob_get_clean();
    $check($caught instanceof RuntimeException && $caught->getMessage() === 'Local email delivery failed.');
    $check($caught->getPrevious() === null && $output === '');
    $check(!str_contains((string) $caught, 'private-smtp-diagnostic'));
    $check(!str_contains((string) $caught, 'developer@foursmart.test'));
    return microtime(true) - $start;
};

try {
    $test('existing Composer autoload and PHPMailer are usable', static function () use ($check): void {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        $check(class_exists(PHPMailer\PHPMailer\PHPMailer::class) && class_exists(PHPMailer\PHPMailer\SMTP::class));
    });
    $test('default environment selects loopback Mailpit with bounded timeout', static function () use ($check): void {
        $config = PasswordRecoveryMailConfig::load();
        $check($config['host'] === '127.0.0.1' && $config['port'] === 1025 && $config['timeout'] === 5);
    });
    foreach (['FOURSMART_MAIL_TRANSPORT' => 'smtp', 'FOURSMART_SMTP_HOST' => 'smtp.gmail.com',
        'FOURSMART_SMTP_PORT' => '0', 'FOURSMART_SMTP_TIMEOUT' => '300',
        'FOURSMART_MAIL_FROM' => 'sender@example.com', 'FOURSMART_MAIL_REPLY_TO' => "reply@foursmart.test\r\nBcc: x@foursmart.test",
        'FOURSMART_MAIL_FROM_NAME' => "FourSmart\r\nBcc: x@foursmart.test"] as $name => $value) {
        $test('rejects unsafe ' . $name . ' without echoing its value', static function () use ($name, $value, $check): void {
            putenv($name . '=' . $value);
            try {
                $caught = null;
                try { PasswordRecoveryMailConfig::load(); } catch (RuntimeException $error) { $caught = $error; }
                $check($caught !== null && $caught->getMessage() === 'Local mail configuration is unavailable.');
            } finally { putenv($name); }
        });
    }
    $test('connection refused produces only a generic error', static function () use ($expectFailure, $check): void {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        putenv('FOURSMART_SMTP_PORT=' . parse_url('tcp://' . $address, PHP_URL_PORT));
        putenv('FOURSMART_SMTP_TIMEOUT=1');
        $check($expectFailure() < 4);
    });
    foreach (['reject', 'timeout'] as $mode) {
        $test('SMTP ' . $mode . ' is bounded and exposes no diagnostic or message', static function () use ($mode, $expectFailure, $check): void {
            $process = proc_open([PHP_BINARY, __DIR__ . '/support/local_smtp_failure.php', $mode],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
            $check(is_resource($process));
            try {
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], 5);
                $address = trim((string) fgets($pipes[1]));
                $check(preg_match('/\A127\.0\.0\.1:[0-9]+\z/', $address) === 1);
                putenv('FOURSMART_SMTP_PORT=' . parse_url('tcp://' . $address, PHP_URL_PORT));
                putenv('FOURSMART_SMTP_TIMEOUT=1');
                $elapsed = $expectFailure();
                $check($elapsed < 5 && ($mode !== 'timeout' || $elapsed >= 0.8));
                if ($mode === 'reject') {
                    // Reaching DATA proves advertised AUTH/STARTTLS were not attempted.
                    $check(trim((string) fgets($pipes[1])) === 'DATA_REJECTED');
                }
            } finally {
                proc_terminate($process);
                foreach ([1, 2] as $index) { if (is_resource($pipes[$index])) fclose($pipes[$index]); }
                proc_close($process);
            }
        });
    }
    $test('mail failures leave no sensitive PHP log output', static function () use ($check, $log): void {
        $check(file_get_contents($log) === '');
    });
} finally {
    foreach ($original as $name => $value) { putenv($value === false ? $name : $name . '=' . $value); }
    ini_set('error_log', (string) $oldLog);
    unlink($log);
}
echo $passed, ' passed, ', $failed, " failed\n";
exit($failed > 0 ? 1 : 0);
