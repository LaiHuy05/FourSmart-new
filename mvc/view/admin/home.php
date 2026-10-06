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

</body>
</html>