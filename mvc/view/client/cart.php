<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <title>Giỏ hàng</title>
</head>

<body>

    <h1>Giỏ hàng</h1>

    <?php if (empty($cartDetails)): ?>

        <p>Giỏ hàng đang trống.</p>

    <?php else: ?>

        <?php foreach ($cartDetails as $item): ?>

            <?php
            $productName = 'Sản phẩm';

            foreach ($products as $product) {
                if (
                    (int) $product['sp_id']
                    ===
                    (int) $item['id_sp']
                ) {
                    $productName = $product['sp_name'];
                    break;
                }
            }
            ?>

            <div>

                <h3>
                    <?= htmlspecialchars(
                        $productName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h3>

                <p>
                    Bộ nhớ:
                    <?= htmlspecialchars(
                        $item['cd_option'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <p>
                    Màu:
                    <?= htmlspecialchars(
                        $item['cd_optionColor'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <p>
                    Số lượng:
                    <?= (int) $item['cd_quantity'] ?>
                </p>

            </div>

            <hr>

        <?php endforeach; ?>

    <?php endif; ?>

</body>

</html>