<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\EnquiryType;
use PHPUnit\Framework\TestCase;

/**
 * Enquiry type/source are resolved SERVER-SIDE from the form key (§5) — the
 * client can never dictate the lead's classification.
 */
final class EnquiryTypeTest extends TestCase
{
    public function testProductFormMapsToProductEnquiry(): void
    {
        $r = EnquiryType::resolve('product-enquiry');
        $this->assertSame('product', $r['type']);
        $this->assertSame('product_enquiry', $r['source']);
    }

    public function testDistributorAndPartnershipMap(): void
    {
        $this->assertSame('distributor', EnquiryType::resolve('become-a-distributor')['type']);
        $this->assertSame('partnership', EnquiryType::resolve('partnership')['type']);
        $this->assertSame('partnership_enquiry', EnquiryType::resolve('partnership')['source']);
    }

    public function testUnknownFormKeyFallsBackToGeneral(): void
    {
        // A spoofed/unknown key must NOT grant an arbitrary type — it defaults safely.
        $r = EnquiryType::resolve('sales;DROP TABLE leads');
        $this->assertSame('general', $r['type']);
        $this->assertSame('contact_form', $r['source']);
    }

    public function testAllTypesAreClosedSet(): void
    {
        $this->assertSame(['general', 'product', 'distributor', 'partnership', 'other'], EnquiryType::all());
    }
}
