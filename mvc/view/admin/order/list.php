<?php
/** @var array $orders */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý đơn hàng</title>
</head>

<body>

<h1>Danh sách đơn hàng</h1>

<table border="1">

    <tr>
        <th>Mã đơn</th>
        <th>Khách hàng</th>
        <th>Ngày đặt</th>
        <th>Trạng thái</th>
        <th>Tổng tiền</th>
        <th></th>
    </tr>

    <?php foreach ($orders as $order): ?>

        <tr>

            <td>
                <?= htmlspecialchars($order['dh_ma']) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $order['dh_nameUser']
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $order['dh_orderdate']
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $order['dh_status']
                ) ?>
            </td>

            <td>
                <?= number_format(
                    $order['dh_totalamount'],
                    0,
                    ',',
                    '.'
                ) ?>
                VNĐ
            </td>

            <td>
                <a href="?act=admin&admin=orderDetail&id=<?= (int) $order['dh_id'] ?>">
                    Chi tiết
                </a>
            </td>

        </tr>

    <?php endforeach; ?>

</table>

</body>
</html>