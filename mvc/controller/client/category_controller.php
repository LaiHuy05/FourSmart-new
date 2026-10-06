<?php

final class ClientCategoryController
{
    public static function handle(string $action): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(404);
            exit('Danh mục không tồn tại');
        }

        $category = CategoryModel::find($id);

        if (!$category) {
            http_response_code(404);
            exit('Danh mục không tồn tại');
        }

        $products = array_filter(
            ProductModel::all(),
            static function (array $product) use ($id): bool {
                return (int) $product['id_dm'] === $id;
            }
        );

        include __DIR__
            . '/../../view/client/category.php';
    }
}