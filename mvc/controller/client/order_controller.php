<?php

final class ClientOrderController
{
    public static function handle(string $action): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            header('Location: ?client=login');
            exit;
        }

        switch ($action) {
            case 'orderHistory':
                self::history($userId);
                break;

            case 'orderDetail':
                self::detail($userId);
                break;

            default:
                http_response_code(404);
                exit('Chức năng đơn hàng không tồn tại');
        }
    }

    private static function history(
        int $userId
    ): void {
        $orders = OrderModel::forUser($userId);

        include __DIR__
            . '/../../view/client/order/list.php';
    }

    private static function detail(
        int $userId
    ): void {
        $id = (int) ($_GET['id'] ?? 0);

        $order = OrderModel::find($id);

        if (
            !$order
            || (int) $order['id_tk'] !== $userId
        ) {
            http_response_code(404);
            exit('Đơn hàng không tồn tại');
        }

        $details = OrderModel::detailsByOrder(
            $id,
            $userId
        );

        include __DIR__
            . '/../../view/client/order/detail.php';
    }
}