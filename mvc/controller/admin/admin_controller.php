<?php

final class AdminRouter
{
    public static function run(): void
    {
        $action = $_GET['admin'] ?? 'home';

        echo 'ADMIN - ' . htmlspecialchars($action);
    }
}

AdminRouter::run();