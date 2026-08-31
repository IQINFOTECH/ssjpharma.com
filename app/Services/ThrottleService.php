<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\LoginAttemptRepository;

/**
 * Login throttling (Phase 2 §7). DB-backed (login_attempts) — no Redis, GoDaddy
 * compatible. Temporary, self-clearing lockout: after N failures within the decay
 * window, further attempts are blocked until the window rolls off. A successful
 * login clears the counter. Users are never permanently locked.
 */
final class ThrottleService
{
    private int $maxAttempts;
    private int $decaySeconds;

    public function __construct(
        private readonly LoginAttemptRepository $attempts,
        Config $config,
    ) {
        $this->maxAttempts  = max(3, (int) $config->get('security.throttle.max_attempts', 5));
        $this->decaySeconds = max(60, (int) $config->get('security.throttle.decay_minutes', 15) * 60);
    }

    public function tooManyAttempts(string $email, string $ip): bool
    {
        return $this->attempts->recentFailures($email, $ip, $this->decaySeconds) >= $this->maxAttempts;
    }

    public function recordFailure(string $email, string $ip): void
    {
        $this->attempts->record($email, $ip, false);
    }

    public function recordSuccess(string $email, string $ip): void
    {
        $this->attempts->record($email, $ip, true);
        $this->attempts->clearFailures($email, $ip);
    }

    public function retryAfterMinutes(): int
    {
        return (int) ceil($this->decaySeconds / 60);
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }
}
