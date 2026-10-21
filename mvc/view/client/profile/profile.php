<?php

/** @var array $account */
/** @var string $error */
/** @var string $message */
/** @var string $passwordError */
/** @var string $passwordMessage */

$passwordError = $passwordError ?? '';
$passwordMessage = $passwordMessage ?? '';

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/account.css">
    <script src="view/assets/js/client-password.js" defer></script>
</head>

<body>

<?php
include dirname(__DIR__) . '/partials/header.php';
?>

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

    <label for="profile-email">Email</label>

    <input
        type="email"
        id="profile-email"
        name="email"
        autocomplete="email"
        value="<?= htmlspecialchars(
            $account['tk_email'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <br>

    <label for="profile-address">Địa chỉ</label>

    <input
        type="text"
        id="profile-address"
        name="address"
        autocomplete="street-address"
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

    <section class="profile-card profile-security" id="account-security" aria-labelledby="account-security-title">
        <h2 id="account-security-title">Bảo mật tài khoản</h2>
        <h3>Đổi mật khẩu</h3>
        <p class="profile-security-intro">Sử dụng mật khẩu riêng cho tài khoản FourSmart của bạn.</p>

        <?php if ($passwordError !== ''): ?>
            <p class="error" role="alert">
                <?= htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($passwordMessage !== ''): ?>
            <p class="success" role="status">
                <?= htmlspecialchars($passwordMessage, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form id="profile-password-form" action="?client=profilePasswordChange" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

            <label for="profile-current-password">Mật khẩu hiện tại</label>
            <input
                id="profile-current-password"
                type="password"
                name="current_password"
                autocomplete="current-password"
                required
            >

            <p class="profile-password-help" id="profile-password-help">
                Mật khẩu mới có ít nhất 12 ký tự, gồm chữ hoa A–Z, chữ thường a–z, số và ký tự đặc biệt.
                Tối đa 72 byte; ký tự có dấu có thể chiếm nhiều byte.
            </p>

            <div class="profile-password-fields">
                <div>
                    <label for="profile-new-password">Mật khẩu mới</label>
                    <input
                        id="profile-new-password"
                        type="password"
                        name="new_password"
                        autocomplete="new-password"
                        minlength="12"
                        maxlength="72"
                        aria-describedby="profile-password-help"
                        required
                    >
                </div>
                <div>
                    <label for="profile-confirm-password">Xác nhận mật khẩu mới</label>
                    <input
                        id="profile-confirm-password"
                        type="password"
                        name="confirm_password"
                        autocomplete="new-password"
                        minlength="12"
                        maxlength="72"
                        required
                    >
                </div>
            </div>

            <button type="submit" id="profile-password-submit">Đổi mật khẩu</button>
        </form>
    </section>
</div>
<?php
include dirname(__DIR__) . '/partials/footer.php';
?>
</body>
</html>
