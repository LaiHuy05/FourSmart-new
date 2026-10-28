<?php /** @var string $error */ ?>
<?php /** @var string $submitted */ ?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu | FourSmart</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/account.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <h1>Quên mật khẩu</h1>
    <p class="auth-subtitle">Nhập email bạn đã dùng cho tài khoản Client.</p>
    <?php if ($error !== ''): ?>
        <div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($submitted): ?>
        <div class="success" role="status">Nếu email phù hợp với tài khoản Client, bạn sẽ nhận được liên kết đặt lại mật khẩu. Vui lòng kiểm tra hộp thư.</div>
    <?php endif; ?>
    <form method="post" action="?client=forgotPassword">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="recovery-email">Địa chỉ email</label>
            <input id="recovery-email" type="email" name="email" autocomplete="email" maxlength="255" required>
        </div>
        <button type="submit">Gửi liên kết khôi phục</button>
    </form>
    <p class="auth-link"><a href="?client=login">Quay lại đăng nhập</a></p>
</main>
</body>
</html>
