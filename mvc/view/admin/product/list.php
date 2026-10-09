<?php

/** @var array $products */
/** @var array $categories */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý sản phẩm</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/product.css">
</head>

<body>
<div class="admin-shell">

<?php
include dirname(__DIR__) . '/partials/sidebar.php';
?>

<main class="admin-content">
<div class="container page">

    <div class="admin-header">

        <h1>Danh sách sản phẩm</h1>

        <a
            class="btn"
            href="?act=admin&admin=productAdd"
        >
            Thêm sản phẩm
        </a>

    </div>

    <div class="table-wrapper">

        <table class="product-table">

            <thead>

            <tr>
                <th>ID</th>
                <th>Tên</th>
                <th>Giá</th>
                <th>Số lượng</th>
                <th>Danh mục</th>
                <th>Thao tác</th>
            </tr>

            </thead>

            <tbody>

            <?php foreach ($products as $product): ?>

                <?php
                $categoryName = '';

                foreach ($categories as $category) {
                    if (
                        (int) $category['dm_id']
                        ===
                        (int) $product['id_dm']
                    ) {
                        $categoryName =
                            $category['dm_name'];

                        break;
                    }
                }
                ?>

                <tr>

                    <td>
                        <?= (int) $product['sp_id'] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $product['sp_name']
                        ) ?>
                    </td>

                    <td>
                        <?= number_format(
                            $product['sp_price'],
                            0,
                            ',',
                            '.'
                        ) ?>
                        VNĐ
                    </td>

                    <td>
                        <?= (int) $product['sp_quantity'] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $categoryName
                        ) ?>
                    </td>

                    <td>

                        <div class="product-admin-actions">

                            <a
                                class="btn"
                                href="?act=admin&admin=productUpdate&id=<?= (int) $product['sp_id'] ?>"
                            >
                                Sửa
                            </a>

                            <form
                                action="?act=admin&admin=productDelete"
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
                                    value="<?= (int) $product['sp_id'] ?>"
                                >

                                <button
                                    class="btn-danger"
                                    type="submit"
                                >
                                    Xóa
                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>
</main>

</div>
</body>
</html>