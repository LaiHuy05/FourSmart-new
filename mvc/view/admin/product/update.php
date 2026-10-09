<?php

/** @var string $error */
/** @var array $product */
/** @var array $categories */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Sửa sản phẩm</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/product.css">
</head>

<body>
<div class="admin-shell">

<?php
include dirname(__DIR__) . '/partials/sidebar.php';
?>

<main class="admin-content">
<div class="product-admin-form">

    <h1>Sửa sản phẩm</h1>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                Csrf::token()
            ) ?>"
        >

        <div class="form-group">

            <label>Tên sản phẩm</label>

            <input
                type="text"
                name="name"
                value="<?= htmlspecialchars(
                    $product['sp_name']
                ) ?>"
            >

        </div>

        <div class="form-group">

            <label>Ảnh</label>

            <input
                type="text"
                name="image"
                value="<?= htmlspecialchars(
                    $product['sp_image']
                ) ?>"
            >

        </div>

        <div class="form-group">

            <label>Giá</label>

            <input
                type="number"
                name="price"
                value="<?= $product['sp_price'] ?>"
            >

        </div>

        <div class="form-group">

            <label>Giá cũ</label>

            <input
                type="number"
                name="old_price"
                value="<?= $product['sp_pricedel'] ?>"
            >

        </div>

        <div class="form-group">

            <label>Số lượng</label>

            <input
                type="number"
                name="quantity"
                value="<?= (int) $product['sp_quantity'] ?>"
            >

        </div>

        <div class="form-group">

            <label>Mô tả</label>

            <textarea name="description"><?= htmlspecialchars(
                $product['sp_describe']
            ) ?></textarea>

        </div>

        <div class="form-group">

            <label>Danh mục</label>

            <select name="category_id">

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= (int) $category['dm_id'] ?>"
                        <?= (int) $category['dm_id']
                            === (int) $product['id_dm']
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= htmlspecialchars(
                            $category['dm_name']
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>
<button type="submit">
            Cập nhật
        </button>

    </form>

</div>
</main>

</div>
</body>
</html>