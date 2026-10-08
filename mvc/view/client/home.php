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

<header class="site-header">

    <div class="container header-inner">

        <a
            class="logo"
            href="?client=home"
        >
            Four<span>Smart</span>
        </a>

        <nav>

            <a href="?client=home">
                Trang chủ
            </a>

            <a href="?client=cart">
                Giỏ hàng
            </a>

            <a href="?client=profile">
                Tài khoản
            </a>

        </nav>

    </div>

</header>

<section class="home-hero">

    <div class="container">

        <h1>FourSmart</h1>

        <p>
            Điện thoại chính hãng - giá tốt
        </p>

    </div>

</section>

<main class="container">

<?php foreach ($categories as $category): ?>

    <section class="category-section">

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

        </div>

        <div class="product-grid">

            <?php foreach ($products as $product): ?>

                <?php if (
                    (int) $product['id_dm']
                    ===
                    (int) $category['dm_id']
                ): ?>

                    <div class="product-card">

                        <h3>

                            <a href="?client=productDetail&id=<?= (int) $product['sp_id'] ?>">

                                <?= htmlspecialchars(
                                    $product['sp_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
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

                <?php endif; ?>

            <?php endforeach; ?>

        </div>
</section>

<?php endforeach; ?>

</main>

</body>

</html>
