<?php

declare(strict_types=1);

final class PasswordRecoveryService
{
    private Closure $clock;
    private Closure $send;

    public function __construct(private PasswordRecoveryModel $model, #[SensitiveParameter] private array $config,
        ?callable $send = null, ?callable $clock = null)
    {
        $mailer = new PasswordRecoveryMailer($config['url']);
        $this->send = Closure::fromCallable($send ?? [$mailer, 'send']);
        $this->clock = Closure::fromCallable($clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    public static function email(mixed $value): ?string
    {
        if (!is_string($value) || strlen($value) > 255 || !filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return strtolower(trim($value));
    }

    public static function validPassword(#[SensitiveParameter] mixed $password): bool
    {
        return is_string($password) && mb_check_encoding($password, 'UTF-8')
            && !str_contains($password, "\0") && strlen($password) <= 72 && mb_strlen($password, 'UTF-8') >= 12
            && preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password) && preg_match('/[^\p{L}\p{N}\s]/u', $password);
    }

    private static function ip(string $ip): string
    {
        $packed = @inet_pton($ip);
        return $packed === false ? 'unknown' : bin2hex($packed);
    }

    public function request(string $email, string $ip): void
    {
        // The caller never receives existence, rate-limit or SMTP information.
        $this->model->request($email, self::ip($ip), $this->config['key'], ($this->clock)());
    }

    public function allowReset(string $ip): bool
    {
        return $this->model->allowReset(self::ip($ip), $this->config['key'], ($this->clock)());
    }

    public function valid(#[SensitiveParameter] string $hash): bool
    {
        return strlen($hash) === 32 && $this->model->valid($hash, ($this->clock)());
    }

    public function reset(#[SensitiveParameter] string $hash, #[SensitiveParameter] mixed $password,
        #[SensitiveParameter] mixed $confirmation): bool
    {
        return self::validPassword($password) && is_string($confirmation) && hash_equals($password, $confirmation)
            && $this->model->reset($hash, $password, ($this->clock)());
    }

    public function processOne(): string
    {
        $job = $this->model->claim(($this->clock)());
        if ($job === null) {
            return 'idle';
        }
        if (isset($job['skipped'])) {
            return 'discarded';
        }
        $issued = $this->model->issue($job, ($this->clock)());
        if ($issued === null) {
            return 'discarded';
        }
        try {
            ($this->send)($issued['email'], $issued['token']);
        } catch (Throwable $error) {
            return $this->model->retry($job, ($this->clock)()) ? 'retry_or_failed' : 'lease_lost';
        } finally {
            unset($issued);
        }
        return $this->model->finish($job, ($this->clock)(), 'sent') ? 'sent' : 'lease_lost';
    }

    public function cleanup(): void
    {
        $this->model->cleanup(($this->clock)());
    }
}
