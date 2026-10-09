<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Giỏ hàng</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/cart-checkout.css">

</head>

<body>

<div class="container cart-page">

    <h1 class="page-title">
        Giỏ hàng
    </h1>

    <?php if (empty($cartDetails)): ?>

        <div class="empty-state">

            <p>Giỏ hàng đang trống.</p>

            <a
                class="btn"
                href="?client=home"
            >
                Tiếp tục mua hàng
            </a>

        </div>

    <?php else: ?>

        <div class="cart-layout">

            <div class="cart-items">

                <?php foreach ($cartDetails as $item): ?>

                    <?php
                    $productName = 'Sản phẩm';

                    foreach ($products as $product) {
                        if (
                            (int) $product['sp_id']
                            ===
                            (int) $item['id_sp']
                        ) {
                            $productName =
                                $product['sp_name'];

                            break;
                        }
                    }
                    ?>

                    <div class="cart-item">

                        <h3>
                            <?= htmlspecialchars(
                                $productName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h3>

                        <p class="cart-meta">
                            Bộ nhớ:
                            <?= htmlspecialchars(
                                $item['cd_option'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <p class="cart-meta">
                            Màu:
                            <?= htmlspecialchars(
                                $item['cd_optionColor'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <p class="cart-meta">
                            Số lượng:
                            <?= (int) $item['cd_quantity'] ?>
                        </p>

                        <div class="quantity-actions">

                            <form
                                action="?client=cartDecrease"
                                method="POST"
                            >

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
                                    -
                                </button>

                            </form>

                            <strong>
                                <?= (int) $item['cd_quantity'] ?>
                            </strong>

                            <form
                                action="?client=cartIncrease"
                                method="POST"
                            >

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
                                    +
                                </button>

                            </form>

                            <form
                                action="?client=cartDelete"
                                method="POST"
                            >

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

                                <button
                                    class="delete-button"
                                    type="submit"
                                >
                                    Xóa
                                </button>

                            </form>

                        </div>
</div>

                <?php endforeach; ?>

            </div>

            <div class="cart-summary">

                <h2>Đơn hàng</h2>

                <p>
                    Số loại sản phẩm:
                    <strong>
                        <?= count($cartDetails) ?>
                    </strong>
                </p>

                <a
                    class="btn checkout-button"
                    href="?client=checkout"
                >
                    Tiến hành thanh toán
                </a>

            </div>

        </div>

    <?php endif; ?>

</div>

</body>

</html>