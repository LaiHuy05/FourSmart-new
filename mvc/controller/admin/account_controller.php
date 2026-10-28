<?php

final class AdminAccountController
{
    private const RESET_TOKEN_LIFETIME = 900;
    private const RESET_FORM_LIMIT = 20;

    public static function handle(string $action): void
    {
        switch ($action) {
            case 'accountList':
                self::index();
                break;

            case 'accountUpdate':
                self::update();
                break;

            case 'accountResetPassword':
                self::resetPassword();
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
        header('Cache-Control: no-store');
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

        $canResetPassword = (int) $account['id_role'] === 2
            && $id !== (int) ($_SESSION['user_id'] ?? 0);
        $resetToken = $canResetPassword ? self::createResetToken($id) : '';
        $resetError = '';
        $resetOpen = false;
        $notice = $_SESSION['account_password_reset_notice'] ?? [];
        $resetMessage = ($notice['target_id'] ?? null) === $id
            ? 'Cấp lại mật khẩu thành công. Mật khẩu cũ không còn sử dụng được.'
            : '';
        unset($_SESSION['account_password_reset_notice']);

        include __DIR__
            . '/../../view/admin/account/update.php';
    }

    private static function createResetToken(int $targetId): string
    {
        $contexts = $_SESSION['account_password_resets'] ?? [];

        foreach ($contexts as $token => $context) {
            if (($context['expires_at'] ?? 0) <= time()) {
                unset($contexts[$token]);
            }
        }

        // Keep forms in multiple tabs independent, with bounded session data.
        $contexts = array_slice($contexts, -(self::RESET_FORM_LIMIT - 1), null, true);
        $token = bin2hex(random_bytes(32));
        $contexts[$token] = [
            'administrator_id' => (int) $_SESSION['user_id'],
            'target_id' => $targetId,
            'expires_at' => time() + self::RESET_TOKEN_LIFETIME,
        ];
        $_SESSION['account_password_resets'] = $contexts;

        return $token;
    }

    private static function resetPassword(): void
    {
        header('Cache-Control: no-store');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Phương thức không hợp lệ');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('CSRF không hợp lệ');
        }

        $rawId = $_POST['id'] ?? null;
        $id = is_string($rawId)
            ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if ($id === false) {
            http_response_code(400);
            exit('ID tài khoản không hợp lệ');
        }

        $resetToken = $_POST['reset_token'] ?? null;
        $context = is_string($resetToken)
            ? ($_SESSION['account_password_resets'][$resetToken] ?? null)
            : null;

        if (
            !is_array($context)
            || ($context['administrator_id'] ?? null) !== (int) $_SESSION['user_id']
            || ($context['target_id'] ?? null) !== $id
            || ($context['expires_at'] ?? 0) <= time()
        ) {
            http_response_code(403);
            exit('Biểu mẫu cấp lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng mở lại trang cập nhật tài khoản.');
        }

        $account = AccountModel::find($id);

        if (!$account) {
            http_response_code(404);
            exit('Tài khoản không tồn tại');
        }

        if ((int) $account['id_role'] !== 2 || $id === (int) $_SESSION['user_id']) {
            http_response_code(403);
            exit('Chỉ được cấp lại mật khẩu cho tài khoản khách hàng');
        }

        $password = $_POST['new_password'] ?? null;
        $confirmation = $_POST['confirm_password'] ?? null;

        if (!is_string($password) || !is_string($confirmation)) {
            http_response_code(400);
            exit('Dữ liệu mật khẩu không hợp lệ');
        }

        $roles = AccountModel::roles();
        $error = '';
        $resetMessage = '';
        $canResetPassword = true;
        $resetOpen = true;
        $resetError = '';

        if (
            !mb_check_encoding($password, 'UTF-8')
            || str_contains($password, "\0")
            || strlen($password) > 72
            || mb_strlen($password, 'UTF-8') < 12
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password)
            || !preg_match('/[^\p{L}\p{N}\s]/u', $password)
            || password_get_info($password)['algoName'] !== 'unknown'
            || preg_match('/^\$(?:2[abxy]\$|argon2(?:i|id)\$)/', $password) === 1
        ) {
            $resetError = 'Mật khẩu phải có ít nhất 12 ký tự, tối đa 72 byte, gồm chữ hoa A–Z, chữ thường a–z, số và ký tự đặc biệt.';
        } elseif ($password !== $confirmation) {
            $resetError = 'Mật khẩu xác nhận không trùng khớp. Vui lòng nhập lại cả hai trường.';
        }

        if ($resetError !== '') {
            http_response_code(422);
        } else {
            try {
                $updated = AccountModel::resetCustomerPassword($id, $password);
            } catch (Throwable $exception) {
                // Do not log the exception or submitted password values.
                http_response_code(500);
                $updated = false;
                $resetError = 'Chưa thể cấp lại mật khẩu. Vui lòng thử lại sau.';
            }

            if ($resetError === '' && !$updated) {
                http_response_code(409);
                exit('Tài khoản đã thay đổi. Vui lòng mở lại trang cập nhật tài khoản.');
            }

            if ($updated) {
                unset($_SESSION['account_password_resets'][$resetToken]);
                $_SESSION['account_password_reset_notice'] = ['target_id' => $id];

                header('Location: ?act=admin&admin=accountUpdate&id=' . $id . '#password-reset', true, 303);
                exit;
            }
        }

        // Password values are never passed to the view or stored in the session.
        unset($password, $confirmation);
        include __DIR__ . '/../../view/admin/account/update.php';
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
          if (AccountModel::hasRelatedData($id)) {
            http_response_code(409);
        
            exit(
                'Không thể xóa tài khoản vì đang có '
                . 'giỏ hàng, đơn hàng hoặc bình luận'
            );
        }
            AccountModel::delete($id);
        }

        header(
            'Location: ?act=admin&admin=accountList'
        );
        exit;
    }
}
