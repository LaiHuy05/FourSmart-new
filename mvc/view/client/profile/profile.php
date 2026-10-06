<?php

/** @var array $account */
/** @var string $error */
/** @var string $message */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Hồ sơ cá nhân</title>
</head>

<body>

<h1>Hồ sơ cá nhân</h1>

<p>
    Tài khoản:
    <?= htmlspecialchars(
        $account['tk_user'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<?php if ($error !== ''): ?>

    <p>
        <?= htmlspecialchars($error) ?>
    </p>

<?php endif; ?>

<?php if ($message !== ''): ?>

    <p>
        <?= htmlspecialchars($message) ?>
    </p>

<?php endif; ?>

<form
    action="?client=profileUpdate"
    method="POST"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            Csrf::token(),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <label>Email:</label>

    <input
        type="email"
        name="email"
        value="<?= htmlspecialchars(
            $account['tk_email'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <br>

    <label>Địa chỉ:</label>

    <input
        type="text"
        name="address"
        value="<?= htmlspecialchars(
            $account['tk_address'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <br>

    <button type="submit">
        Cập nhật
    </button>

</form>

<p>
    <a href="?client=orderHistory">
        Lịch sử đơn hàng
    </a>
</p>

<p>
    <a href="?client=logout">
        Đăng xuất
    </a>
</p>

</body>
</html>