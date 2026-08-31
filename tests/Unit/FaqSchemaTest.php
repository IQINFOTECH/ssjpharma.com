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
 * FAQPage JSON-LD (AEO) must be built ONLY from real owner-entered Q&A pairs —
 * empty questions/answers are dropped, HTML is stripped, nothing fabricated.
 * faq() never touches settings, so no DB is required.
 */
final class FaqSchemaTest extends TestCase
{
    private function schema(): StructuredDataService
    {
        $db = new Database(['driver' => 'mysql', 'host' => 'x', 'port' => 3306, 'database' => '', 'username' => '', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'options' => []]);
        return new StructuredDataService(new SettingsService(new SettingRepository($db), new MediaRepository($db)));
    }

    public function testBuildsFaqPageFromValidPairs(): void
    {
        $s = $this->schema()->faq([
            ['question' => 'What does SSJ do?', 'answer' => '<p>Pharmaceutical manufacturing.</p>'],
            ['question' => 'Do you export?',    'answer' => 'Yes.'],
        ]);
        $this->assertSame('FAQPage', $s['@type']);
        $this->assertCount(2, $s['mainEntity']);
        $this->assertSame('Question', $s['mainEntity'][0]['@type']);
        $this->assertSame('What does SSJ do?', $s['mainEntity'][0]['name']);
        // HTML stripped from the answer text.
        $this->assertSame('Pharmaceutical manufacturing.', $s['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function testDropsEmptyOrIncompletePairs(): void
    {
        $s = $this->schema()->faq([
            ['question' => 'Real?', 'answer' => 'Yes'],
            ['question' => '',      'answer' => 'Orphan answer'],
            ['question' => 'No answer', 'answer' => ''],
            ['not' => 'an-item'],
        ]);
        $this->assertCount(1, $s['mainEntity']);
        $this->assertSame('Real?', $s['mainEntity'][0]['name']);
    }

    public function testEmptyInputYieldsEmptyMainEntity(): void
    {
        $this->assertSame([], $this->schema()->faq([])['mainEntity']);
    }
}
