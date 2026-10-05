<?php

final class ClientProductController
{
    public static function handle(string $action): void
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

        $colors = ProductVariantModel::colorsByProduct($id);
        $memories = ProductVariantModel::memoriesByProduct($id);

        include __DIR__ . '/../../view/client/productDetail.php';
    }
}