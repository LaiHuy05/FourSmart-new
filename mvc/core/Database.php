<?php

final class Database
{
    private static $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::configuration();
        $dsn = 'mysql:host=' . $config['host']
            . ';port=' . $config['port']
            . ';dbname=' . $config['database'] . ';charset=utf8mb4';

        try {
            $connection = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $identity = $connection->query(
                'SELECT @@port AS server_port, DATABASE() AS database_name, CURRENT_USER() AS identity'
            )->fetch();
            if ((int) $identity['server_port'] !== $config['port']
                || $identity['database_name'] !== $config['database']
                || $identity['identity'] !== $config['user'] . '@127.0.0.1') {
                throw new RuntimeException('Database identity mismatch.');
            }
            self::$connection = $connection;
        } catch (Throwable $error) {
            // Log neither the configuration nor PDO messages/arguments.
            error_log('FourSmart database connection unavailable.');
            throw new RuntimeException('Database connection is unavailable.');
        }

        return self::$connection;
    }

    private static function configuration(): array
    {
        $path = getenv('FOURSMART_DATABASE_CONFIG');
        $resolved = is_string($path) && $path !== '' ? realpath($path) : false;
        if (!$resolved || !is_file($resolved) || !is_readable($resolved)
            || filesize($resolved) > 16384) {
            throw new RuntimeException('Database configuration is unavailable.');
        }

        $normalize = static fn(string $value): string =>
            strtolower(rtrim(str_replace('\\', '/', $value), '/'));
        foreach ([dirname(__DIR__, 2), $_SERVER['DOCUMENT_ROOT'] ?? ''] as $webRoot) {
            if (is_string($webRoot) && $webRoot !== ''
                && str_starts_with($normalize($resolved), $normalize($webRoot) . '/')) {
                throw new RuntimeException('Database configuration is unavailable.');
            }
        }

        try {
            $config = json_decode(file_get_contents($resolved), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            throw new RuntimeException('Database configuration is unavailable.');
        }
        if (!is_array($config) || ($config['host'] ?? null) !== '127.0.0.1'
            || !is_int($config['port'] ?? null) || $config['port'] < 1 || $config['port'] > 65535
            || !is_string($config['database'] ?? null)
            || preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]{0,63}\z/', $config['database']) !== 1
            || !is_string($config['user'] ?? null)
            || preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]{0,79}\z/', $config['user']) !== 1
            || strcasecmp($config['user'], 'root') === 0
            || !is_string($config['password'] ?? null) || $config['password'] === ''
            || str_contains($config['password'], "\0")) {
            throw new RuntimeException('Database configuration is unavailable.');
        }
        return $config;
    }

    // SELECT nhiều dòng
    public static function query(string $sql, ...$params): array
    {
        $statement = self::connection()->prepare($sql);

        $statement->execute($params);

        return $statement->fetchAll();
    }

    // SELECT một dòng
    public static function one(string $sql, ...$params)
    {
        $statement = self::connection()->prepare($sql);

        $statement->execute($params);

        $result = $statement->fetch();

        return $result ?: null;
    }

    // INSERT / UPDATE / DELETE
    public static function execute(string $sql, ...$params): void
    {
        $statement = self::connection()->prepare($sql);

        $statement->execute($params);
    }

    // INSERT và lấy ID vừa thêm
    public static function insert(string $sql, ...$params): string
    {
        $connection = self::connection();

        $statement = $connection->prepare($sql);

        $statement->execute($params);

        return $connection->lastInsertId();
    }
}
