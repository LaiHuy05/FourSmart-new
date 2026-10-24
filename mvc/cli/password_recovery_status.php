<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/../core/bootstrap.php';

try {
    $enabled = PasswordRecoveryConfig::enabled();
    // Validate the shared secret/URL even when preparing an OFF deployment.
    if (!$enabled) putenv('FOURSMART_PASSWORD_RECOVERY_ENABLED=1');
    try { $config = PasswordRecoveryConfig::load(); }
    finally { putenv('FOURSMART_PASSWORD_RECOVERY_ENABLED=' . ($enabled ? '1' : '0')); }
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    $mail = PasswordRecoveryMailConfig::load();
    $db = Database::connection();
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $identity = $db->query('SELECT @@port AS port, DATABASE() AS db, CURRENT_USER() AS identity')->fetch();
        // Database::connection() already verifies port/schema/user against the
        // explicit private environment configuration; no machine-specific target here.
        $jobs = $db->query('SELECT status, COUNT(*) AS count, MAX(attempts) AS max_attempts
            FROM password_reset_jobs GROUP BY status ORDER BY status')->fetchAll();
        $errors = $db->query('SELECT last_error_code, COUNT(*) AS count FROM password_reset_jobs
            WHERE last_error_code IS NOT NULL GROUP BY last_error_code')->fetchAll();
        $safeErrors = [];
        foreach ($errors as $error) {
            $code = in_array($error['last_error_code'], ['SMTP_FAILED', 'JOB_EXPIRED'], true)
                ? $error['last_error_code'] : 'OTHER';
            $safeErrors[$code] = ($safeErrors[$code] ?? 0) + (int) $error['count'];
        }
        $tokens = (int) $db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn();
        $limits = (int) $db->query('SELECT COUNT(*) FROM password_reset_rate_limits')->fetchColumn();
    } finally { $db->rollBack(); }
    echo json_encode(['enabled' => $enabled, 'session_required' => SessionVersionPolicy::required(),
        'local_only' => getenv('FOURSMART_RECOVERY_LOCAL_ONLY') === '1', 'database' => $identity,
        'hmac_bytes' => strlen($config['key']), 'app_url' => $config['url'],
        'phpmailer' => PHPMailer\PHPMailer\PHPMailer::VERSION,
        'smtp' => ['host' => $mail['host'], 'port' => $mail['port'], 'timeout' => $mail['timeout']],
        'jobs' => $jobs, 'errors' => $safeErrors, 'tokens_count' => $tokens, 'rate_windows_count' => $limits], JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Local recovery status unavailable. Check private configuration and local dependencies.\n");
    exit(1);
}
