<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/password_recovery_fixture.php';
$directory = realpath($argv[1] ?? '');
if (!$directory || dirname($directory) !== realpath(sys_get_temp_dir())
    || !str_starts_with(basename($directory), 'foursmart-recovery-')
    || !is_file($directory . '/test-only.marker') || !is_file($directory . '/accounts.sqlite')) {
    exit(2);
}
try {
    $input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
    $db = new PDO('sqlite:' . $directory . '/accounts.sqlite');
    $model = new PasswordRecoveryModel($db);
    $now = new DateTimeImmutable($input['now'], new DateTimeZone('UTC'));
    if ($input['operation'] === 'reset') {
        echo $model->reset(hex2bin($input['hash']), 'Concurrent-Password!2026', $now) ? '1' : '0';
    } elseif ($input['operation'] === 'request') {
        echo $model->request('customer@example.test', 'fixture-ip', str_repeat('k', 32), $now) ? '1' : '0';
    } elseif ($input['operation'] === 'worker') {
        $service = new PasswordRecoveryService($model, ['key' => str_repeat('k', 32), 'url' => 'http://localhost/FourSmart/mvc/index.php'],
            function () use ($directory): void {
                usleep(150000);
                file_put_contents($directory . '/deliveries.txt', "sent\n", FILE_APPEND | LOCK_EX);
            }, fn() => $now);
        echo $service->processOne();
    } else {
        exit(2);
    }
} catch (Throwable $error) {
    exit(3);
}
