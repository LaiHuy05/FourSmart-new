<?php

/** @var array $order */
/** @var array $details */

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <title>Chi tiết đơn hàng</title>

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

        Đơn hàng
        <?= htmlspecialchars(
            $order['dh_ma']
        ) ?>

    </h1>

    <div class="order-detail-info">

        <div class="order-detail-box">

            <h3>Thông tin khách hàng</h3>

            <p>
                Họ tên:
                <?= htmlspecialchars(
                    $order['dh_nameUser']
                ) ?>
            </p>

            <p>
                Email:
                <?= htmlspecialchars(
                    $order['dh_emailUser']
                ) ?>
            </p>

            <p>
                SĐT:
                <?= htmlspecialchars(
                    $order['dh_phoneUser']
                ) ?>
            </p>

            <p>
                Địa chỉ:
                <?= htmlspecialchars(
                    $order['dh_addressUser']
                ) ?>
            </p>

        </div>

        <div class="order-detail-box">

            <h3>Cập nhật trạng thái</h3>

            <form
                class="status-form"
                action="?act=admin&admin=orderStatus"
                method="POST"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        Csrf::token()
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $order['dh_id'] ?>"
                >

                <?php

                $statuses = [
                    'Chờ xác nhận',
                    'Đã xác nhận',
                    'Đang giao',
                    'Đã giao',
                    'Đã hủy',
                ];

                ?>

                <select name="status">

                    <?php foreach (
                        $statuses
                        as $status
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $status
                            ) ?>"
                            <?= $order['dh_status']
                                === $status
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= htmlspecialchars(
                                $status
) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <button type="submit">
                    Cập nhật trạng thái
                </button>

            </form>

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

</main>

</div>

</body>

</html>