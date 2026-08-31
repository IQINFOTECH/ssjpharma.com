<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function testRemovesScriptTags(): void
    {
        $out = HtmlSanitizer::clean('<p>Hi</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('<p>Hi</p>', $out);
    }

    public function testStripsEventHandlers(): void
    {
        $out = HtmlSanitizer::clean('<a href="/x" onclick="steal()">link</a>');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('href="/x"', $out);
    }

    public function testNeutralisesJavascriptUri(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function testDropsDisallowedTagsButKeepsText(): void
    {
        $out = HtmlSanitizer::clean('<iframe src="evil"></iframe><strong>keep</strong>');
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringContainsString('<strong>keep</strong>', $out);
    }
}
