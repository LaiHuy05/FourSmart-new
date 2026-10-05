<?php

final class ClientRouter
{
    public static function run(): void
    {
        $action = $_GET['client'] ?? 'home';

        echo 'CLIENT - ' . htmlspecialchars($action);
    }
}

ClientRouter::run();