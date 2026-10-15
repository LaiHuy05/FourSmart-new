<?php

final class AdminProductVariantController
{
    public static function handle(
        string $action
    ): void {
        switch ($action) {

            case 'productVariant':
                self::index();
                break;

            case 'productColorAdd':
                self::colorAdd();
                break;

            case 'productColorUpdate':
                self::colorUpdate();
                break;

            case 'productColorDelete':
                self::colorDelete();
                break;

            case 'productMemoryAdd':
                self::memoryAdd();
                break;

            case 'productMemoryUpdate':
                self::memoryUpdate();
                break;

            case 'productMemoryDelete':
                self::memoryDelete();
                break;

            default:
                http_response_code(404);
                exit(
                    'Chức năng biến thể sản phẩm không tồn tại'
                );
        }
    }

    private static function index(): void
    {
        $productId =
            (int) ($_GET['product_id'] ?? 0);

        $product =
            ProductModel::find($productId);

        if (!$product) {
            http_response_code(404);
            exit('Sản phẩm không tồn tại');
        }

        $colors =
            ProductVariantModel::colorsByProduct(
                $productId
            );

        $memories =
            ProductVariantModel::memoriesByProduct(
                $productId
            );

        include __DIR__
            . '/../../view/admin/product/variant.php';
    }

    private static function colorAdd(): void
    {
        self::checkPost();

        $productId =
            (int) ($_POST['product_id'] ?? 0);

        $name =
            trim($_POST['name'] ?? '');

        self::checkProduct($productId);

        if ($name === '') {
            http_response_code(422);
            exit('Tên màu không được để trống');
        }

        ProductVariantModel::createColor(
            $name,
            $productId
        );

        self::redirect($productId);
    }

    private static function colorUpdate(): void
    {
        self::checkPost();

        $id =
            (int) ($_POST['id'] ?? 0);

        $productId =
            (int) ($_POST['product_id'] ?? 0);

        $name =
            trim($_POST['name'] ?? '');

        self::checkProduct($productId);

        $color =
            ProductVariantModel::findColor($id);

        if (
            !$color
            ||
            (int) $color['id_sp']
            !== $productId
        ) {
            http_response_code(404);
            exit('Màu sản phẩm không tồn tại');
        }

        if ($name === '') {
            http_response_code(422);
            exit('Tên màu không được để trống');
        }

        ProductVariantModel::updateColor(
            $id,
            $name
);

        self::redirect($productId);
    }

    private static function colorDelete(): void
    {
        self::checkPost();

        $id =
            (int) ($_POST['id'] ?? 0);

        $productId =
            (int) ($_POST['product_id'] ?? 0);

        self::checkProduct($productId);

        $color =
            ProductVariantModel::findColor($id);

        if (
            !$color
            ||
            (int) $color['id_sp']
            !== $productId
        ) {
            http_response_code(404);
            exit('Màu sản phẩm không tồn tại');
        }

        ProductVariantModel::deleteColor($id);

        self::redirect($productId);
    }

    private static function memoryAdd(): void
    {
        self::checkPost();

        $productId =
            (int) ($_POST['product_id'] ?? 0);

        $name =
            trim($_POST['name'] ?? '');

        self::checkProduct($productId);

        if ($name === '') {
            http_response_code(422);
            exit('Tên bộ nhớ không được để trống');
        }

        ProductVariantModel::createMemory(
            $name,
            $productId
        );

        self::redirect($productId);
    }

    private static function memoryUpdate(): void
    {
        self::checkPost();

        $id =
            (int) ($_POST['id'] ?? 0);

        $productId =
            (int) ($_POST['product_id'] ?? 0);

        $name =
            trim($_POST['name'] ?? '');

        self::checkProduct($productId);

        $memory =
            ProductVariantModel::findMemory($id);

        if (
            !$memory
            ||
            (int) $memory['id_sp']
            !== $productId
        ) {
            http_response_code(404);
            exit('Bộ nhớ sản phẩm không tồn tại');
        }

        if ($name === '') {
            http_response_code(422);
            exit('Tên bộ nhớ không được để trống');
        }

        ProductVariantModel::updateMemory(
            $id,
            $name
        );

        self::redirect($productId);
    }

    private static function memoryDelete(): void
    {
        self::checkPost();

        $id =
            (int) ($_POST['id'] ?? 0);

        $productId =
            (int) ($_POST['product_id'] ?? 0);

        self::checkProduct($productId);

        $memory =
            ProductVariantModel::findMemory($id);

        if (
            !$memory
            ||
            (int) $memory['id_sp']
            !== $productId
        ) {
            http_response_code(404);
            exit('Bộ nhớ sản phẩm không tồn tại');
        }

        ProductVariantModel::deleteMemory($id);

        self::redirect($productId);
    }

    private static function checkPost(): void
    {
        if (
            $_SERVER['REQUEST_METHOD']
            !== 'POST'
        ) {
            http_response_code(405);
            exit('Phương thức không hợp lệ');
        }

        if (
!Csrf::verify(
                $_POST['csrf_token'] ?? null
            )
        ) {
            http_response_code(403);
            exit('CSRF không hợp lệ');
        }
    }

    private static function checkProduct(
        int $productId
    ): void {
        if (
            $productId <= 0
            ||
            !ProductModel::find($productId)
        ) {
            http_response_code(404);
            exit('Sản phẩm không tồn tại');
        }
    }

    private static function redirect(
        int $productId
    ): void {
        header(
            'Location: ?act=admin'
            . '&admin=productVariant'
            . '&product_id='
            . $productId
        );

        exit;
    }
}