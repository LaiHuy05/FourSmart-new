<?php

/** @var array $category */
/** @var array $products */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>
        <?= htmlspecialchars($category['dm_name']) ?>
    </title>
    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/home-category.css">
</head>

<body>

<h1>
    <?= htmlspecialchars($category['dm_name']) ?>
</h1>

<?php foreach ($products as $product): ?>

    <div>

        <h3>
            <a href="?client=productDetail&id=<?= (int) $product['sp_id'] ?>">
                <?= htmlspecialchars($product['sp_name']) ?>
            </a>
        </h3>

        <p>
            <?= number_format(
                $product['sp_price'],
                0,
                ',',
                '.'
            ) ?>
            VNĐ
        </p>

    </div>

<?php endforeach; ?>

</body>
</html>