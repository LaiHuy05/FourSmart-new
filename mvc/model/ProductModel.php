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
    public static function create(
    string $name,
    string $image,
    float $price,
    int $quantity,
    string $description,
    int $categoryId,
    string $code,
    float $oldPrice
): void {
    Database::execute(
        'INSERT INTO product(
            sp_name,
            sp_image,
            sp_price,
            sp_quantity,
            sp_describe,
            id_dm,
            sp_ma,
            sp_pricedel
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        $name,
        $image,
        $price,
        $quantity,
        $description,
        $categoryId,
        $code,
        $oldPrice
    );
}

public static function update(
    int $id,
    string $name,
    string $image,
    float $price,
    int $quantity,
    string $description,
    int $categoryId,
    float $oldPrice
): void {
    Database::execute(
        'UPDATE product
         SET sp_name = ?,
             sp_image = ?,
             sp_price = ?,
             sp_quantity = ?,
             sp_describe = ?,
             id_dm = ?,
             sp_pricedel = ?
         WHERE sp_id = ?',
        $name,
        $image,
        $price,
        $quantity,
        $description,
        $categoryId,
        $oldPrice,
        $id
    );
}

public static function delete(int $id): void
{
    Database::execute(
        'DELETE FROM product
         WHERE sp_id = ?',
        $id
    );
}
public static function hasStock(
    int $productId,
    int $quantity
): bool {
    $product = self::find($productId);

    if (!$product) {
        return false;
    }

    return (int) $product['sp_quantity'] >= $quantity;
}

public static function decreaseStock(
    int $productId,
    int $quantity
): void {
    Database::execute(
        'UPDATE product
         SET sp_quantity = sp_quantity - ?
         WHERE sp_id = ?
         AND sp_quantity >= ?',
        $quantity,
        $productId,
        $quantity
    );
}

public static function hasRelatedData(int $id): bool
{
    $result = Database::one(
        'SELECT
            (
                (SELECT COUNT(*)
                 FROM cartdetail
                 WHERE id_sp = ?)
                +
                (SELECT COUNT(*)
                 FROM orderdetail
                 WHERE id_sp = ?)
                +
                (SELECT COUNT(*)
                 FROM comment
                 WHERE id_sp = ?)
            ) AS total',
        $id,
        $id,
        $id
    );

    return (int) ($result['total'] ?? 0) > 0;
}
}