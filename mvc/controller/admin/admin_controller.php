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
            'productList' => [
                AdminProductController::class,
                __DIR__ . '/product_controller.php'
            ],

            'productAdd' => [
                AdminProductController::class,
                __DIR__ . '/product_controller.php'
            ],

            'productUpdate' => [
                AdminProductController::class,
                __DIR__ . '/product_controller.php'
            ],

            'productDelete' => [
                AdminProductController::class,
                __DIR__ . '/product_controller.php'
            ],

            'productVariant' => [
                AdminProductVariantController::class,
                __DIR__ . '/product_variant_controller.php'
            ],

            'productColorAdd' => [
                AdminProductVariantController::class,
                __DIR__ . '/product_variant_controller.php'
            ],

            'productColorUpdate' => [
                AdminProductVariantController::class,
                __DIR__ . '/product_variant_controller.php'
            ],

            'productColorDelete' => [
                AdminProductVariantController::class,
                __DIR__ . '/product_variant_controller.php'
            ],

            'productMemoryAdd' => [
                AdminProductVariantController::class,
                __DIR__ . '/product_variant_controller.php'
            ],

            'productMemoryUpdate' => [
                AdminProductVariantController::class,
                __DIR__ . '/product_variant_controller.php'
            ],

            'productMemoryDelete' => [
                AdminProductVariantController::class,
                __DIR__ . '/product_variant_controller.php'
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
            'accountList' => [
                AdminAccountController::class,
                __DIR__ . '/account_controller.php'
            ],

            'accountUpdate' => [
                AdminAccountController::class,
                __DIR__ . '/account_controller.php'
            ],

            'accountResetPassword' => [
                AdminAccountController::class,
                __DIR__ . '/account_controller.php'
            ],

            'accountDelete' => [
                AdminAccountController::class,
                __DIR__ . '/account_controller.php'
            ],

            'commentList' => [
                AdminCommentController::class,
                __DIR__ . '/comment_controller.php'
            ],

            'commentDelete' => [
                AdminCommentController::class,
                __DIR__ . '/comment_controller.php'
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
