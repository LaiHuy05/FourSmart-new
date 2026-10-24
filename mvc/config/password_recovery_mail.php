<?php

declare(strict_types=1);

final class PasswordRecoveryMailConfig
{
    public static function load(): array
    {
        $read = static function (string $name, string $default): string {
            $value = getenv($name);
            return $value === false ? $default : $value;
        };
        // Only the local capture transport is approved. Never fall back to mail().
        $transport = $read('FOURSMART_MAIL_TRANSPORT', 'mailpit');
        $host = $read('FOURSMART_SMTP_HOST', '127.0.0.1');
        $port = $read('FOURSMART_SMTP_PORT', '1025');
        $timeout = $read('FOURSMART_SMTP_TIMEOUT', '5');
        $from = $read('FOURSMART_MAIL_FROM', 'noreply@foursmart.test');
        $name = $read('FOURSMART_MAIL_FROM_NAME', 'FourSmart');
        $replyTo = $read('FOURSMART_MAIL_REPLY_TO', 'support@foursmart.test');
        if ($transport !== 'mailpit' || $host !== '127.0.0.1'
            || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535
            || !ctype_digit($timeout) || (int) $timeout < 1 || (int) $timeout > 10
            || !filter_var($from, FILTER_VALIDATE_EMAIL) || !str_ends_with($from, '.test')
            || !filter_var($replyTo, FILTER_VALIDATE_EMAIL) || !str_ends_with($replyTo, '.test')
            || $name === '' || strlen($name) > 100 || !mb_check_encoding($name, 'UTF-8')
            || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new RuntimeException('Local mail configuration is unavailable.');
        }
        return ['host' => $host, 'port' => (int) $port, 'timeout' => (int) $timeout,
            'from' => $from, 'name' => $name, 'reply_to' => $replyTo];
    }
}
