<?php

/** @var int $totalOrders */
/** @var float $totalRevenue */
/** @var array $monthlyRevenue */
/** @var array $orderStatusSummary */
/** @var array $topSellingProducts */

$pendingOrders = 0;

foreach ($orderStatusSummary as $item) {

    if (
        $item['dh_status']
        === 'Chờ xác nhận'
    ) {
        $pendingOrders =
            (int) $item['total'];

        break;
    }
}

$topProductName =
    $topSellingProducts[0]['sp_name']
    ?? 'Chưa có dữ liệu';

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard FourSmart</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/order-dashboard.css">

</head>

<body>

<div class="admin-shell">

<?php
include __DIR__
    . '/partials/sidebar.php';
?>

<main class="admin-content">

<div class="container dashboard">

    <h1 class="dashboard-title">
        Dashboard FourSmart
    </h1>

    <div class="stat-grid">

        <div class="stat-card">

            <div class="stat-label">
                Tổng đơn hàng
            </div>

            <div class="stat-value">
                <?= $totalOrders ?>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-label">
                Tổng doanh thu
            </div>

            <div class="stat-value">

                <?= number_format(
                    $totalRevenue,
                    0,
                    ',',
                    '.'
                ) ?>

                VNĐ

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-label">
                Chờ xác nhận
            </div>

            <div class="stat-value">
                <?= $pendingOrders ?>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-label">
                Bán chạy nhất
            </div>

            <div class="stat-value">
                <?= htmlspecialchars(
                    $topProductName
                ) ?>
            </div>

        </div>

    </div>

    <div class="dashboard-section">

        <h2>Doanh thu theo tháng</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                <tr>
                    <th>Tháng</th>
                    <th>Doanh thu</th>
                </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $monthlyRevenue
                    as $month => $revenue
                ): ?>

                    <tr>

                        <td>
                            Tháng <?= (int) $month ?>
                        </td>

                        <td>

                            <?= number_format(
                                $revenue,
                                0,
                                ',',
                                '.'
) ?>

                            VNĐ

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="dashboard-grid">

        <div class="dashboard-section">

            <h2>
                Đơn hàng theo trạng thái
            </h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>
                        <th>Trạng thái</th>
                        <th>Số đơn</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $orderStatusSummary
                        as $item
                    ): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $item['dh_status']
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $item['total'] ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <div class="dashboard-section">

            <h2>
                Top 5 sản phẩm bán chạy
            </h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>
                        <th>Sản phẩm</th>
                        <th>Đã bán</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $topSellingProducts
                        as $product
                    ): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $product['sp_name']
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $product[
                                    'sold_quantity'
                                ] ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</main>

</div>

</body>

</html>