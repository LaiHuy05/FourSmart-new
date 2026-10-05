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
        ];

        ActionRouter::dispatch(
            $action,
            $routes,
            [$action]
        );
    }
}

AdminRouter::run();