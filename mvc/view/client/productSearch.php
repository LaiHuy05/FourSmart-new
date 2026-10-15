<?php

/** @var string $keyword */
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

    <title>Tìm kiếm sản phẩm</title>

    <link
        rel="stylesheet"
        href="view/assets/css/base.css"
    >

    <link
        rel="stylesheet"
        href="view/assets/css/home-category.css"
    >

    <link
        rel="stylesheet"
        href="view/assets/css/product.css"
    >

</head>

<body>

<?php
include __DIR__ . '/partials/header.php';
?>

<div class="container page">

    <h1 class="page-title">
        Kết quả tìm kiếm
    </h1>

    <?php if ($keyword !== ''): ?>

        <p>
            Từ khóa:

            <strong>
                <?= htmlspecialchars(
                    $keyword,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>
        </p>

    <?php endif; ?>

    <?php if ($keyword === ''): ?>

        <div class="empty-state">
            Vui lòng nhập từ khóa tìm kiếm.
        </div>

    <?php elseif (empty($products)): ?>

        <div class="empty-state">
            Không tìm thấy sản phẩm phù hợp.
        </div>

    <?php else: ?>

        <div class="product-grid">

            <?php foreach ($products as $product): ?>

                <div class="product-card">

                    <div class="product-card-image">

                        <?php if (
                            !empty($product['sp_image'])
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

                        <a
                            href="?client=productDetail&id=<?= (int) $product['sp_id'] ?>"
                        >
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

</div>

<?php
include __DIR__ . '/partials/footer.php';
?>

</body>

</html>