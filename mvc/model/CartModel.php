<?php

final class CartModel
{
    public static function all(): array
    {
        return Database::query(
            'SELECT * FROM cart'
        );
    }

    public static function details(): array
    {
        return Database::query(
            'SELECT * FROM cartdetail'
        );
    }

    public static function findByUser(int $userId)
    {
        return Database::one(
            'SELECT * FROM cart
             WHERE id_tk = ?
             LIMIT 1',
            $userId
        );
    }
}