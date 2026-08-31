<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

/**
 * CSV export must neutralise spreadsheet formula-injection (§27): a value that
 * starts with = + - @ (or tab/CR) is prefixed with a single quote so Excel/Sheets
 * treat it as text, never execute it.
 */
final class CsvTest extends TestCase
{
    public function testFormulaTriggersAreNeutralised(): void
    {
        $this->assertSame('"\'=1+1"', Csv::cell('=1+1'));
        $this->assertSame('"\'+1"', Csv::cell('+1'));
        $this->assertSame('"\'-1"', Csv::cell('-1'));
        $this->assertSame('"\'@SUM(A1)"', Csv::cell('@SUM(A1)'));
    }

    public function testClassicHyperlinkPayloadIsNeutralised(): void
    {
        $payload = '=HYPERLINK("http://evil","click")';
        $out = Csv::cell($payload);
        $this->assertStringStartsWith('"\'=', $out, 'leading = must be quote-prefixed');
    }

    public function testOrdinaryValuesAreUnchangedApartFromQuoting(): void
    {
        $this->assertSame('"Acme Pharma"', Csv::cell('Acme Pharma'));
        $this->assertSame('""', Csv::cell(''));
    }

    public function testEmbeddedQuotesAreDoubled(): void
    {
        $this->assertSame('"He said ""hi"""', Csv::cell('He said "hi"'));
    }

    public function testLineIsCrlfTerminatedAndComprised(): void
    {
        $line = Csv::line(['a', '=b', 'c,d']);
        $this->assertSame('"a","\'=b","c,d"' . "\r\n", $line);
    }

    public function testBomIsUtf8(): void
    {
        $this->assertSame("\xEF\xBB\xBF", Csv::bom());
    }
}
