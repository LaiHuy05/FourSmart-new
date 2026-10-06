<?php
/** @var string $error */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng ký</title>
</head>

<body>

<h1>Đăng ký</h1>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(Csrf::token()) ?>"
    >

    <label>Tài khoản:</label>
    <input type="text" name="username">

    <br>

    <label>Mật khẩu:</label>
    <input type="password" name="password">

    <br>

    <label>Email:</label>
    <input type="email" name="email">

    <br>

    <label>Địa chỉ:</label>
    <input type="text" name="address">

    <br>

    <button type="submit">
        Đăng ký
    </button>

</form>

<p>
    Đã có tài khoản?
    <a href="?client=login">
        Đăng nhập
    </a>
</p>

</body>
</html>