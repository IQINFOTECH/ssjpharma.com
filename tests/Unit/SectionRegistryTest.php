<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SectionRegistry;
use App\Support\Str;
use PHPUnit\Framework\TestCase;

final class SectionRegistryTest extends TestCase
{
    public function testHasTheTenInitialSectionTypes(): void
    {
        $keys = SectionRegistry::keys();
        foreach (['hero','richtext','image_text','cards','cta','faq','stats','product_showcase','testimonials','contact_cta'] as $t) {
            $this->assertContains($t, $keys, "missing section type {$t}");
            $this->assertTrue(SectionRegistry::exists($t));
            $this->assertNotEmpty(SectionRegistry::fields($t));
        }
    }

    public function testUnknownTypeIsRejected(): void
    {
        $this->assertFalse(SectionRegistry::exists('malicious_type'));
    }

    public function testSlugHelper(): void
    {
        $this->assertSame('about-us', Str::slug('About Us!'));
        $this->assertSame('become-a-distributor', Str::slug('Become  a Distributor'));
    }
}
