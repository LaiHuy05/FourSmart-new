<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/support/password_recovery_fixture.php';
$mariaDB = in_array('--mariadb', $argv, true);
if ($mariaDB) require_once __DIR__ . '/support/guarded_mariadb.php';

function recoveryFields(string $html, string $form = ''): array
{
    $document = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors(); libxml_use_internal_errors($old);
    $fields = [];
    $xpath = new DOMXPath($document);
    foreach ($xpath->query($form === '' ? '//input[@name]' : '//form[@id="' . $form . '"]//input[@name]') as $input) {
        $fields[$input->getAttribute('name')] = $input->getAttribute('value');
    }
    return $fields;
}

final class RecoveryBrowser
{
    private CurlHandle $curl;

    public function __construct(private string $url)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
            CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10, CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [parse_url($url, PHP_URL_HOST) . ':' . parse_url($url, PHP_URL_PORT) . ':127.0.0.1']]);
    }

    public function request(string $path, string $method = 'GET', array $fields = []): array
    {
        curl_setopt($this->curl, CURLOPT_URL, $this->url . '/' . $path);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
        curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        if ($method === 'POST') curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($fields));
        $response = curl_exec($this->curl);
        recoveryAssert(is_string($response), 'Local HTTP failed');
        $size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        return ['status' => curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE),
            'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
    }

    public function login(string $user = 'customer', string $password = 'Original-Password!2026'): array
    {
        $page = $this->request('?client=login');
        return $this->request('?client=login', 'POST', ['csrf_token' => recoveryFields($page['body'])['csrf_token'],
            'username' => $user, 'password' => $password]);
    }
}

