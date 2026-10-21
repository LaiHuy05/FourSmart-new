<?php

// Run with XAMPP PHP CLI. All writes go to a disposable SQLite test database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('zend.exception_ignore_args', '1');

foreach (['pdo_sqlite', 'curl', 'dom', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, 'Missing PHP extension: ' . $extension . PHP_EOL);
        exit(1);
    }
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../model/AccountModel.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function inputs(string $html, ?string $formId = null): array
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($document);
    $query = $formId === null
        ? '//input[@name]'
        : '//form[@id="' . $formId . '"]//input[@name]';
    $values = [];

    foreach ($xpath->query($query) as $input) {
        $values[$input->getAttribute('name')] = $input->getAttribute('value');
    }

    return $values;
}

final class TestBrowser
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

    public function request(string $url, string $method = 'GET', array $data = []): array
    {
        curl_setopt($this->handle, CURLOPT_URL, $this->baseUrl . '/' . $url);
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, null);
        curl_setopt($this->handle, CURLOPT_HTTPGET, true);

        if ($method !== 'GET') {
            curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, 'GET');
        }

        $response = curl_exec($this->handle);
        check(is_string($response), 'Local test server request failed');
        $headerSize = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);

        return [
            'status' => curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE),
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize),
        ];
    }

    public function login(string $username, string $password): array
    {
        $page = $this->request('?client=login');
        $fields = inputs($page['body']);

        return $this->request('?client=login', 'POST', [
            'csrf_token' => $fields['csrf_token'] ?? '',
            'username' => $username,
            'password' => $password,
        ]);
    }

    public function resetFields(int $targetId = 2): array
    {
        $page = $this->request('?act=admin&admin=accountUpdate&id=' . $targetId);
        check($page['status'] === 200, 'Account edit page did not load');
        $fields = inputs($page['body'], 'password-reset-form');
        check(isset($fields['id'], $fields['csrf_token'], $fields['reset_token']), 'Reset form missing');

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
}

$testDirectory = sys_get_temp_dir() . '/foursmart-password-reset-' . bin2hex(random_bytes(8));
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
        echo 'FAIL ', $name, ': ', $error->getMessage(), PHP_EOL;
    }
};

