<?php

final class AdminHomeController
{
    public static function handle(string $action): void
    {
        $totalOrders = OrderModel::totalOrders();

        $totalRevenue = OrderModel::totalRevenue();

        $monthlyRevenue =
            OrderModel::currentYearMonthlyRevenue();
        $orderStatusSummary =
            OrderModel::ordersByStatus();

        $topSellingProducts =
            OrderModel::topSellingProducts();

        include __DIR__
            . '/../../view/admin/home.php';
    }
}