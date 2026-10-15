<?php

final class ClientProductController
{
    public static function handle(string $action): void
    {
        switch ($action) {

            case 'productDetail':
                self::detail();
                break;

            case 'productSearch':
                self::search();
                break;

            default:
                http_response_code(404);
                exit('Chức năng sản phẩm không tồn tại');
        }
    }

    private static function detail(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(404);
            exit('Sản phẩm không tồn tại');
        }

        $product = ProductModel::find($id);

        if (!$product) {
            http_response_code(404);
            exit('Sản phẩm không tồn tại');
        }

        $colors =
            ProductVariantModel::colorsByProduct(
                $id
            );

        $memories =
            ProductVariantModel::memoriesByProduct(
                $id
            );

        $comments =
            CommentModel::byProduct($id);

        include __DIR__
            . '/../../view/client/productDetail.php';
    }

    private static function search(): void
    {
        $keyword =
            trim($_GET['q'] ?? '');

        $products =
            ProductModel::search($keyword);

        include __DIR__
            . '/../../view/client/productSearch.php';
    }
}