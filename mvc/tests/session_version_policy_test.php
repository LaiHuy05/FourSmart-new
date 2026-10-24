<?php

// Never reads a MySQL DSN or private MariaDB credentials. Fixtures are disposable.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/support/password_recovery_fixture.php';

function policyFields(string $html, string $form = ''): array
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($dom);
    $fields = [];
    foreach ($xpath->query($form === '' ? '//input[@name]' : '//form[@id="' . $form . '"]//input[@name]') as $input) {
        $fields[$input->getAttribute('name')] = $input->getAttribute('value');
    }
    return $fields;
}

final class PolicyBrowser
{
    private CurlHandle $curl;

    public function __construct(private string $url, private string $directory)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
            CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10,
            CURLOPT_PROXY => '', CURLOPT_RESOLVE => ['localhost:' . parse_url($url, PHP_URL_PORT) . ':127.0.0.1']]);
    }

    public function request(string $path, string $method = 'GET', array $fields = []): array
    {
        curl_setopt($this->curl, CURLOPT_URL, $this->url . '/' . $path);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
        curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        if ($method === 'POST') curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($fields));
        $response = curl_exec($this->curl);
        recoveryAssert(is_string($response), 'Local fixture HTTP request failed');
        $size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        return ['status' => curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE),
            'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
    }

    public function login(string $user = 'customer', string $password = 'Original-Password!2026'): array
    {
        $page = $this->request('?client=login');
        return $this->request('?client=login', 'POST', ['csrf_token' => policyFields($page['body'])['csrf_token'] ?? '',
            'username' => $user, 'password' => $password]);
    }

    private function sessionPath(): string
    {
        foreach (curl_getinfo($this->curl, CURLINFO_COOKIELIST) as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'FOURSMART_POLICY_TESTSESSID'
                && preg_match('/\A[a-zA-Z0-9,-]{1,256}\z/', $parts[6] ?? '')) {
                $path = $this->directory . '/sessions/sess_' . $parts[6];
                recoveryAssert(is_file($path), 'Fixture session file unavailable');
                return $path;
            }
        }
        throw new RuntimeException('Fixture session cookie unavailable');
    }

    public function session(): array
    {
        $value = unserialize(file_get_contents($this->sessionPath()), ['allowed_classes' => false]);
        recoveryAssert(is_array($value), 'Fixture session unavailable');
        return $value;
    }

    public function replaceSession(array $session): void
    {
        file_put_contents($this->sessionPath(), serialize($session));
    }
}

$directory = recoveryDirectory();
$server = null;
$db = null;
$legacy = null;
$tests = new RecoveryTests();
$originalMode = getenv('FOURSMART_SESSION_VERSION_MODE');
$originalRecovery = getenv('FOURSMART_PASSWORD_RECOVERY_ENABLED');
$key = random_bytes(32);
$settings = ['mode' => 'required', 'recovery' => false, 'schema' => 'versioned',
    'database_error' => false, 'key' => base64_encode($key)];
