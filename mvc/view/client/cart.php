<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <title>Giỏ hàng</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
<link rel="stylesheet" href="view/assets/css/cart-checkout.css">
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
                <form action="?client=cartIncrease" method="POST">

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
        name="detail_id"
        value="<?= (int) $item['cd_id'] ?>"
    >

    <button type="submit">+</button>

</form>


<form action="?client=cartDecrease" method="POST">

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
        name="detail_id"
        value="<?= (int) $item['cd_id'] ?>"
    >

    <button type="submit">-</button>

</form>


<form action="?client=cartDelete" method="POST">

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
        name="detail_id"
        value="<?= (int) $item['cd_id'] ?>"
    >

    <button type="submit">
        Xóa
    </button>

</form>

            </div>

            <hr>

        <?php endforeach; ?>
            <p>
    <a href="?client=checkout">
        Tiến hành thanh toán
    </a>
</p>
    <?php endif; ?>

</body>

</html>