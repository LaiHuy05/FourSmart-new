<?php
/** @var array $orders */
?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quản lý đơn hàng</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/order-dashboard.css">

</head>

<body>

<div class="admin-shell">

<?php
include dirname(__DIR__)
    . '/partials/sidebar.php';
?>

<main class="admin-content">

<div class="container order-page">

    <h1 class="page-title">
        Quản lý đơn hàng
    </h1>

    <?php if (empty($orders)): ?>

        <div class="empty-state">
            Chưa có đơn hàng.
        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table>

                <thead>

                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Ngày đặt</th>
                    <th>Trạng thái</th>
                    <th>Tổng tiền</th>
                    <th>Thao tác</th>
                </tr>

                </thead>

                <tbody>

                <?php foreach ($orders as $order): ?>

                    <?php

                    $statusClass = match (
                        $order['dh_status']
                    ) {
                        'Chờ xác nhận'
                            => 'badge-pending',

                        'Đã xác nhận'
                            => 'badge-confirmed',

                        'Đang giao'
                            => 'badge-shipping',

                        'Đã giao'
                            => 'badge-success',

                        'Đã hủy'
                            => 'badge-cancel',

                        default => ''
                    };

                    ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $order['dh_ma']
                            ) ?>
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

                            <span
                                class="badge <?= $statusClass ?>"
                            >

                                <?= htmlspecialchars(
                                    $order['dh_status']
                                ) ?>

                            </span>

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

                            <a
                                class="btn"
                                href="?act=admin&admin=orderDetail&id=<?= (int) $order['dh_id'] ?>"
                            >
                                Chi tiết
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

</main>

</div>

</body>

</html>