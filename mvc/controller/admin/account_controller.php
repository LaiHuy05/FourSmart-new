<?php

final class AdminAccountController
{
    public static function handle(string $action): void
    {
        switch ($action) {
            case 'accountList':
                self::index();
                break;

            case 'accountUpdate':
                self::update();
                break;

            case 'accountDelete':
                self::delete();
                break;

            default:
                http_response_code(404);
                exit('Chức năng tài khoản không tồn tại');
        }
    }

    private static function index(): void
    {
        $accounts = AccountModel::all();

        include __DIR__
            . '/../../view/admin/account/list.php';
    }

    private static function update(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        $account = AccountModel::find($id);

        if (!$account) {
            http_response_code(404);
            exit('Tài khoản không tồn tại');
        }

        $roles = AccountModel::roles();
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                exit('CSRF không hợp lệ');
            }

            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $roleId = (int) ($_POST['role_id'] ?? 0);

            if (
                $username === ''
                || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || $address === ''
                || $roleId <= 0
            ) {
                $error = 'Thông tin tài khoản không hợp lệ';
            } elseif (
                AccountModel::usernameExistsForOtherUser(
                    $username,
                    $id
                )
            ) {
                $error = 'Tên đăng nhập đã tồn tại';
            } elseif (
                AccountModel::emailExistsForOtherUser(
                    $email,
                    $id
                )
            ) {
                $error = 'Email đã tồn tại';
            } else {
                AccountModel::updateAdmin(
                    $id,
                    $username,
                    $email,
                    $address,
                    $roleId
                );

                header(
                    'Location: ?act=admin&admin=accountList'
                );
                exit;
            }
        }

        include __DIR__
            . '/../../view/admin/account/update.php';
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

        // Không cho Admin tự xóa chính mình.
        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            http_response_code(403);
            exit('Không thể tự xóa tài khoản đang đăng nhập');
        }

        if ($id > 0) {
            AccountModel::delete($id);
        }

        header(
            'Location: ?act=admin&admin=accountList'
        );
        exit;
    }
}