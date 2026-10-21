<?php /** @var string $error */ ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu | FourSmart</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/account.css">
    <script src="view/assets/js/client-password-recovery.js" defer></script>
</head>
<body class="auth-page">
<aside class="auth-intro">
    <a class="client-logo" href="?client=home">
        <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span class="brand-word">Four<span>Smart</span></span>
    </a>
    <h2>Một khởi đầu an toàn hơn.</h2>
    <p>Chọn mật khẩu mới cho tài khoản của bạn. Giữ riêng mật khẩu và liên kết khôi phục.</p>
    <a class="auth-back" href="?client=login">Về trang đăng nhập</a>
    <div class="auth-art" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
</aside>
<main class="auth-card">
    <h1>Đặt lại mật khẩu</h1>
    <p class="auth-subtitle" id="recovery-password-help">Ít nhất 12 ký tự, tối đa 72 byte, gồm chữ hoa, chữ thường, số và ký tự đặc biệt.</p>
    <?php if ($error !== ''): ?>
        <div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <p id="recovery-link-status" role="status" aria-live="polite"></p>
    <noscript><p class="error">Vui lòng bật JavaScript rồi mở lại liên kết trong email để đặt mật khẩu an toàn.</p></noscript>
    <form method="post" action="?client=resetPassword" id="recovery-reset-form" data-has-context="<?= $hasContext ? '1' : '0' ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" id="recovery-token" value="">
        <div class="form-group">
            <label for="recovery-new-password">Mật khẩu mới</label>
            <input id="recovery-new-password" type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="recovery-password-help" required>
        </div>
        <div class="form-group">
            <label for="recovery-confirm-password">Xác nhận mật khẩu mới</label>
            <input id="recovery-confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="72" required>
        </div>
        <button type="submit" id="recovery-submit" disabled>Đặt lại mật khẩu</button>
    </form>
    <p class="auth-link"><a href="?client=forgotPassword">Yêu cầu liên kết mới</a> · <a href="?client=login">Đăng nhập</a></p>
</main>
</body>
</html>
