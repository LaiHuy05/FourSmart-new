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
    <title>Chi tiết sản phẩm</title>
    <link rel="stylesheet" href="view/assets/css/base.css">
<link rel="stylesheet" href="view/assets/css/product.css">
</head>

<body>

<h1>
    <?= htmlspecialchars(
        $product['sp_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</h1>

<p>
    Giá:
    <?= number_format(
        $product['sp_price'],
        0,
        ',',
        '.'
    ) ?>
    VNĐ
</p>

<p>
    Mô tả:
    <?= htmlspecialchars(
        $product['sp_describe'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    Số lượng:
    <?= (int) $product['sp_quantity'] ?>
</p>

<form action="?client=cartAdd" method="POST">

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

    <br>

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

    <br>

    <button type="submit">
        Thêm vào giỏ hàng
    </button>

</form>
<hr>

<h2>Bình luận</h2>

<?php if (isset($_SESSION['user_id'])): ?>

<form
    action="?client=commentAdd"
    method="POST"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(Csrf::token()) ?>"
    >

    <input
        type="hidden"
        name="product_id"
        value="<?= (int) $product['sp_id'] ?>"
    >

    <textarea
        name="content"
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

<div>
    <strong>
        <?= htmlspecialchars($comment['tk_user']) ?>
    </strong>

    <p>
        <?= nl2br(
            htmlspecialchars($comment['bl_content'])
        ) ?>
    </p>
</div>
<?php endforeach; ?>
</body>
</html>