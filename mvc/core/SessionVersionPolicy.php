<?php

declare(strict_types=1);

final class SessionVersionPolicy
{
    public static function required(): bool
    {
        $mode = getenv('FOURSMART_SESSION_VERSION_MODE');
        if ($mode === 'legacy') {
            return false;
        }
        if ($mode === 'required') {
            return true;
        }
        // Missing or invalid deployment settings must never disable revocation.
        throw new RuntimeException('Session version policy is unavailable.');
    }

    public static function accountVersion(array $account): int
    {
        $version = self::value($account['session_version'] ?? null);
        if ($version === null) {
            throw new RuntimeException('Session schema is unavailable.');
        }
        return $version;
    }

    public static function matches(array $session, array $account): bool
    {
        // Validate schema first: a malformed/missing DB column is an outage,
        // while a malformed/missing session snapshot requires a fresh login.
        $expected = self::accountVersion($account);
        $actual = self::value($session['session_version'] ?? null);
        return $actual !== null && $actual === $expected;
    }

    private static function value(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', $value)) {
            $value = (int) $value;
        }
        return is_int($value) && $value >= 0 && $value <= 4294967295 ? $value : null;
    }
}
