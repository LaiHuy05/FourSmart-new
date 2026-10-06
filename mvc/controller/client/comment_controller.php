<?php

final class ClientCommentController
{
    public static function handle(string $action): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            header('Location: ?client=login');
            exit;
        }

        if ($action !== 'commentAdd') {
            http_response_code(404);
            exit('Chức năng bình luận không tồn tại');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Phương thức không hợp lệ');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('CSRF không hợp lệ');
        }

        $productId = (int) ($_POST['product_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if (
            $productId <= 0
            || $content === ''
        ) {
            http_response_code(422);
            exit('Bình luận không hợp lệ');
        }

        if (!ProductModel::find($productId)) {
            http_response_code(404);
            exit('Sản phẩm không tồn tại');
        }

        CommentModel::create(
            $content,
            $userId,
            $productId
        );

        header(
            'Location: ?client=productDetail&id='
            . $productId
        );
        exit;
    }
}