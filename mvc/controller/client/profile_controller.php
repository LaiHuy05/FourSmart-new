<?php

final class ClientProfileController
{
    public static function handle(string $action): void
    {
        header('Cache-Control: no-store');
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

            case 'profilePasswordChange':
                self::changePassword($userId);
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
        $passwordMessage = !empty($_SESSION['password_change_success'])
            ? 'Đổi mật khẩu thành công.' : '';
        unset($_SESSION['password_change_success']);

        include __DIR__
            . '/../../view/client/profile/profile.php';
    }

    private static function changePassword(int $userId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Phương thức không hợp lệ');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('CSRF không hợp lệ');
        }

        // The authenticated session is the only source of the account ID.
        $account = AccountModel::find($userId);
        if (!$account) {
            $_SESSION = [];
            http_response_code(403);
            exit('Phiên đăng nhập không còn hợp lệ. Vui lòng đăng nhập lại.');
        }

        $currentPassword = $_POST['current_password'] ?? null;
        $newPassword = $_POST['new_password'] ?? null;
        $confirmation = $_POST['confirm_password'] ?? null;
        $error = '';
        $message = '';
        $passwordMessage = '';
        $passwordError = '';
        $status = 422;

        $attempts = $_SESSION['password_change_attempts'] ?? [];
        if (($attempts['user_id'] ?? null) !== $userId
            || ($attempts['started_at'] ?? 0) + 900 <= time()) {
            $attempts = ['user_id' => $userId, 'started_at' => time(), 'failed' => 0];
        }

        if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmation)) {
            $status = 400;
            $passwordError = 'Dữ liệu mật khẩu không hợp lệ. Vui lòng nhập lại các trường.';
        } elseif (($attempts['failed'] ?? 0) >= 5) {
            $status = 429;
            header('Retry-After: ' . max(1, $attempts['started_at'] + 900 - time()));
            $passwordError = 'Bạn đã nhập sai mật khẩu hiện tại quá nhiều lần. Vui lòng thử lại sau ít phút.';
        } elseif ($currentPassword === '' || str_contains($currentPassword, "\0")
            || !mb_check_encoding($currentPassword, 'UTF-8')) {
            $passwordError = 'Vui lòng nhập mật khẩu hiện tại hợp lệ.';
        } elseif (!mb_check_encoding($newPassword, 'UTF-8')
            || str_contains($newPassword, "\0")
            || strlen($newPassword) > 72
            || mb_strlen($newPassword, 'UTF-8') < 12
            || !preg_match('/[A-Z]/', $newPassword)
            || !preg_match('/[a-z]/', $newPassword)
            || !preg_match('/[0-9]/', $newPassword)
            || !preg_match('/[^\p{L}\p{N}\s]/u', $newPassword)) {
            $passwordError = 'Mật khẩu mới phải có ít nhất 12 ký tự, tối đa 72 byte, gồm chữ hoa A–Z, chữ thường a–z, số và ký tự đặc biệt.';
        } elseif ($newPassword !== $confirmation) {
            $passwordError = 'Xác nhận mật khẩu mới không trùng khớp. Vui lòng nhập lại.';
        } elseif ($newPassword === $currentPassword) {
            $passwordError = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
        } else {
            try {
                $changed = AccountModel::changePassword($userId, $currentPassword, $newPassword);
                if ($changed) {
                    if (SessionVersionPolicy::required()) {
                        // Preserve this session; all other sessions retain the old version.
                        $_SESSION['session_version'] = SessionVersionPolicy::accountVersion($account) + 1;
                    }
                    session_regenerate_id(true);
                    unset($_SESSION['csrf_token'], $_SESSION['password_change_attempts']);
                    Csrf::token();
                    $_SESSION['password_change_success'] = true;
                    header('Location: ?client=profile#account-security', true, 303);
                    exit;
                }
                $attempts['failed']++;
                $_SESSION['password_change_attempts'] = $attempts;
                $passwordError = 'Mật khẩu hiện tại không đúng hoặc vừa được thay đổi.';
            } catch (Throwable $exception) {
                // Submitted passwords and exception arguments must not reach logs.
                $status = 500;
                $passwordError = 'Chưa thể đổi mật khẩu. Vui lòng thử lại sau.';
            }
        }

        http_response_code($status);
        unset($currentPassword, $newPassword, $confirmation);
        unset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password']);
        include __DIR__ . '/../../view/client/profile/profile.php';
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
