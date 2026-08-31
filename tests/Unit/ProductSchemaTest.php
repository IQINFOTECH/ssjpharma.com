<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\MediaRepository;
use App\Repositories\SettingRepository;
use App\Services\SettingsService;
use App\Services\StructuredDataService;
use PHPUnit\Framework\TestCase;

/**
 * Product JSON-LD must contain ONLY real, supported fields — never fabricated
 * price/availability/rating/review (§20). With absolute URLs, product() never
 * touches settings, so no DB is required.
 */
final class ProductSchemaTest extends TestCase
{
    private function schema(): StructuredDataService
    {
        $db = new Database(['driver' => 'mysql', 'host' => 'x', 'port' => 3306, 'database' => '', 'username' => '', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'options' => []]);
        $settings = new SettingsService(new SettingRepository($db), new MediaRepository($db));
        return new StructuredDataService($settings);
    }

    public function testIncludesOnlySuppliedFields(): void
    {
        $s = $this->schema()->product([
            'name'        => 'Demo Product',
            'url'         => 'https://ssjpharma.com/products/demo',
            'description' => 'A description',
            'image'       => 'https://ssjpharma.com/i.png',
            'sku'         => 'DEMO-001',
        ]);
        $this->assertSame('Product', $s['@type']);
        $this->assertSame('Demo Product', $s['name']);
        $this->assertSame('DEMO-001', $s['sku']);
        $this->assertSame('https://ssjpharma.com/i.png', $s['image']);
    }

    public function testOmitsEmptyFields(): void
    {
        $s = $this->schema()->product(['name' => 'Bare', 'url' => 'https://ssjpharma.com/products/bare']);
        $this->assertArrayNotHasKey('description', $s);
        $this->assertArrayNotHasKey('image', $s);
        $this->assertArrayNotHasKey('sku', $s);
    }

    public function testNeverEmitsFabricatedCommerceFields(): void
    {
        $s = $this->schema()->product([
            'name' => 'X', 'url' => 'https://ssjpharma.com/products/x',
            'description' => 'd', 'image' => 'https://ssjpharma.com/i.png', 'sku' => 'S',
        ]);
        foreach (['price', 'offers', 'availability', 'aggregateRating', 'review', 'rating', 'brand'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $s);
        }
        $json = $this->schema()->encode($s);
        $this->assertStringNotContainsString('aggregateRating', $json);
    }
}
