<?php

// Never creates a database or applies migrations. A temporary fault-injection
// trigger is created/dropped only through the guarded duan1_test connection.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/support/guarded_mariadb.php';
$child = in_array('--race-child', $argv, true);
$privatePath = mariaConfigPath($argv);
if ($privatePath === '' || (!$child && !in_array('--run-approved', $argv, true))
    || ($child && getenv('FOURSMART_MYSQL_TEST_APPROVED') !== '1')) {
    echo "NOT TESTED MariaDB: separate approval and a pre-provisioned disposable schema are required.\n";
    exit(0);
}
require_once __DIR__ . '/support/password_recovery_fixture.php';
try {
    $private = mariaPrivateConfig($privatePath);
    $db = new GuardedMariaDB($private);
    $db->assertFixture();
    putenv('FOURSMART_MYSQL_TEST_APPROVED=1');
    putenv('FOURSMART_MARIADB_PRIVATE_CONFIG=' . $privatePath);
    mariaTestEnvironment($private);
    (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $db);
    $db->query('SELECT session_version FROM account LIMIT 0');
    $db->query('SELECT token_hash FROM password_reset_tokens LIMIT 0');
    $db->query('SELECT lock_token FROM password_reset_jobs LIMIT 0');
    $model = new PasswordRecoveryModel($db);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if ($child) {
        $input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
        $time = new DateTimeImmutable($input['now']);
        $barrier = $input['barrier'] ?? '';
        if (dirname($barrier) !== $private['private_directory']
            || !preg_match('/\Arace-(?:request|worker|reset)-[0-9a-f]{8}\z/', basename($barrier))) exit(4);
        file_put_contents($barrier . '.ready.' . (int) $input['participant'], (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
        $deadline = microtime(true) + 6;
        while (!is_file($barrier . '.go')) {
            if (microtime(true) > $deadline) exit(4);
            usleep(1000);
            clearstatcache();
        }
        if ($input['operation'] === 'reset') {
            echo $model->reset(hex2bin($input['hash']), 'Concurrent-Password!2026', $time) ? '1' : '0';
        } elseif ($input['operation'] === 'request') {
            echo $model->request('__recovery_fixture_customer@example.test', 'test-ip', str_repeat('k', 32), $time) ? '1' : '0';
        } elseif ($input['operation'] === 'worker') {
            $service = new PasswordRecoveryService($model, ['key' => str_repeat('k', 32), 'url' => 'http://localhost/FourSmart/mvc/index.php'],
                function (): void { usleep(150000); }, fn() => $time);
            echo $service->processOne();
        } else {
            exit(2);
        }
        exit(0);
    }
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL MariaDB guard: private configuration or approved fixture schema unavailable.\n");
    exit(1);
}
$tests = new RecoveryTests();
$key = str_repeat('k', 32);
$email = '__recovery_fixture_customer@example.test';
$clean = function () use ($db, $email): void {
    $db->prepare('DELETE FROM password_reset_jobs WHERE email = ?')->execute([$email]);
    $db->exec('DELETE FROM password_reset_rate_limits');
    $db->exec('DELETE FROM account WHERE tk_id = 9000002');
};
$seed = function () use ($db, $email, $clean): void {
    $clean();
    $db->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role)
        VALUES (9000002, ?, ?, ?, ?, 2)')->execute(['__recovery_fixture_customer', password_hash('Original-Password!2026', PASSWORD_BCRYPT), $email, 'Test fixture']);
};
$token = function () use ($seed, $model, $email, $key, $now): string {
    $seed();
    recoveryAssert($model->request($email, 'test-ip', $key, $now), 'Fixture request failed');
    $issued = $model->issue($model->claim($now), $now);
    recoveryAssert(is_array($issued), 'Fixture token missing');
    return $issued['token'];
};
try {
    $tests->test('restricted identity and per-write target checks reject production targets before execution', function () use ($db): void {
        $db->assertTarget();
        foreach (['USE duan1', 'UPDATE `duan1`.`account` SET tk_user = tk_user'] as $statement) {
            try { $db->guardStatement($statement); }
            catch (RuntimeException $expected) { continue; }
            throw new LogicException('Unsafe target accepted');
        }
        recoveryAssert($db->query('SELECT DATABASE()')->fetchColumn() === 'duan1_test', 'Target changed');
    });
    $tests->test('MariaDB schema has enabled checks, InnoDB and signed INT recovery FK', function () use ($db): void {
        recoveryAssert((int) $db->query('SELECT @@session.check_constraint_checks')->fetchColumn() === 1, 'Checks disabled');
        $columns = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
            AND ((TABLE_NAME = 'account' AND COLUMN_NAME = 'tk_id') OR (TABLE_NAME = 'password_reset_tokens' AND COLUMN_NAME = 'account_id'))")->fetchAll(PDO::FETCH_COLUMN);
        recoveryAssert(count($columns) === 2 && $columns[0] === $columns[1] && !str_contains($columns[0], 'unsigned'), 'FK type mismatch');
        recoveryAssert((int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('account','password_reset_tokens','password_reset_jobs','password_reset_rate_limits') AND ENGINE = 'InnoDB'")->fetchColumn() === 4, 'Wrong engine');
    });
    $tests->test('MariaDB CHECK rejects invalid TTL and FK rejects orphan token', function () use ($db, $seed, $now): void {
        $seed();
        $sql = 'INSERT INTO password_reset_tokens (account_id, token_hash, password_fingerprint, email_fingerprint,
            issued_session_version, created_at, expires_at) VALUES (?, UNHEX(?), UNHEX(?), UNHEX(?), 0, ?, ?)';
        foreach ([[9000002, '+1 minute', 4025], [2147483647, '+15 minutes', 1452]] as [$id, $ttl, $expected]) {
            try {
                $db->prepare($sql)->execute([$id, bin2hex(random_bytes(32)), bin2hex(random_bytes(32)), bin2hex(random_bytes(32)),
                    PasswordRecoveryModel::stamp($now), PasswordRecoveryModel::stamp($now->modify($ttl))]);
                throw new LogicException('Constraint not enforced');
            } catch (PDOException $error) {
                recoveryAssert((int) ($error->errorInfo[1] ?? 0) === $expected, 'Unexpected constraint result');
            }
        }
    });
    $tests->test('session_version, all recovery indexes, FK actions and eight CHECK constraints exist', function () use ($db): void {
        $column = $db->query("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'account' AND COLUMN_NAME = 'session_version'")->fetch();
        recoveryAssert($column && str_contains($column['COLUMN_TYPE'], 'unsigned') && $column['IS_NULLABLE'] === 'NO'
            && (int) $column['COLUMN_DEFAULT'] === 0, 'Wrong session version column');
        $expected = [
            'account:idx_account_recovery_email' => 'tk_email,id_role',
            'password_reset_tokens:PRIMARY' => 'account_id',
            'password_reset_tokens:uq_password_reset_token_hash' => 'token_hash',
            'password_reset_tokens:idx_password_reset_token_expiry' => 'expires_at',
            'password_reset_rate_limits:PRIMARY' => 'scope,subject_hash',
            'password_reset_rate_limits:idx_password_reset_rate_expiry' => 'window_ends_at',
            'password_reset_jobs:idx_password_reset_job_ready' => 'status,available_at,id',
            'password_reset_jobs:idx_password_reset_job_lease' => 'status,locked_until,id',
            'password_reset_jobs:idx_password_reset_job_email' => 'email,status,id',
            'password_reset_jobs:idx_password_reset_job_retention' => 'created_at',
        ];
        $actual = [];
        foreach ($db->query('SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() GROUP BY TABLE_NAME, INDEX_NAME')->fetchAll() as $row) {
            $actual[$row['TABLE_NAME'] . ':' . $row['INDEX_NAME']] = $row['columns_list'];
        }
        foreach ($expected as $name => $columns) recoveryAssert(($actual[$name] ?? null) === $columns, 'Missing or incorrect index');
        recoveryAssert(($actual['password_reset_jobs:PRIMARY'] ?? null) === 'id', 'Missing job primary key');
        recoveryAssert((int) $db->query("SELECT NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'password_reset_tokens' AND INDEX_NAME = 'uq_password_reset_token_hash'")->fetchColumn() === 0, 'Token hash index is not UNIQUE');
        recoveryAssert((int) $db->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'password_reset_tokens' AND COLUMN_NAME = 'token_hash' AND DATA_TYPE = 'binary'")->fetchColumn() === 32, 'Token digest is not BINARY(32)');
        $fk = $db->query("SELECT DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_password_reset_token_account'")->fetch();
        recoveryAssert($fk && $fk['DELETE_RULE'] === 'CASCADE' && $fk['UPDATE_RULE'] === 'RESTRICT', 'Incorrect FK actions');
        recoveryAssert((int) $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('password_reset_tokens','password_reset_rate_limits','password_reset_jobs') AND CONSTRAINT_TYPE = 'CHECK'")->fetchColumn() === 8, 'Missing CHECK constraint');
    });
    $tests->test('rate window and job state, attempt, lease and completion CHECK constraints are enforced', function () use ($db, $now): void {
        $stamp = PasswordRecoveryModel::stamp($now);
        $cases = [
            ["INSERT INTO password_reset_rate_limits (scope, subject_hash, window_started_at, window_ends_at) VALUES ('invalid', UNHEX(?), ?, ?)",
                [bin2hex(random_bytes(32)), $stamp, PasswordRecoveryModel::stamp($now->modify('+15 minutes'))]],
            ["INSERT INTO password_reset_rate_limits (scope, subject_hash, window_started_at, window_ends_at) VALUES ('reset_ip', UNHEX(?), ?, ?)",
                [bin2hex(random_bytes(32)), $stamp, PasswordRecoveryModel::stamp($now->modify('+1 minute'))]],
        ];
        foreach ([['invalid', 0], ['pending', 4], ['processing', 1], ['sent', 1]] as [$status, $attempts]) {
            $cases[] = ['INSERT INTO password_reset_jobs (email, status, attempts, created_at, available_at) VALUES (?, ?, ?, ?, ?)',
                ['constraint@example.test', $status, $attempts, $stamp, $stamp]];
        }
        foreach ($cases as [$sql, $parameters]) {
            try { $db->prepare($sql)->execute($parameters); }
            catch (PDOException $error) {
                recoveryAssert((int) ($error->errorInfo[1] ?? 0) === 4025, 'Unexpected CHECK failure');
                continue;
            }
            throw new LogicException('CHECK accepted invalid data');
        }
    });
    $tests->test('token TTL is exactly 15 minutes and the exact expiration boundary is rejected', function () use ($db, $model, $token, $now): void {
        $raw = $token(); $hash = hash('sha256', $raw, true);
        recoveryAssert((int) $db->query('SELECT TIMESTAMPDIFF(SECOND, created_at, expires_at) FROM password_reset_tokens WHERE account_id = 9000002')->fetchColumn() === 900, 'Wrong token TTL');
        recoveryAssert($model->valid($hash, $now->modify('+899 seconds')), 'Premature expiry');
        recoveryAssert(!$model->valid($hash, $now->modify('+900 seconds')), 'Expiry boundary accepted');
        recoveryAssert(!$model->reset($hash, 'Expired-Password!2026', $now->modify('+900 seconds')), 'Expired reset succeeded');
        recoveryAssert((int) $db->query('SELECT session_version FROM account WHERE tk_id = 9000002')->fetchColumn() === 0, 'Expired reset wrote account');
    });
    $tests->test('successful reset commits password, version, token use and job cancellation; replay cannot write', function () use ($db, $model, $token, $now): void {
        $raw = $token(); $hash = hash('sha256', $raw, true);
        recoveryAssert($model->reset($hash, 'Recovered-Password!2026', $now), 'Valid reset failed');
        $after = $db->query('SELECT * FROM account WHERE tk_id = 9000002')->fetch();
        recoveryAssert((int) $after['session_version'] === 1 && password_verify('Recovered-Password!2026', $after['tk_password']), 'Password/version not committed');
        recoveryAssert($db->query('SELECT used_at FROM password_reset_tokens WHERE account_id = 9000002')->fetchColumn() !== null, 'Token not consumed');
        recoveryAssert($db->query("SELECT status FROM password_reset_jobs WHERE email = '__recovery_fixture_customer@example.test'")->fetchColumn() === 'discarded', 'Job not canceled');
        recoveryAssert(!$model->reset($hash, 'Replay-Password!2026', $now), 'Replay accepted');
        recoveryAssert($db->query('SELECT * FROM account WHERE tk_id = 9000002')->fetch() === $after, 'Replay mutated account');
    });
    $tests->test('injected token-write failure rolls the preceding password/version update back', function () use ($db, $model, $token, $now): void {
        $raw = $token(); $hash = hash('sha256', $raw, true);
        $before = $db->query('SELECT * FROM account WHERE tk_id = 9000002')->fetch();
        $db->exec("CREATE TRIGGER trg_test_reject_token_use BEFORE UPDATE ON password_reset_tokens
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'TEST_ONLY_TOKEN_WRITE_DENIED'");
        try {
            try { $model->reset($hash, 'Rollback-Password!2026', $now); }
            catch (PDOException $error) {
                recoveryAssert((int) ($error->errorInfo[1] ?? 0) === 1644, 'Unexpected injected failure');
                recoveryAssert($db->query('SELECT * FROM account WHERE tk_id = 9000002')->fetch() === $before, 'Partial password/version commit');
                recoveryAssert($model->valid($hash, $now), 'Token consumed on rollback');
                return;
            }
            throw new LogicException('Fault injection did not abort reset');
        } finally {
            $db->exec('DROP TRIGGER trg_test_reject_token_use');
        }
    });
    $tests->test('authenticated phase 1 password change also increments version and revokes recovery work', function () use ($db, $model, $token, $now): void {
        $raw = $token();
        recoveryAssert(AccountModel::changePassword(9000002, 'Original-Password!2026', 'Changed-Password!2026'), 'Phase 1 change rejected');
        recoveryAssert((int) $db->query('SELECT session_version FROM account WHERE tk_id = 9000002')->fetchColumn() === 1, 'Phase 1 version unchanged');
        recoveryAssert(!$model->valid(hash('sha256', $raw, true), $now), 'Old recovery link retained');
        recoveryAssert(!AccountModel::verifyPassword(AccountModel::find(9000002), 'Original-Password!2026'), 'Old password retained');
        recoveryAssert(AccountModel::verifyPassword(AccountModel::find(9000002), 'Changed-Password!2026'), 'New password rejected');
    });
    $tests->test('deleting the synthetic account cascades only its token', function () use ($db, $token): void {
        $token();
        $db->exec('DELETE FROM account WHERE tk_id = 9000002');
        recoveryAssert((int) $db->query('SELECT COUNT(*) FROM password_reset_tokens WHERE account_id = 9000002')->fetchColumn() === 0, 'Token FK did not cascade');
    });
    foreach (['request', 'worker', 'reset'] as $operation) {
        $tests->test('MariaDB concurrent independent processes: ' . $operation, function () use ($operation, $seed, $model, $db, $email, $key, $now, $private): void {
            $seed();
            $input = ['operation' => $operation, 'now' => $now->format('Y-m-d\TH:i:s.uP')];
            if ($operation !== 'request') {
                $model->request($email, 'test-ip', $key, $now);
            }
            if ($operation === 'reset') {
                $issued = $model->issue($model->claim($now), $now);
                $input['hash'] = hash('sha256', $issued['token']);
            }
            $children = [];
            $barrier = $private['private_directory'] . '/race-' . $operation . '-' . bin2hex(random_bytes(4));
            $input['barrier'] = $barrier;
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, __FILE__, '--race-child'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes, null, null, ['bypass_shell' => true]);
                recoveryAssert(is_resource($process), 'Child not started');
                $input['participant'] = $i;
                fwrite($pipes[0], json_encode($input)); fclose($pipes[0]);
                $children[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 5;
            while (!is_file($barrier . '.ready.0') || !is_file($barrier . '.ready.1')) {
                recoveryAssert(microtime(true) <= $deadline, 'Children did not reach the simultaneous start barrier');
                usleep(10000); clearstatcache();
            }
            $connections = [file_get_contents($barrier . '.ready.0'), file_get_contents($barrier . '.ready.1')];
            recoveryAssert($connections[0] !== $connections[1], 'Children shared a database connection');
            file_put_contents($barrier . '.go', 'start');
            $results = [];
            foreach ($children as [$process, $pipes]) {
                $results[] = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]);
                recoveryAssert(proc_close($process) === 0, 'Child failed');
            }
            sort($results);
            recoveryAssert($results === ($operation === 'worker' ? ['idle', 'sent'] : ['0', '1']), 'Concurrent operation not serialized');
            echo '  Independent connection IDs: ', implode(', ', $connections), '; one winner.', PHP_EOL;
            foreach (['.ready.0', '.ready.1', '.go'] as $suffix) unlink($barrier . $suffix);
        });
    }
    $tests->test('MariaDB rejects password reuse without consuming the token', function () use ($db, $seed, $model, $email, $key, $now): void {
        $seed();
        $model->request($email, 'test-ip', $key, $now);
        $issued = $model->issue($model->claim($now), $now);
        $hash = hash('sha256', $issued['token'], true);
        $before = $db->query('SELECT tk_password FROM account WHERE tk_id = 9000002')->fetchColumn();
        recoveryAssert(!$model->reset($hash, 'Original-Password!2026', $now), 'Same password accepted');
        recoveryAssert($db->query('SELECT tk_password FROM account WHERE tk_id = 9000002')->fetchColumn() === $before && $model->valid($hash, $now), 'Rejected write mutated state');
    });
    if (in_array('--mailpit', $argv, true)) {
        $tests->test('MariaDB-backed worker sends real PHPMailer email to loopback Mailpit', function () use ($db, $seed, $model, $email, $key, $now, $private): void {
            $seed();
            $model->request($email, 'mailpit-test-ip', $key, $now);
            $service = new PasswordRecoveryService($model, ['key' => base64_decode($private['hmac_key'], true), 'url' => $private['app_url']]);
            recoveryAssert($service->processOne() === 'sent', 'SMTP delivery failed');
            $hash = $db->query('SELECT token_hash FROM password_reset_tokens WHERE account_id = 9000002')->fetchColumn();
            $curl = curl_init('http://127.0.0.1:8025/api/v1/messages?limit=30');
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 5]);
            $list = json_decode(curl_exec($curl), true, 32, JSON_THROW_ON_ERROR);
            foreach ($list['messages'] as $message) {
                if (($message['To'][0]['Address'] ?? '') !== $email) continue;
                curl_setopt($curl, CURLOPT_URL, 'http://127.0.0.1:8025/api/v1/message/' . rawurlencode($message['ID']));
                $mail = json_decode(curl_exec($curl), true, 32, JSON_THROW_ON_ERROR);
                if (preg_match('/#token=([0-9a-f]{64})/', $mail['Text'] ?? '', $match)
                    && hash_equals($hash, hash('sha256', $match[1], true))) {
                    recoveryAssert(str_contains($mail['Text'], $private['app_url'] . '?client=resetPassword#token='), 'Wrong email application URL');
                    recoveryAssert($model->valid(hash('sha256', $match[1], true), new DateTimeImmutable('now', new DateTimeZone('UTC'))), 'Delivered token invalid');
                    echo '  Verified Mailpit message ID: ', $message['ID'], PHP_EOL;
                    return;
                }
            }
            throw new LogicException('Exact MariaDB token not found in Mailpit');
        });
    } else {
        echo "NOT TESTED real Mailpit delivery (add --mailpit).\n";
    }
} finally {
    $clean();
    echo 'Per-write database/identity guards executed: ', $db->guardedWrites, PHP_EOL;
    (new ReflectionProperty(Database::class, 'connection'))->setValue(null, null);
}
$tests->finish();
