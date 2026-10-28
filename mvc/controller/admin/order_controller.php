<?php

final class AdminOrderController
{
    public static function handle(string $action): void
    {
        switch ($action) {
            case 'orderList':
                self::index();
                break;

            case 'orderDetail':
                self::detail();
                break;

            case 'orderStatus':
                self::updateStatus();
                break;

            default:
                http_response_code(404);
                exit('Chức năng đơn hàng không tồn tại');
        }
    }

    private static function index(): void
    {
        $orders = OrderModel::all();

        include __DIR__
            . '/../../view/admin/order/list.php';
    }

    private static function detail(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        $order = OrderModel::find($id);

        if (!$order) {
            http_response_code(404);
            exit('Đơn hàng không tồn tại');
        }

        $details = OrderModel::detailsByOrderId($id);

        include __DIR__
            . '/../../view/admin/order/detail.php';
    }

    private static function updateStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Phương thức không hợp lệ');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('CSRF không hợp lệ');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        $allowedStatuses = [
            'Chờ xác nhận',
            'Đã xác nhận',
            'Đang giao',
            'Đã giao',
            'Đã hủy',
        ];

        if (
            $id <= 0
            || !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            http_response_code(422);
            exit('Dữ liệu không hợp lệ');
        }

        if (!OrderModel::find($id)) {
            http_response_code(404);
            exit('Đơn hàng không tồn tại');
        }

        OrderModel::updateStatus(
            $id,
            $status
        );

        header(
            'Location: ?act=admin&admin=orderDetail&id='
            . $id
        );
        exit;
    }
}