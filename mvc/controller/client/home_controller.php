<?php

final class ClientHomeController
{
    public static function handle(string $action): void
    {
        $categories = CategoryModel::all();
        $products = ProductModel::all();

        include __DIR__ . '/../../view/client/home.php';
    }
}