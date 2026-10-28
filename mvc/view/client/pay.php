<?php

/** @var array $items */
/** @var array $summary */
/** @var array|null $account */
/** @var array $errors */

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Thanh toán</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/cart-checkout.css">

</head>

<body>
<?php
include __DIR__ . '/partials/header.php';
?>

<div class="container page">

    <h1 class="page-title">
        Thanh toán
    </h1>

    <div class="checkout-layout">

        <div class="checkout-products">

            <h2>Sản phẩm</h2>

            <?php foreach ($items as $item): ?>

                <div class="checkout-item">

                    <strong>
                        <?= htmlspecialchars(
                            $item['sp_name']
                        ) ?>
                    </strong>

                    <p>
                        Bộ nhớ:
                        <?= htmlspecialchars(
                            $item['cd_option']
                        ) ?>
                    </p>

                    <p>
                        Màu:
                        <?= htmlspecialchars(
                            $item['cd_optionColor']
                        ) ?>
                    </p>

                    <p>
                        Số lượng:
                        <?= (int) $item['cd_quantity'] ?>
                    </p>

                    <p>
                        Giá:
                        <?= number_format(
                            $item['sp_price'],
                            0,
                            ',',
                            '.'
                        ) ?>
                        VNĐ
                    </p>

                </div>

            <?php endforeach; ?>

            <div class="checkout-total">

                <p>

                    <span>Tổng số lượng</span>

                    <strong>
                        <?= (int) $summary['quantity'] ?>
                    </strong>

                </p>

                <p class="total-price">

                    <span>Tổng tiền</span>

                    <strong>
                        <?= number_format(
                            $summary['total'],
                            0,
                            ',',
                            '.'
                        ) ?>
                        VNĐ
                    </strong>

                </p>

            </div>

        </div>

        <div class="checkout-form">

            <h2>Thông tin nhận hàng</h2>

            <?php foreach ($errors as $error): ?>

                <div class="error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endforeach; ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
value="<?= htmlspecialchars(
                        Csrf::token()
                    ) ?>"
                >

                <div class="form-group">

                    <label>Họ tên</label>

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars(
                            $_POST['name'] ?? ''
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Email</label>

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $_POST['email']
                            ?? ($account['tk_email'] ?? '')
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Số điện thoại</label>

                    <input
                        type="text"
                        name="phone"
                        value="<?= htmlspecialchars(
                            $_POST['phone'] ?? ''
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Địa chỉ</label>

                    <input
                        type="text"
                        name="address"
                        value="<?= htmlspecialchars(
                            $_POST['address']
                            ?? ($account['tk_address'] ?? '')
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Quốc gia</label>

                    <input
                        type="text"
                        name="country"
                        value="<?= htmlspecialchars(
                            $_POST['country'] ?? ''
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Tỉnh / Thành phố</label>

                    <input
                        type="text"
                        name="city"
                        value="<?= htmlspecialchars(
                            $_POST['city'] ?? ''
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Quận / Huyện</label>

                    <input
                        type="text"
                        name="district"
                        value="<?= htmlspecialchars(
                            $_POST['district'] ?? ''
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Phường / Xã</label>

                    <input
                        type="text"
name="commune"
                        value="<?= htmlspecialchars(
                            $_POST['commune'] ?? ''
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Ghi chú</label>

                    <textarea name="message"><?= htmlspecialchars(
                        $_POST['message'] ?? ''
                    ) ?></textarea>

                </div>

                <button type="submit">
                    Đặt hàng
                </button>

            </form>

        </div>

    </div>

</div>
<?php
include __DIR__ . '/partials/footer.php';
?>

</body>

</html>
