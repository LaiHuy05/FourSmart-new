<?php

// XAMPP PHP CLI only. The real application database is never opened.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('zend.exception_ignore_args', '1');
// Isolated fixtures include the versioned schema. Never infer legacy from absence.
if (getenv('FOURSMART_SESSION_VERSION_MODE') === false) {
    putenv('FOURSMART_SESSION_VERSION_MODE=required');
}

foreach (['pdo_sqlite', 'curl', 'dom', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, 'Missing PHP extension: ' . $extension . PHP_EOL);
        exit(1);
    }
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../model/AccountModel.php';
require_once __DIR__ . '/support/password_recovery_fixture.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function formInputs(string $html, ?string $formId = null): array
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($document);
    $query = $formId === null ? '//input[@name]' : '//form[@id="' . $formId . '"]//input[@name]';
    $fields = [];
    foreach ($xpath->query($query) as $input) {
        if (!($input instanceof DOMElement)) {
            continue;
        }

        $fields[$input->getAttribute('name')] = $input->getAttribute('value');
    }
    return $fields;
}

final class PasswordTestBrowser
{
    private CurlHandle $handle;

    public function __construct(private string $baseUrl)
    {
        $this->handle = curl_init();
        curl_setopt_array($this->handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_PROXY => '',
        ]);
    }

    public function request(string $url, string $method = 'GET', array $fields = []): array
    {
        curl_setopt($this->handle, CURLOPT_URL, $this->baseUrl . '/' . $url);
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, null);
        curl_setopt($this->handle, CURLOPT_HTTPGET, true);
        curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $method);
        if ($method !== 'GET') {
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $response = curl_exec($this->handle);
        check(is_string($response), 'Local test request failed');
        $size = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
        return [
            'status' => curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE),
            'headers' => substr($response, 0, $size),
            'body' => substr($response, $size),
        ];
    }

    public function login(string $username = 'customer', $password = 'Original-Password!2026'): array
    {
        $page = $this->request('?client=login');
        return $this->request('?client=login', 'POST', [
            'csrf_token' => formInputs($page['body'])['csrf_token'] ?? '',
            'username' => $username,
            'password' => $password,
        ]);
    }

    public function fields(): array
    {
        $response = $this->request('?client=profile');
        check($response['status'] === 200, 'Profile did not load');
        $fields = formInputs($response['body'], 'profile-password-form');
        check(isset($fields['csrf_token'], $fields['current_password'], $fields['new_password'], $fields['confirm_password']), 'Change password form missing');
        return $fields;
    }

    public function sessionId(): string
    {
        foreach (curl_getinfo($this->handle, CURLINFO_COOKIELIST) as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'PHPSESSID') {
                return $parts[6];
            }
        }
        throw new RuntimeException('Test session missing');
    }

    public function useSessionId(string $id): void
    {
        curl_setopt($this->handle, CURLOPT_COOKIE, 'PHPSESSID=' . $id);
    }
}

$directory = sys_get_temp_dir() . '/foursmart-client-password-' . bin2hex(random_bytes(8));
$server = null;
$passed = 0;
$failed = 0;
$test = function (string $name, callable $run) use (&$passed, &$failed): void {
    try {
        $run();
        $passed++;
        echo 'PASS ', $name, PHP_EOL;
    } catch (Throwable $error) {
        $failed++;
        // Assertion messages contain no submitted values or database exceptions.
        echo 'FAIL ', $name, ': ', $error->getMessage(), PHP_EOL;
    }
};

