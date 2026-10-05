<?php

/** @var array $product */
/** @var array $colors */
/** @var array $memories */

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Chi tiết sản phẩm</title>
</head>

<body>

<h1>
    <?= htmlspecialchars(
        $product['sp_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</h1>

<p>
    Giá:
    <?= number_format(
        $product['sp_price'],
        0,
        ',',
        '.'
    ) ?>
    VNĐ
</p>

<p>
    Mô tả:
    <?= htmlspecialchars(
        $product['sp_describe'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    Số lượng:
    <?= (int) $product['sp_quantity'] ?>
</p>

<form action="?client=cartAdd" method="POST">

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
        name="product_id"
        value="<?= (int) $product['sp_id'] ?>"
    >

    <label>Màu:</label>

    <select name="color" required>

        <?php foreach ($colors as $color): ?>

            <option
                value="<?= htmlspecialchars(
                    $color['pc_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                <?= htmlspecialchars(
                    $color['pc_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <br>

    <label>Bộ nhớ:</label>

    <select name="memory" required>

        <?php foreach ($memories as $memory): ?>

            <option
                value="<?= htmlspecialchars(
                    $memory['pm_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                <?= htmlspecialchars(
                    $memory['pm_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <br>

    <button type="submit">
        Thêm vào giỏ hàng
    </button>

</form>

</body>
</html>