<?php

/** @var array $products */
/** @var array $categories */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý sản phẩm</title>
</head>

<body>

<h1>Danh sách sản phẩm</h1>

<a href="?act=admin&admin=productAdd">
    Thêm sản phẩm
</a>

<table border="1">

    <tr>
        <th>ID</th>
        <th>Tên</th>
        <th>Giá</th>
        <th>Số lượng</th>
        <th>Danh mục</th>
        <th>Thao tác</th>
    </tr>

    <?php foreach ($products as $product): ?>

        <?php
        $categoryName = '';

        foreach ($categories as $category) {
            if (
                (int) $category['dm_id']
                ===
                (int) $product['id_dm']
            ) {
                $categoryName = $category['dm_name'];
                break;
            }
        }
        ?>

        <tr>

            <td><?= (int) $product['sp_id'] ?></td>

            <td>
                <?= htmlspecialchars($product['sp_name']) ?>
            </td>

            <td>
                <?= number_format($product['sp_price'], 0, ',', '.') ?>
            </td>

            <td><?= (int) $product['sp_quantity'] ?></td>

            <td>
                <?= htmlspecialchars($categoryName) ?>
            </td>

            <td>

                <a href="?act=admin&admin=productUpdate&id=<?= (int) $product['sp_id'] ?>">
                    Sửa
                </a>

                <form
                    action="?act=admin&admin=productDelete"
                    method="POST"
                    style="display:inline"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(Csrf::token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $product['sp_id'] ?>"
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