<?php
/** @var array $orders */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Lịch sử đơn hàng</title>
</head>

<body>
<?php
include dirname(__DIR__) . '/partials/header.php';
?>

<h1>Lịch sử đơn hàng</h1>

<?php if (empty($orders)): ?>

    <p>Bạn chưa có đơn hàng.</p>

<?php else: ?>

    <table border="1">

        <tr>
            <th>Mã đơn</th>
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
                    <a href="?client=orderDetail&id=<?= (int) $order['dh_id'] ?>">
                        Xem
                    </a>
                </td>

            </tr>

        <?php endforeach; ?>

    </table>

<?php endif; ?>
<?php
include dirname(__DIR__) . '/partials/footer.php';
?>
</body>
</html>