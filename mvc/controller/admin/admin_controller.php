<?php

require_once __DIR__
    . '/../../middleware/require_admin.php';

require_admin();

final class AdminRouter
{
    public static function run(): void
    {
        $action = $_GET['admin'] ?? 'home';

        $routes = [
            'home' => [
                AdminHomeController::class,
                __DIR__ . '/home_controller.php'
            ],

            'categoryList' => [
                AdminCategoryController::class,
                __DIR__ . '/category_controller.php'
            ],

            'categoryAdd' => [
                AdminCategoryController::class,
                __DIR__ . '/category_controller.php'
            ],

            'categoryUpdate' => [
                AdminCategoryController::class,
                __DIR__ . '/category_controller.php'
            ],

            'categoryDelete' => [
                AdminCategoryController::class,
                __DIR__ . '/category_controller.php'
            ],
            'orderList' => [
                AdminOrderController::class,
                __DIR__ . '/order_controller.php'
            ],

            'orderDetail' => [
                AdminOrderController::class,
                __DIR__ . '/order_controller.php'
            ],

            'orderStatus' => [
                AdminOrderController::class,
                __DIR__ . '/order_controller.php'
            ],
        ];

        ActionRouter::dispatch(
            $action,
            $routes,
            [$action]
        );
    }
}

AdminRouter::run();