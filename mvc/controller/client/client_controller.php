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
        ];

        ActionRouter::dispatch(
            $action,
            $routes,
            [$action]
        );
    }
}

ClientRouter::run();