<?php
/** @var string $error */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
  <link rel="stylesheet" href="view/assets/css/account.css">
</head>

<body class="auth-page">

<aside class="auth-intro">
    <a class="client-logo" href="?client=home">
        <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span class="brand-word">Four<span>Smart</span></span>
    </a>
    <h2>Mọi kết nối bắt đầu từ đây.</h2>
    <p>Đăng nhập để quản lý tài khoản, mua sắm và theo dõi đơn hàng của bạn.</p>
    <a class="auth-back" href="?client=home">Về trang mua sắm</a>
    <div class="auth-art" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
</aside>

<div class="auth-card">

    <h1>Đăng nhập</h1>

    <?php if (!empty($_SESSION['password_reset_success'])): ?>
        <div class="success" role="status">Đặt lại mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.</div>
        <?php unset($_SESSION['password_reset_success']); ?>
    <?php endif; ?>

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
            <label for="login-username">Tài khoản</label>
            <input id="login-username" type="text" name="username" autocomplete="username">
        </div>

        <div class="form-group">
            <label for="login-password">Mật khẩu</label>
            <input id="login-password" type="password" name="password" autocomplete="current-password">
        </div>

        <button type="submit">
            Đăng nhập
        </button>

    </form>

    <?php if (PasswordRecoveryConfig::enabled()): ?>
        <p class="auth-link"><a href="?client=forgotPassword">Quên mật khẩu?</a></p>
    <?php endif; ?>

    <p class="auth-link">
        Chưa có tài khoản?
        <a href="?client=register">Đăng ký</a>
    </p>

</div>

</body>
</html>
