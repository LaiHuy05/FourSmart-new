<?php

final class AdminHomeController
{
    public static function handle(string $action): void
    {
        include __DIR__ . '/../../view/admin/home.php';
    }
}