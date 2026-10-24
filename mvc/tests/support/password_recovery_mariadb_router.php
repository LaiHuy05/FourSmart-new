<?php

// CLI server only. Failure never falls back to the production Database factory.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/guarded_mariadb.php';
try {
    $config = mariaPrivateConfig(getenv('FOURSMART_MARIADB_PRIVATE_CONFIG') ?: '');
    $sessionDirectory = getenv('FOURSMART_RECOVERY_TEST_DIRECTORY') ?: $config['private_directory'];
    $sessionDirectory = realpath($sessionDirectory);
    if (!$sessionDirectory || !is_dir($sessionDirectory . '/sessions')
        || (dirname($sessionDirectory) !== realpath(sys_get_temp_dir()))
        || !preg_match('/\Afoursmart-(?:recovery-|mariadb-test-)[0-9a-f]{16}\z/', basename($sessionDirectory))) {
        throw new RuntimeException('Private test sessions unavailable.');
    }
    $connection = new GuardedMariaDB($config);
    $connection->assertFixture();
    require_once __DIR__ . '/../../core/Database.php';
    (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $connection);
    // Ports do not isolate cookies; keep this distinct from live PHPSESSID.
    session_name('FOURSMART_TESTSESSID');
    ini_set('session.save_path', $sessionDirectory . '/sessions');
    ini_set('session.serialize_handler', 'php_serialize');
} catch (Throwable $error) {
    http_response_code(503);
    exit('Test database unavailable. No production fallback.');
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$asset = realpath(__DIR__ . '/../..' . $path);
$assetRoot = realpath(__DIR__ . '/../../view/assets');
if (is_string($asset) && str_starts_with($asset, $assetRoot . DIRECTORY_SEPARATOR)
    && in_array(pathinfo($asset, PATHINFO_EXTENSION), ['css', 'js'], true)) {
    return false;
}
require __DIR__ . '/../../index.php';
