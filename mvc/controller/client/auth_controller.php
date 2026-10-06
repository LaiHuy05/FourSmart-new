<?php

final class ClientAuthController
{
    public static function handle(string $action): void
    {
        switch ($action) {
            case 'login':
                self::login();
                break;
            case 'register':
                self::register();
                break;
            case 'logout':
                self::logout();
                break;
            default:
                http_response_code(404);
                exit('Chức năng tài khoản không tồn tại');
        }
    }

    private static function login(): void
    {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                exit('CSRF không hợp lệ');
            }

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $error = 'Vui lòng nhập tài khoản và mật khẩu';
            } else {
                $account = AccountModel::findByUsername(
                    $username
                );

                if (
                    !$account
                    || !AccountModel::verifyPassword(
                        $account,
                        $password
                    )
                ) {
                    $error = 'Tài khoản hoặc mật khẩu không đúng';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id']
                        = (int) $account['tk_id'];

                    $_SESSION['username']
                        = $account['tk_user'];

                    $_SESSION['role_id']
                        = (int) $account['id_role'];

                    if ((int) $account['id_role'] === 1) {
                        header('Location: ?act=admin');
                    } else {
                        header('Location: ?client=home');
                    }

                    exit;
                }
            }
        }

        include __DIR__
            . '/../../view/client/login/login.php';
    }

    private static function register(): void
    {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                exit('CSRF không hợp lệ');
            }

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (
                $username === ''
                || $password === ''
                || $email === ''
                || $address === ''
            ) {
                $error = 'Vui lòng nhập đầy đủ thông tin';
            } elseif (strlen($password) < 6) {
                $error = 'Mật khẩu phải có ít nhất 6 ký tự';
            } elseif (
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                $error = 'Email không hợp lệ';
            } elseif (
                AccountModel::usernameExists($username)
            ) {
                $error = 'Tên đăng nhập đã tồn tại';
            } elseif (
                AccountModel::emailExists($email)
            ) {
                $error = 'Email đã tồn tại';
            } else {
                AccountModel::create(
                    $username,
                    $password,
                    $email,
                    $address,
                    2
                );

                header('Location: ?client=login');
                exit;
            }
        }

        include __DIR__
            . '/../../view/client/login/register.php';
    }
    private static function logout(): void
  {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: ?client=login');
    exit;
  }
}