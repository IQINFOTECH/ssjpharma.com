<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard: a named placeholder must never be reused within one SQL
 * statement. PDO with ATTR_EMULATE_PREPARES=false (this project's setting)
 * rejects a repeated named placeholder with HY093 "Invalid parameter number",
 * which previously broke every admin/public search. Searches now concatenate the
 * columns (CONCAT_WS) and bind a single placeholder.
 */
final class SearchPlaceholderTest extends TestCase
{
    public function testNoRepositoryReusesANamedPlaceholder(): void
    {
        $dir = dirname(__DIR__, 2) . '/app/Repositories';
        $files = glob($dir . '/*.php') ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            // Detect the same :name appearing twice close together (e.g. LIKE :q ... LIKE :q)
            $offending = preg_match('/:([a-z_][a-z0-9_]*)\b[\s\S]{0,160}?\bLIKE\s+:\1\b/i', $src)
                || preg_match('/LIKE\s+:([a-z_][a-z0-9_]*)\b[\s\S]{0,160}?LIKE\s+:\1\b/i', $src);
            $this->assertSame(0, (int) $offending, 'Reused named placeholder in ' . basename($file) . ' (fails under EMULATE_PREPARES=false).');
        }
    }
}
