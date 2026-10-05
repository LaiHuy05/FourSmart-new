<?php

final class ProductModel
{
    public static function all(): array
    {
        return Database::query(
            'SELECT * FROM product'
        );
    }

    public static function find(int $id)
    {
        return Database::one(
            'SELECT * FROM product
             WHERE sp_id = ?',
            $id
        );
    }
}