<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Template rendering security (Phase 5, §8). Rendering is pure string
 * substitution — it must never execute code, must HTML-escape values in HTML
 * bodies, must strip CR/LF from subjects (header-injection), and must drop
 * unknown placeholders.
 */
final class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $r;

    protected function setUp(): void
    {
        $this->r = new TemplateRenderer();
    }

    public function testHtmlValuesAreEscaped(): void
    {
        $out = $this->r->render('Hi {{lead.name}}', ['lead.name' => '<script>alert(1)</script>'], true);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function testTextValuesAreNotEscapedButAreNotExecuted(): void
    {
        // A value that looks like PHP is inserted literally as text — never run.
        $out = $this->r->render('X {{v}} Y', ['v' => '<?php echo 99; ?>'], false);
        $this->assertSame('X <?php echo 99; ?> Y', $out);
    }

    public function testTemplateMarkupIsNeverEvaluated(): void
    {
        // PHP tags inside the TEMPLATE itself are literal text, not executed.
        $out = $this->r->render('A <?php system("x"); ?> {{v}}', ['v' => 'ok'], false);
        $this->assertStringContainsString('<?php system("x"); ?>', $out);
        $this->assertStringContainsString('ok', $out);
    }

    public function testUnknownPlaceholdersBecomeEmpty(): void
    {
        $this->assertSame('a  b', $this->r->render('a {{nope.key}} b', [], true));
    }

    public function testSubjectStripsCrLf(): void
    {
        $s = $this->r->renderSubject('Re: {{v}}', ['v' => "hello\r\nBcc: evil@x.com"]);
        $this->assertStringNotContainsString("\r", $s);
        $this->assertStringNotContainsString("\n", $s);
        $this->assertStringContainsString('Bcc: evil@x.com', $s); // now inert, single line
    }

    public function testRawKeysBypassEscapingOnlyWhenListed(): void
    {
        $ctx = ['safe.html' => '<b>rows</b>', 'lead.name' => '<i>x</i>'];
        $out = $this->r->render('{{safe.html}}|{{lead.name}}', $ctx, true, ['safe.html']);
        $this->assertStringContainsString('<b>rows</b>', $out);      // raw key inserted as-is
        $this->assertStringContainsString('&lt;i&gt;x&lt;/i&gt;', $out); // non-raw still escaped
    }

    public function testRenderTemplateProducesSubjectHtmlText(): void
    {
        $tpl = ['subject' => '{{s}}', 'body_html' => '<p>{{s}}</p>', 'body_text' => '{{s}}'];
        $out = $this->r->renderTemplate($tpl, ['s' => 'A & B']);
        $this->assertSame('A & B', $out['subject']);
        $this->assertSame('<p>A &amp; B</p>', $out['html']);
        $this->assertSame('A & B', $out['text']);
    }

    public function testFlattenNestedToDottedKeys(): void
    {
        $flat = $this->r->flatten(['lead' => ['name' => 'Sam', 'city' => 'Pune']]);
        $this->assertSame('Sam', $flat['lead.name']);
        $this->assertSame('Pune', $flat['lead.city']);
    }
}
