<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves a user's lead-visibility SCOPE from their permissions (Phase 4.1).
 *
 * Permission-driven, never role-name driven:
 *   - leads.view_all       → sees every lead                        (mode "all")
 *   - leads.view_assigned  → sees only leads assigned to themselves  (mode "assigned")
 *   - neither              → sees NO leads                           (mode "none")
 *
 * `leads.view` alone is module access only — it does NOT grant visibility.
 * super_admin/admin obtain view_all through the normal RBAC grant/wildcard, so
 * no role is special-cased here.
 *
 * The resulting scope is enforced in SQL at the data-access layer
 * (LeadRepository) — it is never applied by filtering rows in PHP, and the
 * assigned user id comes from the authenticated session, never from client input.
 */
final class LeadVisibility
{
    public const ALL      = 'all';
    public const ASSIGNED = 'assigned';
    public const NONE     = 'none';

    /**
     * @param callable(string):bool $can    permission check for the current user
     * @param int|null              $userId authenticated user id (for assigned scope)
     * @return array{mode:string,user_id:int|null}
     */
    public static function scope(callable $can, ?int $userId): array
    {
        if ($can('leads.view_all')) {
            return ['mode' => self::ALL, 'user_id' => $userId];
        }
        if ($userId !== null && $can('leads.view_assigned')) {
            return ['mode' => self::ASSIGNED, 'user_id' => $userId];
        }
        return ['mode' => self::NONE, 'user_id' => $userId];
    }

    /** True when the scope permits viewing at least some leads. */
    public static function canSeeAny(array $scope): bool
    {
        return ($scope['mode'] ?? self::NONE) !== self::NONE;
    }
}
