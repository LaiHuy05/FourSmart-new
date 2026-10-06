<?php

final class OrderModel
{
    public static function all(): array
    {
        return Database::query(
            'SELECT * FROM `order`
             ORDER BY dh_orderdate DESC'
        );
    }

    public static function find(int $id)
    {
        return Database::one(
            'SELECT * FROM `order`
             WHERE dh_id = ?',
            $id
        );
    }

    public static function forUser(int $userId): array
    {
        return Database::query(
            'SELECT * FROM `order`
             WHERE id_tk = ?
             ORDER BY dh_orderdate DESC',
            $userId
        );
    }
}