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
            $image = trim($_POST['image'] ?? '');
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
                $error = 'Vui lòng nhập đầy đủ thông tin hợp lệ';
            } else {
                $code = 'SP_' . random_int(100000, 999999);

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
                    'Location: ?act=admin&admin=productList'
                );
                exit;
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
            $image = trim($_POST['image'] ?? '');
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
                $error = 'Vui lòng nhập đầy đủ thông tin hợp lệ';
            } else {
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
            ProductModel::delete($id);
        }

        header(
            'Location: ?act=admin&admin=productList'
        );
        exit;
    }
}