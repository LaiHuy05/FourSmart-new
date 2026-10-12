UPDATE `order`
SET dh_status = 'Đã xác nhận'
WHERE dh_status IN (
    'Đã Xác Nhận',
    'Đã xác nhận'
);

UPDATE `order`
SET dh_status = 'Đã giao'
WHERE dh_status IN (
    'Đã nhận hàng',
    'Giao Hàng Thành Công',
    'Đã Giao'
);