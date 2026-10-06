<?php
/** @var array $comments */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý bình luận</title>
</head>

<body>

<h1>Quản lý bình luận</h1>

<table border="1">

    <tr>
        <th>ID</th>
        <th>Người dùng</th>
        <th>Sản phẩm</th>
        <th>Nội dung</th>
        <th>Thao tác</th>
    </tr>

    <?php foreach ($comments as $comment): ?>

        <tr>

            <td>
                <?= (int) $comment['bl_id'] ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $comment['tk_user']
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $comment['sp_name']
                ) ?>
            </td>

            <td>
                <?= nl2br(
                    htmlspecialchars(
                        $comment['bl_content']
                    )
                ) ?>
            </td>

            <td>

                <form
                    action="?act=admin&admin=commentDelete"
                    method="POST"
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
                        value="<?= (int) $comment['bl_id'] ?>"
                    >

                    <button type="submit">
                        Xóa
                    </button>

                </form>

            </td>

        </tr>

    <?php endforeach; ?>

</table>

</body>
</html>