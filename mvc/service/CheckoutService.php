<?php

final class CheckoutService
{
    public static function summary(array $items): array
    {
        $totalQuantity = 0;
        $totalPrice = 0;

        foreach ($items as $item) {
            $quantity = (int) $item['cd_quantity'];
            $price = (float) $item['sp_price'];

            $totalQuantity += $quantity;
            $totalPrice += $price * $quantity;
        }

        return [
            'quantity' => $totalQuantity,
            'total' => $totalPrice,
        ];
    }

    public static function validateCustomer(
        array $data
    ): array {
        $errors = [];

        if (trim($data['name'] ?? '') === '') {
            $errors['name'] = 'Vui lòng nhập họ tên';
        }

        $email = trim($data['email'] ?? '');

        if (
            $email === ''
            || !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $errors['email'] = 'Email không hợp lệ';
        }

        if (trim($data['phone'] ?? '') === '') {
            $errors['phone']
                = 'Vui lòng nhập số điện thoại';
        }

        if (trim($data['address'] ?? '') === '') {
            $errors['address']
                = 'Vui lòng nhập địa chỉ';
        }

        return $errors;
    }

    public static function createOrder(
        int $userId,
        int $cartId,
        array $items,
        array $customer
    ): string {
        $summary = self::summary($items);

        $orderCode =
            'DH'
            . date('YmdHis')
            . random_int(100, 999);

        $connection = Database::connection();

        try {
            $connection->beginTransaction();
            foreach ($items as $item) {

    $productId = (int) $item['id_sp'];
    $quantity = (int) $item['cd_quantity'];

    if (
        !ProductModel::hasStock(
            $productId,
            $quantity
        )
    ) {
        throw new RuntimeException(
            'Sản phẩm không đủ số lượng tồn kho'
        );
    }
}


            $orderId = OrderModel::create(
                trim($customer['name']),
                trim($customer['email']),
                trim($customer['phone']),
                trim($customer['address']),
                trim($customer['country'] ?? ''),
                trim($customer['city'] ?? ''),
                trim($customer['district'] ?? ''),
                trim($customer['commune'] ?? ''),
                trim($customer['message'] ?? ''),
                'Chờ xác nhận',
                (float) $summary['total'],
                $userId,
                (int) $summary['quantity'],
                $orderCode
            );

            foreach ($items as $item) {
                OrderModel::addDetail(
                    (int) $orderId,
                    (int) $item['id_sp'],
                    (int) $item['cd_quantity'],
                    (string) $item['cd_option'],
                    (string) $item['cd_optionColor']
                );
                ProductModel::decreaseStock(
    (int) $item['id_sp'],
    (int) $item['cd_quantity']
);
            }

            CartModel::clearDetails($cartId);

            $connection->commit();

            return $orderId;
        } catch (Throwable $exception) {

            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
}
    }
}