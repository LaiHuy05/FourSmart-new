<?php

/** @var array $order */
/** @var array $details */

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Chi tiết đơn hàng</title>
</head>

<body>

<h1>
    Đơn hàng
    <?= htmlspecialchars($order['dh_ma']) ?>
</h1>

<p>
    Khách hàng:
    <?= htmlspecialchars($order['dh_nameUser']) ?>
</p>

<p>
    SĐT:
    <?= htmlspecialchars($order['dh_phoneUser']) ?>
</p>

<p>
    Địa chỉ:
    <?= htmlspecialchars($order['dh_addressUser']) ?>
</p>

<p>
    Trạng thái:
    <?= htmlspecialchars($order['dh_status']) ?>
</p>

<form
    action="?act=admin&admin=orderStatus"
    method="POST"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(Csrf::token()) ?>"
    >

    <input
        type="hidden"
        name="id"
        value="<?= (int) $order['dh_id'] ?>"
    >

    <select name="status">

        <?php
        $statuses = [
            'Chờ xác nhận',
            'Đã xác nhận',
            'Đang giao',
            'Đã giao',
            'Đã hủy',
        ];
        ?>

        <?php foreach ($statuses as $status): ?>

            <option
                value="<?= htmlspecialchars($status) ?>"
                <?= $order['dh_status'] === $status
                    ? 'selected'
                    : ''
                ?>
            >
                <?= htmlspecialchars($status) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <button type="submit">
        Cập nhật trạng thái
    </button>

</form>

<hr>

<h2>Sản phẩm</h2>

<?php foreach ($details as $detail): ?>

    <div>

        <strong>
            <?= htmlspecialchars($detail['sp_name']) ?>
        </strong>

        <p>
            SL:
            <?= (int) $detail['ct_quantity'] ?>
        </p>

        <p>
            Bộ nhớ:
            <?= htmlspecialchars($detail['od_option']) ?>
        </p>

        <p>
            Màu:
            <?= htmlspecialchars(
                $detail['od_optionColor']
            ) ?>
        </p>

    </div>

<?php endforeach; ?>

</body>
</html>