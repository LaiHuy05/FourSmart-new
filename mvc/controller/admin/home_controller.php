<?php

final class AdminHomeController
{
    public static function handle(string $action): void
    {
        $totalOrders = OrderModel::totalOrders();

        $totalRevenue = OrderModel::totalRevenue();

        $monthlyRevenue =
            OrderModel::currentYearMonthlyRevenue();

        include __DIR__
            . '/../../view/admin/home.php';
    }
}