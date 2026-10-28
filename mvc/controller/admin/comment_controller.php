<?php

final class AdminCommentController
{
    public static function handle(string $action): void
    {
        if ($action === 'commentList') {
            $comments = CommentModel::all();

            include __DIR__
                . '/../../view/admin/comment/list.php';

            return;
        }

        if ($action === 'commentDelete') {

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
                CommentModel::delete($id);
            }

            header(
                'Location: ?act=admin&admin=commentList'
            );
            exit;
        }

        http_response_code(404);
        exit('Chức năng bình luận không tồn tại');
    }
}