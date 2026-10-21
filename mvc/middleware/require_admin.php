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

    // A session must not retain admin access after the account is removed
    // or its role is changed in the database.
    $administrator = AccountModel::find((int) $_SESSION['user_id']);

    if (!$administrator || (int) $administrator['id_role'] !== 1) {
        unset($_SESSION['role_id'], $_SESSION['account_password_resets']);
        http_response_code(403);
        exit('Quyền quản trị của tài khoản không còn hiệu lực');
    }
}
