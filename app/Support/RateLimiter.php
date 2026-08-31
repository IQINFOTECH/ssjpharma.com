<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Filesystem rate limiter (no Redis — ADR-001). Fixed-window counter per key,
 * stored under storage/cache/ratelimit. Used to throttle public form abuse.
 */
final class RateLimiter
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (base_path('storage/cache/ratelimit'));
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    /**
     * Returns true if the action is allowed (and records a hit), false if the
     * limit for the window has been exceeded.
     */
    public function attempt(string $key, int $maxHits, int $windowSeconds): bool
    {
        $file = $this->path($key);
        $now  = time();

        $data = ['count' => 0, 'reset' => $now + $windowSeconds];
        if (is_file($file)) {
            $raw = json_decode((string) @file_get_contents($file), true);
            if (is_array($raw) && ($raw['reset'] ?? 0) > $now) {
                $data = $raw;
            }
        }

        if ($data['count'] >= $maxHits) {
            return false;
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . hash('sha256', $key) . '.json';
    }
}
