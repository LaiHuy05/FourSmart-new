<?php

/** @var array $account */
/** @var array $roles */
/** @var string $error */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Cập nhật tài khoản</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
<link rel="stylesheet" href="view/assets/css/account.css">
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

<p>
    <a href="?act=admin&admin=accountList">
        Quay lại
    </a>
</p>
</main>

</div>
</body>
</html>