<?php

require_once __DIR__
    . '/../../service/CheckoutService.php';

final class ClientCheckoutController
{
    public static function handle(
        string $action
    ): void {
        if ($action !== 'checkout') {
            http_response_code(404);
            exit('Chức năng thanh toán không tồn tại');
        }

        $userId =
            (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            header('Location: ?client=login');
            exit;
        }

        $cart = CartModel::findByUser($userId);

        if (!$cart) {
            header('Location: ?client=cart');
            exit;
        }

        $cartId = (int) $cart['gh_id'];

        $items =
            CartModel::detailsWithProductsByCart(
                $cartId
            );

        if (empty($items)) {
            header('Location: ?client=cart');
            exit;
        }

        $summary =
            CheckoutService::summary($items);

        $account = AccountModel::find($userId);

        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (
                !Csrf::verify(
                    $_POST['csrf_token'] ?? null
                )
            ) {
                http_response_code(403);
                exit('CSRF không hợp lệ');
            }

            $errors =
                CheckoutService::validateCustomer(
                    $_POST
                );

            if (empty($errors)) {

                $orderId =
                    CheckoutService::createOrder(
                        $userId,
                        $cartId,
                        $items,
                        $_POST
                    );

                header(
                    'Location: ?client=orderDetail&id='
                    . $orderId
                );
                exit;
            }
        }

        include __DIR__
            . '/../../view/client/pay.php';
    }
}