<?php
/** @var array $orders */
?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Lịch sử đơn hàng</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/order-dashboard.css">

</head>

<body>

<?php
include dirname(__DIR__) . '/partials/header.php';
?>

<div class="container order-page">

    <h1 class="page-title">
        Lịch sử đơn hàng
    </h1>

    <?php if (empty($orders)): ?>

        <div class="empty-state">

            <p>
                Bạn chưa có đơn hàng.
            </p>

            <a
                class="btn"
                href="?client=home"
            >
                Tiếp tục mua hàng
            </a>

        </div>

    <?php else: ?>

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

            <div class="order-card">

                <div class="order-card-header">

                    <div>

                        <strong>
                            <?= htmlspecialchars(
                                $order['dh_ma']
                            ) ?>
                        </strong>

                        <div>
                            <?= htmlspecialchars(
                                $order['dh_orderdate']
                            ) ?>
                        </div>

                    </div>

                    <span
                        class="badge <?= $statusClass ?>"
                    >

                        <?= htmlspecialchars(
                            $order['dh_status']
                        ) ?>

                    </span>

                </div>

                <p class="order-price">

                    Tổng tiền:

                    <?= number_format(
                        $order['dh_totalamount'],
                        0,
                        ',',
                        '.'
                    ) ?>

                    VNĐ

                </p>

                <a
                    class="btn"
                    href="?client=orderDetail&id=<?= (int) $order['dh_id'] ?>"
                >
                    Xem chi tiết
                </a>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php
include dirname(__DIR__) . '/partials/footer.php';
?>

</body>

</html>