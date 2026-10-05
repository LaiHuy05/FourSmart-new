<?php

final class Database
{
    private static $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = 'localhost';
        $database = 'duan1';
        $username = 'root';
        $password = '';

        $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

        self::$connection = new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$connection;
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