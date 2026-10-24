<?php

declare(strict_types=1);

final class PasswordRecoveryModel
{
    private bool $sqlite;

    public function __construct(private PDO $db)
    {
        $this->sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($this->sqlite) {
            $db->exec('PRAGMA foreign_keys = ON');
            $db->exec('PRAGMA busy_timeout = 10000');
        }
    }

    public static function stamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function run(string $sql, #[SensitiveParameter] array $parameters = []): PDOStatement
    {
        $statement = $this->db->prepare($sql);
        foreach (array_values($parameters) as $index => $value) {
            if (is_array($value)) {
                [$value, $type] = $value;
            } else {
                $type = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            $statement->bindValue($index + 1, $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    private function locked(): string
    {
        return $this->sqlite ? '' : ' FOR UPDATE';
    }

    private function atomic(callable $operation): mixed
    {
        if ($this->db->inTransaction()) {
            throw new LogicException('Nested recovery transactions are not supported.');
        }
        // BEGIN IMMEDIATE gives SQLite fixture writers the same lock order.
        for ($attempt = 0; ; $attempt++) {
            $this->db->exec($this->sqlite ? 'BEGIN IMMEDIATE' : 'START TRANSACTION');
            try {
                $result = $operation();
                $this->db->exec('COMMIT');
                return $result;
            } catch (Throwable $error) {
                $this->db->exec('ROLLBACK');
                if (!$this->sqlite && $error instanceof PDOException && $attempt < 2
                    && in_array((int) ($error->errorInfo[1] ?? 0), [1205, 1213], true)) {
                    usleep(random_int(10000, 30000));
                    continue;
                }
                throw $error;
            }
        }
    }

    private function limits(array $limits, DateTimeImmutable $now): bool
    {
        usort($limits, fn(array $a, array $b): int => strcmp($a['scope'], $b['scope']));
        $stamp = self::stamp($now);
        $end = self::stamp($now->modify('+15 minutes'));
        $allowed = true;
        foreach ($limits as &$limit) {
            $insert = $this->sqlite ? 'INSERT OR IGNORE' : 'INSERT';
            $duplicate = $this->sqlite ? '' : ' ON DUPLICATE KEY UPDATE scope = VALUES(scope)';
            $this->run("$insert INTO password_reset_rate_limits
                (scope, subject_hash, window_started_at, window_ends_at, attempts)
                VALUES (?, ?, ?, ?, 0)" . $duplicate, [$limit['scope'], [$limit['hash'], PDO::PARAM_LOB], $stamp, $end]);
            $row = $this->run('SELECT * FROM password_reset_rate_limits
                WHERE scope = ? AND subject_hash = ?' . $this->locked(),
                [$limit['scope'], [$limit['hash'], PDO::PARAM_LOB]])->fetch();
            if ($row['window_ends_at'] <= $stamp) {
                $row['window_started_at'] = $stamp;
                $row['window_ends_at'] = $end;
                $row['attempts'] = 0;
            }
            if ((int) $row['attempts'] >= $limit['max']
                || ($limit['scope'] === 'request_email' && $row['last_allowed_at'] !== null
                    && $row['last_allowed_at'] > self::stamp($now->modify('-60 seconds')))) {
                $allowed = false;
            }
            $limit['row'] = $row;
        }
        unset($limit);
        foreach ($limits as $limit) {
            $row = $limit['row'];
            $increment = $allowed || $limit['scope'] !== 'request_email';
            $this->run('UPDATE password_reset_rate_limits SET window_started_at = ?,
                window_ends_at = ?, attempts = ?, last_allowed_at = ?
                WHERE scope = ? AND subject_hash = ?', [
                    $row['window_started_at'], $row['window_ends_at'],
                    min($limit['max'], (int) $row['attempts'] + (int) $increment),
                    $allowed ? $stamp : $row['last_allowed_at'],
                    $limit['scope'], [$limit['hash'], PDO::PARAM_LOB],
                ]);
        }
        return $allowed;
    }

    public function request(string $email, string $ip, #[SensitiveParameter] string $key, DateTimeImmutable $now): bool
    {
        return $this->atomic(function () use ($email, $ip, $key, $now): bool {
            $allowed = $this->limits([
                ['scope' => 'request_email', 'hash' => hash_hmac('sha256', 'request_email:' . $email, $key, true), 'max' => 3],
                ['scope' => 'request_ip', 'hash' => hash_hmac('sha256', 'request_ip:' . $ip, $key, true), 'max' => 20],
            ], $now);
            if ($allowed) {
                $this->run('INSERT INTO password_reset_jobs (email, created_at, available_at) VALUES (?, ?, ?)',
                    [$email, self::stamp($now), self::stamp($now)]);
            }
            return $allowed;
        });
    }

    public function allowReset(string $ip, #[SensitiveParameter] string $key, DateTimeImmutable $now): bool
    {
        return $this->atomic(fn(): bool => $this->limits([
            ['scope' => 'reset_ip', 'hash' => hash_hmac('sha256', 'reset_ip:' . $ip, $key, true), 'max' => 30],
        ], $now));
    }

    public function claim(DateTimeImmutable $now): ?array
    {
        return $this->atomic(function () use ($now): ?array {
            $stamp = self::stamp($now);
            $job = $this->run("SELECT * FROM password_reset_jobs WHERE
                (status = 'pending' AND available_at <= ?)
                OR (status = 'processing' AND locked_until <= ?)
                ORDER BY id LIMIT 1" . $this->locked(), [$stamp, $stamp])->fetch();
            if (!$job) {
                return null;
            }
            if ($job['created_at'] <= self::stamp($now->modify('-5 minutes')) || (int) $job['attempts'] >= 3) {
                $this->run("UPDATE password_reset_jobs SET status = ?, lock_token = NULL,
                    locked_until = NULL, finished_at = ?, last_error_code = ? WHERE id = ?", [
                        (int) $job['attempts'] >= 3 ? 'failed' : 'discarded', $stamp, 'JOB_EXPIRED', (int) $job['id'],
                    ]);
                return ['skipped' => true];
            }
            $job['lock_token'] = random_bytes(16);
            $job['attempts'] = (int) $job['attempts'] + 1;
            $this->run("UPDATE password_reset_jobs SET status = 'processing', attempts = ?,
                lock_token = ?, locked_until = ? WHERE id = ?", [
                    $job['attempts'], [$job['lock_token'], PDO::PARAM_LOB],
                    self::stamp($now->modify('+120 seconds')), (int) $job['id'],
                ]);
            return $job;
        });
    }

    private function owns(array $job, DateTimeImmutable $now, int $started): bool
    {
        $row = $this->run('SELECT * FROM password_reset_jobs WHERE id = ?' . $this->locked(), [(int) $job['id']])->fetch();
        $now = $now->modify('+' . (int) ceil((hrtime(true) - $started) / 1000) . ' microseconds');
        return $row && $row['status'] === 'processing' && is_string($row['lock_token'])
            && hash_equals($row['lock_token'], $job['lock_token']) && $row['locked_until'] > self::stamp($now);
    }

    public function issue(array $job, DateTimeImmutable $now): ?array
    {
        $started = hrtime(true);
        return $this->atomic(function () use ($job, $now, $started): ?array {
            // Account -> job -> token is also the order used by reset/change.
            $accounts = $this->run('SELECT * FROM account WHERE tk_email = ? AND id_role = 2
                ORDER BY tk_id LIMIT 2' . $this->locked(), [$job['email']])->fetchAll();
            if (!$this->owns($job, $now, $started)) {
                return null;
            }
            $effective = $now->modify('+' . (int) ceil((hrtime(true) - $started) / 1000) . ' microseconds');
            if (count($accounts) !== 1 || $job['created_at'] <= self::stamp($effective->modify('-5 minutes'))) {
                $this->finish($job, $now, 'discarded');
                return null;
            }
            $account = $accounts[0];
            $token = bin2hex(random_bytes(32));
            $this->run('DELETE FROM password_reset_tokens WHERE account_id = ?', [(int) $account['tk_id']]);
            $this->run('INSERT INTO password_reset_tokens
                (account_id, token_hash, password_fingerprint, email_fingerprint,
                 issued_session_version, created_at, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)', [
                    (int) $account['tk_id'], [hash('sha256', $token, true), PDO::PARAM_LOB],
                    [hash('sha256', $account['tk_password'], true), PDO::PARAM_LOB],
                    [hash('sha256', strtolower(trim($account['tk_email'])), true), PDO::PARAM_LOB],
                    (int) $account['session_version'], self::stamp($now), self::stamp($now->modify('+15 minutes')),
                ]);
            return ['token' => $token, 'email' => $account['tk_email']];
        });
    }

    public function finish(array $job, DateTimeImmutable $now, string $status, ?string $error = null): bool
    {
        return $this->run('UPDATE password_reset_jobs SET status = ?, lock_token = NULL,
            locked_until = NULL, finished_at = ?, last_error_code = ?
            WHERE id = ? AND status = ? AND lock_token = ? AND locked_until > ?', [
                $status, self::stamp($now), $error, (int) $job['id'], 'processing',
                [$job['lock_token'], PDO::PARAM_LOB], self::stamp($now),
            ])->rowCount() === 1;
    }

    public function retry(array $job, DateTimeImmutable $now): bool
    {
        if ($job['attempts'] >= 3) {
            return $this->finish($job, $now, 'failed', 'SMTP_FAILED');
        }
        return $this->run("UPDATE password_reset_jobs SET status = 'pending', lock_token = NULL,
            locked_until = NULL, available_at = ?, last_error_code = 'SMTP_FAILED'
            WHERE id = ? AND status = 'processing' AND lock_token = ? AND locked_until > ?", [
                self::stamp($now->modify('+' . (10 * $job['attempts']) . ' seconds')),
                (int) $job['id'], [$job['lock_token'], PDO::PARAM_LOB], self::stamp($now),
            ])->rowCount() === 1;
    }

    private function matches(array $token, array $account, DateTimeImmutable $now): bool
    {
        return (int) $account['id_role'] === 2 && $token['used_at'] === null
            && $token['expires_at'] > self::stamp($now) && $token['created_at'] <= self::stamp($now)
            && (int) $token['issued_session_version'] === (int) $account['session_version']
            && hash_equals($token['password_fingerprint'], hash('sha256', $account['tk_password'], true))
            && hash_equals($token['email_fingerprint'], hash('sha256', strtolower(trim($account['tk_email'])), true));
    }

    public function valid(#[SensitiveParameter] string $hash, DateTimeImmutable $now): bool
    {
        $token = $this->run('SELECT * FROM password_reset_tokens WHERE token_hash = ?', [[$hash, PDO::PARAM_LOB]])->fetch();
        $account = $token ? $this->run('SELECT * FROM account WHERE tk_id = ?', [(int) $token['account_id']])->fetch() : false;
        return $account && $this->matches($token, $account, $now);
    }

    private function cancelJobs(string $email, DateTimeImmutable $now): void
    {
        $this->run("UPDATE password_reset_jobs SET status = 'discarded', lock_token = NULL,
            locked_until = NULL, finished_at = ? WHERE email = ? AND status IN ('pending', 'processing')",
            [self::stamp($now), $email]);
    }

    public function reset(#[SensitiveParameter] string $hash, #[SensitiveParameter] string $password, DateTimeImmutable $now): bool
    {
        $started = hrtime(true);
        $reference = $this->run('SELECT account_id FROM password_reset_tokens WHERE token_hash = ?', [[$hash, PDO::PARAM_LOB]])->fetch();
        if (!$reference) {
            return false;
        }
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        return $this->atomic(function () use ($reference, $hash, $password, $newHash, $now, $started): bool {
            $id = (int) $reference['account_id'];
            $account = $this->run('SELECT * FROM account WHERE tk_id = ?' . $this->locked(), [$id])->fetch();
            // Cancel job rows before touching token rows, keeping worker/reset order.
            if (!$account) {
                return false;
            }
            $this->run("SELECT id FROM password_reset_jobs WHERE email = ?
                AND status IN ('pending', 'processing') ORDER BY id" . $this->locked(), [$account['tk_email']])->fetchAll();
            $token = $this->run('SELECT * FROM password_reset_tokens WHERE account_id = ?' . $this->locked(), [$id])->fetch();
            // Include bcrypt cost, lock waits and retries in the expiration check.
            $elapsed = (int) ceil((hrtime(true) - $started) / 1000);
            $now = $now->modify('+' . $elapsed . ' microseconds');
            if (!$token || !hash_equals($token['token_hash'], $hash) || !$this->matches($token, $account, $now)
                || password_verify($password, $account['tk_password'])
                || hash_equals($account['tk_password'], $password)) {
                return false;
            }
            $changed = $this->run('UPDATE account SET tk_password = ?, session_version = session_version + 1
                WHERE tk_id = ? AND tk_password = ? AND session_version = ?',
                [$newHash, $id, $account['tk_password'], (int) $account['session_version']])->rowCount();
            $used = $this->run('UPDATE password_reset_tokens SET used_at = ?
                WHERE account_id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > ?',
                [self::stamp($now), $id, [$hash, PDO::PARAM_LOB], self::stamp($now)])->rowCount();
            if ($changed !== 1 || $used !== 1) {
                throw new RuntimeException('Recovery transaction failed.');
            }
            $this->cancelJobs($account['tk_email'], $now);
            return true;
        });
    }

    public function changeAuthenticatedPassword(int $id, #[SensitiveParameter] string $expected, #[SensitiveParameter] string $newHash): bool
    {
        return $this->atomic(function () use ($id, $expected, $newHash): bool {
            $account = $this->run('SELECT * FROM account WHERE tk_id = ?' . $this->locked(), [$id])->fetch();
            if (!$account || !hash_equals($account['tk_password'], $expected)) {
                return false;
            }
            $this->cancelJobs($account['tk_email'], new DateTimeImmutable('now', new DateTimeZone('UTC')));
            $this->run('DELETE FROM password_reset_tokens WHERE account_id = ?', [$id]);
            if ($this->run('UPDATE account SET tk_password = ?, session_version = session_version + 1
                WHERE tk_id = ? AND tk_password = ?', [$newHash, $id, $expected])->rowCount() !== 1) {
                throw new RuntimeException('Password change transaction failed.');
            }
            return true;
        });
    }

    public function cleanup(DateTimeImmutable $now): void
    {
        $old = self::stamp($now->modify('-24 hours'));
        $this->run('DELETE FROM password_reset_jobs WHERE created_at < ?', [$old]);
        $this->run('DELETE FROM password_reset_tokens WHERE expires_at < ?', [$old]);
        $this->run('DELETE FROM password_reset_rate_limits WHERE window_ends_at < ?', [$old]);
    }
}
