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

    public static function detailsByCart(int $cartId): array
    {
        return Database::query(
            'SELECT * FROM cartdetail
             WHERE id_gh = ?',
            $cartId
        );
    }

    public static function create(int $userId): void
    {
        Database::execute(
            'INSERT INTO cart(id_tk)
             VALUES (?)',
            $userId
        );
    }

    public static function addItem(
        int $cartId,
        int $productId,
        string $memory,
        string $color
    ): void {
        Database::execute(
            'INSERT INTO cartdetail(
                id_gh,
                id_sp,
                cd_option,
                cd_optionColor
             )
             VALUES (?, ?, ?, ?)',
            $cartId,
            $productId,
            $memory,
            $color
        );
    }

    public static function increaseQuantity(int $detailId): void
    {
        Database::execute(
            'UPDATE cartdetail
             SET cd_quantity = cd_quantity + 1
             WHERE cd_id = ?',
            $detailId
        );
    }

    public static function decreaseQuantity(int $detailId): void
    {
        Database::execute(
            'UPDATE cartdetail
             SET cd_quantity = cd_quantity - 1
             WHERE cd_id = ?
             AND cd_quantity > 1',
            $detailId
        );
    }

    public static function deleteItem(int $detailId): void
    {
        Database::execute(
            'DELETE FROM cartdetail
             WHERE cd_id = ?',
            $detailId
        );
    }
    public static function detailBelongsToUser(
    int $detailId,
    int $userId
): bool {
    $result = Database::one(
        'SELECT COUNT(*) AS total
         FROM cartdetail cd
         JOIN cart c ON c.gh_id = cd.id_gh
         WHERE cd.cd_id = ?
         AND c.id_tk = ?',
        $detailId,
        $userId
    );

    return (int) ($result['total'] ?? 0) > 0;
}
public static function detailsWithProductsByCart(
    int $cartId
): array {
    return Database::query(
        'SELECT
            cd.*,
            p.sp_name,
            p.sp_price
         FROM cartdetail cd
         JOIN product p
            ON p.sp_id = cd.id_sp
         WHERE cd.id_gh = ?',
        $cartId
    );
}

public static function clearDetails(
    int $cartId
): void {
    Database::execute(
        'DELETE FROM cartdetail
         WHERE id_gh = ?',
        $cartId
    );
}
}
