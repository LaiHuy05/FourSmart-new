<?php

// Run only against an already started, isolated MariaDB preview server.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/support/guarded_mariadb.php';
$stage = 'private configuration';
try {
    $path = mariaConfigPath($argv);
    $config = mariaPrivateConfig($path);
    $db = new GuardedMariaDB($config);
    $db->assertFixture();
    $parts = parse_url($config['app_url']);
    if (($parts['host'] ?? '') !== 'localhost') throw new RuntimeException('Unsafe preview URL');
    $base = $config['app_url'];
    $curl = curl_init();
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => '', CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_RESOLVE => ['localhost:' . $parts['port'] . ':127.0.0.1']]);
    $request = static function (string $route, ?array $data = null) use ($curl, $base): array {
        curl_setopt($curl, CURLOPT_URL, $base . '?' . $route);
        curl_setopt($curl, CURLOPT_POSTFIELDS, null);
        curl_setopt($curl, CURLOPT_HTTPGET, true);
        if ($data !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($curl);
        if (!is_string($response)) throw new RuntimeException('Local HTTP failed');
        $length = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        return [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), substr($response, $length), substr($response, 0, $length)];
    };
    $csrf = static function (string $html): string {
        if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $match)) throw new RuntimeException('Missing CSRF');
        return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    };
    $stage = 'forgot page and cookie';
    [$status, $body, $headers] = $request('client=forgotPassword');
    if ($status !== 200 || !str_contains($headers, 'FOURSMART_TESTSESSID=')) throw new RuntimeException('Preview/cookie mismatch');
    $stage = 'forgot submission';
    if ($request('client=forgotPassword', ['csrf_token' => $csrf($body), 'email' => 'customer_test@example.test'])[0] !== 303) throw new RuntimeException('Request failed');
    $stage = 'actual CLI worker';
    $process = proc_open([PHP_BINARY, __DIR__ . '/password_recovery_mariadb_preview.php', '--worker', '--private-config=' . $path],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Worker did not start');
    fclose($pipes[0]);
    $workerOutput = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $workerError = stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || !preg_match('/^sent\r?$/m', trim($workerOutput))) throw new RuntimeException('Worker failed');
    $stage = 'Mailpit token';
    $hash = $db->query('SELECT token_hash FROM password_reset_tokens WHERE account_id = 9000002')->fetchColumn();
    $api = curl_init('http://127.0.0.1:8025/api/v1/messages?limit=30');
    curl_setopt_array($api, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 5]);
    $messages = json_decode(curl_exec($api), true, 32, JSON_THROW_ON_ERROR);
    $token = null;
    foreach ($messages['messages'] as $message) {
        if (($message['To'][0]['Address'] ?? '') !== 'customer_test@example.test') continue;
        curl_setopt($api, CURLOPT_URL, 'http://127.0.0.1:8025/api/v1/message/' . rawurlencode($message['ID']));
        $mail = json_decode(curl_exec($api), true, 32, JSON_THROW_ON_ERROR);
        if (preg_match('/#token=([0-9a-f]{64})/', $mail['Text'] ?? '', $match)
            && is_string($hash) && hash_equals($hash, hash('sha256', $match[1], true))) {
            $token = $match[1]; $messageId = $message['ID']; break;
        }
    }
    if ($token === null) throw new RuntimeException('Exact Mailpit token missing');
    $stage = 'reset submission';
    [$status, $body] = $request('client=resetPassword');
    if ($status !== 200 || $request('client=resetPassword', ['csrf_token' => $csrf($body), 'token' => $token,
        'new_password' => 'Preview-Password!2026', 'confirm_password' => 'Preview-Password!2026'])[0] !== 303) throw new RuntimeException('Reset failed');
    if ($request('client=profile')[0] !== 302) throw new RuntimeException('Unexpected automatic login');
    $stage = 'password authentication';
    foreach (['Original-Password!2026' => 200, 'Preview-Password!2026' => 302] as $password => $expected) {
        [, $loginPage] = $request('client=login');
        if ($request('client=login', ['csrf_token' => $csrf($loginPage), 'username' => 'customer_test', 'password' => $password])[0] !== $expected) {
            throw new RuntimeException('Password authentication result mismatch');
        }
    }
    $stage = 'profile and home';
    if ($request('client=profile')[0] !== 200 || $request('client=home')[0] !== 200) throw new RuntimeException('Preview routes unavailable');
    echo "PASS isolated preview -> forgot request -> actual CLI worker -> Mailpit -> reset -> login -> profile/home.\n";
    echo 'Mailpit message ID: ', $messageId, '; application URL: ', $base, PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL isolated preview flow at ' . $stage . ' (' . get_class($error) . "); no production fallback.\n");
    exit(1);
}
