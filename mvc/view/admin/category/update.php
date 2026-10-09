<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Sửa danh mục</title>
</head>

<body>
<div class="admin-shell">

<?php
include dirname(__DIR__) . '/partials/sidebar.php';
?>

<main class="admin-content">
<h1>Sửa danh mục</h1>

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
        value="<?= htmlspecialchars(
            $category['dm_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <button type="submit">
        Cập nhật
    </button>

</form>
</main>

</div>
</body>
</html>