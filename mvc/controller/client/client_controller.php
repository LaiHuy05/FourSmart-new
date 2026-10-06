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
            'category' => [
              ClientCategoryController::class,
              __DIR__ . '/category_controller.php'
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

            'login' => [
                ClientAuthController::class,
                __DIR__ . '/auth_controller.php'
            ],

            'register' => [
                ClientAuthController::class,
                __DIR__ . '/auth_controller.php'
            ],

            'orderHistory' => [
                ClientOrderController::class,
                __DIR__ . '/order_controller.php'
            ],

            'orderDetail' => [
                ClientOrderController::class,
                __DIR__ . '/order_controller.php'
            ],
            'profile' => [
              ClientProfileController::class,
              __DIR__ . '/profile_controller.php'
            ],

            'profileUpdate' => [
              ClientProfileController::class,
              __DIR__ . '/profile_controller.php'
            ],

            'logout' => [
              ClientAuthController::class,
              __DIR__ . '/auth_controller.php'
            ],
            'commentAdd' => [
              ClientCommentController::class,
              __DIR__ . '/comment_controller.php'
            ],
            'checkout' => [
              ClientCheckoutController::class,
              __DIR__ . '/checkout_controller.php'
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