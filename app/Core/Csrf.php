<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token manager (synchronizer-token pattern). See SECURITY_PLAN §8.
 * A per-session token is issued and verified with hash_equals (constant time).
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    /** Return the current token, generating one if needed. */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /** Constant-time comparison of a candidate token. */
    public function verify(?string $candidate): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        return is_string($stored)
            && is_string($candidate)
            && $candidate !== ''
            && hash_equals($stored, $candidate);
    }

    /** Rotate the token (e.g. after login). */
    public function rotate(): void
    {
        $this->session->put(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }
}
