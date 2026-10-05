<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Thêm danh mục</title>
</head>

<body>

<h1>Thêm danh mục</h1>

<?php if ($error !== ''): ?>

    <p>
        <?= htmlspecialchars($error) ?>
    </p>

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

    <label>Tên danh mục:</label>

    <input
        type="text"
        name="name"
    >

    <button type="submit">
        Thêm
    </button>

</form>

</body>
</html>