<?php

/** @var array $product */
/** @var array $colors */
/** @var array $memories */
/** @var array $comments */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Chi tiết sản phẩm</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/product.css">
</head>

<body>

<div class="container page">

    <div class="product-detail">

        <div class="product-image-box">

            <?php if (!empty($product['sp_image'])): ?>

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

                <p>Chưa có hình ảnh</p>

            <?php endif; ?>

        </div>

        <div class="product-info">

            <h1 class="product-name">
                <?= htmlspecialchars(
                    $product['sp_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h1>

            <div class="product-price">
                <?= number_format(
                    $product['sp_price'],
                    0,
                    ',',
                    '.'
                ) ?>
                VNĐ
            </div>

            <p class="product-description">
                <?= htmlspecialchars(
                    $product['sp_describe'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <div class="product-stock">
                Còn
                <?= (int) $product['sp_quantity'] ?>
                sản phẩm
            </div>

            <form
                action="?client=cartAdd"
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
                    name="product_id"
                    value="<?= (int) $product['sp_id'] ?>"
                >

                <div class="variant-group">

                    <label>Màu:</label>

                    <select name="color" required>

                        <?php foreach ($colors as $color): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $color['pc_name'],
ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    $color['pc_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="variant-group">

                    <label>Bộ nhớ:</label>

                    <select name="memory" required>

                        <?php foreach ($memories as $memory): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $memory['pm_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    $memory['pm_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button
                    class="add-cart-btn"
                    type="submit"
                >
                    Thêm vào giỏ hàng
                </button>

            </form>

        </div>

    </div>

    <div class="comment-section">

        <h2>Bình luận</h2>

        <?php if (isset($_SESSION['user_id'])): ?>

            <form
                class="comment-form"
                action="?client=commentAdd"
                method="POST"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        Csrf::token()
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="product_id"
                    value="<?= (int) $product['sp_id'] ?>"
                >

                <textarea
                    name="content"
                    placeholder="Nhập bình luận..."
                    required
                ></textarea>

                <button type="submit">
                    Gửi bình luận
                </button>

            </form>

        <?php else: ?>

            <p>
                <a href="?client=login">
                    Đăng nhập để bình luận
                </a>
            </p>

        <?php endif; ?>

        <?php foreach ($comments as $comment): ?>

            <div class="comment-item">

                <div class="comment-user">
                    <?= htmlspecialchars(
$comment['tk_user']
                    ) ?>
                </div>

                <p class="comment-text">
                    <?= nl2br(
                        htmlspecialchars(
                            $comment['bl_content']
                        )
                    ) ?>
                </p>

            </div>

        <?php endforeach; ?>

    </div>

</div>

</body>
</html>