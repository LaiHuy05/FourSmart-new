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
    <title>Thanh toán</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
<link rel="stylesheet" href="view/assets/css/cart-checkout.css">
</head>

<body>

<h1>Thanh toán</h1>

<h2>Sản phẩm</h2>

<?php foreach ($items as $item): ?>

    <div>

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

    <hr>

<?php endforeach; ?>

<h3>
    Tổng số lượng:
    <?= (int) $summary['quantity'] ?>
</h3>

<h3>
    Tổng tiền:
    <?= number_format(
        $summary['total'],
        0,
        ',',
        '.'
    ) ?>
    VNĐ
</h3>

<form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            Csrf::token()
        ) ?>"
    >

    <label>Họ tên:</label>

    <input
        type="text"
        name="name"
        value="<?= htmlspecialchars(
            $_POST['name'] ?? ''
        ) ?>"
    >

    <br>

    <label>Email:</label>

    <input
        type="email"
        name="email"
        value="<?= htmlspecialchars(
            $_POST['email']
            ?? ($account['tk_email'] ?? '')
        ) ?>"
    >

    <br>

    <label>Số điện thoại:</label>

    <input
        type="text"
        name="phone"
        value="<?= htmlspecialchars(
            $_POST['phone'] ?? ''
        ) ?>"
    >

    <br>

    <label>Địa chỉ:</label>

    <input
        type="text"
        name="address"
        value="<?= htmlspecialchars(
            $_POST['address']
            ?? ($account['tk_address'] ?? '')
        ) ?>"
    >

    <br>

    <label>Quốc gia:</label>
    <input type="text" name="country">

    <br>

    <label>Tỉnh / Thành phố:</label>
    <input type="text" name="city">

    <br>

    <label>Quận / Huyện:</label>
    <input type="text" name="district">

    <br>

    <label>Phường / Xã:</label>
    <input type="text" name="commune">

    <br>

    <label>Ghi chú:</label>
    <textarea name="message"></textarea>

    <br>

    <?php foreach ($errors as $error): ?>

        <p>
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endforeach; ?>

    <button type="submit">
        Đặt hàng
    </button>

</form>

</body>
</html>