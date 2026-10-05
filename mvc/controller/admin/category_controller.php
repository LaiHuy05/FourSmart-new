<?php

final class AdminCategoryController
{
    public static function handle(string $action): void
    {
        switch ($action) {
            case 'categoryList':
                self::index();
                break;

            case 'categoryAdd':
                self::add();
                break;

            case 'categoryUpdate':
                self::update();
                break;

            case 'categoryDelete':
                self::delete();
                break;

            default:
                http_response_code(404);
                echo 'Chức năng danh mục không tồn tại';
        }
    }

    private static function index(): void
    {
        $categories = CategoryModel::all();

        include __DIR__
            . '/../../view/admin/category/list.php';
    }

    private static function add(): void
    {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                exit('CSRF không hợp lệ');
            }

            $name = trim($_POST['name'] ?? '');

            if ($name === '') {
                $error = 'Tên danh mục không được để trống';
            } else {
                CategoryModel::create($name);

                header(
                    'Location: ?act=admin&admin=categoryList'
                );
                exit;
            }
        }

        include __DIR__
            . '/../../view/admin/category/add.php';
    }

    private static function update(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        $category = CategoryModel::find($id);

        if (!$category) {
            http_response_code(404);
            exit('Danh mục không tồn tại');
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                exit('CSRF không hợp lệ');
            }

            $name = trim($_POST['name'] ?? '');

            if ($name === '') {
                $error = 'Tên danh mục không được để trống';
            } else {
                CategoryModel::update(
                    $id,
                    $name
                );

                header(
                    'Location: ?act=admin&admin=categoryList'
                );
                exit;
            }
        }

        include __DIR__
            . '/../../view/admin/category/update.php';
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
            CategoryModel::delete($id);
        }

        header(
            'Location: ?act=admin&admin=categoryList'
        );
        exit;
    }
}