<?php

function require_admin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ?client=login');
        exit;
    }

    if (
        !isset($_SESSION['role_id'])
        || (int) $_SESSION['role_id'] !== 1
    ) {
        http_response_code(403);

        exit('Bạn không có quyền truy cập trang quản trị');
    }
}