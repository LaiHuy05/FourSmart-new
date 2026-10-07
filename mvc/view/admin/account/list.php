<?php
/** @var array $accounts */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý tài khoản</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
<link rel="stylesheet" href="view/assets/css/account.css">
</head>

<body>

<h1>Quản lý tài khoản</h1>

<table border="1">

    <tr>
        <th>ID</th>
        <th>Tài khoản</th>
        <th>Email</th>
        <th>Địa chỉ</th>
        <th>Quyền</th>
        <th>Thao tác</th>
    </tr>

    <?php foreach ($accounts as $account): ?>

        <tr>

            <td>
                <?= (int) $account['tk_id'] ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $account['tk_user']
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $account['tk_email']
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $account['tk_address']
                ) ?>
            </td>

            <td>
                <?= (int) $account['id_role'] === 1
                    ? 'Admin'
                    : 'Khách hàng'
                ?>
            </td>

            <td>

                <a href="?act=admin&admin=accountUpdate&id=<?= (int) $account['tk_id'] ?>">
                    Sửa
                </a>

                <?php if (
                    (int) $account['tk_id']
                    !== (int) ($_SESSION['user_id'] ?? 0)
                ): ?>

                    <form
                        action="?act=admin&admin=accountDelete"
                        method="POST"
                        style="display:inline"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                Csrf::token()
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $account['tk_id'] ?>"
                        >

                        <button type="submit">
                            Xóa
                        </button>

                    </form>

                <?php endif; ?>

            </td>

        </tr>

    <?php endforeach; ?>

</table>

</body>
</html>