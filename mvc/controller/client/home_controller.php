<?php

final class ClientHomeController
{
    public static function handle(string $action): void
    {
        include __DIR__ . '/../../view/client/home.php';
    }
}