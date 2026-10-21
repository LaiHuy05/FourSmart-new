<?php

/** @var array $account */
/** @var array $roles */
/** @var string $error */
/** @var bool $canResetPassword */
/** @var string $resetToken */
/** @var string $resetError */
/** @var string $resetMessage */
/** @var bool $resetOpen */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật tài khoản</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
<link rel="stylesheet" href="view/assets/css/account.css">
    <script src="view/assets/js/admin-account.js" defer></script>
</head>

<body>
<div class="admin-shell">

<?php
include dirname(__DIR__) . '/partials/sidebar.php';
?>

<main class="admin-content">
<h1>Cập nhật tài khoản</h1>

<?php if ($error !== ''): ?>

    <p>
        <?= htmlspecialchars($error) ?>
    </p>

<?php endif; ?>

<form
    action="?act=admin&admin=accountUpdate&id=<?= (int) $account['tk_id'] ?>"
    method="POST"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            Csrf::token()
        ) ?>"
    >

    <label>Tên đăng nhập:</label>

    <input
        type="text"
        name="username"
        value="<?= htmlspecialchars(
            $account['tk_user']
        ) ?>"
        required
    >

    <br>

    <label>Email:</label>

    <input
        type="email"
        name="email"
        value="<?= htmlspecialchars(
            $account['tk_email']
        ) ?>"
        required
    >

    <br>

    <label>Địa chỉ:</label>

    <input
        type="text"
        name="address"
        value="<?= htmlspecialchars(
            $account['tk_address']
        ) ?>"
        required
    >

    <br>

    <label>Quyền:</label>

    <select name="role_id">

        <?php foreach ($roles as $role): ?>

            <option
                value="<?= (int) $role['role_id'] ?>"
                <?= (int) $account['id_role']
                    === (int) $role['role_id']
                    ? 'selected'
                    : ''
                ?>
            >
                <?= htmlspecialchars(
                    $role['role_name']
                ) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <br>

    <button type="submit">
        Cập nhật
    </button>

</form>

<?php if ($canResetPassword): ?>

<section class="password-reset" id="password-reset" aria-labelledby="password-reset-title">
    <div class="password-reset-header">
        <div>
            <h2 id="password-reset-title">Cấp lại mật khẩu</h2>
            <p>
                Tạo mật khẩu tạm thời mới cho
                <strong><?= htmlspecialchars($account['tk_user'], ENT_QUOTES, 'UTF-8') ?></strong>.
                Thông tin tài khoản ở trên được cập nhật riêng.
            </p>
        </div>
        <button
            type="button"
            class="btn-secondary"
            id="password-reset-toggle"
            aria-controls="password-reset-form"
            aria-expanded="<?= $resetOpen ? 'true' : 'false' ?>"
        >
            <?= $resetOpen ? 'Đóng biểu mẫu' : 'Mở biểu mẫu cấp lại mật khẩu' ?>
        </button>
    </div>

    <?php if ($resetMessage !== ''): ?>
        <p class="success" role="status">
            <?= htmlspecialchars($resetMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form
        id="password-reset-form"
        class="password-reset-form"
        action="?act=admin&amp;admin=accountResetPassword"
        method="POST"
        data-account-name="<?= htmlspecialchars($account['tk_user'], ENT_QUOTES, 'UTF-8') ?>"
        <?= $resetOpen ? '' : 'hidden' ?>
    >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="reset_token" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= (int) $account['tk_id'] ?>">

        <?php if ($resetError !== ''): ?>
            <p class="error" role="alert">
                <?= htmlspecialchars($resetError, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <p class="password-reset-help" id="password-reset-help">
            Ít nhất 12 ký tự, có chữ hoa A–Z, chữ thường a–z, số và ký tự đặc biệt.
            Tối đa 72 byte; ký tự có dấu có thể chiếm nhiều byte.
        </p>

        <div class="password-reset-fields">
            <div>
                <label for="new-password">Mật khẩu tạm thời mới</label>
                <input
                    type="password"
                    id="new-password"
                    name="new_password"
                    autocomplete="new-password"
                    minlength="12"
                    maxlength="72"
                    aria-describedby="password-reset-help"
                    required
                >
            </div>
            <div>
                <label for="confirm-password">Xác nhận mật khẩu</label>
                <input
                    type="password"
                    id="confirm-password"
                    name="confirm_password"
                    autocomplete="new-password"
                    minlength="12"
                    maxlength="72"
                    required
                >
            </div>
        </div>

        <p class="password-reset-warning">
            Sau khi xác nhận, mật khẩu cũ sẽ không còn sử dụng được.
            Hãy chuyển mật khẩu mới cho khách hàng qua kênh riêng an toàn.
        </p>
        <button type="submit" class="btn-danger" id="password-reset-submit">
            Cấp lại mật khẩu
        </button>
    </form>

    <noscript>
        <p class="error">Vui lòng bật JavaScript để mở biểu mẫu và xác nhận cấp lại mật khẩu.</p>
    </noscript>
</section>

<?php endif; ?>

<p>
    <a href="?act=admin&admin=accountList">
        Quay lại
    </a>
</p>
</main>

</div>
</body>
</html>