$directory = recoveryDirectory();
if ($mariaDB) {
    $privatePath = mariaConfigPath($argv);
    $private = mariaPrivateConfig($privatePath);
    $db = new GuardedMariaDB($private);
    $db->assertFixture();
    foreach ([1 => 'admin', 2 => 'customer', 3 => 'other'] as $id => $name) {
        $db->prepare('DELETE FROM password_reset_jobs WHERE email = ?')->execute([$name . '@example.test']);
        $db->prepare('DELETE FROM account WHERE tk_id = ?')->execute([$id]);
        $db->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role)
            VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $name, password_hash('Original-Password!2026', PASSWORD_BCRYPT),
                $name . '@example.test', 'Synthetic HTTP fixture', $id === 1 ? 1 : 2]);
    }
} else {
    $db = new PDO('sqlite:' . $directory . '/accounts.sqlite');
    recoveryFixture($db);
}
$model = new PasswordRecoveryModel($db);
$tests = new RecoveryTests();
$server = null;
$socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
$address = stream_socket_get_name($socket, false); fclose($socket);
$port = substr(strrchr($address, ':'), 1);
$url = 'http://localhost:' . $port;
$key = random_bytes(32);
$config = ['key' => $key, 'url' => $url . '/index.php'];
$now = fn() => new DateTimeImmutable('now', new DateTimeZone('UTC'));
$environment = getenv();
$environment['FOURSMART_RECOVERY_TEST_DIRECTORY'] = $directory;
$environment['FOURSMART_PASSWORD_RECOVERY_ENABLED'] = '1';
$environment['FOURSMART_RECOVERY_HMAC_KEY'] = base64_encode($key);
$environment['FOURSMART_APP_URL'] = $config['url'];
if ($mariaDB) $environment['FOURSMART_MARIADB_PRIVATE_CONFIG'] = $privatePath;
$issue = function () use ($model, $key, $now, $db): string {
    $db->exec('DELETE FROM password_reset_jobs');
    $db->exec('DELETE FROM password_reset_rate_limits');
    $model->request('customer@example.test', 'fixture-ip', $key, $now());
    $issued = $model->issue($model->claim($now()), $now());
    return $issued['token'];
};
try {
    $router = $mariaDB ? 'password_recovery_mariadb_router.php' : 'password_recovery_router.php';
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', dirname(__DIR__), __DIR__ . '/support/' . $router],
        [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']],
        $pipes, dirname(__DIR__), $environment, ['bypass_shell' => true]);
    recoveryAssert(is_resource($server), 'Server did not start'); fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $code, $message, 0.1);
        if (is_resource($probe)) { fclose($probe); $ready = true; break; }
        usleep(50000);
    }
    recoveryAssert($ready, 'Server not ready');
    $browser = new RecoveryBrowser($url);
    $tests->test('localhost page preserves auth layout and sets HttpOnly SameSite cookies', function () use ($browser): void {
        $page = $browser->request('?client=forgotPassword');
        recoveryAssert($page['status'] === 200 && str_contains($page['body'], 'auth-intro') && str_contains($page['body'], 'account.css'), 'Layout missing');
        recoveryAssert(stripos($page['headers'], 'HttpOnly') !== false && stripos($page['headers'], 'SameSite=Lax') !== false, 'Cookie flags missing');
        recoveryAssert(stripos($page['headers'], 'no-store') !== false && stripos($page['headers'], 'no-referrer') !== false, 'Cache/referrer policy missing');
    });
    $tests->test('known, unknown and admin requests receive the same redirect and message', function () use ($browser): void {
        $statuses = [];
        foreach (['customer@example.test', 'missing@example.test', 'admin@example.test'] as $email) {
            $fields = recoveryFields($browser->request('?client=forgotPassword')['body']);
            $response = $browser->request('?client=forgotPassword', 'POST', ['csrf_token' => $fields['csrf_token'], 'email' => $email]);
            $statuses[] = $response['status'];
            recoveryAssert(str_contains($response['headers'], 'Location: ?client=forgotPassword&submitted=1'), 'Existence-dependent redirect');
        }
        recoveryAssert($statuses === [303, 303, 303], 'Existence-dependent status');
        recoveryAssert(str_contains($browser->request('?client=forgotPassword&submitted=1')['body'], 'Nếu email phù hợp'), 'Generic message missing');
    });
    $tests->test('invalid CSRF, method and malformed email do not enqueue work', function () use ($browser, $db): void {
        $before = (int) $db->query('SELECT COUNT(*) FROM password_reset_jobs')->fetchColumn();
        recoveryAssert($browser->request('?client=forgotPassword', 'POST', ['csrf_token' => 'wrong', 'email' => 'customer@example.test'])['status'] === 403, 'CSRF accepted');
        recoveryAssert($browser->request('?client=forgotPassword', 'DELETE')['status'] === 405, 'Method accepted');
        $fields = recoveryFields($browser->request('?client=forgotPassword')['body']);
        recoveryAssert($browser->request('?client=forgotPassword', 'POST', ['csrf_token' => $fields['csrf_token'], 'email' => ['bad']])['status'] === 422, 'Malformed email accepted');
        recoveryAssert((int) $db->query('SELECT COUNT(*) FROM password_reset_jobs')->fetchColumn() === $before, 'Rejected request created job');
    });
    $token = $issue();
    $tests->test('GET never consumes token; CSRF failure cannot reset it', function () use ($browser, $model, $token, $now): void {
        $page = $browser->request('?client=resetPassword');
        recoveryAssert($page['status'] === 200 && str_contains($page['body'], 'client-password-recovery.js'), 'Reset form missing');
        recoveryAssert($browser->request('?client=resetPassword', 'POST', ['token' => $token, 'new_password' => 'New-Password!2026'])['status'] === 403, 'Reset CSRF accepted');
        recoveryAssert($model->valid(hash('sha256', $token, true), $now()), 'GET/CSRF consumed token');
    });
    $tests->test('password error preserves only hashed context, never echoes secrets', function () use ($browser, $token): void {
        $fields = recoveryFields($browser->request('?client=resetPassword')['body']);
        $response = $browser->request('?client=resetPassword', 'POST', ['csrf_token' => $fields['csrf_token'],
            'token' => $token, 'new_password' => 'short-secret', 'confirm_password' => 'short-secret']);
        recoveryAssert($response['status'] === 422 && str_contains($response['body'], 'data-has-context="1"'), 'Retry context missing');
        recoveryAssert(!str_contains($response['body'], $token) && !str_contains($response['body'], 'short-secret'), 'Secret echoed');
    });
    $first = new RecoveryBrowser($url); $second = new RecoveryBrowser($url);
    $first->login(); $second->login();
    $tests->test('reset revokes both logged-in devices and requires a fresh login', function () use ($browser, $first, $second, $token, $db): void {
        $fields = recoveryFields($browser->request('?client=resetPassword')['body']);
        $response = $browser->request('?client=resetPassword', 'POST', ['csrf_token' => $fields['csrf_token'],
            'token' => '', 'new_password' => 'Recovered-Password!2026', 'confirm_password' => 'Recovered-Password!2026']);
        recoveryAssert($response['status'] === 303 && str_contains($response['headers'], '?client=login'), 'Reset failed');
        recoveryAssert((int) $db->query('SELECT session_version FROM account WHERE tk_id = 2')->fetchColumn() === 1, 'Version not bumped');
        recoveryAssert($first->request('?client=profile')['status'] === 302 && $second->request('?client=profile')['status'] === 302, 'Old device remained authenticated');
        recoveryAssert($browser->request('?client=profile')['status'] === 302, 'Reset auto logged in');
        recoveryAssert($browser->login('customer', 'Original-Password!2026')['status'] === 200, 'Old password accepted');
        recoveryAssert($browser->login('customer', 'Recovered-Password!2026')['status'] === 302, 'New password rejected');
    });
    $tests->test('replay in another browser is rejected', function () use ($token, $url): void {
        $fresh = new RecoveryBrowser($url);
        $fields = recoveryFields($fresh->request('?client=resetPassword')['body']);
        recoveryAssert($fresh->request('?client=resetPassword', 'POST', ['csrf_token' => $fields['csrf_token'], 'token' => $token,
            'new_password' => 'Replay-Password!2026', 'confirm_password' => 'Replay-Password!2026'])['status'] === 422, 'Replay accepted');
    });
    $tests->test('phase 1 change keeps current device and revokes other devices', function () use ($browser, $url, $db): void {
        $other = new RecoveryBrowser($url);
        recoveryAssert($other->login('customer', 'Recovered-Password!2026')['status'] === 302, 'Other device login failed');
        $fields = recoveryFields($browser->request('?client=profile')['body'], 'profile-password-form');
        $response = $browser->request('?client=profilePasswordChange', 'POST', array_merge($fields, [
            'current_password' => 'Recovered-Password!2026', 'new_password' => 'Changed-Password!2026', 'confirm_password' => 'Changed-Password!2026']));
        recoveryAssert($response['status'] === 303, 'Phase 1 change failed');
        recoveryAssert((int) $db->query('SELECT session_version FROM account WHERE tk_id = 2')->fetchColumn() === 2, 'Phase 1 version not bumped');
        recoveryAssert($browser->request('?client=profile')['status'] === 200, 'Current device revoked');
        recoveryAssert($other->request('?client=profile')['status'] === 302, 'Other device not revoked');
    });
    $tests->test('admin login and protected route still work', function () use ($url): void {
        $admin = new RecoveryBrowser($url);
        $response = $admin->login('admin');
        recoveryAssert($response['status'] === 302 && str_contains($response['headers'], '?act=admin'), 'Admin redirect changed');
        // Account listing uses only the isolated fixture; no dashboard/catalog queries.
        recoveryAssert($admin->request('?act=admin&admin=accountList')['status'] === 200, 'Admin account route failed');
    });
    if (in_array('--mailpit', $argv, true)) {
        $tests->test('real PHPMailer worker delivery is visible in Mailpit with localhost fragment URL', function () use ($db, $model, $config, $now, $key): void {
            $db->exec('DELETE FROM password_reset_jobs');
            $db->exec('DELETE FROM password_reset_rate_limits');
            $model->request('customer@example.test', 'mailpit-ip', $key, $now());
            $service = new PasswordRecoveryService($model, $config);
            recoveryAssert($service->processOne() === 'sent', 'SMTP delivery failed');
            $hash = $db->query('SELECT token_hash FROM password_reset_tokens WHERE account_id = 2')->fetchColumn();
            $client = curl_init('http://127.0.0.1:8025/api/v1/messages?limit=20');
            curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 5]);
            $list = json_decode(curl_exec($client), true, 32, JSON_THROW_ON_ERROR);
            $found = false;
            foreach ($list['messages'] as $message) {
                if (($message['To'][0]['Address'] ?? '') !== 'customer@example.test') continue;
                curl_setopt($client, CURLOPT_URL, 'http://127.0.0.1:8025/api/v1/message/' . rawurlencode($message['ID']));
                $mail = json_decode(curl_exec($client), true, 32, JSON_THROW_ON_ERROR);
                if (preg_match('/#token=([0-9a-f]{64})/', $mail['Text'] ?? '', $match)
                    && hash_equals($hash, hash('sha256', $match[1], true))) {
                    recoveryAssert(str_contains($mail['Text'], $config['url'] . '?client=resetPassword#token='), 'Wrong application hostname');
                    recoveryAssert($model->valid(hash('sha256', $match[1], true), $now()), 'Email token invalid');
                    echo '  Mailpit message ID: ', $message['ID'], PHP_EOL;
                    $found = true; break;
                }
            }
            recoveryAssert($found, 'Exact sent token not found in Mailpit');
        });
    } else {
        echo 'NOT TESTED Mailpit delivery (run with --mailpit)', PHP_EOL;
    }
    $tests->test('server log contains no submitted passwords, raw tokens or private key', function () use ($directory, $token, $key): void {
        $log = file_get_contents($directory . '/server.log');
        foreach ([$token, base64_encode($key), 'Recovered-Password!2026', 'Changed-Password!2026', 'short-secret'] as $secret) {
            recoveryAssert(!str_contains($log, $secret), 'Secret leaked to HTTP log');
        }
    });
    if (in_array('--serve', $argv, true)) {
        echo $mariaDB ? 'duan1_test preview: ' : 'SQLite-only preview: ', $url, '/?client=forgotPassword', PHP_EOL;
        echo 'Press Enter to stop and remove the temporary fixture.', PHP_EOL;
        fgets(STDIN);
    }
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($mariaDB) {
        foreach ([1 => 'admin', 2 => 'customer', 3 => 'other'] as $id => $name) {
            $db->prepare('DELETE FROM password_reset_jobs WHERE email = ?')->execute([$name . '@example.test']);
            $db->prepare('DELETE FROM account WHERE tk_id = ?')->execute([$id]);
        }
        $db->exec('DELETE FROM password_reset_rate_limits');
    }
    unset($issue);
    $model = null; $db = null;
    recoveryRemoveDirectory($directory);
}
$tests->finish();
