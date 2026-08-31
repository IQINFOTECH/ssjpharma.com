<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal, dependency-free CSV writer with spreadsheet formula-injection
 * protection (§27). Any cell whose first character could be interpreted as a
 * formula (= + - @) or as a leading control/tab/CR is prefixed with a single
 * quote so Excel/Sheets treat it as text, never as an executable formula.
 */
final class Csv
{
    /** Characters that make a spreadsheet treat a cell as a formula. */
    private const DANGEROUS = ['=', '+', '-', '@', "\t", "\r"];

    /** Quote + neutralise a single value. */
    public static function cell(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::DANGEROUS, true)) {
            $value = "'" . $value;
        }
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Build one CRLF-terminated CSV record.
     * @param array<int,string|int|float|null> $cells
     */
    public static function line(array $cells): string
    {
        $out = [];
        foreach ($cells as $c) {
            $out[] = self::cell((string) $c);
        }
        return implode(',', $out) . "\r\n";
    }

    /** UTF-8 BOM so Excel opens the file in the correct encoding. */
    public static function bom(): string
    {
        return "\xEF\xBB\xBF";
    }
}