$configure = function (array $changes = []) use (&$settings, $directory): void {
    $settings = array_replace($settings, $changes);
    file_put_contents($directory . '/policy.json', json_encode($settings, JSON_THROW_ON_ERROR));
};
try {
    $db = new PDO('sqlite:' . $directory . '/versioned.sqlite');
    recoveryFixture($db);
    $legacy = new PDO('sqlite:' . $directory . '/legacy.sqlite', null, null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $legacy->exec('CREATE TABLE account (tk_id INTEGER PRIMARY KEY, tk_user TEXT UNIQUE NOT NULL,
        tk_password TEXT NOT NULL, tk_email TEXT NOT NULL, tk_address TEXT, id_role INTEGER)');
    $seed = $legacy->prepare('INSERT INTO account VALUES (?, ?, ?, ?, ?, ?)');
    foreach ([1 => 'admin', 2 => 'customer', 3 => 'other', 4 => 'legacy'] as $id => $user) {
        $seed->execute([$id, $user, $id === 4 ? 'Legacy-Plain!2026' : password_hash('Original-Password!2026', PASSWORD_BCRYPT),
            $user . '@example.test', 'Synthetic policy fixture', $id === 1 ? 1 : 2]);
    }
    $seed = null;
    $model = new PasswordRecoveryModel($db);
    $now = static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $reset = function () use ($db, $configure): void {
        $configure(['mode' => 'required', 'recovery' => false, 'schema' => 'versioned', 'database_error' => false]);
        foreach (['password_reset_tokens', 'password_reset_jobs', 'password_reset_rate_limits'] as $table) $db->exec('DELETE FROM ' . $table);
        $statement = $db->prepare('UPDATE account SET tk_password = ?, session_version = 0');
        $statement->execute([password_hash('Original-Password!2026', PASSWORD_BCRYPT)]);
    };
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    recoveryAssert(is_resource($socket), 'Cannot reserve local fixture port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $url = 'http://localhost:' . substr(strrchr($address, ':'), 1);
    $newBrowser = fn(): PolicyBrowser => new PolicyBrowser($url, $directory);
    $environment = getenv();
    $environment['FOURSMART_SESSION_POLICY_TEST_DIRECTORY'] = $directory;
    $environment['FOURSMART_APP_URL'] = $url . '/index.php';
    $configure();
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', dirname(__DIR__), __DIR__ . '/support/session_version_router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']],
        $pipes, dirname(__DIR__), $environment, ['bypass_shell' => true]);
    recoveryAssert(is_resource($server), 'Fixture server did not start');
    fclose($pipes[0]);
    $ready = false;
    for ($i = 0; $i < 60; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $code, $message, 0.1);
        if (is_resource($probe)) { fclose($probe); $ready = true; break; }
        usleep(50000);
    }
    recoveryAssert($ready, 'Fixture server not ready');

    $tests->test('policy rejects missing or invalid modes and permits only explicit legacy', function (): void {
        $previous = getenv('FOURSMART_SESSION_VERSION_MODE');
        try {
            putenv('FOURSMART_SESSION_VERSION_MODE');
            $rejected = false;
            try { SessionVersionPolicy::required(); } catch (RuntimeException $expected) { $rejected = true; }
            recoveryAssert($rejected, 'Missing policy silently lowered session security');
            putenv('FOURSMART_SESSION_VERSION_MODE=legacy');
            recoveryAssert(!SessionVersionPolicy::required(), 'Explicit legacy mode is not supported');
            putenv('FOURSMART_SESSION_VERSION_MODE=required');
            recoveryAssert(SessionVersionPolicy::required(), 'Required mode ignored');
            foreach (['', '0', 'on', 'REQUIRED'] as $mode) {
                putenv('FOURSMART_SESSION_VERSION_MODE=' . $mode);
                $rejected = false;
                try { SessionVersionPolicy::required(); } catch (RuntimeException $expected) { $rejected = true; }
                recoveryAssert($rejected, 'Invalid mode silently disabled checking');
            }
        } finally {
            putenv($previous === false ? 'FOURSMART_SESSION_VERSION_MODE' : 'FOURSMART_SESSION_VERSION_MODE=' . $previous);
        }
    });
    $tests->test('legacy schema keeps login and password change working without a version column', function () use ($configure, $newBrowser, $legacy): void {
        $configure(['mode' => 'legacy', 'recovery' => false, 'schema' => 'legacy']);
        $browser = $newBrowser();
        recoveryAssert($browser->login()['status'] === 302, 'Legacy login failed');
        recoveryAssert(!array_key_exists('session_version', $browser->session()), 'Legacy login requires a nonexistent column');
        $page = $browser->request('?client=profile');
        $fields = policyFields($page['body'], 'profile-password-form');
        $response = $browser->request('?client=profilePasswordChange', 'POST', array_merge($fields, [
            'current_password' => 'Original-Password!2026', 'new_password' => 'Legacy-Changed!2026', 'confirm_password' => 'Legacy-Changed!2026']));
        recoveryAssert($response['status'] === 303 && $browser->request('?client=profile')['status'] === 200, 'Legacy change failed');
        recoveryAssert(password_verify('Legacy-Changed!2026', $legacy->query('SELECT tk_password FROM account WHERE tk_id = 2')->fetchColumn()), 'Legacy password not updated');
    });
    $tests->test('legacy Admin authorization remains intact', function () use ($newBrowser): void {
        $admin = $newBrowser();
        recoveryAssert($admin->login('admin')['status'] === 302, 'Legacy Admin login failed');
        recoveryAssert($admin->request('?act=admin&admin=accountList')['status'] === 200, 'Legacy Admin blocked');
        $client = $newBrowser();
        recoveryAssert($client->login('other')['status'] === 302, 'Legacy client login failed');
        recoveryAssert($client->request('?act=admin&admin=accountList')['status'] === 403, 'Client gained Admin access');
    });
    $tests->test('required login records version zero while recovery and HMAC are off', function () use ($reset, $newBrowser): void {
        $reset(); $browser = $newBrowser();
        recoveryAssert($browser->login()['status'] === 302, 'Required login failed');
        recoveryAssert(($browser->session()['session_version'] ?? null) === 0, 'Required snapshot missing');
        recoveryAssert($browser->request('?client=profile')['status'] === 200, 'Valid zero version rejected');
    });
    $tests->test('stale GET session is revoked with recovery off', function () use ($reset, $newBrowser, $db): void {
        $reset(); $browser = $newBrowser(); $browser->login();
        $db->exec('UPDATE account SET session_version = 1 WHERE tk_id = 2');
        recoveryAssert($browser->request('?client=profile')['status'] === 302, 'Stale session accepted');
        recoveryAssert(!isset($browser->session()['user_id']), 'Revoked session retained authentication');
    });
    $tests->test('stale POST is refused before profile mutation with recovery off', function () use ($reset, $newBrowser, $db): void {
        $reset(); $browser = $newBrowser(); $browser->login();
        $fields = policyFields($browser->request('?client=profile')['body']);
        $db->exec('UPDATE account SET session_version = 1 WHERE tk_id = 2');
        $before = $db->query('SELECT tk_address FROM account WHERE tk_id = 2')->fetchColumn();
        recoveryAssert($browser->request('?client=profileUpdate', 'POST', ['csrf_token' => $fields['csrf_token'],
            'email' => 'customer@example.test', 'address' => 'Unauthorized mutation'])['status'] === 403, 'Stale POST accepted');
        recoveryAssert($db->query('SELECT tk_address FROM account WHERE tk_id = 2')->fetchColumn() === $before, 'Stale POST wrote profile');
    });
    $tests->test('missing session version forces reauthentication with recovery off', function () use ($reset, $newBrowser): void {
        $reset(); $browser = $newBrowser(); $browser->login();
        $session = $browser->session(); unset($session['session_version']); $browser->replaceSession($session);
        recoveryAssert($browser->request('?client=profile')['status'] === 302, 'Unversioned session accepted');
    });
    $tests->test('malformed session versions cannot be coerced into valid zero', function () use ($reset, $newBrowser): void {
        $reset();
        foreach ([null, false, 0.0, -1, '0junk', [], '4294967296'] as $value) {
            $browser = $newBrowser(); $browser->login();
            $session = $browser->session(); $session['session_version'] = $value; $browser->replaceSession($session);
            recoveryAssert($browser->request('?client=profile')['status'] === 302, 'Malformed version authenticated');
        }
    });
    $tests->test('canonical PDO numeric strings match the authenticated version', function () use ($reset, $newBrowser): void {
        $reset(); $browser = $newBrowser(); $browser->login();
        $session = $browser->session(); $session['session_version'] = '0'; $browser->replaceSession($session);
        recoveryAssert($browser->request('?client=profile')['status'] === 200, 'Canonical numeric snapshot rejected');
    });
    $tests->test('malformed stored versions fail closed for both login and protected requests', function () use ($reset, $newBrowser, $db): void {
        $reset(); $browser = $newBrowser(); $browser->login();
        $db->exec("UPDATE account SET session_version = 'broken' WHERE tk_id = 2");
        recoveryAssert($browser->request('?client=profile')['status'] === 503, 'Malformed schema bypassed guard');
        recoveryAssert($newBrowser()->login()['status'] === 503, 'Malformed schema bypassed login');
    });
    $tests->test('password change still increments version and cancels tokens/jobs when recovery is off', function () use ($reset, $newBrowser, $db, $model, $key, $now): void {
        $reset(); $first = $newBrowser(); $second = $newBrowser(); $first->login(); $second->login();
        $model->request('customer@example.test', 'policy-change', $key, $now());
        recoveryAssert(is_array($model->issue($model->claim($now()), $now())), 'Token fixture failed');
        $db->exec("INSERT INTO password_reset_jobs (email,created_at,available_at) VALUES ('customer@example.test','2026-10-21','2026-10-21')");
        $fields = policyFields($first->request('?client=profile')['body'], 'profile-password-form');
        $response = $first->request('?client=profilePasswordChange', 'POST', array_merge($fields, [
            'current_password' => 'Original-Password!2026', 'new_password' => 'Policy-Changed!2026', 'confirm_password' => 'Policy-Changed!2026']));
        recoveryAssert($response['status'] === 303, 'Required password change failed');
        recoveryAssert((int) $db->query('SELECT session_version FROM account WHERE tk_id = 2')->fetchColumn() === 1, 'No version increment');
        recoveryAssert((int) $db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn() === 0, 'Token not revoked');
        recoveryAssert((int) $db->query("SELECT COUNT(*) FROM password_reset_jobs WHERE status IN ('pending','processing')")->fetchColumn() === 0, 'Job not canceled');
        recoveryAssert(($first->session()['session_version'] ?? null) === 1 && $first->request('?client=profile')['status'] === 200, 'Current device revoked');
        recoveryAssert($second->request('?client=profile')['status'] === 302, 'Other device retained access');
    });
    $tests->test('reset followed by recovery OFF never revives either old device', function () use ($reset, $configure, $newBrowser, $model, $key, $now, $db): void {
        $reset(); $configure(['recovery' => true]);
        $first = $newBrowser(); $second = $newBrowser(); $first->login(); $second->login();
        $postFields = policyFields($second->request('?client=profile')['body']);
        $model->request('customer@example.test', 'policy-reset', $key, $now());
        $issued = $model->issue($model->claim($now()), $now());
        $resetter = $newBrowser();
        $fields = policyFields($resetter->request('?client=resetPassword')['body']);
        $response = $resetter->request('?client=resetPassword', 'POST', array_merge($fields, [
            'token' => $issued['token'], 'new_password' => 'Policy-Recovered!2026', 'confirm_password' => 'Policy-Recovered!2026']));
        recoveryAssert($response['status'] === 303 && (int) $db->query('SELECT session_version FROM account WHERE tk_id = 2')->fetchColumn() === 1, 'Reset failed');
        $configure(['recovery' => false]);
        recoveryAssert($first->request('?client=profile')['status'] === 302, 'OFF revived old GET session');
        recoveryAssert($second->request('?client=profileUpdate', 'POST', ['csrf_token' => $postFields['csrf_token'],
            'email' => 'customer@example.test', 'address' => 'Unauthorized after reset'])['status'] === 403, 'OFF revived old POST session');
        $fresh = $newBrowser();
        recoveryAssert($fresh->login('customer', 'Policy-Recovered!2026')['status'] === 302, 'Fresh login while OFF failed');
        recoveryAssert(($fresh->session()['session_version'] ?? null) === 1, 'Fresh login omitted version');
        $configure(['recovery' => true]);
        recoveryAssert($fresh->request('?client=profile')['status'] === 200, 'ON revoked valid session');
        recoveryAssert($fresh->request('?client=forgotPassword')['status'] === 200, 'Recovery did not reenable');
    });
    $tests->test('required guard fails closed when the version column disappears', function () use ($reset, $configure, $newBrowser): void {
        $reset(); $browser = $newBrowser(); $browser->login();
        $configure(['schema' => 'legacy']);
        recoveryAssert($browser->request('?client=profile')['status'] === 503, 'Missing schema bypassed guard');
    });
    $tests->test('required login refuses missing schema before legacy password upgrade', function () use ($reset, $configure, $newBrowser, $legacy): void {
        $reset(); $configure(['schema' => 'legacy']);
        $before = $legacy->query('SELECT tk_password FROM account WHERE tk_id = 4')->fetchColumn();
        $browser = $newBrowser();
        recoveryAssert($browser->login('legacy', 'Legacy-Plain!2026')['status'] === 503, 'Missing schema login accepted');
        recoveryAssert($legacy->query('SELECT tk_password FROM account WHERE tk_id = 4')->fetchColumn() === $before, 'Failed login upgraded password');
        recoveryAssert(!isset($browser->session()['user_id']), 'Failed login authenticated');
    });
    $tests->test('required database outage returns 503 for guard and login without leaking details', function () use ($reset, $configure, $newBrowser): void {
        $reset(); $browser = $newBrowser(); $browser->login();
        $configure(['database_error' => true]);
        $guard = $browser->request('?client=profile');
        $login = $newBrowser()->login();
        recoveryAssert($guard['status'] === 503 && $login['status'] === 503, 'Database outage bypassed policy');
        recoveryAssert(!str_contains($guard['body'] . $login['body'], 'private-fixture-database-outage'), 'Database error exposed');
    });
    $tests->test('invalid policy configuration refuses authentication rather than falling back', function () use ($reset, $configure, $newBrowser): void {
        $reset(); $browser = $newBrowser(); $browser->login();
        $configure(['mode' => 'requried']);
        recoveryAssert($browser->request('?client=profile')['status'] === 503, 'Invalid mode bypassed guard');
        recoveryAssert($newBrowser()->login()['status'] === 503, 'Invalid mode bypassed login');
    });
    $tests->test('activating required policy invalidates pre-policy sessions', function () use ($reset, $configure, $newBrowser): void {
        $reset(); $configure(['mode' => 'legacy']);
        $browser = $newBrowser(); $browser->login();
        recoveryAssert(!array_key_exists('session_version', $browser->session()), 'Legacy fixture versioned unexpectedly');
        $configure(['mode' => 'required']);
        recoveryAssert($browser->request('?client=profile')['status'] === 302, 'Old unversioned session accepted after activation');
    });
    $tests->test('Admin permissions remain enforced and Admin versions are revoked while recovery is off', function () use ($reset, $newBrowser, $db): void {
        $reset(); $admin = $newBrowser();
        recoveryAssert($admin->login('admin')['status'] === 302, 'Required Admin login failed');
        recoveryAssert($admin->request('?act=admin&admin=accountList')['status'] === 200, 'Required Admin route blocked');
        $client = $newBrowser(); $client->login();
        recoveryAssert($client->request('?act=admin&admin=accountList')['status'] === 403, 'Client escalated privileges');
        recoveryAssert($newBrowser()->request('?act=admin&admin=accountList')['status'] === 302, 'Anonymous Admin access accepted');
        $db->exec('UPDATE account SET session_version = 1 WHERE tk_id = 1');
        recoveryAssert($admin->request('?act=admin&admin=accountList')['status'] === 302, 'Stale Admin session accepted');
    });
    $tests->test('recovery cannot run under legacy session policy', function () use ($reset, $configure, $newBrowser, $db): void {
        $reset(); $configure(['mode' => 'legacy', 'recovery' => true]);
        recoveryAssert($newBrowser()->request('?client=forgotPassword')['status'] === 503, 'Unsafe recovery configuration accepted');
        recoveryAssert((int) $db->query('SELECT COUNT(*) FROM password_reset_jobs')->fetchColumn() === 0, 'Invalid configuration enqueued mail');
    });
    $tests->test('required password model never falls back to a legacy write on missing schema', function () use ($legacy): void {
        putenv('FOURSMART_SESSION_VERSION_MODE=required');
        putenv('FOURSMART_PASSWORD_RECOVERY_ENABLED=0');
        (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $legacy);
        $before = $legacy->query('SELECT tk_password FROM account WHERE tk_id = 3')->fetchColumn();
        $rejected = false;
        try { AccountModel::changePassword(3, 'Original-Password!2026', 'Forbidden-Change!2026'); }
        catch (RuntimeException $expected) { $rejected = true; }
        recoveryAssert($rejected, 'Required model fell back to legacy');
        recoveryAssert($legacy->query('SELECT tk_password FROM account WHERE tk_id = 3')->fetchColumn() === $before, 'Missing schema changed password');
    });
    $tests->test('HTTP diagnostics do not contain fixture passwords or private HMAC', function () use ($directory, $key): void {
        $log = file_get_contents($directory . '/server.log');
        foreach (['Policy-Changed!2026', 'Policy-Recovered!2026', 'Legacy-Plain!2026', base64_encode($key), 'private-fixture-database-outage'] as $secret) {
            recoveryAssert(!str_contains($log, $secret), 'Private fixture value leaked');
        }
    });
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    putenv($originalMode === false ? 'FOURSMART_SESSION_VERSION_MODE' : 'FOURSMART_SESSION_VERSION_MODE=' . $originalMode);
    putenv($originalRecovery === false ? 'FOURSMART_PASSWORD_RECOVERY_ENABLED' : 'FOURSMART_PASSWORD_RECOVERY_ENABLED=' . $originalRecovery);
    (new ReflectionProperty(Database::class, 'connection'))->setValue(null, null);
    unset($model, $reset, $seed, $db, $legacy);
    recoveryRemoveDirectory($directory);
}
$tests->finish();
