<?php

declare(strict_types=1);

final class SessionGuard
{
    public static function start(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('zend.exception_ignore_args', '1');
        ini_set('display_errors', '0');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    public static function clear(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        Csrf::token();
    }

    public static function enforce(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }
        try {
            if (!SessionVersionPolicy::required()) {
                return;
            }
            $account = AccountModel::find((int) $_SESSION['user_id']);
            $valid = $account && SessionVersionPolicy::matches($_SESSION, $account);
        } catch (Throwable $error) {
            http_response_code(503);
            exit('Chưa thể kiểm tra phiên đăng nhập. Vui lòng thử lại sau.');
        }
        if (!$valid) {
            self::clear();
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(403);
                exit('Phiên đăng nhập đã hết hiệu lực. Vui lòng đăng nhập lại.');
            }
            header('Location: ?client=login');
            exit;
        }
    }
}
