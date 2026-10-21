<?php

declare(strict_types=1);

final class PasswordRecoveryConfig
{
    public static function enabled(): bool
    {
        return getenv('FOURSMART_PASSWORD_RECOVERY_ENABLED') === '1';
    }

    public static function load(): array
    {
        if (!self::enabled()) {
            throw new RuntimeException('Password recovery is disabled.');
        }
        $key = base64_decode((string) getenv('FOURSMART_RECOVERY_HMAC_KEY'), true);
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Recovery configuration is unavailable.');
        }
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
