<?php

/** @var string $error */
/** @var array $categories */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Thêm sản phẩm</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/product.css">
</head>

<body>

<div class="product-admin-form">

    <h1>Thêm sản phẩm</h1>

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
            <input type="text" name="name">
        </div>

        <div class="form-group">
            <label>Ảnh</label>
            <input type="text" name="image">
        </div>

        <div class="form-group">
            <label>Giá</label>
            <input type="number" name="price">
        </div>

        <div class="form-group">
            <label>Giá cũ</label>
            <input type="number" name="old_price">
        </div>

        <div class="form-group">
            <label>Số lượng</label>
            <input type="number" name="quantity">
        </div>

        <div class="form-group">

            <label>Mô tả</label>

            <textarea name="description"></textarea>

        </div>

        <div class="form-group">

            <label>Danh mục</label>

            <select name="category_id">

                <option value="0">
                    Chọn danh mục
                </option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= (int) $category['dm_id'] ?>"
                    >
                        <?= htmlspecialchars(
                            $category['dm_name']
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <button type="submit">
            Thêm sản phẩm
        </button>

    </form>

</div>

</body>
</html>