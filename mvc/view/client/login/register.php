<?php
/** @var string $error */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
  <link rel="stylesheet" href="view/assets/css/account.css">
</head>

<body class="auth-page">
<aside class="auth-intro">
    <a class="client-logo" href="?client=home">
        <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span class="brand-word">Four<span>Smart</span></span>
    </a>
    <h2>Thêm kết nối. Thêm trải nghiệm.</h2>
    <p>Tạo tài khoản để mua sắm và theo dõi các đơn hàng của bạn tại FourSmart.</p>
    <a class="auth-back" href="?client=home">Về trang mua sắm</a>
    <div class="auth-art" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
</aside>

<div class="auth-card">

<h1>Đăng ký</h1>
<p class="auth-subtitle">Tạo tài khoản FourSmart của bạn.</p>

<?php if ($error !== ''): ?>
    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(Csrf::token()) ?>"
    >

    <div class="form-group">
    <label for="register-username">Tài khoản</label>
    <input id="register-username" type="text" name="username" autocomplete="username">
</div>

<div class="form-group">
    <label for="register-password">Mật khẩu</label>
    <input id="register-password" type="password" name="password" autocomplete="new-password">
</div>

<div class="form-group">
    <label for="register-email">Email</label>
    <input id="register-email" type="email" name="email" autocomplete="email">
</div>

<div class="form-group">
    <label for="register-address">Địa chỉ</label>
    <input id="register-address" type="text" name="address" autocomplete="street-address">
</div>

    <button type="submit">
        Đăng ký
    </button>

</form>

<p class="auth-link">
    Đã có tài khoản?
    <a href="?client=login">
        Đăng nhập
    </a>
</p>
</div>

</body>
</html>
