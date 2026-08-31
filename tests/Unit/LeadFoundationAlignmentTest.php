<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the Phase 4 lead-foundation alignment: granular
 * note/status/priority permissions, the follow-up column, the Converted status,
 * and the Low/Medium/High/Urgent priority vocabulary. Pure source scans (no DB).
 */
final class LeadFoundationAlignmentTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $rel): string
    {
        return (string) file_get_contents($this->root() . '/' . $rel);
    }

    public function testGranularLeadPermissionsAreSeeded(): void
    {
        $rbac = $this->read('database/seeds/001_rbac.sql');
        foreach (['leads.notes', 'leads.status', 'leads.priority', 'leads.view_all', 'leads.view_assigned'] as $perm) {
            $this->assertStringContainsString("'{$perm}'", $rbac, "Missing permission {$perm} in RBAC seed.");
        }
    }

    public function testMutationRoutesUseGranularPermissions(): void
    {
        $routes = $this->read('routes/web.php');
        $this->assertMatchesRegularExpression('#/leads/\{id:\\\\d\+\}/status.*can:leads\.status#', $routes);
        $this->assertMatchesRegularExpression('#/leads/\{id:\\\\d\+\}/priority.*can:leads\.priority#', $routes);
        $this->assertMatchesRegularExpression('#/leads/\{id:\\\\d\+\}/notes.*can:leads\.notes#', $routes);
        $this->assertStringContainsString('/followup', $routes, 'Follow-up route missing.');
    }

    public function testFollowUpColumnMigrationExists(): void
    {
        $mig = $this->read('database/migrations/013_lead_followup_alignment.sql');
        $this->assertStringContainsString('follow_up_date', $mig);
        $this->assertStringContainsString("DEFAULT 'medium'", $mig, 'Priority default should be medium.');
    }

    public function testStatusVocabularyIsConverted(): void
    {
        $align = $this->read('database/seeds/009_lead_phase4_align.sql');
        $this->assertStringContainsString("'converted'", $align);
        // The controller priority vocabulary must be Low/Medium/High/Urgent (no "normal").
        $ctrl = $this->read('app/Controllers/Admin/LeadsController.php');
        $this->assertStringContainsString("['low', 'medium', 'high', 'urgent']", $ctrl);
        $this->assertStringNotContainsString("'normal'", $ctrl, 'Priority "normal" should be renamed to "medium".');
    }
}
