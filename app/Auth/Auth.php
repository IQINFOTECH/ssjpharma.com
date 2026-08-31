<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Session;

/**
 * Authentication foundation (session-based identity).
 *
 * Phase 0 establishes the API and session contract only — the login flow, user
 * repository, password hashing and throttling are implemented in Phase 2. The
 * identity is stored in the session as a minimal array so later phases can hydrate
 * a full user + permissions without changing callers.
 */
final class Auth
{
    private const SESSION_KEY = '_auth_user';

    public function __construct(private readonly Session $session)
    {
    }

    /** @param array{id:int,name:string,email:string,roles?:array,permissions?:array} $user */
    public function login(array $user): void
    {
        // Regenerate id on privilege change to prevent session fixation.
        $this->session->regenerate(true);
        $this->session->put(self::SESSION_KEY, $user);
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->invalidate();
    }

    public function check(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        $user = $this->session->get(self::SESSION_KEY);
        return is_array($user) ? $user : null;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return isset($user['id']) ? (int) $user['id'] : null;
    }

    /** @return array<int,string> */
    public function permissions(): array
    {
        return (array) ($this->user()['permissions'] ?? []);
    }

    /** @return array<int,string> */
    public function roles(): array
    {
        return (array) ($this->user()['roles'] ?? []);
    }
}
