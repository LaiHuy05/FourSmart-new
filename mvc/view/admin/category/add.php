<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <title>Thêm danh mục</title>

    <link rel="stylesheet" href="view/assets/css/base.css">
    <link rel="stylesheet" href="view/assets/css/home-category.css">

</head>

<body>

<div class="category-admin-form">

    <h1>Thêm danh mục</h1>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                Csrf::token(),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <div class="form-group">

            <label>Tên danh mục</label>

            <input
                type="text"
                name="name"
            >

        </div>

        <button type="submit">
            Thêm danh mục
        </button>

    </form>

</div>

</body>
</html>