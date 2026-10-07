<?php

/** @var int $totalOrders */
/** @var float $totalRevenue */
/** @var array $monthlyRevenue */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Dashboard FourSmart</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/home-category.css">
</head>

<body>

<h1>Dashboard FourSmart</h1>

<p>
    Tổng đơn hàng:
    <?= $totalOrders ?>
</p>

<p>
    Tổng doanh thu:
    <?= number_format(
        $totalRevenue,
        0,
        ',',
        '.'
    ) ?>
    VNĐ
</p>

<h2>Doanh thu theo tháng</h2>

<table border="1">

<tr>
    <th>Tháng</th>
    <th>Doanh thu</th>
</tr>

<?php foreach ($monthlyRevenue as $month => $revenue): ?>

<tr>
    <td><?= (int) $month ?></td>

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

</table>
    <h2>Đơn hàng theo trạng thái</h2>

<table border="1">
    <tr>
        <th>Trạng thái</th>
        <th>Số đơn</th>
    </tr>

    <?php foreach ($orderStatusSummary as $item): ?>

        <tr>
            <td>
                <?= htmlspecialchars($item['dh_status']) ?>
            </td>

            <td>
                <?= (int) $item['total'] ?>
            </td>
        </tr>

    <?php endforeach; ?>
</table>

<h2>Top 5 sản phẩm bán chạy</h2>

<table border="1">
    <tr>
        <th>Sản phẩm</th>
        <th>Đã bán</th>
    </tr>

    <?php foreach ($topSellingProducts as $product): ?>

        <tr>
            <td>
                <?= htmlspecialchars($product['sp_name']) ?>
            </td>

            <td>
                <?= (int) $product['sold_quantity'] ?>
            </td>
        </tr>

    <?php endforeach; ?>
</table>
</body>
</html>