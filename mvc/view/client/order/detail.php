<?php

/** @var array $order */
/** @var array $details */

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

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Chi tiết đơn hàng</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/order-dashboard.css">

</head>

<body>

<?php
include dirname(__DIR__) . '/partials/header.php';
?>

<div class="container order-page">

    <h1 class="page-title">

        Đơn hàng:
        <?= htmlspecialchars(
            $order['dh_ma']
        ) ?>

    </h1>

    <div class="order-detail-info">

        <div class="order-detail-box">

            <h3>Trạng thái</h3>

            <span
                class="badge <?= $statusClass ?>"
            >

                <?= htmlspecialchars(
                    $order['dh_status']
                ) ?>

            </span>

        </div>

        <div class="order-detail-box">

            <h3>Tổng tiền</h3>

            <div class="order-price">

                <?= number_format(
                    $order['dh_totalamount'],
                    0,
                    ',',
                    '.'
                ) ?>

                VNĐ

            </div>

        </div>

    </div>

    <h2>Sản phẩm</h2>

    <?php foreach ($details as $detail): ?>

        <div class="order-product">

            <strong>

                <?= htmlspecialchars(
                    $detail['sp_name']
                ) ?>

            </strong>

            <p>
                Số lượng:
                <?= (int) $detail['ct_quantity'] ?>
            </p>

            <p>
                Đơn giá:

                <?= number_format(
                    $detail['sp_price'],
                    0,
                    ',',
                    '.'
                ) ?>

                VNĐ
            </p>

            <p>
                Thành tiền:

                <strong>

                    <?= number_format(
                        (float) $detail['sp_price']
                        *
                        (int) $detail['ct_quantity'],
                        0,
                        ',',
                        '.'
                    ) ?>

                    VNĐ

                </strong>

            </p>

            <p>
                Bộ nhớ:
                <?= htmlspecialchars(
                    $detail['od_option']
                ) ?>
            </p>

            <p>
                Màu:
                <?= htmlspecialchars(
                    $detail['od_optionColor']
) ?>
            </p>

        </div>

    <?php endforeach; ?>

</div>

<?php
include dirname(__DIR__) . '/partials/footer.php';
?>

</body>

</html>