<?php

final class ClientCartController
{
    public static function handle(string $action): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            http_response_code(401);
            exit('Vui lòng đăng nhập');
        }

        switch ($action) {

            case 'cart':
                self::show($userId);
                break;

            case 'cartAdd':
                self::add($userId);
                break;

            case 'cartIncrease':
                self::increase($userId);
                break;

            case 'cartDecrease':
                self::decrease($userId);
                break;

            case 'cartDelete':
                self::delete($userId);
                break;

            default:
                http_response_code(404);
                exit('Chức năng giỏ hàng không tồn tại');
        }
    }

    private static function show(int $userId): void
    {
        $cart = CartModel::findByUser($userId);

        if (!$cart) {
            CartModel::create($userId);
            $cart = CartModel::findByUser($userId);
        }

        $cartDetails = [];

        if ($cart) {
            $cartDetails = CartModel::detailsByCart(
                (int) $cart['gh_id']
            );
        }

        $products = ProductModel::all();

        include __DIR__
            . '/../../view/client/cart.php';
    }

    private static function add(int $userId): void
    {
        self::checkPost();

        $productId = (int) ($_POST['product_id'] ?? 0);
        $memory = trim($_POST['memory'] ?? '');
        $color = trim($_POST['color'] ?? '');

        if (
            $productId <= 0
            || $memory === ''
            || $color === ''
        ) {
            http_response_code(422);
            exit('Dữ liệu sản phẩm không hợp lệ');
        }

        if (!ProductModel::find($productId)) {
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

$validColor = false;

foreach ($colors as $item) {
    if ($item['pc_name'] === $color) {
        $validColor = true;
        break;
    }
}

$validMemory = false;

foreach ($memories as $item) {
    if ($item['pm_name'] === $memory) {
        $validMemory = true;
        break;
    }
}

if (!$validColor || !$validMemory) {
    http_response_code(422);
    exit('Biến thể sản phẩm không hợp lệ');
}

        $cart = CartModel::findByUser($userId);

        if (!$cart) {
            CartModel::create($userId);
            $cart = CartModel::findByUser($userId);
        }

        CartModel::addItem(
            (int) $cart['gh_id'],
            $productId,
            $memory,
            $color
        );

        self::redirectCart();
    }

    private static function increase(int $userId): void
    {
        self::checkPost();

        $detailId = (int) ($_POST['detail_id'] ?? 0);

        self::checkOwnership(
            $detailId,
            $userId
        );

        CartModel::increaseQuantity($detailId);

        self::redirectCart();
    }

    private static function decrease(int $userId): void
    {
        self::checkPost();

        $detailId = (int) ($_POST['detail_id'] ?? 0);

        self::checkOwnership(
            $detailId,
            $userId
        );

        CartModel::decreaseQuantity($detailId);
self::redirectCart();
    }

    private static function delete(int $userId): void
    {
        self::checkPost();

        $detailId = (int) ($_POST['detail_id'] ?? 0);

        self::checkOwnership(
            $detailId,
            $userId
        );

        CartModel::deleteItem($detailId);

        self::redirectCart();
    }

    private static function checkPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Phương thức không hợp lệ');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('CSRF không hợp lệ');
        }
    }

    private static function checkOwnership(
        int $detailId,
        int $userId
    ): void {
        if (
            $detailId <= 0
            || !CartModel::detailBelongsToUser(
                $detailId,
                $userId
            )
        ) {
            http_response_code(403);
            exit('Bạn không có quyền thao tác');
        }
    }

    private static function redirectCart(): void
    {
        header('Location: ?client=cart');
        exit;
    }
}