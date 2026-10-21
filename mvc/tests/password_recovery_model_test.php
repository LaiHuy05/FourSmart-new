<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/support/password_recovery_fixture.php';

$tests = new RecoveryTests();
$now = new DateTimeImmutable('2026-10-21 08:00:00', new DateTimeZone('UTC'));
$key = str_repeat('k', 32);
$fresh = function () use ($now, $key): array {
    $db = new PDO('sqlite::memory:');
    recoveryFixture($db);
    $model = new PasswordRecoveryModel($db);
    return [$db, $model];
};
$issue = function (PasswordRecoveryModel $model, string $email = 'customer@example.test') use ($now, $key): array {
    recoveryAssert($model->request($email, 'fixture-ip', $key, $now), 'Request denied');
    $job = $model->claim($now);
    $issued = $model->issue($job, $now);
    recoveryAssert(is_array($issued), 'No token issued');
    return [$issued, $job];
};

$tests->test('configuration rejects disabled recovery, missing private key and non-localhost URLs', function (): void {
    $names = ['FOURSMART_PASSWORD_RECOVERY_ENABLED', 'FOURSMART_RECOVERY_HMAC_KEY', 'FOURSMART_APP_URL'];
    $previous = [];
    foreach ($names as $name) $previous[$name] = getenv($name);
    $reject = static function (): void {
        try { PasswordRecoveryConfig::load(); } catch (RuntimeException $expected) { return; }
        throw new LogicException('Unsafe configuration accepted');
    };
    try {
        putenv('FOURSMART_PASSWORD_RECOVERY_ENABLED=0'); $reject();
        putenv('FOURSMART_PASSWORD_RECOVERY_ENABLED=1');
        putenv('FOURSMART_RECOVERY_HMAC_KEY'); $reject();
        putenv('FOURSMART_RECOVERY_HMAC_KEY=' . base64_encode(str_repeat('k', 32)));
        foreach (['http://127.0.0.1/FourSmart/mvc/index.php', 'http://evil.test/index.php',
            'http://user@localhost/index.php', 'http://localhost/index.php?token=x', 'http://localhost/index.php#token=x'] as $url) {
            putenv('FOURSMART_APP_URL=' . $url); $reject();
        }
        putenv('FOURSMART_APP_URL=http://localhost/FourSmart/mvc/index.php');
        recoveryAssert(PasswordRecoveryConfig::load()['url'] === 'http://localhost/FourSmart/mvc/index.php', 'Valid local URL rejected');
    } finally {
        foreach ($previous as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
    }
});

$tests->test('token is random 256-bit material, only its hash is stored, TTL is 15 minutes', function () use ($fresh, $issue, $now): void {
    [$db, $model] = $fresh();
    [$issued] = $issue($model);
    $row = $db->query('SELECT * FROM password_reset_tokens')->fetch();
    recoveryAssert(preg_match('/\A[0-9a-f]{64}\z/', $issued['token']) === 1, 'Invalid entropy encoding');
    recoveryAssert($row['token_hash'] === hash('sha256', $issued['token'], true), 'Hash mismatch');
    recoveryAssert(!str_contains(json_encode($db->query('SELECT * FROM password_reset_jobs')->fetchAll()), $issued['token']), 'Raw token in queue');
    recoveryAssert($row['expires_at'] === PasswordRecoveryModel::stamp($now->modify('+15 minutes')), 'Wrong TTL');
});
$tests->test('unknown and admin emails queue identically but never get a token or delivery', function () use ($fresh, $now, $key): void {
    foreach (['unknown@example.test', 'admin@example.test'] as $email) {
        [$db, $model] = $fresh();
        $model->request($email, 'fixture-ip', $key, $now);
        $sent = 0;
        $service = new PasswordRecoveryService($model, ['key' => $key, 'url' => 'http://localhost/FourSmart/mvc/index.php'],
            function () use (&$sent): void { $sent++; }, fn() => $now);
        recoveryAssert($service->processOne() === 'discarded', 'Wrong job result');
        recoveryAssert($sent === 0 && (int) $db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn() === 0, 'Account data leaked');
    }
});
$tests->test('ambiguous client email fails closed without UNIQUE assumptions', function () use ($fresh, $now, $key): void {
    [$db, $model] = $fresh();
    $db->exec("UPDATE account SET tk_email = 'customer@example.test' WHERE tk_id = 3");
    $model->request('customer@example.test', 'fixture-ip', $key, $now);
    recoveryAssert($model->issue($model->claim($now), $now) === null, 'Ambiguous email issued token');
});
$tests->test('valid reset succeeds once, bumps session version, rejects old password and replay', function () use ($fresh, $issue, $now): void {
    [$db, $model] = $fresh();
    [$issued] = $issue($model);
    $hash = hash('sha256', $issued['token'], true);
    recoveryAssert($model->reset($hash, 'Updated-Password!2026', $now), 'Valid reset rejected');
    $account = $db->query('SELECT * FROM account WHERE tk_id = 2')->fetch();
    recoveryAssert((int) $account['session_version'] === 1 && password_verify('Updated-Password!2026', $account['tk_password']), 'Reset not atomic');
    recoveryAssert(!password_verify('Original-Password!2026', $account['tk_password']), 'Old password retained');
    recoveryAssert(!$model->reset($hash, 'Replay-Password!2026', $now), 'Replay accepted');
});
$tests->test('token expires at the exact 15 minute boundary', function () use ($fresh, $issue, $now): void {
    [$db, $model] = $fresh();
    [$issued] = $issue($model);
    $hash = hash('sha256', $issued['token'], true);
    recoveryAssert($model->valid($hash, $now->modify('+899 seconds')), 'Premature expiration');
    recoveryAssert(!$model->valid($hash, $now->modify('+900 seconds')), 'Boundary accepted');
    recoveryAssert(!$model->reset($hash, 'Expired-Password!2026', $now->modify('+900 seconds')), 'Expired reset accepted');
});
$tests->test('forged tokens, changed email, changed password and changed role are rejected', function () use ($fresh, $issue, $now): void {
    foreach (["tk_email = 'changed@example.test'", "tk_password = 'changed-legacy'", 'id_role = 1', 'session_version = 1'] as $change) {
        [$db, $model] = $fresh();
        [$issued] = $issue($model);
        $db->exec('UPDATE account SET ' . $change . ' WHERE tk_id = 2');
        recoveryAssert(!$model->valid(hash('sha256', $issued['token'], true), $now), 'Stale token accepted');
        recoveryAssert(!$model->valid(random_bytes(32), $now), 'Forged token accepted');
    }
});
$tests->test('expiry is rechecked after password hashing and waiting for transaction locks', function () use ($fresh, $issue, $now): void {
    [$db, $model] = $fresh();
    [$issued] = $issue($model);
    $nearExpiry = $now->modify('+15 minutes')->modify('-1 microsecond');
    recoveryAssert(!$model->reset(hash('sha256', $issued['token'], true), 'Boundary-Password!2026', $nearExpiry), 'Stale validation clock accepted expired token');
});
$tests->test('replacement token invalidates previous link', function () use ($fresh, $issue, $now, $key): void {
    [$db, $model] = $fresh();
    [$old] = $issue($model);
    $later = $now->modify('+61 seconds');
    $model->request('customer@example.test', 'fixture-ip', $key, $later);
    // Finish the original job so the later job is selected.
    $db->exec("UPDATE password_reset_jobs SET status = 'sent', lock_token = NULL, locked_until = NULL, finished_at = created_at WHERE id = 1");
    $new = $model->issue($model->claim($later), $later);
    recoveryAssert(!$model->valid(hash('sha256', $old['token'], true), $later), 'Old link remained valid');
    recoveryAssert($model->valid(hash('sha256', $new['token'], true), $later), 'Replacement invalid');
});
$tests->test('email cooldown and three accepted requests per window survive session changes', function () use ($fresh, $now, $key): void {
    [$db, $model] = $fresh();
    recoveryAssert($model->request('customer@example.test', 'ip-1', $key, $now), 'First blocked');
    recoveryAssert(!$model->request('customer@example.test', 'ip-2', $key, $now->modify('+59 seconds')), 'Cooldown bypassed');
    recoveryAssert($model->request('customer@example.test', 'ip-3', $key, $now->modify('+60 seconds')), 'Cooldown boundary blocked');
    recoveryAssert($model->request('customer@example.test', 'ip-4', $key, $now->modify('+120 seconds')), 'Third blocked');
    recoveryAssert(!$model->request('customer@example.test', 'ip-5', $key, $now->modify('+180 seconds')), 'Email limit bypassed');
    recoveryAssert($model->request('customer@example.test', 'ip-6', $key, $now->modify('+900 seconds')), 'Window did not recover');
});
$tests->test('IP limits cover unknown addresses and reset verification', function () use ($fresh, $now, $key): void {
    [$db, $model] = $fresh();
    for ($i = 0; $i < 20; $i++) {
        recoveryAssert($model->request('unknown' . $i . '@example.test', 'same-ip', $key, $now), 'Early IP block');
    }
    recoveryAssert(!$model->request('last@example.test', 'same-ip', $key, $now), 'IP request limit bypassed');
    for ($i = 0; $i < 30; $i++) recoveryAssert($model->allowReset('same-ip', $key, $now), 'Early reset block');
    recoveryAssert(!$model->allowReset('same-ip', $key, $now), 'Reset limit bypassed');
});
$tests->test('worker leases prevent duplicate claims and stale owner completion', function () use ($fresh, $now, $key): void {
    [$db, $model] = $fresh();
    $model->request('customer@example.test', 'ip', $key, $now);
    $first = $model->claim($now);
    recoveryAssert($model->claim($now) === null, 'Duplicate claim');
    $later = $now->modify('+121 seconds');
    $second = $model->claim($later);
    recoveryAssert(!$model->finish($first, $later, 'sent'), 'Stale owner completed');
    recoveryAssert($model->issue($first, $later) === null, 'Stale owner issued token');
    recoveryAssert($model->finish($second, $later, 'sent'), 'New owner rejected');
});
$tests->test('SMTP failure retries at most three times without persisting error details', function () use ($fresh, $now, $key): void {
    [$db, $model] = $fresh();
    $model->request('customer@example.test', 'ip', $key, $now);
    $clock = $now;
    $service = new PasswordRecoveryService($model, ['key' => $key, 'url' => 'http://localhost/FourSmart/mvc/index.php'],
        function (): void { throw new RuntimeException('secret SMTP diagnostic'); }, function () use (&$clock) { return $clock; });
    for ($i = 0; $i < 3; $i++) {
        recoveryAssert($service->processOne() === 'retry_or_failed', 'Retry not scheduled');
        $clock = $clock->modify('+31 seconds');
    }
    $job = $db->query('SELECT * FROM password_reset_jobs')->fetch();
    recoveryAssert($job['status'] === 'failed' && (int) $job['attempts'] === 3 && $job['last_error_code'] === 'SMTP_FAILED', 'Retry bound failed');
});
$tests->test('worker rechecks lease expiry after waiting for account locks', function () use ($now, $key): void {
    $db = new class('sqlite::memory:') extends PDO {
        public bool $delay = false;
        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            if ($this->delay && str_starts_with($query, 'SELECT * FROM account WHERE tk_email')) usleep(20000);
            return parent::prepare($query, $options);
        }
    };
    recoveryFixture($db);
    $model = new PasswordRecoveryModel($db);
    $model->request('customer@example.test', 'ip', $key, $now);
    $job = $model->claim($now);
    $db->delay = true;
    $nearEnd = $now->modify('+120 seconds')->modify('-1 microsecond');
    recoveryAssert($model->issue($job, $nearEnd) === null, 'Worker used an expired lease after lock wait');
});
$tests->test('jobs older than five minutes are discarded before token issuance', function () use ($fresh, $now, $key): void {
    [$db, $model] = $fresh();
    $model->request('customer@example.test', 'ip', $key, $now);
    recoveryAssert(isset($model->claim($now->modify('+5 minutes'))['skipped']), 'Old job not discarded');
    recoveryAssert((int) $db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn() === 0, 'Old job created token');
});
$tests->test('database write failure rolls password, version and token consumption back together', function () use ($fresh, $issue, $now): void {
    [$db, $model] = $fresh();
    [$issued] = $issue($model);
    $before = $db->query('SELECT * FROM account WHERE tk_id = 2')->fetch();
    $db->exec("CREATE TRIGGER fail_token BEFORE UPDATE OF used_at ON password_reset_tokens BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
    try {
        $model->reset(hash('sha256', $issued['token'], true), 'Updated-Password!2026', $now);
        throw new LogicException('Write failure not raised');
    } catch (PDOException $expected) {
        recoveryAssert($db->query('SELECT * FROM account WHERE tk_id = 2')->fetch() === $before, 'Partial account write');
        recoveryAssert($model->valid(hash('sha256', $issued['token'], true), $now), 'Token consumed after rollback');
    }
});
$tests->test('authenticated change increments version and cancels old recovery work', function () use ($fresh, $issue, $now): void {
    [$db, $model] = $fresh();
    [$issued] = $issue($model);
    $old = $db->query('SELECT tk_password FROM account WHERE tk_id = 2')->fetchColumn();
    recoveryAssert($model->changeAuthenticatedPassword(2, $old, password_hash('Changed-Password!2026', PASSWORD_BCRYPT)), 'Change failed');
    recoveryAssert((int) $db->query('SELECT session_version FROM account WHERE tk_id = 2')->fetchColumn() === 1, 'Version not increased');
    recoveryAssert(!$model->valid(hash('sha256', $issued['token'], true), $now), 'Old token retained');
    recoveryAssert($db->query('SELECT status FROM password_reset_jobs')->fetchColumn() === 'discarded', 'Old queue retained');
});
$tests->test('password policy handles UTF-8, nulls, arrays and bcrypt byte boundary', function (): void {
    foreach (['short', str_repeat('A', 73), "Good-Password!2026\0", ['invalid'], "\xff"] as $password) {
        recoveryAssert(!PasswordRecoveryService::validPassword($password), 'Invalid password accepted');
    }
    recoveryAssert(PasswordRecoveryService::validPassword('Aa1!' . str_repeat('x', 68)), '72-byte password rejected');
    recoveryAssert(PasswordRecoveryService::email(['invalid']) === null, 'Email array accepted');
});

foreach (['request', 'worker', 'reset'] as $operation) {
    $tests->test('parallel independent SQLite connections: ' . $operation, function () use ($operation, $now, $key): void {
        $directory = recoveryDirectory();
        $db = new PDO('sqlite:' . $directory . '/accounts.sqlite');
        recoveryFixture($db);
        $model = new PasswordRecoveryModel($db);
        $input = ['operation' => $operation, 'now' => $now->format('Y-m-d\TH:i:s.uP')];
        if ($operation !== 'request') {
            $model->request('customer@example.test', 'ip', $key, $now);
        }
        if ($operation === 'reset') {
            $issued = $model->issue($model->claim($now), $now);
            $input['hash'] = hash('sha256', $issued['token']);
        }
        $children = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, __DIR__ . '/support/password_recovery_race.php', $directory],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
                recoveryAssert(is_resource($process), 'Child did not start');
                fwrite($pipes[0], json_encode($input)); fclose($pipes[0]);
                $children[] = [$process, $pipes];
            }
            $results = [];
            foreach ($children as [$process, $pipes]) {
                $results[] = stream_get_contents($pipes[1]);
                fclose($pipes[1]); fclose($pipes[2]);
                recoveryAssert(proc_close($process) === 0, 'Child failed');
            }
            sort($results);
            recoveryAssert($results === ($operation === 'worker' ? ['idle', 'sent'] : ['0', '1']), 'Parallel operation was not serialized');
            if ($operation === 'worker') recoveryAssert(file_get_contents($directory . '/deliveries.txt') === "sent\n", 'Duplicate SMTP work');
            if ($operation === 'reset') recoveryAssert((int) $db->query('SELECT session_version FROM account WHERE tk_id = 2')->fetchColumn() === 1, 'Double version increment');
        } finally {
            $model = null; $db = null;
            recoveryRemoveDirectory($directory);
        }
    });
}
$tests->finish();
