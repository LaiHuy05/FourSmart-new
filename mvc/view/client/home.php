<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>FourSmart</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/home-category.css">

</head>

<body>

<?php
include __DIR__ . '/partials/header.php';
?>

<section class="home-hero">

    <div class="container">

        <h1>FourSmart</h1>

        <p>
            Điện thoại chính hãng - giá tốt
        </p>

    </div>

</section>

<main class="container">

    <div class="category-quick-nav">

        <?php foreach ($categories as $category): ?>

            <a href="#category-<?= (int) $category['dm_id'] ?>">

                <?= htmlspecialchars(
                    $category['dm_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </a>

        <?php endforeach; ?>

    </div>

    <?php foreach ($categories as $category): ?>

        <?php

        $categoryProducts = array_filter(
            $products,
            static function (
                array $product
            ) use ($category): bool {
                return
                    (int) $product['id_dm']
                    ===
                    (int) $category['dm_id'];
            }
        );

        ?>

        <section
            class="category-section"
            id="category-<?= (int) $category['dm_id'] ?>"
        >

            <div class="category-title">

                <h2>

                    <a href="?client=category&id=<?= (int) $category['dm_id'] ?>">

                        <?= htmlspecialchars(
                            $category['dm_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </a>

                </h2>

                <a href="?client=category&id=<?= (int) $category['dm_id'] ?>">
                    Xem tất cả
                </a>

            </div>

            <?php if (empty($categoryProducts)): ?>

                <div class="empty-state">
                    Danh mục này chưa có sản phẩm.
                </div>

            <?php else: ?>

                <div class="product-grid">

                    <?php foreach (
                        $categoryProducts
                        as $product
                    ): ?>

                        <div class="product-card">

                            <div class="product-card-image">

                                <?php if (
                                    !empty(
                                        $product['sp_image']
                                    )
                                ): ?>

                                    <img
                                        src="<?= htmlspecialchars(
                                            $product['sp_image'],
ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        alt="<?= htmlspecialchars(
                                            $product['sp_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                <?php else: ?>

                                    <div class="product-no-image">
                                        Chưa có ảnh
                                    </div>

                                <?php endif; ?>

                            </div>

                            <h3>

                                <a href="?client=productDetail&id=<?= (int) $product['sp_id'] ?>">

                                    <?= htmlspecialchars(
                                        $product['sp_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </a>

                            </h3>

                            <?php if (
                                (float) $product['sp_pricedel']
                                >
                                (float) $product['sp_price']
                            ): ?>

                                <div class="product-old-price">

                                    <?= number_format(
                                        $product['sp_pricedel'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                    VNĐ

                                </div>

                            <?php endif; ?>

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

            <?php endif; ?>

        </section>

    <?php endforeach; ?>

</main>

<?php
include __DIR__ . '/partials/footer.php';
?>

</body>

</html>
