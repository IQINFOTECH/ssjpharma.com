<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Role-Based Access Control foundation (SECURITY_PLAN §7).
 *
 * Permission checks are deny-by-default. A "super_admin" role is treated as a
 * wildcard. This foundation is enforced both by the Authorize middleware AND
 * re-checked inside services (defence in depth) from Phase 2 onward.
 */
final class Rbac
{
    public const SUPER_ADMIN = 'super_admin';

    public function __construct(private readonly Auth $auth)
    {
    }

    public function can(string $permission): bool
    {
        if ($this->auth->guest()) {
            return false;
        }

        if (in_array(self::SUPER_ADMIN, $this->auth->roles(), true)) {
            return true;
        }

        return in_array($permission, $this->auth->permissions(), true);
    }

    public function cannot(string $permission): bool
    {
        return !$this->can($permission);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->auth->roles(), true);
    }

    /** @param array<int,string> $permissions */
    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }
        return false;
    }
}
