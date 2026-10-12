<?php

/** @var array $cartDetails */
/** @var int $totalQuantity */
/** @var float $totalPrice */
/** @var string $cartError */

?>
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
<?php
include __DIR__ . '/partials/header.php';
?>

<div class="container cart-page">

    <h1 class="page-title">
        Giỏ hàng
    </h1>
    <?php if ($cartError !== ''): ?>

    <div class="error">
        <?= htmlspecialchars(
            $cartError,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>

<?php endif; ?>
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

                    
                    <div class="cart-item">

                        <h3>
                            <?= htmlspecialchars(
        $item['sp_name'],
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

    Đơn giá:

    <strong class="cart-item-price">

        <?= number_format(
            $item['sp_price'],
            0,
            ',',
            '.'
        ) ?>

        VNĐ

    </strong>


</p>
<p class="cart-meta">

    Thành tiền:

    <strong class="cart-item-total">

        <?= number_format(
            (float) $item['sp_price']
            *
            (int) $item['cd_quantity'],
            0,
            ',',
            '.'
        ) ?>

        VNĐ

    </strong>

</p>
<p class="cart-meta">

    Tồn kho:

    <strong>
        <?= (int) $item['sp_quantity'] ?>
    </strong>

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

    <h2>
        Tóm tắt đơn hàng
    </h2>

    <p>

        Tổng số lượng:

        <strong>
            <?= $totalQuantity ?>
        </strong>

    </p>

    <p>

        Tổng tiền:

        <strong class="cart-total-price">

            <?= number_format(
                $totalPrice,
                0,
                ',',
                '.'
            ) ?>

            VNĐ

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
<?php
include __DIR__ . '/partials/footer.php';
?>

</body>

</html>