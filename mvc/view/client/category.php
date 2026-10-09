<?php

/** @var array $category */
/** @var array $products */

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars(
            $category['dm_name']
        ) ?>
    </title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/home-category.css">

</head>

<body>
<?php
include __DIR__ . '/partials/header.php';
?>
<div class="container page">

    <div class="category-page-header">

        <h1>
            <?= htmlspecialchars(
                $category['dm_name']
            ) ?>
        </h1>

    </div>

    <div class="product-grid">

        <?php foreach ($products as $product): ?>

            <div class="product-card">

                <h3>

                    <a href="?client=productDetail&id=<?= (int) $product['sp_id'] ?>">

                        <?= htmlspecialchars(
                            $product['sp_name']
                        ) ?>

                    </a>

                </h3>

                <div class="product-card-price">

                    <?= number_format(
                        $product['sp_price'],
                        0,
                        ',',
                        '.'
                    ) ?>

                    VNĐ

                </div>

                <a
                    class="detail-link"
                    href="?client=productDetail&id=<?= (int) $product['sp_id'] ?>"
                >
                    Xem chi tiết
                </a>

            </div>

        <?php endforeach; ?>

    </div>

</div>
<?php
include __DIR__ . '/partials/footer.php';
?>
</body>

</html>