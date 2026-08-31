<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for Phase 6 launch-hardening: content governance (demo
 * exclusion in production), JSON-LD breakout hardening, production security
 * net (debug + secure cookies forced), and the product review workflow. Pure
 * source scans (no DB), consistent with the project's other guard tests.
 */
final class Phase6HardeningTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testProductionForcesDebugOff(): void
    {
        $this->assertStringContainsString("!== 'production'", $this->read('config/app.php'));
        $this->assertStringContainsString('$isProduction', $this->read('bootstrap/app.php'));
    }

    public function testProductionForcesSecureCookie(): void
    {
        $sec = $this->read('config/security.php');
        $this->assertMatchesRegularExpression("/APP_ENV.*production.*\\?\\s*true/s", $sec);
    }

    public function testDemoRecordsAreHiddenFromPublicInProduction(): void
    {
        foreach (['app/Repositories/ProductRepository.php', 'app/Repositories/ProductCategoryRepository.php', 'app/Repositories/TherapeuticAreaRepository.php'] as $repo) {
            $src = $this->read($repo);
            $this->assertStringContainsString('demoCond', $src, "$repo must gate demo visibility");
            $this->assertStringContainsString("config('app.env') === 'production'", $src);
        }
    }

    public function testJsonLdEscapesScriptBreakout(): void
    {
        $this->assertStringContainsString('JSON_HEX_TAG', $this->read('app/Services/StructuredDataService.php'));
    }

    public function testProductReviewWorkflowPermissions(): void
    {
        $ctrl = $this->read('app/Controllers/Admin/ProductsController.php');
        $this->assertStringContainsString("'published' => 'products.publish'", $ctrl);
        $this->assertStringContainsString("'approved'  => 'products.review'", $ctrl);
        $this->assertStringContainsString("'archived'  => 'products.archive'", $ctrl);
        $rbac = $this->read('database/seeds/001_rbac.sql');
        $this->assertStringContainsString("'products.review'", $rbac);
        $this->assertStringContainsString("'products.archive'", $rbac);
    }

    public function testConversionTrackingSendsNoPii(): void
    {
        // Only a whitelisted, non-PII event marker is passed via the URL.
        $js = $this->read('public/assets/js/app.js');
        $this->assertStringContainsString('contact_form_submit', $js);
        $this->assertStringContainsString('ssjTrack', $js);
        // The contact controller redirects with the marker, not lead content.
        $this->assertStringContainsString("/thank-you?c=", $this->read('app/Controllers/Site/ContactController.php'));
    }
}
