<?php

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
        ];

        ActionRouter::dispatch(
            $action,
            $routes,
            [$action]
        );
    }
}

AdminRouter::run();