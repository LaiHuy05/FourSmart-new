<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý danh mục</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/home-category.css">
</head>

<body>

<h1>Danh sách danh mục</h1>

<a href="?act=admin&admin=categoryAdd">
    Thêm danh mục
</a>

<table border="1">

    <tr>
        <th>ID</th>
        <th>Tên</th>
        <th>Thao tác</th>
    </tr>

    <?php foreach ($categories as $category): ?>

        <tr>

            <td>
                <?= (int) $category['dm_id'] ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $category['dm_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </td>

            <td>

                <a href="?act=admin&admin=categoryUpdate&id=<?= (int) $category['dm_id'] ?>">
                    Sửa
                </a>

                <form
                    action="?act=admin&admin=categoryDelete"
                    method="POST"
                    style="display:inline"
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
                        value="<?= (int) $category['dm_id'] ?>"
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
