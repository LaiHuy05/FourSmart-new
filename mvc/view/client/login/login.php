<?php
/** @var string $error */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
  <link rel="stylesheet" href="view/assets/css/account.css">
</head>

<body class="auth-page">

<div class="auth-card">

    <h1>Đăng nhập</h1>

    <p class="auth-subtitle">
        Đăng nhập vào FourSmart
    </p>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
    <div class="form-group">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(Csrf::token()) ?>"
        >
        </div>

        <div class="form-group">
            <label>Tài khoản</label>
            <input type="text" name="username">
        </div>

        <div class="form-group">
            <label>Mật khẩu</label>
            <input type="password" name="password">
        </div>

        <button type="submit">
            Đăng nhập
        </button>

    </form>

    <p class="auth-link">
        Chưa có tài khoản?
        <a href="?client=register">Đăng ký</a>
    </p>

</div>

</body>
</html>