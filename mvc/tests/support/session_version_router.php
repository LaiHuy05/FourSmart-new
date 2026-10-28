<?php

// Only the private, disposable SQLite fixture is reachable from this router.
$directory = getenv('FOURSMART_SESSION_POLICY_TEST_DIRECTORY');
$resolved = $directory ? realpath($directory) : false;
if (PHP_SAPI !== 'cli-server' || !$resolved
    || dirname($resolved) !== realpath(sys_get_temp_dir())
    || !preg_match('/\Afoursmart-recovery-[0-9a-f]{16}\z/', basename($resolved))
    || !is_file($resolved . '/test-only.marker') || !is_file($resolved . '/policy.json')) {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
$settings = json_decode(file_get_contents($resolved . '/policy.json'), true, 16, JSON_THROW_ON_ERROR);
$schema = $settings['schema'] ?? '';
if (!in_array($schema, ['versioned', 'legacy'], true)) {
    http_response_code(503);
    exit;
}
$file = $resolved . '/' . $schema . '.sqlite';
if (!is_file($file)) {
    http_response_code(503);
    exit;
}
putenv('FOURSMART_SESSION_VERSION_MODE=' . $settings['mode']);
putenv('FOURSMART_PASSWORD_RECOVERY_ENABLED=' . ($settings['recovery'] ? '1' : '0'));
putenv($settings['recovery'] ? 'FOURSMART_RECOVERY_HMAC_KEY=' . $settings['key'] : 'FOURSMART_RECOVERY_HMAC_KEY');
session_name('FOURSMART_POLICY_TESTSESSID');
ini_set('session.save_path', $resolved . '/sessions');
ini_set('session.serialize_handler', 'php_serialize');
require_once __DIR__ . '/../../core/Database.php';
$connection = new class('sqlite:' . $file, (bool) $settings['database_error']) extends PDO {
    public function __construct(string $dsn, private bool $unavailable)
    {
        parent::__construct($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->unavailable && preg_match('/\bFROM\s+account\b/i', $query)) {
            throw new PDOException('private-fixture-database-outage');
        }
        return parent::prepare($query, $options);
    }
};
if ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
    http_response_code(503);
    exit;
}
(new ReflectionProperty(Database::class, 'connection'))->setValue(null, $connection);
require __DIR__ . '/../../index.php';
