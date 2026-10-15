<?php

final class AdminProductController
{
    public static function handle(string $action): void
    {
        switch ($action) {
            case 'productList':
                self::index();
                break;

            case 'productAdd':
                self::add();
                break;

            case 'productUpdate':
                self::update();
                break;

            case 'productDelete':
                self::delete();
                break;

            default:
                http_response_code(404);
                exit('Chức năng sản phẩm không tồn tại');
        }
    }

    private static function index(): void
    {
        $products = ProductModel::all();
        $categories = CategoryModel::all();

        include __DIR__
            . '/../../view/admin/product/list.php';
    }

    private static function add(): void
    {
        $categories = CategoryModel::all();

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                exit('CSRF không hợp lệ');
            }

            $name = trim($_POST['name'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $quantity = (int) ($_POST['quantity'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $oldPrice = (float) ($_POST['old_price'] ?? 0);

            if (
    $name === ''
    || $price <= 0
    || $quantity < 0
    || $description === ''
    || $categoryId <= 0
) {
    $error =
        'Vui lòng nhập đầy đủ thông tin hợp lệ';
} else {
    try {
        $image =
            self::uploadImage(
                $_FILES['image'] ?? []
            );

        $code =
            'SP_'
            . random_int(
                100000,
                999999
            );

        ProductModel::create(
            $name,
            $image,
            $price,
            $quantity,
            $description,
            $categoryId,
            $code,
            $oldPrice
        );

        header(
            'Location: ?act=admin'
            . '&admin=productList'
        );

        exit;

    } catch (RuntimeException $exception) {
        $error =
            $exception->getMessage();
    }
}
        }

        include __DIR__
            . '/../../view/admin/product/add.php';
    }

    private static function update(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        $product = ProductModel::find($id);

        if (!$product) {
            http_response_code(404);
            exit('Sản phẩm không tồn tại');
        }

        $categories = CategoryModel::all();
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
exit('CSRF không hợp lệ');
            }

            $name = trim($_POST['name'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $quantity = (int) ($_POST['quantity'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $oldPrice = (float) ($_POST['old_price'] ?? 0);
            $image = $product['sp_image'];

            if (
                $name === ''
                || $price <= 0
                || $quantity < 0
                || $description === ''
                || $categoryId <= 0
            ) {
                $error = 'Vui lòng nhập đầy đủ thông tin hợp lệ';
            } else {
              try {

    if (
        isset($_FILES['image'])
        &&
        ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_NO_FILE
    ) {
        $image =
            self::uploadImage(
                $_FILES['image']
            );
    }

    ProductModel::update(
        $id,
        $name,
        $image,
        $price,
        $quantity,
        $description,
        $categoryId,
        $oldPrice
    );

    header(
        'Location: ?act=admin'
        . '&admin=productList'
    );

    exit;

} catch (RuntimeException $exception) {

    $error =
        $exception->getMessage();
}
                ProductModel::update(
                    $id,
                    $name,
                    $image,
                    $price,
                    $quantity,
                    $description,
                    $categoryId,
                    $oldPrice
                );

                header(
                    'Location: ?act=admin&admin=productList'
                );
                exit;
            }
        }

        include __DIR__
            . '/../../view/admin/product/update.php';
    }

    private static function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Phương thức không hợp lệ');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('CSRF không hợp lệ');
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            if (ProductModel::hasRelatedData($id)) {
    http_response_code(409);

    exit(
        'Không thể xóa sản phẩm vì đang có '
        . 'giỏ hàng, đơn hàng hoặc bình luận liên quan'
    );
}
            ProductModel::delete($id);
        }

        header(
            'Location: ?act=admin&admin=productList'
        );
        exit;
    }
    private static function uploadImage(
    array $file
): string {
    if (
        empty($file)
        || !isset($file['error'])
        || $file['error']
            === UPLOAD_ERR_NO_FILE
    ) {
        throw new RuntimeException(
            'Vui lòng chọn ảnh sản phẩm'
        );
    }

    if (
        $file['error']
        !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'Có lỗi xảy ra khi upload ảnh'
        );
    }

    if (
        (int) $file['size']
        >
        5 * 1024 * 1024
    ) {
        throw new RuntimeException(
            'Ảnh không được vượt quá 5MB'
        );
    }

    $imageInfo =
        getimagesize(
            $file['tmp_name']
        );

    if ($imageInfo === false) {
        throw new RuntimeException(
            'File được chọn không phải ảnh hợp lệ'
        );
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mimeType =
        $imageInfo['mime'] ?? '';

    if (
        !isset(
            $allowedTypes[$mimeType]
        )
    ) {
        throw new RuntimeException(
            'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP'
        );
    }

    $extension =
        $allowedTypes[$mimeType];

    $fileName =
        'product_'
        . bin2hex(
            random_bytes(8)
        )
        . '.'
        . $extension;

    $uploadDir =
        __DIR__
        . '/../../upload/';

    if (!is_dir($uploadDir)) {
        if (
            !mkdir(
                $uploadDir,
                0777,
                true
            )
        ) {
            throw new RuntimeException(
                'Không thể tạo thư mục upload'
            );
        }
    }

    $destination =
        $uploadDir
        . $fileName;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        throw new RuntimeException(
            'Không thể lưu ảnh sản phẩm'
        );
    }

    return
        'upload/'
        . $fileName;
}
}