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
}