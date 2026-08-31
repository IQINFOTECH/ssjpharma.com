<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LeadVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4.1 — permission → visibility-scope resolution. Permission-driven (a
 * callable predicate stands in for RBAC), never role-name driven.
 */
final class LeadVisibilityTest extends TestCase
{
    /** @param string[] $granted */
    private function can(array $granted): callable
    {
        return static fn (string $perm): bool => in_array($perm, $granted, true);
    }

    public function testViewAllSeesEverything(): void
    {
        $s = LeadVisibility::scope($this->can(['leads.view', 'leads.view_all']), 7);
        $this->assertSame('all', $s['mode']);
        $this->assertTrue(LeadVisibility::canSeeAny($s));
    }

    public function testViewAllWinsOverAssigned(): void
    {
        // A user granted both resolves to the broader scope.
        $s = LeadVisibility::scope($this->can(['leads.view_all', 'leads.view_assigned']), 7);
        $this->assertSame('all', $s['mode']);
    }

    public function testAssignedIsRestrictedToOwnUserId(): void
    {
        $s = LeadVisibility::scope($this->can(['leads.view', 'leads.view_assigned']), 42);
        $this->assertSame('assigned', $s['mode']);
        $this->assertSame(42, $s['user_id']);
        $this->assertTrue(LeadVisibility::canSeeAny($s));
    }

    public function testViewOnlyGrantsNoVisibility(): void
    {
        // leads.view alone is MODULE ACCESS — it must NOT grant visibility.
        $s = LeadVisibility::scope($this->can(['leads.view']), 7);
        $this->assertSame('none', $s['mode']);
        $this->assertFalse(LeadVisibility::canSeeAny($s));
    }

    public function testNoLeadPermissionsSeesNothing(): void
    {
        $s = LeadVisibility::scope($this->can([]), 7);
        $this->assertSame('none', $s['mode']);
        $this->assertFalse(LeadVisibility::canSeeAny($s));
    }

    public function testAssignedWithoutUserIdFallsToNone(): void
    {
        // No authenticated user id → cannot scope to "assigned" → deny.
        $s = LeadVisibility::scope($this->can(['leads.view_assigned']), null);
        $this->assertSame('none', $s['mode']);
    }
}
