<?php

// Explicit, one-time provisioning. Never invoked by the application or tests.
if (PHP_SAPI !== 'cli' || !in_array('--approved-duan1-test', $argv, true)) {
    http_response_code(404);
    exit(1);
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/support/guarded_mariadb.php';

$path = mariaConfigPath($argv);
$directory = realpath(dirname($path));
if (!$directory || dirname($directory) !== realpath(sys_get_temp_dir())
    || !preg_match('/\Afoursmart-mariadb-test-[0-9a-f]{16}\z/', basename($directory))
    || basename($path) !== 'connection.json' || is_file($path)) {
    fwrite(STDERR, "FAIL: expected a fresh, ACL-protected private configuration directory.\n");
    exit(1);
}
$migration = __DIR__ . '/../migrations/20261021_client_password_recovery.proposed.sql';
$migrationHash = hash_file('sha256', $migration);
$admin = null;
try {
    // This identity is used only for read-only introspection and provisioning.
    $admin = new PDO('mysql:host=127.0.0.1;port=3306;dbname=mysql;charset=utf8mb4', 'root',
        getenv('FOURSMART_MYSQL_PROVISION_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ($admin->query('SELECT VERSION()')->fetchColumn() !== '10.4.32-MariaDB') throw new RuntimeException('Unexpected server version.');
    $existingDatabase = (int) $admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'duan1_test'")->fetchColumn();
    $existingUser = (int) $admin->query("SELECT COUNT(*) FROM mysql.user WHERE User = 'foursmart_duan1_test'")->fetchColumn();
    $resume = in_array('--resume-empty-provisioning', $argv, true);
    if ($resume) {
        if ($existingDatabase !== 1 || $existingUser !== 1
            || !is_file($directory . '/production-schema-before.json')
            || (int) $admin->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'duan1_test'")->fetchColumn() !== 0) {
            throw new RuntimeException('Only this previously inspected, empty provisioning can resume.');
        }
    } elseif ($existingDatabase !== 0 || $existingUser !== 0) {
        throw new RuntimeException('Existing database/user; refusing to overwrite them.');
    }
    $admin->exec('START TRANSACTION READ ONLY');
    $tables = $admin->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'duan1' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $schema = [];
    foreach ($tables as $table) {
        if (!preg_match('/\A[a-z][a-z0-9_]*\z/', $table)) throw new RuntimeException('Unexpected table identifier.');
        $schema[$table] = $admin->query('SHOW CREATE TABLE `duan1`.`' . $table . '`')->fetch()['Create Table'];
    }
    $dependencies = [];
    foreach ($admin->query("SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'duan1' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll() as $row) {
        $dependencies[$row['TABLE_NAME']][] = $row['REFERENCED_TABLE_NAME'];
    }
    $admin->exec('ROLLBACK');
    $ordered = [];
    while (count($ordered) < count($tables)) {
        $progress = false;
        foreach ($tables as $table) {
            if (in_array($table, $ordered, true)) continue;
            if (array_diff($dependencies[$table] ?? [], $ordered)) continue;
            $ordered[] = $table;
            $progress = true;
        }
        if (!$progress) throw new RuntimeException('Cyclic or external schema dependency.');
    }
    $before = ['tables' => $schema, 'migration_sha256' => $migrationHash];
    if ($resume) {
        if (json_decode(file_get_contents($directory . '/production-schema-before.json'), true, 64, JSON_THROW_ON_ERROR) !== $before) {
            throw new RuntimeException('Production schema or migration changed since initial provisioning.');
        }
    } else {
        file_put_contents($directory . '/production-schema-before.json', json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
    $password = bin2hex(random_bytes(32));
    $config = ['database' => GuardedMariaDB::DATABASE, 'user' => GuardedMariaDB::USER, 'password' => $password,
        'hmac_key' => base64_encode(random_bytes(32)), 'app_url' => 'http://localhost:8091/index.php'];
    $provision = function (string $sql) use ($admin): void {
        if ($admin->query('SELECT DATABASE()')->fetchColumn() !== 'mysql'
            || !preg_match('/\A(?:CREATE DATABASE `duan1_test`|(?:CREATE|ALTER) USER \'foursmart_duan1_test\'@\'127\.0\.0\.1\'|GRANT [A-Z, ]+ ON `duan1_test`\.`[a-z][a-z0-9_]*` TO \'foursmart_duan1_test\'@\'127\.0\.0\.1\')/', $sql)) {
            throw new RuntimeException('Refusing an unexpected provisioning target.');
        }
        $admin->exec($sql);
    };
    if (!$resume) $provision('CREATE DATABASE `duan1_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $provision(($resume ? 'ALTER' : 'CREATE') . " USER 'foursmart_duan1_test'@'127.0.0.1' IDENTIFIED BY " . $admin->quote($password));
    // Exact-table grants avoid wildcard database privileges and mysql.db writes.
    foreach (array_merge($ordered, ['recovery_test_guard', 'password_reset_tokens', 'password_reset_rate_limits', 'password_reset_jobs']) as $table) {
        $provision('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, TRIGGER ON `duan1_test`.`'
            . $table . "` TO 'foursmart_duan1_test'@'127.0.0.1'");
    }
    $admin = null;
    $db = new GuardedMariaDB($config);
    $db->exec('SET SESSION foreign_key_checks = 1');
    $db->exec('SET SESSION check_constraint_checks = 1');
    foreach ($ordered as $table) {
        $ddl = preg_replace('/ AUTO_INCREMENT=\d+/', '', $schema[$table]);
        if (preg_match('/`duan1`\s*\./i', $ddl)) throw new RuntimeException('Qualified production schema reference.');
        $db->exec($ddl);
    }
    echo 'PASS structure copied: ', count($ordered), ' tables; zero source rows selected or copied.', PHP_EOL;
    $db->exec('CREATE TABLE recovery_test_guard (marker VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $db->prepare('INSERT INTO recovery_test_guard (marker) VALUES (?)')->execute([GuardedMariaDB::MARKER]);
    $db->assertFixture();
    // Apply precisely the approved file, once, as the restricted identity.
    $sql = preg_replace('/^\s*--[^\n]*(?:\n|$)/m', '', file_get_contents($migration));
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    if (count($statements) !== 4) throw new RuntimeException('Unexpected migration statement count.');
    foreach ($statements as $index => $statement) {
        try {
            $db->exec($statement);
        } catch (PDOException $error) {
            echo 'STOP: migration statement ', $index + 1, ' incompatible/failed; SQLSTATE ', $error->getCode(),
                ', MariaDB error ', (int) ($error->errorInfo[1] ?? 0), '. No SQL/code repair attempted.', PHP_EOL;
            exit(2);
        }
        echo 'PASS migration statement ', $index + 1, '/4; database=duan1_test', PHP_EOL;
    }
    if (hash_file('sha256', $migration) !== $migrationHash) throw new RuntimeException('Migration file changed.');
    $db->exec("INSERT INTO role (role_id, role_name) VALUES (1, 'admin'), (2, 'user')");
    echo 'PASS restricted identity: ', GuardedMariaDB::USER, '@127.0.0.1; grants restricted to duan1_test.', PHP_EOL;
    echo 'PASS guarded writes: ', $db->guardedWrites, '; migration SHA-256: ', $migrationHash, PHP_EOL;
    echo 'Private config: ', $path, PHP_EOL;
} catch (Throwable $error) {
    // Never echo admin SQL, generated passwords, private keys or PDO arguments.
    fwrite(STDERR, 'STOP provisioning failed (' . get_class($error) . "); inspect state before retrying. No automatic repair.\n");
    exit(1);
}
