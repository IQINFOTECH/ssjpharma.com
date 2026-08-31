<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Allowlist HTML sanitizer for admin-authored rich text (SECURITY_PLAN §4).
 *
 * Admins are trusted, but stored rich text is still scrubbed to defuse XSS:
 * dangerous elements are removed, tags are restricted to an allowlist, and
 * event-handler / javascript: attributes are stripped. This is a pragmatic
 * dependency-free filter; a full parser-based sanitizer can replace it later
 * behind this same API.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><span><hr>';

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // 1. Remove whole dangerous elements (with their content).
        $html = preg_replace('#<(script|style|iframe|object|embed|form|svg|math)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        // 2. Remove any stray dangerous self-contained tags.
        $html = preg_replace('#<(script|style|iframe|object|embed|link|meta|base)\b[^>]*/?>#i', '', $html) ?? '';

        // 3. Restrict to the tag allowlist.
        $html = strip_tags($html, self::ALLOWED_TAGS);

        // 4. Strip event-handler attributes (on*="...").
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';

        // 5. Neutralise javascript:/vbscript:/data: URIs in href/src.
        $html = preg_replace_callback(
            '#\b(href|src)\s*=\s*("([^"]*)"|\'([^\']*)\')#i',
            static function (array $m): string {
                $val = $m[3] !== '' ? $m[3] : ($m[4] ?? '');
                $trimmed = strtolower(trim($val));
                if (preg_match('#^(javascript|vbscript|data):#', $trimmed)) {
                    return $m[1] . '="#"';
                }
                return $m[0];
            },
            $html
        ) ?? '';

        return trim($html);
    }
}
