<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small string utilities used across the app (slugs, references, etc.).
 */
final class Str
{
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = strtolower(trim($value));
        // Transliterate where intl/iconv is available; degrade gracefully.
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }
        $value = preg_replace('/[^a-z0-9]+/i', $separator, $value) ?? '';
        return trim($value, $separator);
    }

    public static function random(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function limit(string $value, int $limit = 100, string $end = '…'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }
}
