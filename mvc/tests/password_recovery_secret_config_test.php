<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/../config/password_recovery.php';
$names = ['FOURSMART_PASSWORD_RECOVERY_ENABLED','FOURSMART_SESSION_VERSION_MODE','FOURSMART_RECOVERY_HMAC_KEY',
    'FOURSMART_RECOVERY_HMAC_KEY_FILE','FOURSMART_APP_URL','FOURSMART_RECOVERY_LOCAL_ONLY'];
$old = [];
foreach ($names as $name) { $old[$name] = getenv($name); putenv($name); }
$file = tempnam(sys_get_temp_dir(), 'foursmart-secret-fixture-');
$passed = 0; $failed = 0;
$test = static function (string $name, callable $run) use (&$passed, &$failed): void {
    try { if (!$run()) throw new RuntimeException(); $passed++; echo 'PASS ', $name, "\n"; }
    catch (Throwable $error) { $failed++; echo 'FAIL ', $name, "\n"; }
};
$reject = static function (): bool {
    try { PasswordRecoveryConfig::load(); return false; }
    catch (RuntimeException $error) { return $error->getMessage() === 'Recovery configuration is unavailable.'; }
};
try {
    $test('recovery is OFF without an explicit deployment flag', static fn() => !PasswordRecoveryConfig::enabled());
    $test('missing session policy fails closed instead of selecting legacy', static function (): bool {
        try { SessionVersionPolicy::required(); return false; }
        catch (RuntimeException $error) { return true; }
    });
    putenv('FOURSMART_PASSWORD_RECOVERY_ENABLED=1'); putenv('FOURSMART_SESSION_VERSION_MODE=required');
    $key = random_bytes(32); file_put_contents($file, base64_encode($key) . "\n");
    putenv('FOURSMART_RECOVERY_HMAC_KEY_FILE=' . $file);
    $test('external key file resolves 256-bit secret without exposing it', static fn() => hash_equals($key, PasswordRecoveryConfig::load()['key']));
    putenv('FOURSMART_RECOVERY_HMAC_KEY=' . base64_encode($key));
    $test('ambiguous direct and file secret sources are rejected', $reject);
    putenv('FOURSMART_RECOVERY_HMAC_KEY');
    file_put_contents($file, base64_encode(random_bytes(31)));
    $test('short secret file fails closed', $reject);
    file_put_contents($file, 'not-base64!');
    $test('invalid secret encoding fails closed', $reject);
    putenv('FOURSMART_RECOVERY_HMAC_KEY_FILE=' . $file . '-missing');
    $test('missing secret file fails closed', $reject);
    putenv('FOURSMART_RECOVERY_HMAC_KEY_FILE=' . __DIR__ . '/../../composer.json');
    $test('secret source inside repository is rejected', $reject);
    file_put_contents($file, str_repeat('a', 4097));
    putenv('FOURSMART_RECOVERY_HMAC_KEY_FILE=' . $file);
    $test('oversized secret file is rejected', $reject);
    putenv('FOURSMART_RECOVERY_HMAC_KEY_FILE');
    putenv('FOURSMART_RECOVERY_HMAC_KEY=' . base64_encode($key));
    $test('existing direct environment secret remains supported', static fn() => hash_equals($key, PasswordRecoveryConfig::load()['key']));
} finally {
    unlink($file);
    foreach ($old as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
}
echo $passed, ' passed, ', $failed, " failed\n";
exit($failed ? 1 : 0);
