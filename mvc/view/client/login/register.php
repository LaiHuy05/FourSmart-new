<?php
/** @var string $error */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng ký</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
  <link rel="stylesheet" href="view/assets/css/account.css">
</head>

<body class="auth-page">
<div class="auth-card">

<h1>Đăng ký</h1>

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
    <label>Tài khoản:</label>
    <input type="text" name="username">
</div>

<div class="form-group">
    <label>Mật khẩu:</label>
    <input type="password" name="password">
</div>

<div class="form-group">
    <label>Email:</label>
    <input type="email" name="email">
</div>

<div class="form-group">
    <label>Địa chỉ:</label>
    <input type="text" name="address">
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