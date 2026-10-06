<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>FourSmart</title>
</head>

<body>

    <h1>FourSmart</h1>

    <?php foreach ($categories as $category): ?>

        <section>

        <h2>
    <a href="?client=category&id=<?= (int) $category['dm_id'] ?>">
        <?= htmlspecialchars(
            $category['dm_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </a>
</h2>

            <?php foreach ($products as $product): ?>

                <?php if (
                    (int) $product['id_dm']
                    ===
                    (int) $category['dm_id']
                ): ?>

<div>

    <h3>
        <a href="?client=productDetail&id=<?= (int) $product['sp_id'] ?>">
            <?= htmlspecialchars(
                $product['sp_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
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

    <p>
        <a href="?client=productDetail&id=<?= (int) $product['sp_id'] ?>">
            Xem chi tiết
        </a>
    </p>

</div>

<hr>

                <?php endif; ?>

            <?php endforeach; ?>

        </section>

    <?php endforeach; ?>

</body>

</html>