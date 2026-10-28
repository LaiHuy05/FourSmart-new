<?php

final class CommentModel
{
    public static function byProduct(
        int $productId
    ): array {
        return Database::query(
            'SELECT
                c.*,
                a.tk_user
             FROM comment c
             JOIN account a
                ON a.tk_id = c.id_tk
             WHERE c.id_sp = ?
             ORDER BY c.bl_id DESC',
            $productId
        );
    }

    public static function create(
        string $content,
        int $userId,
        int $productId
    ): void {
        Database::execute(
            'INSERT INTO comment(
                bl_content,
                id_tk,
                id_sp
             )
             VALUES (?, ?, ?)',
            $content,
            $userId,
            $productId
        );
    }
    public static function all(): array
{
    return Database::query(
        'SELECT
            c.*,
            a.tk_user,
            p.sp_name
         FROM comment c
         JOIN account a
            ON a.tk_id = c.id_tk
         JOIN product p
            ON p.sp_id = c.id_sp
         ORDER BY c.bl_id DESC'
    );
}

public static function delete(int $id): void
{
    Database::execute(
        'DELETE FROM comment
         WHERE bl_id = ?',
        $id
    );
}
}