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
    Đơn hàng:
    <?= htmlspecialchars($order['dh_ma']) ?>
</h1>

<p>
    Trạng thái:
    <?= htmlspecialchars($order['dh_status']) ?>
</p>

<p>
    Tổng tiền:
    <?= number_format(
        $order['dh_totalamount'],
        0,
        ',',
        '.'
    ) ?>
    VNĐ
</p>

<h2>Sản phẩm</h2>

<?php foreach ($details as $detail): ?>

    <div>

        <strong>
            <?= htmlspecialchars(
                $detail['sp_name']
            ) ?>
        </strong>

        <p>
            Số lượng:
            <?= (int) $detail['ct_quantity'] ?>
        </p>

        <p>
            Bộ nhớ:
            <?= htmlspecialchars(
                $detail['od_option']
            ) ?>
        </p>

        <p>
            Màu:
            <?= htmlspecialchars(
                $detail['od_optionColor']
            ) ?>
        </p>

    </div>

    <hr>

<?php endforeach; ?>

</body>
</html>