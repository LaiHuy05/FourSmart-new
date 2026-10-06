<?php

final class ClientProfileController
{
    public static function handle(string $action): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            header('Location: ?client=login');
            exit;
        }

        switch ($action) {
            case 'profile':
                self::show($userId);
                break;

            case 'profileUpdate':
                self::update($userId);
                break;

            default:
                http_response_code(404);
                exit('Chức năng hồ sơ không tồn tại');
        }
    }

    private static function show(int $userId): void
    {
        $account = AccountModel::find($userId);

        if (!$account) {
            http_response_code(404);
            exit('Tài khoản không tồn tại');
        }

        $error = '';
        $message = '';

        include __DIR__
            . '/../../view/client/profile/profile.php';
    }

    private static function update(int $userId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Phương thức không hợp lệ');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('CSRF không hợp lệ');
        }

        $account = AccountModel::find($userId);

        if (!$account) {
            http_response_code(404);
            exit('Tài khoản không tồn tại');
        }

        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $error = '';
        $message = '';

        if (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $error = 'Email không hợp lệ';
        } elseif (
            AccountModel::emailExistsForOtherUser(
                $email,
                $userId
            )
        ) {
            $error = 'Email đã được tài khoản khác sử dụng';
        } elseif ($address === '') {
            $error = 'Địa chỉ không được để trống';
        } else {
            AccountModel::updateProfile(
                $userId,
                $email,
                $address
            );

            $account = AccountModel::find($userId);

            $message = 'Cập nhật hồ sơ thành công';
        }

        include __DIR__
            . '/../../view/client/profile/profile.php';
    }
}