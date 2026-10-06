<?php
/** @var string $error */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
</head>

<body>

<h1>Đăng nhập</h1>

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

    <button type="submit">
        Đăng nhập
    </button>

</form>

<p>
    Chưa có tài khoản?
    <a href="?client=register">
        Đăng ký
    </a>
</p>

</body>
</html>