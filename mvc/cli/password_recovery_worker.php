<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/../core/bootstrap.php';

try {
    $config = PasswordRecoveryConfig::load();
    $service = new PasswordRecoveryService(new PasswordRecoveryModel(Database::connection()), $config);
    $limit = in_array('--once', $argv, true) ? 1 : 100;
    for ($processed = 0; $processed < $limit; $processed++) {
        $result = $service->processOne();
        echo $result, PHP_EOL;
        if ($result === 'idle') {
            break;
        }
    }
    $service->cleanup();
} catch (Throwable $exception) {
    fwrite(STDERR, "Worker unavailable: check private configuration and approved schema.\n");
    exit(1);
}
