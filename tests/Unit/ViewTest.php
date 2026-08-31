<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the layout/section renderer. Guards the bug where an
 * explicit start('content')/stop() section was clobbered by the auto-content
 * assignment, producing an empty page body.
 */
final class ViewTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ssjp-views-' . uniqid();
        @mkdir($this->dir . '/layouts', 0777, true);

        file_put_contents($this->dir . '/layouts/base.php',
            '<html><body>[<?= $this->section(\'content\') ?>]</body></html>');

        // Template that uses explicit section capture.
        file_put_contents($this->dir . '/page.php',
            '<?php $this->layout(\'layouts.base\'); $this->start(\'content\'); ?>HELLO-<?= e($name) ?><?php $this->stop(); ?>');

        // Template that echoes body directly (no explicit section).
        file_put_contents($this->dir . '/direct.php',
            '<?php $this->layout(\'layouts.base\'); ?>DIRECT-BODY');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/layouts/*') ?: []);
        array_map('unlink', glob($this->dir . '/*.php') ?: []);
        @rmdir($this->dir . '/layouts');
        @rmdir($this->dir);
    }

    public function testExplicitContentSectionIsRenderedIntoLayout(): void
    {
        $view = new View($this->dir);
        $html = $view->render('page', ['name' => 'World']);
        $this->assertStringContainsString('[HELLO-World]', $html);
    }

    public function testDirectBodyIsCapturedAsContent(): void
    {
        $view = new View($this->dir);
        $html = $view->render('direct');
        $this->assertStringContainsString('[DIRECT-BODY]', $html);
    }

    public function testSectionsDoNotBleedAcrossRenders(): void
    {
        $view = new View($this->dir);
        $view->render('page', ['name' => 'First']);
        $second = $view->render('direct'); // must not still contain First's content
        $this->assertStringContainsString('[DIRECT-BODY]', $second);
        $this->assertStringNotContainsString('HELLO-First', $second);
    }

    public function testOutputIsEscapedViaHelper(): void
    {
        $view = new View($this->dir);
        $html = $view->render('page', ['name' => '<script>x</script>']);
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
