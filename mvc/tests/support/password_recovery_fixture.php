<?php

// Test-only SQLite fixtures, not the proposed MariaDB migration.
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../core/bootstrap.php';

function recoveryFixture(PDO $db, bool $accounts = true): void
{
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
        throw new RuntimeException('Fixture refuses non-SQLite connections.');
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
    if ($accounts) {
        $db->exec('CREATE TABLE account (tk_id INTEGER PRIMARY KEY, tk_user TEXT UNIQUE NOT NULL,
            tk_password TEXT NOT NULL, tk_email TEXT COLLATE NOCASE NOT NULL, tk_address TEXT,
            id_role INTEGER, session_version INTEGER NOT NULL DEFAULT 0)');
    }
    $db->exec('CREATE TABLE password_reset_tokens (account_id INTEGER PRIMARY KEY REFERENCES account(tk_id) ON DELETE CASCADE,
        token_hash BLOB NOT NULL UNIQUE, password_fingerprint BLOB NOT NULL, email_fingerprint BLOB NOT NULL,
        issued_session_version INTEGER NOT NULL, created_at TEXT NOT NULL, expires_at TEXT NOT NULL, used_at TEXT)');
    $db->exec('CREATE TABLE password_reset_rate_limits (scope TEXT NOT NULL, subject_hash BLOB NOT NULL,
        window_started_at TEXT NOT NULL, window_ends_at TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0,
        last_allowed_at TEXT, PRIMARY KEY(scope, subject_hash))');
    $db->exec("CREATE TABLE password_reset_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT COLLATE NOCASE NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL,
        available_at TEXT NOT NULL, lock_token BLOB, locked_until TEXT, finished_at TEXT, last_error_code TEXT,
        CHECK (attempts <= 3), CHECK (status IN ('pending','processing','sent','discarded','failed')))");
    if ($accounts) {
        $statement = $db->prepare('INSERT INTO account (tk_id, tk_user, tk_password, tk_email, tk_address, id_role) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ([1 => 'admin', 2 => 'customer', 3 => 'other'] as $id => $name) {
            $statement->execute([$id, $name, password_hash('Original-Password!2026', PASSWORD_BCRYPT), $name . '@example.test', 'Fixture', $id === 1 ? 1 : 2]);
        }
        $db->exec('CREATE TABLE category (dm_id INTEGER PRIMARY KEY, dm_name TEXT)');
        $db->exec('CREATE TABLE product (sp_id INTEGER PRIMARY KEY, sp_name TEXT)');
    }
}

function recoveryDirectory(): string
{
    $path = sys_get_temp_dir() . '/foursmart-recovery-' . bin2hex(random_bytes(8));
    mkdir($path, 0700);
    mkdir($path . '/sessions', 0700);
    file_put_contents($path . '/test-only.marker', 'FourSmart recovery isolated fixture');
    return $path;
}

function recoveryRemoveDirectory(string $path): void
{
    $resolved = realpath($path);
    if ($resolved === false || dirname($resolved) !== realpath(sys_get_temp_dir())
        || !str_starts_with(basename($resolved), 'foursmart-recovery-')
        || !is_file($resolved . '/test-only.marker')) {
        throw new RuntimeException('Refusing cleanup outside the exact temporary fixture.');
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->getFilename() === 'test-only.marker') continue;
        $removed = $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        if (!$removed) throw new RuntimeException('Temporary fixture cleanup failed.');
    }
    unlink($resolved . '/test-only.marker');
    rmdir($resolved);
}

function recoveryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class RecoveryTests
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo 'PASS ', $name, PHP_EOL;
        } catch (Throwable $error) {
            $this->failed++;
            echo 'FAIL ', $name, ' (', get_class($error), ')', PHP_EOL;
        }
    }

    public function finish(): never
    {
        echo $this->passed, ' passed, ', $this->failed, ' failed', PHP_EOL;
        exit($this->failed === 0 ? 0 : 1);
    }
}