try {
    check(mkdir($directory, 0700), 'Cannot create isolated test directory');
    check(mkdir($directory . '/sessions', 0700), 'Cannot create isolated sessions');
    file_put_contents($directory . '/test-only.marker', 'Client password SQLite fixture');
    $database = new PDO('sqlite:' . $directory . '/accounts.sqlite');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $database->exec('CREATE TABLE account (tk_id INTEGER PRIMARY KEY, tk_user TEXT UNIQUE NOT NULL, tk_password TEXT NOT NULL, tk_email TEXT UNIQUE NOT NULL, tk_address TEXT NOT NULL, id_role INTEGER NOT NULL, session_version INTEGER NOT NULL DEFAULT 0)');
    recoveryFixture($database, false);
    // Empty catalog fixtures let manual preview follow the normal login redirect.
    $database->exec('CREATE TABLE category (dm_id INTEGER PRIMARY KEY, dm_name TEXT)');
    $database->exec('CREATE TABLE product (sp_id INTEGER PRIMARY KEY, sp_name TEXT)');
    $seed = $database->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ([1 => 'admin', 2 => 'customer', 3 => 'other-customer', 4 => 'legacy', 5 => 'deleted-customer'] as $id => $username) {
        $seed->execute([
            $id, $username,
            $id === 4 ? 'Legacy-Password!2026' : password_hash('Original-Password!2026', PASSWORD_BCRYPT),
            $username . '@example.test', 'Test address', $id === 1 ? 1 : 2,
        ]);
    }
    $property = new ReflectionProperty(Database::class, 'connection');
    $property->setValue(null, $database);
    check(Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite', 'Refusing non-test database');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    check(is_resource($socket), 'Cannot reserve test port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $baseUrl = 'http://' . $address;
    $environment = getenv();
    $environment['FOURSMART_CLIENT_PASSWORD_TEST_DIRECTORY'] = $directory;
    $server = proc_open([
        PHP_BINARY, '-d', 'display_errors=0', '-d', 'zend.exception_ignore_args=1',
        '-S', $address, '-t', dirname(__DIR__), __DIR__ . '/support/client_password_router.php',
    ], [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']],
        $pipes, dirname(__DIR__), $environment, ['bypass_shell' => true]);
    check(is_resource($server), 'Cannot start isolated PHP server');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $code, $message, 0.1);
        if (is_resource($probe)) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(50000);
    }
    check($ready, 'Isolated server did not start');

    if (in_array('--serve', $argv, true)) {
        echo 'SQLite preview: ', $baseUrl, '/?client=login', PHP_EOL;
        echo 'Synthetic account: customer. Password is the test fixture default documented in this file.', PHP_EOL;
        echo 'Press Enter to stop and remove the isolated fixture.', PHP_EOL;
        fgets(STDIN);
    } else {
        $newPassword = 'Changed-Password!2026';
        $url = '?client=profilePasswordChange';
        $makeBrowser = function (string $username = 'customer', string $password = 'Original-Password!2026') use ($baseUrl): PasswordTestBrowser {
            $browser = new PasswordTestBrowser($baseUrl);
            check($browser->login($username, $password)['status'] === 302, 'Fixture login failed');
            return $browser;
        };
        $payload = fn(PasswordTestBrowser $browser): array => array_merge($browser->fields(), [
            'current_password' => 'Original-Password!2026', 'new_password' => $newPassword, 'confirm_password' => $newPassword,
        ]);
        $snapshot = fn(): array => $database->query('SELECT * FROM account ORDER BY tk_id')->fetchAll();
        $reject = function (PasswordTestBrowser $browser, array $fields, int $status, string $method = 'POST') use ($snapshot, $url): array {
            $before = $snapshot();
            $response = $browser->request($url, $method, $fields);
            check($response['status'] === $status, 'Expected HTTP ' . $status . ', got ' . $response['status']);
            check($snapshot() === $before, 'Rejected request modified account data');
            return $response;
        };

        $test('bcrypt password works and wrong password is rejected', function (): void {
            $account = AccountModel::find(2);
            check(AccountModel::verifyPassword($account, 'Original-Password!2026'), 'Correct password rejected');
            check(!AccountModel::verifyPassword($account, 'Wrong-Password!2026'), 'Wrong password accepted');
        });
        $test('stored hash cannot be a login password and does not trigger a write', function () use ($database): void {
            $account = AccountModel::find(2);
            $accepted = AccountModel::verifyPassword($account, $account['tk_password']);
            $after = AccountModel::find(2);
            $restore = $database->prepare('UPDATE account SET tk_password = ? WHERE tk_id = 2');
            $restore->execute([$account['tk_password']]);
            check(!$accepted && $after === $account, 'Stored hash accepted or account changed');
        });
        $test('malformed hashes never fall back to plaintext authentication', function (): void {
            foreach (['$2y$truncated', '$argon2id$truncated', '$6$truncated'] as $stored) {
                check(!AccountModel::verifyPassword(['tk_id' => 999999, 'tk_password' => $stored], $stored), 'Malformed hash accepted as plaintext');
            }
        });
        $test('legacy wrong password does not mutate data; correct login upgrades to bcrypt', function () use ($makeBrowser): void {
            $before = AccountModel::find(4);
            check(!AccountModel::verifyPassword($before, 'Wrong-Legacy!2026'), 'Wrong legacy password accepted');
            check(AccountModel::find(4) === $before, 'Wrong legacy password modified data');
            $browser = $makeBrowser('legacy', 'Legacy-Password!2026');
            $upgraded = AccountModel::find(4);
            check($upgraded['tk_password'] !== $before['tk_password'] && password_verify('Legacy-Password!2026', $upgraded['tk_password']), 'Legacy migration failed');
            check($browser->request('?client=profile')['status'] === 200, 'Legacy login session invalid');
        });
        $test('long legacy password stays exact and can be replaced without bcrypt truncation', function () use ($database): void {
            $stored = 'Aa1!' . str_repeat('x', 69);
            $database->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role) VALUES (?, ?, ?, ?, ?, ?)')->execute([9, 'long-legacy', $stored, 'long-legacy@example.test', 'Test', 2]);
            $before = AccountModel::find(9);
            check(AccountModel::verifyPassword($before, $stored), 'Exact long legacy password rejected');
            check(AccountModel::find(9) === $before, 'Long legacy password silently converted to truncated bcrypt');
            check(!AccountModel::verifyPassword($before, substr($stored, 0, -1) . 'y'), 'Different long legacy suffix accepted');
            check(AccountModel::changePassword(9, $stored, 'Long-Legacy-Updated!2026'), 'Long legacy owner cannot change password');
            check(password_verify('Long-Legacy-Updated!2026', AccountModel::find(9)['tk_password']), 'Legacy replacement not hashed');
            check(!AccountModel::verifyPassword($before, $stored), 'Stale long legacy snapshot accepted after replacement');
        });
        $test('short legacy password still authenticates and upgrades safely', function () use ($database): void {
            $database->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role) VALUES (?, ?, ?, ?, ?, ?)')->execute([6, 'short-legacy', 'old', 'short-legacy@example.test', 'Test', 2]);
            check(AccountModel::verifyPassword(AccountModel::find(6), 'old'), 'Short legacy password rejected');
            check(password_verify('old', AccountModel::find(6)['tk_password']), 'Short legacy password not upgraded');
        });
        $test('changing legacy password is a single replacement with no plaintext write', function () use ($database): void {
            $database->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role) VALUES (?, ?, ?, ?, ?, ?)')->execute([7, 'direct-legacy', 'Legacy-Direct!2026', 'direct-legacy@example.test', 'Test', 2]);
            $before = AccountModel::find(7);
            check(!AccountModel::changePassword(7, 'Wrong-Password!2026', 'Updated-Legacy!2026'), 'Wrong legacy password accepted');
            check(AccountModel::find(7) === $before, 'Rejected legacy change modified data');
            check(AccountModel::changePassword(7, 'Legacy-Direct!2026', 'Updated-Legacy!2026'), 'Legacy password change failed');
            check(password_verify('Updated-Legacy!2026', AccountModel::find(7)['tk_password']), 'New legacy password not hashed');
        });
        $test('stale legacy login cannot overwrite a password changed after lookup', function () use ($database): void {
            $database->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role) VALUES (?, ?, ?, ?, ?, ?)')->execute([8, 'stale-legacy', 'Legacy-Race!2026', 'stale-legacy@example.test', 'Test', 2]);
            $stale = AccountModel::find(8);
            $latestHash = password_hash('Latest-Password!2026', PASSWORD_BCRYPT);
            $database->prepare('UPDATE account SET tk_password = ? WHERE tk_id = 8')->execute([$latestHash]);
            check(!AccountModel::verifyPassword($stale, 'Legacy-Race!2026'), 'Stale legacy authentication accepted');
            check(AccountModel::find(8)['tk_password'] === $latestHash, 'Newer password overwritten by legacy upgrade');
        });

        $browser = $makeBrowser();
        $test('security section is separate and contains no account ID or password values', function () use ($browser): void {
            $page = $browser->request('?client=profile');
            $fields = $browser->fields();
            check(str_contains($page['body'], 'Bảo mật tài khoản') && str_contains($page['body'], '?client=profileUpdate'), 'Profile section missing');
            check(!isset($fields['id']) && !isset($fields['user_id']) && !isset($fields['tk_id']), 'Target ID is present in password form');
            foreach (['current_password', 'new_password', 'confirm_password'] as $name) {
                check($fields[$name] === '', 'Password input prefilled');
            }
            check(!str_contains($page['body'], AccountModel::find(2)['tk_password']), 'Stored hash exposed');
            check(stripos($page['headers'], 'Cache-Control: no-store') !== false, 'Sensitive profile can be cached');
        });
        $test('GET is rejected with Allow POST', function () use ($browser, $reject): void {
            $response = $reject($browser, [], 405, 'GET');
            check(stripos($response['headers'], 'Allow: POST') !== false, 'Allow header missing');
        });
        foreach (['missing' => null, 'wrong' => 'wrong', 'array' => ['bad']] as $name => $token) {
            $test('CSRF ' . $name . ' is rejected', function () use ($browser, $payload, $reject, $token): void {
                $fields = $payload($browser);
                $fields['csrf_token'] = $token;
                $reject($browser, $fields, 403);
            });
        }
        $test('anonymous request cannot change a password', function () use ($baseUrl, $browser, $payload, $reject): void {
            $reject(new PasswordTestBrowser($baseUrl), $payload($browser), 302);
        });
        $test('incorrect current password is rejected without echoing submitted secrets', function () use ($browser, $payload, $reject): void {
            $fields = $payload($browser);
            $fields['current_password'] = 'Wrong-Current!2026';
            $response = $reject($browser, $fields, 422);
            foreach (['current_password', 'new_password', 'confirm_password'] as $name) {
                check(!str_contains($response['body'], $fields[$name]), 'Submitted password echoed');
                check(formInputs($response['body'], 'profile-password-form')[$name] === '', 'Password input prefilled on error');
            }
        });
        $test('stored hash cannot be used as current password', function () use ($browser, $payload, $reject): void {
            $fields = $payload($browser);
            $fields['current_password'] = AccountModel::find(2)['tk_password'];
            $reject($browser, $fields, 422);
        });
        $test('another customer password cannot authorize a forged target ID', function () use ($browser, $payload, $reject, $database): void {
            $database->prepare('UPDATE account SET tk_password = ? WHERE tk_id = 3')->execute([password_hash('Other-Customer!2026', PASSWORD_BCRYPT)]);
            $fields = $payload($browser);
            $fields['id'] = 3;
            $fields['user_id'] = 3;
            $fields['current_password'] = 'Other-Customer!2026';
            $reject($browser, $fields, 422);
        });
        foreach ([
            'short' => 'Aa1!short', 'no uppercase' => 'lowercase-only!2026',
            'no lowercase' => 'UPPERCASE-ONLY!2026', 'no number' => 'No-Numbers-Password!',
            'no symbol' => 'PasswordWithoutSymbol2026', 'over 72 bytes' => 'Aa1!' . str_repeat('x', 69),
            'Unicode over 72 bytes' => 'Aa1!' . str_repeat('ệ', 24),
            'null byte' => "Strong-Password!2026\0", 'invalid UTF-8' => "Strong-Password!2026\xff",
        ] as $name => $invalid) {
            $test('password policy rejects ' . $name, function () use ($browser, $payload, $reject, $invalid): void {
                $fields = $payload($browser);
                $fields['new_password'] = $invalid;
                $fields['confirm_password'] = $invalid;
                $reject($browser, $fields, 422);
            });
        }
        $test('confirmation mismatch is rejected', function () use ($browser, $payload, $reject): void {
            $fields = $payload($browser);
            $fields['confirm_password'] = 'Different-Password!2026';
            $reject($browser, $fields, 422);
        });
        $test('unchanged password is rejected', function () use ($browser, $payload, $reject): void {
            $fields = $payload($browser);
            $fields['new_password'] = $fields['confirm_password'] = $fields['current_password'];
            $reject($browser, $fields, 422);
        });
        foreach (['current_password', 'new_password', 'confirm_password'] as $name) {
            $test('array input is safely rejected for ' . $name, function () use ($browser, $payload, $reject, $name): void {
                $fields = $payload($browser);
                $fields[$name] = ['sensitive-value'];
                $reject($browser, $fields, 400);
            });
        }
        $test('deleted account cannot use its old session', function () use ($makeBrowser, $payload, $reject, $database): void {
            $deleted = $makeBrowser('deleted-customer');
            $fields = $payload($deleted);
            $database->exec('DELETE FROM account WHERE tk_id = 5');
            $reject($deleted, $fields, 403);
            check($deleted->request('?client=profile')['status'] === 302, 'Invalid authentication retained');
        });
        $test('failed current password attempts are limited within the session', function () use ($makeBrowser, $payload, $reject): void {
            $limited = $makeBrowser();
            $fields = $payload($limited);
            $fields['current_password'] = 'Wrong-Current!2026';
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $reject($limited, $fields, 422);
            }
            $response = $reject($limited, $fields, 429);
            check(stripos($response['headers'], 'Retry-After:') !== false, 'Rate limit retry header missing');
        });
        $test('database write failure returns a safe error and leaves account data intact', function () use ($browser, $payload, $reject, $database): void {
            $database->exec("CREATE TRIGGER fail_password_write BEFORE UPDATE OF tk_password ON account BEGIN SELECT RAISE(ABORT, 'test-only write failure'); END");
            try {
                $response = $reject($browser, $payload($browser), 500);
                check(!str_contains($response['body'], 'PDOException') && !str_contains($response['body'], 'test-only write failure'), 'Exception details exposed');
            } finally {
                $database->exec('DROP TRIGGER fail_password_write');
            }
        });
        $test('successful change affects only authenticated account and rotates session and CSRF', function () use ($browser, $payload, $url, $snapshot, $newPassword, $baseUrl): void {
            $fields = $payload($browser);
            $fields['id'] = 1;
            $fields['user_id'] = 3;
            $oldSession = $browser->sessionId();
            $before = $snapshot();
            $response = $browser->request($url . '&id=3', 'POST', $fields);
            check($response['status'] === 303, 'Password change did not redirect');
            $after = $snapshot();
            $hash = $after[1]['tk_password'];
            $after[1]['tk_password'] = $before[1]['tk_password'];
            $expectedVersion = $before[1]['session_version'] + (int) SessionVersionPolicy::required();
            check((int) $after[1]['session_version'] === $expectedVersion, 'Unexpected session version after change');
            $after[1]['session_version'] = $before[1]['session_version'];
            check($after === $before, 'Other account or profile fields changed');
            check($hash !== $newPassword && password_verify($newPassword, $hash), 'New password hash invalid');
            check($browser->sessionId() !== $oldSession, 'Session ID not rotated');
            $page = $browser->request('?client=profile');
            check(str_contains($page['body'], 'Đổi mật khẩu thành công'), 'Success message missing');
            check($browser->fields()['csrf_token'] !== $fields['csrf_token'], 'CSRF token not rotated');
            $oldBrowser = new PasswordTestBrowser($baseUrl);
            $oldBrowser->useSessionId($oldSession);
            check($oldBrowser->request('?client=profile')['status'] === 302, 'Old session ID still authenticated');
            check($browser->request($url, 'POST', $fields)['status'] === 403, 'Old CSRF request replay accepted');
        });
        $test('new password logs in; old password and stored hash cannot log in', function () use ($baseUrl, $newPassword): void {
            $fresh = new PasswordTestBrowser($baseUrl);
            check($fresh->login('customer', 'Original-Password!2026')['status'] === 200, 'Old password still accepted');
            check($fresh->login('customer', AccountModel::find(2)['tk_password'])['status'] === 200, 'Stored hash accepted');
            check($fresh->login('customer', $newPassword)['status'] === 302, 'New password rejected');
            check($fresh->request('?client=profile')['status'] === 200, 'New login session invalid');
        });
        $test('malformed login password does not leak values or throw a type error', function () use ($baseUrl): void {
            $fresh = new PasswordTestBrowser($baseUrl);
            $response = $fresh->login('customer', ['sensitive-login-value']);
            check($response['status'] === 200 && !str_contains($response['body'], 'sensitive-login-value'), 'Malformed login not handled safely');
        });
        $test('profile email and address still update without changing password', function () use ($browser): void {
            $before = AccountModel::find(2);
            $fields = $browser->fields();
            $response = $browser->request('?client=profileUpdate', 'POST', [
                'csrf_token' => $fields['csrf_token'], 'email' => 'updated-customer@example.test', 'address' => 'Updated address',
                'new_password' => 'Ignored-Password!2026', 'current_password' => 'Ignored-Password!2026',
            ]);
            $after = AccountModel::find(2);
            check($response['status'] === 200 && $after['tk_email'] === 'updated-customer@example.test' && $after['tk_address'] === 'Updated address', 'Profile update failed');
            check($after['tk_password'] === $before['tk_password'], 'Profile update changed password');
        });
        $test('profile update still rejects duplicate email and invalid CSRF', function () use ($browser, $snapshot): void {
            $fields = $browser->fields();
            $before = $snapshot();
            $response = $browser->request('?client=profileUpdate', 'POST', ['csrf_token' => $fields['csrf_token'], 'email' => 'other-customer@example.test', 'address' => 'Test']);
            check($response['status'] === 200 && str_contains($response['body'], 'Email đã được tài khoản khác sử dụng'), 'Duplicate email not rejected');
            check($browser->request('?client=profileUpdate', 'POST', ['csrf_token' => 'wrong'])['status'] === 403, 'Profile CSRF rejected incorrectly');
            check($snapshot() === $before, 'Rejected profile update changed account');
        });
        $test('exact 72-byte new password is accepted', function () use ($makeBrowser, $url): void {
            $other = $makeBrowser('other-customer', 'Other-Customer!2026');
            $fields = $other->fields();
            $fields['current_password'] = 'Other-Customer!2026';
            $fields['new_password'] = $fields['confirm_password'] = 'Aa1!' . str_repeat('x', 68);
            check($other->request($url, 'POST', $fields)['status'] === 303, '72-byte password rejected');
            check(password_verify($fields['new_password'], AccountModel::find(3)['tk_password']), 'Boundary password invalid');
            $account = AccountModel::find(3);
            check(!AccountModel::verifyPassword($account, $fields['new_password'] . 'x'), 'bcrypt ignored extra password suffix');
            $account['tk_password'] = '$2b$' . substr($account['tk_password'], 4);
            check(!AccountModel::verifyPassword($account, $fields['new_password'] . 'x'), 'Alternate bcrypt prefix ignored extra suffix');
        });
        $test('application logs contain no submitted password, hash, or database exception', function () use ($directory, $newPassword): void {
            $log = file_get_contents($directory . '/server.log');
            foreach ([$newPassword, 'Original-Password!2026', AccountModel::find(2)['tk_password'], 'sensitive-login-value', 'test-only write failure'] as $secret) {
                check(!str_contains($log, $secret), 'Sensitive data or exception leaked to log');
            }
        });
    }
} catch (Throwable $error) {
    $failed++;
    fwrite(STDERR, 'Test harness failed: ' . $error->getMessage() . PHP_EOL);
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    if (isset($property)) {
        $property->setValue(null, null);
    }
    unset($reject, $snapshot);
    $database = null;
    $seed = null;
    $resolved = realpath($directory);
    // Verify the exact parent, prefix, and fixture marker before recursive cleanup.
    if ($resolved !== false && dirname($resolved) === realpath(sys_get_temp_dir())
        && str_starts_with(basename($resolved), 'foursmart-client-password-')
        && is_file($resolved . '/test-only.marker')) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($resolved);
    }
}

if (!in_array('--serve', $argv, true)) {
    echo PHP_EOL, $passed, ' passed, ', $failed, ' failed (isolated SQLite only)', PHP_EOL;
}
exit($failed === 0 ? 0 : 1);
