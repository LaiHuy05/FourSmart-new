<?php
/** @var array $comments */
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý bình luận</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/account.css">
</head>

<body>

<div class="container page">

    <div class="admin-header">
        <h1>Quản lý bình luận</h1>
    </div>

    <div class="table-wrapper">

        <table class="comment-table">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Người dùng</th>
                    <th>Sản phẩm</th>
                    <th>Nội dung</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($comments as $comment): ?>

                <tr>

                    <td>
                        <?= (int) $comment['bl_id'] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $comment['tk_user'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $comment['sp_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td class="comment-content">
                        <?= nl2br(
                            htmlspecialchars(
                                $comment['bl_content'],
                                ENT_QUOTES,
                                'UTF-8'
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
                                    Csrf::token(),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $comment['bl_id'] ?>"
                            >

                            <button
                                class="btn-danger"
                                type="submit"
                            >
                                Xóa
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>