<?php

// No fallback to the application MySQL connection is possible in this router.
$directory = getenv('FOURSMART_RECOVERY_TEST_DIRECTORY');
$resolved = $directory ? realpath($directory) : false;
if (PHP_SAPI !== 'cli-server' || !$resolved || dirname($resolved) !== realpath(sys_get_temp_dir())
    || !str_starts_with(basename($resolved), 'foursmart-recovery-')
    || !is_file($resolved . '/test-only.marker') || !is_file($resolved . '/accounts.sqlite')) {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
ini_set('session.save_path', $resolved . '/sessions');
ini_set('session.serialize_handler', 'php_serialize');
require_once __DIR__ . '/../../core/Database.php';
$connection = new PDO('sqlite:' . $resolved . '/accounts.sqlite');
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$connection->exec('PRAGMA busy_timeout = 10000');
(new ReflectionProperty(Database::class, 'connection'))->setValue(null, $connection);
if (Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
    http_response_code(500);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$asset = realpath(__DIR__ . '/../..' . $path);
$assetRoot = realpath(__DIR__ . '/../../view/assets');
if (is_string($asset) && str_starts_with($asset, $assetRoot . DIRECTORY_SEPARATOR)
    && in_array(pathinfo($asset, PATHINFO_EXTENSION), ['css', 'js'], true)) {
    return false;
}
require __DIR__ . '/../../index.php';
