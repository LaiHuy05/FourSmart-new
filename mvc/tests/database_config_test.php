<?php

// Configuration failures are exercised in fresh CLI processes. No real database is used.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
ini_set('zend.exception_ignore_args', '1');
$directory = sys_get_temp_dir() . '/foursmart-db-config-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) exit(1);
$probe = $directory . '/probe.php';
$source = realpath(__DIR__ . '/../core/Database.php');
file_put_contents($probe, '<?php ini_set("display_errors", "0"); ini_set("zend.exception_ignore_args", "1"); require '
    . var_export($source, true) . '; try { Database::connection(); echo "CONNECTED"; exit(2); }'
    . ' catch (RuntimeException $error) { echo $error->getMessage(); exit(0); }');
$passed = 0;
$failed = 0;
$base = ['host' => '127.0.0.1', 'port' => 1, 'database' => 'fixture',
    'user' => 'fixture_runtime', 'password' => 'synthetic-config-test-only'];
$run = function (string $name, string|false $path, string $expected) use ($probe, &$passed, &$failed): void {
    $environment = getenv();
    unset($environment['FOURSMART_DATABASE_CONFIG']);
    if ($path !== false) $environment['FOURSMART_DATABASE_CONFIG'] = $path;
    $process = proc_open([PHP_BINARY, $probe], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes, null, $environment, ['bypass_shell' => true]);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit === 0 && $output === $expected
        && !str_contains($output . $errors, 'synthetic-config-test-only')
        && ($path === false || !str_contains($output . $errors, $path))) {
        $passed++; echo 'PASS ', $name, PHP_EOL;
    } else {
        $failed++; echo 'FAIL ', $name, PHP_EOL;
    }
};
try {
    $unavailable = 'Database configuration is unavailable.';
    $run('missing configuration has no legacy default', false, $unavailable);
    $run('missing file is rejected', $directory . '/absent.json', $unavailable);
    $run('configuration inside source is rejected', __DIR__ . '/../../composer.json', $unavailable);
    $path = $directory . '/connection.json';
    foreach (['malformed JSON' => '{', 'non-object JSON' => 'null'] as $name => $json) {
        file_put_contents($path, $json); $run($name, $path, $unavailable);
    }
    foreach (['localhost' => ['host', 'localhost'], 'root account' => ['user', 'root'],
        'zero port' => ['port', 0], 'string port' => ['port', '33367'],
        'DSN injection' => ['database', 'fixture;port=3306'], 'empty password' => ['password', '']] as $name => [$key, $value]) {
        $config = $base; $config[$key] = $value;
        file_put_contents($path, json_encode($config, JSON_THROW_ON_ERROR));
        $run($name . ' is rejected', $path, $unavailable);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    if (!is_resource($socket)) throw new RuntimeException('Cannot reserve isolated test port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $config = $base; $config['port'] = (int) substr(strrchr($address, ':'), 1);
    file_put_contents($path, json_encode($config, JSON_THROW_ON_ERROR));
    $run('failed explicit endpoint returns a safe error without fallback', $path, 'Database connection is unavailable.');
} finally {
    $resolved = realpath($directory);
    if ($resolved && dirname($resolved) === realpath(sys_get_temp_dir())
        && str_starts_with(basename($resolved), 'foursmart-db-config-')) {
        foreach (['probe.php', 'connection.json'] as $name) if (is_file($resolved . '/' . $name)) unlink($resolved . '/' . $name);
        rmdir($resolved);
    }
}
echo $passed, ' passed, ', $failed, ' failed', PHP_EOL;
exit($failed === 0 ? 0 : 1);
