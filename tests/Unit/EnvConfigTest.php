<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

final class EnvConfigTest extends TestCase
{
    public function testConfigDotNotationReads(): void
    {
        $config = new Config(dirname(__DIR__, 2) . '/config');

        // security.php ships a stable, non-secret default we can assert on.
        $this->assertSame('_token', $config->get('security.csrf.field_name'));
        $this->assertSame('DENY', $config->get('security.headers.X-Frame-Options'));
    }

    public function testConfigReturnsDefaultForMissingKey(): void
    {
        $config = new Config(dirname(__DIR__, 2) . '/config');
        $this->assertSame('fallback', $config->get('security.does.not.exist', 'fallback'));
    }

    public function testConfigRejectsTraversalKey(): void
    {
        $config = new Config(dirname(__DIR__, 2) . '/config');
        $this->assertSame('safe', $config->get('..%2Fevil.x', 'safe'));
    }
}
