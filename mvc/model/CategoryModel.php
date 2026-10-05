<?php

final class CategoryModel
{
    public static function all(): array
    {
        return Database::query(
            'SELECT * FROM category'
        );
    }

    public static function find(int $id)
    {
        return Database::one(
            'SELECT * FROM category
             WHERE dm_id = ?',
            $id
        );
    }
}