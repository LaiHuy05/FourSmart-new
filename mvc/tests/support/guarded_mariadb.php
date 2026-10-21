<?php

declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    http_response_code(404);
    exit;
}

final class GuardedMariaDBStatement extends PDOStatement
{
    protected function __construct(private GuardedMariaDB $connection)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->connection->guardStatement($this->queryString);
        return parent::execute($params);
    }
}

final class GuardedMariaDB extends PDO
{
    public const DATABASE = 'duan1_test';
    public const USER = 'foursmart_duan1_test';
    public const MARKER = 'foursmart-password-recovery-isolated-test';
    public int $guardedWrites = 0;

    public function __construct(#[SensitiveParameter] array $config)
    {
        if (($config['database'] ?? '') !== self::DATABASE || ($config['user'] ?? '') !== self::USER) {
            throw new RuntimeException('Only the approved duan1_test identity is allowed.');
        }
        parent::__construct('mysql:host=127.0.0.1;port=3306;dbname=duan1_test;charset=utf8mb4',
            self::USER, $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        $this->assertTarget();
        $scoped = false;
        foreach (parent::query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
            $normalized = str_replace('\\', '', $grant);
            if (preg_match('/\AGRANT USAGE ON \*\.\* TO /i', $normalized)) continue;
            if (!preg_match('/\AGRANT .+ ON `duan1_test`\.(?:\*|`(?:account|role|cart|cartdetail|category|comment|order|orderdetail|product|productcolor|productmemory|recovery_test_guard|password_reset_tokens|password_reset_rate_limits|password_reset_jobs)`) TO /i', $normalized)
                || stripos($normalized, 'WITH GRANT OPTION') !== false) {
                throw new RuntimeException('Test identity has privileges outside the approved database.');
            }
            $scoped = true;
        }
        if (!$scoped) throw new RuntimeException('Test-scoped privileges are missing.');
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [GuardedMariaDBStatement::class, [$this]]);
    }

    public function assertTarget(): void
    {
        $identity = parent::query('SELECT DATABASE() AS db_name, CURRENT_USER() AS identity')->fetch();
        if ($identity['db_name'] !== self::DATABASE || $identity['identity'] !== self::USER . '@127.0.0.1') {
            throw new RuntimeException('Wrong database or database identity; write refused.');
        }
    }

    public function assertFixture(): void
    {
        $this->assertTarget();
        if ($this->query('SELECT marker FROM recovery_test_guard')->fetchColumn() !== self::MARKER) {
            throw new RuntimeException('Test fixture marker missing.');
        }
    }

    public function guardStatement(string $sql): void
    {
        $sql = preg_replace('/^\s*(?:--[^\n]*(?:\n|$)\s*)*/', '', $sql);
        if (preg_match('/\A\s*(SELECT|SHOW|EXPLAIN)\b/i', $sql)) return;
        $this->assertTarget();
        if (preg_match('/\bduan1\s*\./i', str_replace('`', '', $sql))
            || preg_match('/\A\s*(USE|GRANT|REVOKE|CREATE\s+(DATABASE|USER)|DROP\s+(DATABASE|USER)|ALTER\s+USER|SET\s+GLOBAL)\b/i', $sql)) {
            throw new RuntimeException('Statement is outside the approved test database.');
        }
        $this->guardedWrites++;
    }

    public function exec(string $statement): int|false
    {
        $this->guardStatement($statement);
        return parent::exec($statement);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->guardStatement($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

function mariaPrivateConfig(string $path): array
{
    $resolved = realpath($path);
    $parent = $resolved ? dirname($resolved) : '';
    if (!$resolved || basename($resolved) !== 'connection.json'
        || dirname($parent) !== realpath(sys_get_temp_dir())
        || !preg_match('/\Afoursmart-mariadb-test-[0-9a-f]{16}\z/', basename($parent))) {
        throw new RuntimeException('Private test configuration must be outside web roots in its guarded temporary directory.');
    }
    $config = json_decode(file_get_contents($resolved), true, 16, JSON_THROW_ON_ERROR);
    if (($config['database'] ?? '') !== GuardedMariaDB::DATABASE || ($config['user'] ?? '') !== GuardedMariaDB::USER) {
        throw new RuntimeException('Unapproved private test target.');
    }
    return $config + ['private_directory' => $parent];
}

function mariaConfigPath(array $arguments): string
{
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--private-config=')) return substr($argument, 17);
    }
    return getenv('FOURSMART_MARIADB_PRIVATE_CONFIG') ?: '';
}

function mariaTestEnvironment(array $config): void
{
    putenv('FOURSMART_PASSWORD_RECOVERY_ENABLED=1');
    putenv('FOURSMART_RECOVERY_HMAC_KEY=' . $config['hmac_key']);
    putenv('FOURSMART_APP_URL=' . $config['app_url']);
}
