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
    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/account.css">
</head>

<body>

<div class="profile-wrapper">

    <div class="profile-card">

        <h1>Hồ sơ cá nhân</h1>

        <div class="profile-name">
            Tài khoản:
            <?= htmlspecialchars($account['tk_user']) ?>
        </div>

        <?php if ($error !== ''): ?>
    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($message !== ''): ?>
    <div class="success">
        <?= htmlspecialchars($message) ?>
    </div>
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

<div class="profile-actions">

    <a class="btn" href="?client=orderHistory">
        Lịch sử đơn hàng
    </a>

    <a class="btn btn-danger" href="?client=logout">
        Đăng xuất
    </a>

</div>

    </div>
</div>

</body>
</html>