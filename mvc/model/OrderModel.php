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
    public static function create(
    string $name,
    string $email,
    string $phone,
    string $address,
    string $country,
    string $city,
    string $district,
    string $commune,
    string $message,
    string $status,
    float $totalAmount,
    int $accountId,
    int $quantity,
    string $orderCode
): string {
    return Database::insert(
        'INSERT INTO `order` (
            dh_nameUser,
            dh_emailUser,
            dh_phoneUser,
            dh_addressUser,
            dh_countryPay,
            dh_cityPay,
            dh_districtPay,
            dh_communePay,
            dh_messagePay,
            dh_orderdate,
            dh_status,
            dh_totalamount,
            id_tk,
            sp_quantity,
            dh_ma
         )
         VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
            CURRENT_TIMESTAMP,
            ?, ?, ?, ?, ?
         )',
        $name,
        $email,
        $phone,
        $address,
        $country,
        $city,
        $district,
        $commune,
        $message,
        $status,
        $totalAmount,
        $accountId,
        $quantity,
        $orderCode
    );
}

public static function addDetail(
    int $orderId,
    int $productId,
    int $quantity,
    string $memory,
    string $color
): void {
    Database::execute(
        'INSERT INTO orderdetail(
            id_dh,
            id_sp,
            ct_quantity,
            od_option,
            od_optionColor
         )
         VALUES (?, ?, ?, ?, ?)',
        $orderId,
        $productId,
        $quantity,
        $memory,
        $color
    );
}

public static function updateStatus(
    int $id,
    string $status
): void {
    Database::execute(
        'UPDATE `order`
         SET dh_status = ?
         WHERE dh_id = ?',
        $status,
        $id
    );
}
public static function detailsByOrder(
    int $orderId,
    int $userId
): array {
    return Database::query(
        'SELECT
            od.*,
            p.sp_name,
            p.sp_price
         FROM orderdetail od
         JOIN `order` o
            ON o.dh_id = od.id_dh
         JOIN product p
            ON p.sp_id = od.id_sp
         WHERE od.id_dh = ?
         AND o.id_tk = ?',
        $orderId,
        $userId
    );
}
}