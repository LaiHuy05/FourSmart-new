<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/support/guarded_mariadb.php';
require_once __DIR__ . '/../core/bootstrap.php';

try {
    $path = mariaConfigPath($argv);
    $config = mariaPrivateConfig($path);
    $db = new GuardedMariaDB($config);
    $db->assertFixture();
    mariaTestEnvironment($config);
    (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $db);
    if (in_array('--worker', $argv, true)) {
        // Exercise the real worker entry point with the guarded injected PDO.
        require __DIR__ . '/../cli/password_recovery_worker.php';
        exit(0);
    }
    if (in_array('--reset-demo', $argv, true)) {
        $db->exec('START TRANSACTION');
        try {
            $db->exec("DELETE FROM password_reset_jobs WHERE email = 'customer_test@example.test'");
            $db->exec('DELETE FROM password_reset_rate_limits');
            $db->exec('DELETE FROM account WHERE tk_id = 9000002');
            $db->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role)
                VALUES (9000002, ?, ?, ?, ?, 2)')->execute(['customer_test', password_hash('Original-Password!2026', PASSWORD_BCRYPT),
                    'customer_test@example.test', 'Synthetic preview account']);
            $db->exec('COMMIT');
        } catch (Throwable $error) {
            $db->exec('ROLLBACK');
            throw $error;
        }
    }
    if ($db->query("SELECT tk_user FROM account WHERE tk_id = 9000002")->fetchColumn() !== 'customer_test') {
        throw new RuntimeException('Demo missing; use --reset-demo to seed the synthetic account.');
    }
    if (in_array('--seed-only', $argv, true)) {
        echo "PASS synthetic customer_test ready in duan1_test; no server started.\n";
        exit(0);
    }
    $parts = parse_url($config['app_url']);
    if (($parts['host'] ?? '') !== 'localhost' || ($parts['scheme'] ?? '') !== 'http' || !isset($parts['port'])) {
        throw new RuntimeException('Expected a localhost HTTP preview URL with explicit port.');
    }
    $address = '127.0.0.1:' . $parts['port'];
    $probe = @stream_socket_server('tcp://' . $address, $errorCode, $errorMessage);
    if (!is_resource($probe)) throw new RuntimeException('Preview port is already in use.');
    fclose($probe);
    if (!is_dir($config['private_directory'] . '/sessions')) mkdir($config['private_directory'] . '/sessions', 0700);
    $environment = getenv();
    $environment['FOURSMART_MARIADB_PRIVATE_CONFIG'] = $path;
    $environment['FOURSMART_RECOVERY_TEST_DIRECTORY'] = $config['private_directory'];
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', dirname(__DIR__), __DIR__ . '/support/password_recovery_mariadb_router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $config['private_directory'] . '/preview.log', 'a'],
            2 => ['file', $config['private_directory'] . '/preview.log', 'a']], $pipes, dirname(__DIR__), $environment, ['bypass_shell' => true]);
    if (!is_resource($server)) throw new RuntimeException('Preview server did not start.');
    fclose($pipes[0]);
    echo 'Database: duan1_test; restricted identity; separate FOURSMART_TESTSESSID cookie.', PHP_EOL;
    echo 'Chrome: ', $config['app_url'], '?client=forgotPassword', PHP_EOL;
    echo 'Synthetic login: customer_test / Original-Password!2026 (after --reset-demo).', PHP_EOL;
    echo 'Email: customer_test@example.test; run this script with --worker after submitting.', PHP_EOL;
    echo 'Press Enter to stop this server. Test database and private configuration are retained.', PHP_EOL;
    fgets(STDIN);
    proc_terminate($server);
    proc_close($server);
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL preview: check private config, approved schema, demo seed and free localhost port.\n");
    exit(1);
}
