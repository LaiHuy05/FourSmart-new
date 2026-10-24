<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/SessionVersionPolicy.php';

final class PasswordRecoveryConfig
{
    public static function enabled(): bool
    {
        if (getenv('FOURSMART_PASSWORD_RECOVERY_ENABLED') !== '1') {
            return false;
        }
        // Optional local deployment restriction; never trust forwarded headers.
        return PHP_SAPI === 'cli' || getenv('FOURSMART_RECOVERY_LOCAL_ONLY') !== '1'
            || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    }

    private static function key(): string
    {
        $encoded = getenv('FOURSMART_RECOVERY_HMAC_KEY');
        $path = getenv('FOURSMART_RECOVERY_HMAC_KEY_FILE');
        if ($path !== false && $path !== '') {
            if ($encoded !== false && $encoded !== '') {
                throw new RuntimeException('Recovery configuration is unavailable.');
            }
            $resolved = realpath($path);
            if (!$resolved || !is_file($resolved) || !is_readable($resolved) || filesize($resolved) > 4096) {
                throw new RuntimeException('Recovery configuration is unavailable.');
            }
            $normalize = static fn(string $value): string => strtolower(rtrim(str_replace('\\', '/', $value), '/'));
            foreach ([dirname(__DIR__, 2), $_SERVER['DOCUMENT_ROOT'] ?? ''] as $root) {
                if ($root !== '' && str_starts_with($normalize($resolved), $normalize($root) . '/')) {
                    throw new RuntimeException('Recovery configuration is unavailable.');
                }
            }
            $encoded = trim((string) file_get_contents($resolved));
        }
        $key = base64_decode((string) $encoded, true);
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Recovery configuration is unavailable.');
        }
        return $key;
    }

    public static function load(): array
    {
        if (!self::enabled()) {
            throw new RuntimeException('Password recovery is disabled.');
        }
        if (!SessionVersionPolicy::required()) {
            throw new RuntimeException('Recovery requires versioned sessions.');
        }
        $key = self::key();
        $url = getenv('FOURSMART_APP_URL') ?: 'http://localhost/FourSmart/mvc/index.php';
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['host'] ?? '') !== 'localhost'
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !str_ends_with($parts['path'] ?? '', '/index.php')) {
            throw new RuntimeException('Application URL must use localhost.');
        }
        return ['key' => $key, 'url' => $url];
    }
}
