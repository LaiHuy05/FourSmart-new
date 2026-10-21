<?php

// This fixture must never run through Apache or connect to the application DB.
$testDirectory = getenv('FOURSMART_PASSWORD_RESET_TEST_DIRECTORY');

if (
    PHP_SAPI !== 'cli-server'
    || !$testDirectory
    || !is_file($testDirectory . '/test-only.marker')
    || !is_file($testDirectory . '/accounts.sqlite')
) {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
ini_set('session.save_path', $testDirectory . '/sessions');
ini_set('session.serialize_handler', 'php_serialize');

require_once __DIR__ . '/../../core/Database.php';

$connection = new PDO('sqlite:' . $testDirectory . '/accounts.sqlite');
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$property = new ReflectionProperty(Database::class, 'connection');
$property->setValue(null, $connection);

if (Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
    http_response_code(500);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assetRoot = realpath(__DIR__ . '/../../view/assets');
$asset = realpath(__DIR__ . '/../..' . $path);

if (
    is_string($asset)
    && str_starts_with($asset, $assetRoot . DIRECTORY_SEPARATOR)
    && in_array(pathinfo($asset, PATHINFO_EXTENSION), ['css', 'js'], true)
) {
    return false;
}

require __DIR__ . '/../../index.php';
