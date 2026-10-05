<?php

final class ClientRouter
{
    public static function run(): void
    {
        $action = $_GET['client'] ?? 'home';

        $routes = [
            'home' => [
                ClientHomeController::class,
                __DIR__ . '/home_controller.php'
            ],

            'productDetail' => [
                ClientProductController::class,
                __DIR__ . '/product_controller.php'
            ],

            'cart' => [
                ClientCartController::class,
                __DIR__ . '/cart_controller.php'
            ],

            'cartAdd' => [
                ClientCartController::class,
                __DIR__ . '/cart_controller.php'
            ],

            'cartIncrease' => [
                ClientCartController::class,
                __DIR__ . '/cart_controller.php'
            ],

            'cartDecrease' => [
                ClientCartController::class,
                __DIR__ . '/cart_controller.php'
            ],

            'cartDelete' => [
                ClientCartController::class,
                __DIR__ . '/cart_controller.php'
            ],
        ];

        ActionRouter::dispatch(
            $action,
            $routes,
            [$action]
        );
    }
}

ClientRouter::run();