try {
    check(mkdir($testDirectory, 0700), 'Cannot create isolated test directory');
    check(mkdir($testDirectory . '/sessions', 0700), 'Cannot create isolated sessions');
    file_put_contents($testDirectory . '/test-only.marker', 'SQLite tests only');
    $database = new PDO('sqlite:' . $testDirectory . '/accounts.sqlite');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $database->exec('CREATE TABLE role (role_id INTEGER PRIMARY KEY, role_name TEXT NOT NULL)');
    $database->exec('CREATE TABLE account (
        tk_id INTEGER PRIMARY KEY,
        tk_user TEXT NOT NULL UNIQUE,
        tk_password TEXT NOT NULL,
        tk_email TEXT NOT NULL UNIQUE,
        tk_address TEXT NOT NULL,
        id_role INTEGER NOT NULL
    )');
    $database->exec("INSERT INTO role VALUES (1, 'Admin'), (2, 'Khách hàng'), (3, 'Other role')");
    $seed = $database->prepare('INSERT INTO account VALUES (?, ?, ?, ?, ?, ?)');

    foreach ([1 => 'admin', 2 => 'customer', 3 => 'other-customer', 4 => 'other-admin', 5 => 'other-role', 6 => 'legacy'] as $id => $username) {
        $seed->execute([
            $id, $username,
            $id === 6 ? 'Legacy-Password!2026' : password_hash('Original-Password!2026', PASSWORD_BCRYPT),
            $username . '@example.test', 'Test address',
            in_array($id, [1, 4], true) ? 1 : ($id === 5 ? 3 : 2),
        ]);
    }

    $property = new ReflectionProperty(Database::class, 'connection');
    $property->setValue(null, $database);
    check(Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite', 'Refusing non-test database');

    $test('existing bcrypt password works', function (): void {
        check(AccountModel::verifyPassword(AccountModel::find(2), 'Original-Password!2026'), 'Correct password rejected');
        check(!AccountModel::verifyPassword(AccountModel::find(2), 'Wrong-Password!2026'), 'Wrong password accepted');
    });

    $test('stored hash cannot be used as login password', function () use ($database): void {
        $account = AccountModel::find(2);
        $accepted = AccountModel::verifyPassword($account, $account['tk_password']);
        $currentHash = AccountModel::find(2)['tk_password'];
        // Restore the fixture after reproducing the old authentication bug.
        $statement = $database->prepare('UPDATE account SET tk_password = ? WHERE tk_id = 2');
        $statement->execute([$account['tk_password']]);
        check(!$accepted && $currentHash === $account['tk_password'], 'Stored hash accepted or changed');
    });

    $test('legacy plaintext login still upgrades to bcrypt', function (): void {
        check(AccountModel::verifyPassword(AccountModel::find(6), 'Legacy-Password!2026'), 'Legacy password rejected');
        $hash = AccountModel::find(6)['tk_password'];
        check($hash !== 'Legacy-Password!2026' && password_verify('Legacy-Password!2026', $hash), 'Legacy migration failed');
    });

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    check(is_resource($socket), 'Cannot reserve local test port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $baseUrl = 'http://' . $address;
    $environment = getenv();
    $environment['FOURSMART_PASSWORD_RESET_TEST_DIRECTORY'] = $testDirectory;
    $server = proc_open([
        PHP_BINARY, '-d', 'display_errors=0', '-d', 'zend.exception_ignore_args=1',
        '-S', $address, '-t', dirname(__DIR__), __DIR__ . '/support/password_reset_router.php',
    ], [
        0 => ['pipe', 'r'],
        1 => ['file', $testDirectory . '/server.log', 'a'],
        2 => ['file', $testDirectory . '/server.log', 'a'],
    ], $pipes, dirname(__DIR__), $environment, ['bypass_shell' => true]);
    check(is_resource($server), 'Cannot start isolated PHP test server');
    fclose($pipes[0]);

    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
        if (is_resource($probe)) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(50000);
    }
    check($ready, 'Isolated PHP server did not start');

    $admin = new TestBrowser($baseUrl);
    check($admin->login('admin', 'Original-Password!2026')['status'] === 302, 'Admin fixture login failed');
    $resetUrl = '?act=admin&admin=accountResetPassword';
    $password = 'Temporary-Reset!2026';
    $payload = fn(array $fields): array => array_merge($fields, [
        'new_password' => $password,
        'confirm_password' => $password,
    ]);
    $snapshot = fn(): array => $database->query('SELECT * FROM account ORDER BY tk_id')->fetchAll();
    $reject = function (TestBrowser $browser, array $fields, int $status, string $method = 'POST') use ($snapshot, $resetUrl): array {
        $before = $snapshot();
        $response = $browser->request($resetUrl, $method, $fields);
        check($response['status'] === $status, 'Unexpected rejection status; expected ' . $status . ', got ' . $response['status']);
        check($snapshot() === $before, 'Rejected request changed account data');
        return $response;
    };

    $test('edit page keeps account form separate and passwords empty', function () use ($admin): void {
        $page = $admin->request('?act=admin&admin=accountUpdate&id=2');
        $fields = inputs($page['body'], 'password-reset-form');
        check(isset($fields['new_password'], $fields['confirm_password']), 'Password inputs missing');
        check($fields['new_password'] === '' && $fields['confirm_password'] === '', 'Password inputs prefilled');
        check(str_contains($page['body'], 'accountUpdate&id=2'), 'Original account update action missing');
        check(!str_contains($page['body'], AccountModel::find(2)['tk_password']), 'Stored hash exposed in HTML');
        check(str_contains($page['headers'], 'Cache-Control: no-store'), 'Edit form can be cached');
    });

    $test('GET reset is rejected with Allow POST', function () use ($admin, $reject): void {
        $response = $reject($admin, [], 405, 'GET');
        check(stripos($response['headers'], 'Allow: POST') !== false, 'Allow header missing');
    });

    foreach (['missing' => null, 'wrong' => 'wrong-token', 'array' => ['bad']] as $name => $token) {
        $test('CSRF ' . $name . ' is rejected', function () use ($admin, $payload, $reject, $token): void {
            $fields = $payload($admin->resetFields());
            if ($token === null) {
                unset($fields['csrf_token']);
            } else {
                $fields['csrf_token'] = $token;
            }
            $reject($admin, $fields, 403);
        });
    }

    $test('editing customer ID cannot change another account', function () use ($admin, $payload, $reject): void {
        $fields = $payload($admin->resetFields());
        $fields['id'] = '3';
        $reject($admin, $fields, 403);
    });

    $test('malformed ID is rejected', function () use ($admin, $payload, $reject): void {
        $fields = $payload($admin->resetFields());
        $fields['id'] = '2abc';
        $reject($admin, $fields, 400);
    });

    $test('missing and malformed reset tokens are rejected', function () use ($admin, $payload, $reject): void {
        foreach ([null, ['bad'], str_repeat('a', 64)] as $token) {
            $fields = $payload($admin->resetFields());
            $fields['reset_token'] = $token;
            $reject($admin, $fields, 403);
        }
    });

    $test('password confirmation mismatch is rejected', function () use ($admin, $payload, $reject): void {
        $fields = $payload($admin->resetFields());
        $fields['confirm_password'] = 'Different-Password!2026';
        $response = $reject($admin, $fields, 422);
        check(!str_contains($response['body'], $fields['new_password']), 'Submitted password echoed');
        check(inputs($response['body'], 'password-reset-form')['new_password'] === '', 'Rejected password prefilled');
    });

    foreach ([
        'short' => 'Aa1!short',
        'missing uppercase' => 'lowercase-only!2026',
        'missing lowercase' => 'UPPERCASE-ONLY!2026',
        'missing number' => 'Password-Without-Number!',
        'missing symbol' => 'PasswordWithoutSymbol2026',
        'over 72 bytes' => 'Aa1!' . str_repeat('x', 69),
        'unicode over 72 bytes' => 'Aa1!' . str_repeat('ệ', 24),
        'null byte' => "Strong-Password!2026\0",
        'invalid UTF-8' => "Strong-Password!2026\xff",
    ] as $name => $invalidPassword) {
        $test('password policy rejects ' . $name, function () use ($admin, $reject, $invalidPassword): void {
            $fields = $admin->resetFields();
            $fields['new_password'] = $invalidPassword;
            $fields['confirm_password'] = $invalidPassword;
            $reject($admin, $fields, 422);
        });
    }

    $test('array password is rejected safely', function () use ($admin, $payload, $reject): void {
        $fields = $payload($admin->resetFields());
        $fields['new_password'] = ['bad'];
        $reject($admin, $fields, 400);
    });

    $test('anonymous and customer requests cannot reset passwords', function () use ($baseUrl, $admin, $payload, $reject): void {
        $fields = $payload($admin->resetFields());
        $anonymous = new TestBrowser($baseUrl);
        $reject($anonymous, $fields, 302);
        $customer = new TestBrowser($baseUrl);
        check($customer->login('other-customer', 'Original-Password!2026')['status'] === 302, 'Customer fixture login failed');
        $reject($customer, $fields, 403);
    });

    $test('reset token is bound to the acting administrator', function () use ($baseUrl, $admin, $payload, $reject): void {
        $fields = $payload($admin->resetFields());
        $otherAdmin = new TestBrowser($baseUrl);
        check($otherAdmin->login('other-admin', 'Original-Password!2026')['status'] === 302, 'Second admin login failed');
        $fields['csrf_token'] = $otherAdmin->resetFields()['csrf_token'];
        $reject($otherAdmin, $fields, 403);
    });

    $test('expired token is rejected', function () use ($admin, $payload, $reject, $testDirectory): void {
        $fields = $payload($admin->resetFields());
        $sessionFile = $testDirectory . '/sessions/sess_' . $admin->sessionId();
        $session = unserialize(file_get_contents($sessionFile), ['allowed_classes' => false]);
        $session['account_password_resets'][$fields['reset_token']]['expires_at'] = time() - 1;
        file_put_contents($sessionFile, serialize($session), LOCK_EX);
        $reject($admin, $fields, 403);
    });

    $test('administrator whose role was revoked is rejected', function () use ($baseUrl, $database, $payload, $reject): void {
        $otherAdmin = new TestBrowser($baseUrl);
        check($otherAdmin->login('other-admin', 'Original-Password!2026')['status'] === 302, 'Admin fixture login failed');
        $fields = $payload($otherAdmin->resetFields());
        $database->exec('UPDATE account SET id_role = 2 WHERE tk_id = 4');
        try {
            $reject($otherAdmin, $fields, 403);
        } finally {
            $database->exec('UPDATE account SET id_role = 1 WHERE tk_id = 4');
        }
    });

    $test('target promoted to administrator is rejected', function () use ($admin, $database, $payload, $reject): void {
        $fields = $payload($admin->resetFields());
        $database->exec('UPDATE account SET id_role = 1 WHERE tk_id = 2');
        try {
            $reject($admin, $fields, 403);
        } finally {
            $database->exec('UPDATE account SET id_role = 2 WHERE tk_id = 2');
        }
    });

    $test('self, other admin, and other roles have no reset form', function () use ($admin, $payload, $reject): void {
        foreach ([1, 4, 5] as $id) {
            $page = $admin->request('?act=admin&admin=accountUpdate&id=' . $id);
            check(!str_contains($page['body'], 'id="password-reset-form"'), 'Disallowed target has reset form');
            $fields = $payload($admin->resetFields());
            $fields['id'] = (string) $id;
            $reject($admin, $fields, 403);
        }
    });

    $test('valid administrator resets only the selected customer password', function () use ($admin, $payload, $resetUrl, $password, $snapshot, $reject): void {
        $fields = $payload($admin->resetFields());
        $before = $snapshot();
        $response = $admin->request($resetUrl, 'POST', $fields);
        check($response['status'] === 303, 'Successful reset did not redirect');
        $after = $snapshot();
        $hash = $after[1]['tk_password'];
        $after[1]['tk_password'] = $before[1]['tk_password'];
        check($after === $before, 'Reset changed other account fields or accounts');
        check($hash !== $password && password_verify($password, $hash), 'New password not hashed correctly');
        check(!password_verify('Original-Password!2026', $hash), 'Old password still valid');
        $reject($admin, $fields, 403);
        $page = $admin->request('?act=admin&admin=accountUpdate&id=2');
        check(str_contains($page['body'], 'Cấp lại mật khẩu thành công'), 'Success notice missing');
        $page = $admin->request('?act=admin&admin=accountUpdate&id=2');
        check(!str_contains($page['body'], 'Cấp lại mật khẩu thành công'), 'Success notice did not expire');
    });

    $test('actual login accepts new password and rejects old password and hash', function () use ($baseUrl, $password): void {
        $customer = new TestBrowser($baseUrl);
        check($customer->login('customer', 'Original-Password!2026')['status'] === 200, 'Old password still logs in');
        check($customer->login('customer', AccountModel::find(2)['tk_password'])['status'] === 200, 'Stored hash logs in');
        check($customer->login('customer', $password)['status'] === 302, 'New password cannot log in');
        check($customer->request('?client=profile')['status'] === 200, 'New login session cannot access profile');
    });

    $test('account information update remains separate from password reset', function () use ($admin): void {
        $account = AccountModel::find(2);
        $fields = $admin->resetFields();
        $response = $admin->request('?act=admin&admin=accountUpdate&id=2', 'POST', [
            'csrf_token' => $fields['csrf_token'],
            'username' => $account['tk_user'],
            'email' => 'updated-customer@example.test',
            'address' => 'Updated test address',
            'role_id' => 2,
            'new_password' => 'Ignored-Password!2026',
            'confirm_password' => 'Ignored-Password!2026',
        ]);
        check($response['status'] === 302, 'Account information update failed');
        $updated = AccountModel::find(2);
        check($updated['tk_password'] === $account['tk_password'], 'Information update changed password');
        check($updated['tk_email'] === 'updated-customer@example.test' && $updated['tk_address'] === 'Updated test address', 'Information fields not updated');
    });

    $test('model cannot reset an administrator password', function (): void {
        $before = AccountModel::find(4);
        check(!AccountModel::resetCustomerPassword(4, 'Disallowed-Password!2026'), 'Model updated administrator');
        check(AccountModel::find(4) === $before, 'Administrator data changed');
    });

    $test('72-byte password boundary is accepted', function () use ($admin, $resetUrl): void {
        $fields = $admin->resetFields(3);
        $fields['new_password'] = 'Aa1!' . str_repeat('x', 68);
        $fields['confirm_password'] = $fields['new_password'];
        check($admin->request($resetUrl, 'POST', $fields)['status'] === 303, '72-byte password rejected');
        check(password_verify($fields['new_password'], AccountModel::find(3)['tk_password']), 'Boundary password verification failed');
    });

    $test('test server logs contain no submitted password or stored hash', function () use ($testDirectory, $password): void {
        $log = file_get_contents($testDirectory . '/server.log');
        check(!str_contains($log, $password), 'Password found in server log');
        check(!str_contains($log, AccountModel::find(2)['tk_password']), 'Stored hash found in server log');
    });
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

    // Cleanup is restricted to the exact disposable directory created above.
    $resolved = realpath($testDirectory);
    $temporaryRoot = realpath(sys_get_temp_dir());
    if (
        $resolved !== false
        && dirname($resolved) === $temporaryRoot
        && str_starts_with(basename($resolved), 'foursmart-password-reset-')
        && is_file($resolved . '/test-only.marker')
    ) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($resolved);
    }
}

echo PHP_EOL, $passed, ' passed, ', $failed, ' failed (isolated SQLite only)', PHP_EOL;
exit($failed === 0 ? 0 : 1);
