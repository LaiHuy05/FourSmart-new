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

<h1>Thêm sản phẩm</h1>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(Csrf::token()) ?>"
    >

    <label>Tên:</label>
    <input type="text" name="name">

    <br>

    <label>Ảnh:</label>
    <input type="text" name="image">

    <br>

    <label>Giá:</label>
    <input type="number" name="price">

    <br>

    <label>Giá cũ:</label>
    <input type="number" name="old_price">

    <br>

    <label>Số lượng:</label>
    <input type="number" name="quantity">

    <br>

    <label>Mô tả:</label>
    <textarea name="description"></textarea>

    <br>

    <label>Danh mục:</label>

    <select name="category_id">

        <option value="0">
            Chọn danh mục
        </option>

        <?php foreach ($categories as $category): ?>

            <option value="<?= (int) $category['dm_id'] ?>">
                <?= htmlspecialchars($category['dm_name']) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <br>

    <button type="submit">
        Thêm
    </button>

</form>

</body>
</html>