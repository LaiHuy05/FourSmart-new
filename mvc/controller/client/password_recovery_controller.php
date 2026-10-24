<?php

declare(strict_types=1);

final class ClientPasswordRecoveryController
{
    public static function handle(string $action): void
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        header('X-Content-Type-Options: nosniff');
        $error = '';
        $submitted = isset($_GET['submitted']);
        $hasContext = false;
        $view = $action === 'forgotPassword' ? 'forgot_password' : 'reset_password';
        $method = $_SERVER['REQUEST_METHOD'];
        if (!in_array($method, ['GET', 'POST'], true)) {
            header('Allow: GET, POST');
            http_response_code(405);
            exit('Phương thức không hợp lệ.');
        }
        if ($method === 'POST' && !Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('CSRF không hợp lệ.');
        }
        try {
            $config = PasswordRecoveryConfig::load();
            $service = new PasswordRecoveryService(new PasswordRecoveryModel(Database::connection()), $config);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($action === 'forgotPassword') {
                if ($method === 'POST') {
                    $email = PasswordRecoveryService::email($_POST['email'] ?? null);
                    if ($email === null) {
                        http_response_code(422);
                        $error = 'Vui lòng nhập địa chỉ email hợp lệ, tối đa 255 byte.';
                    } else {
                        $service->request($email, $ip);
                        header('Location: ?client=forgotPassword&submitted=1', true, 303);
                        exit;
                    }
                }
            } else {
                if (!$service->allowReset($ip)) {
                    http_response_code(429);
                    header('Retry-After: 900');
                    $error = 'Bạn đã thử quá nhiều lần. Vui lòng quay lại sau 15 phút.';
                } else {
                    $context = $_SESSION['password_recovery_hash'] ?? null;
                    $hash = is_string($context) && preg_match('/\A[0-9a-f]{64}\z/D', $context) ? hex2bin($context) : null;
                    if ($method === 'POST') {
                        $raw = $_POST['token'] ?? null;
                        if (is_string($raw) && preg_match('/\A[0-9a-f]{64}\z/D', $raw)) {
                            $hash = hash('sha256', $raw, true);
                        } elseif ($raw !== null && $raw !== '') {
                            $hash = null;
                        }
                    }
                    $hasContext = is_string($hash) && $service->valid($hash);
                    if ($hasContext) {
                        $_SESSION['password_recovery_hash'] = bin2hex($hash);
                    } else {
                        unset($_SESSION['password_recovery_hash']);
                    }
                    if ($method === 'POST') {
                        if (!$hasContext) {
                            http_response_code(422);
                            $error = 'Liên kết không hợp lệ, đã dùng hoặc đã hết hạn. Vui lòng yêu cầu liên kết mới.';
                        } elseif (!PasswordRecoveryService::validPassword($_POST['new_password'] ?? null)) {
                            http_response_code(422);
                            $error = 'Mật khẩu cần ít nhất 12 ký tự, tối đa 72 byte, có chữ hoa, chữ thường, số và ký tự đặc biệt.';
                        } elseif (!is_string($_POST['confirm_password'] ?? null)
                            || $_POST['new_password'] !== $_POST['confirm_password']) {
                            http_response_code(422);
                            $error = 'Xác nhận mật khẩu mới không trùng khớp.';
                        } elseif ($service->reset($hash, $_POST['new_password'], $_POST['confirm_password'])) {
                            SessionGuard::clear();
                            $_SESSION['password_reset_success'] = true;
                            header('Location: ?client=login', true, 303);
                            exit;
                        } else {
                            http_response_code(422);
                            $error = 'Không thể dùng liên kết này hoặc mật khẩu trùng mật khẩu hiện tại. Vui lòng kiểm tra lại.';
                            $hasContext = $service->valid($hash);
                        }
                    }
                }
            }
        } catch (Throwable $exception) {
            http_response_code(503);
            $error = 'Chức năng khôi phục mật khẩu chưa sẵn sàng. Vui lòng thử lại sau.';
        }
        unset($_POST['token'], $_POST['new_password'], $_POST['confirm_password'], $raw, $hash, $config);
        include __DIR__ . '/../../view/client/login/' . $view . '.php';
    }
}
