<?php

final class ClientCartController
{
    public static function handle(string $action): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId === null) {
            header('Location: ?client=login');
            exit;
        }

        $cart = CartModel::findByUser(
            (int) $userId
        );

        if (!$cart) {
            CartModel::create(
                (int) $userId
            );

            $cart = CartModel::findByUser(
                (int) $userId
            );
        }

        $cartDetails = [];

        if ($cart) {
            $cartDetails = CartModel::detailsByCart(
                (int) $cart['gh_id']
            );
        }

        $products = ProductModel::all();

        include __DIR__
            . '/../../view/client/cart.php';
    }
}