<?php

/** @var string $error */
/** @var array $product */
/** @var array $categories */

?>

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

<h1>Sửa sản phẩm</h1>

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

    <input
        type="text"
        name="name"
        value="<?= htmlspecialchars($product['sp_name']) ?>"
    >

    <br>

    <label>Ảnh:</label>

    <input
        type="text"
        name="image"
        value="<?= htmlspecialchars($product['sp_image']) ?>"
    >

    <br>

    <label>Giá:</label>

    <input
        type="number"
        name="price"
        value="<?= $product['sp_price'] ?>"
    >

    <br>

    <label>Giá cũ:</label>

    <input
        type="number"
        name="old_price"
        value="<?= $product['sp_pricedel'] ?>"
    >

    <br>

    <label>Số lượng:</label>

    <input
        type="number"
        name="quantity"
        value="<?= (int) $product['sp_quantity'] ?>"
    >

    <br>

    <label>Mô tả:</label>

    <textarea name="description"><?= htmlspecialchars(
        $product['sp_describe']
    ) ?></textarea>

    <br>

    <label>Danh mục:</label>

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
                <?= htmlspecialchars($category['dm_name']) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <br>

    <button type="submit">
        Cập nhật
    </button>

</form>

</body>
</html>