<?php

/** @var array $product */
/** @var array $colors */
/** @var array $memories */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Biến thể sản phẩm
    </title>

    <link
        rel="stylesheet"
        href="view/assets/css/base.css"
    >

    <link
        rel="stylesheet"
        href="view/assets/css/product.css"
    >
</head>

<body>

<div class="admin-shell">

<?php
include dirname(__DIR__)
    . '/partials/sidebar.php';
?>

<main class="admin-content">

<div class="container page">

    <div class="admin-header">

        <div>

            <h1>
                Biến thể sản phẩm
            </h1>

            <p>
                <?= htmlspecialchars(
                    $product['sp_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

        </div>

        <a
            class="btn"
            href="?act=admin&admin=productList"
        >
            Quay lại
        </a>

    </div>

    <div class="variant-admin-grid">

        <div class="variant-admin-card">

            <h2>
                Màu sản phẩm
            </h2>

            <form
                action="?act=admin&admin=productColorAdd"
                method="POST"
                class="variant-add-form"
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

                <input
                    type="text"
                    name="name"
                    placeholder="Nhập màu..."
                    required
                >

                <button type="submit">
                    Thêm màu
                </button>

            </form>

            <?php if (empty($colors)): ?>

                <div class="empty-state">
                    Chưa có màu sản phẩm.
                </div>

            <?php else: ?>

                <?php foreach ($colors as $color): ?>

                    <div class="variant-admin-row">

                        <form
                            action="?act=admin&admin=productColorUpdate"
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
                                name="id"
                                value="<?= (int) $color['pc_id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int) $product['sp_id'] ?>"
                            >

                            <input
                                type="text"
                                name="name"
                                value="<?= htmlspecialchars(
                                    $color['pc_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >

                            <button type="submit">
                                Lưu
                            </button>

                        </form>

                        <form
                            action="?act=admin&admin=productColorDelete"
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
                                name="id"
                                value="<?= (int) $color['pc_id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int) $product['sp_id'] ?>"
                            >

                            <button
                                class="btn-danger"
                                type="submit"
                            >
                                Xóa
                            </button>

                        </form>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <div class="variant-admin-card">

            <h2>
                Bộ nhớ sản phẩm
            </h2>

            <form
                action="?act=admin&admin=productMemoryAdd"
                method="POST"
                class="variant-add-form"
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

                <input
                    type="text"
                    name="name"
                    placeholder="Ví dụ: 128GB"
                    required
                >

                <button type="submit">
                    Thêm bộ nhớ
                </button>

            </form>

            <?php if (empty($memories)): ?>

                <div class="empty-state">
                    Chưa có bộ nhớ sản phẩm.
                </div>

            <?php else: ?>

                <?php foreach ($memories as $memory): ?>

                    <div class="variant-admin-row">

                        <form
                            action="?act=admin&admin=productMemoryUpdate"
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
                                name="id"
                                value="<?= (int) $memory['pm_id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int) $product['sp_id'] ?>"
                            >

                            <input
                                type="text"
                                name="name"
                                value="<?= htmlspecialchars(
                                    $memory['pm_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >

                            <button type="submit">
                                Lưu
                            </button>

                        </form>

                        <form
                            action="?act=admin&admin=productMemoryDelete"
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
                                name="id"
                                value="<?= (int) $memory['pm_id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int) $product['sp_id'] ?>"
                            >

                            <button
                                class="btn-danger"
                                type="submit"
                            >
                                Xóa
                            </button>

                        </form>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

</main>

</div>

</body>
</html>