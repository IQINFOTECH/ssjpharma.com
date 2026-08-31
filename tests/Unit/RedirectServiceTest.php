<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\RedirectRepository;
use App\Services\RedirectService;
use PHPUnit\Framework\TestCase;

/**
 * Covers RedirectService::validate() (pure — no DB access), including loop and
 * open-redirect protection.
 */
final class RedirectServiceTest extends TestCase
{
    private function service(): RedirectService
    {
        // validate() never touches the DB, so an unconnected repo/db is fine.
        $db = new Database(['driver' => 'mysql', 'host' => 'x', 'port' => 3306, 'database' => '', 'username' => '', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'options' => []]);
        return new RedirectService(new RedirectRepository($db));
    }

    public function testRejectsLoop(): void
    {
        $this->assertNotNull($this->service()->validate('/a', '/a'));
    }

    public function testRejectsHomeSource(): void
    {
        $this->assertNotNull($this->service()->validate('/', '/somewhere'));
    }

    public function testRejectsUnsafeTarget(): void
    {
        // protocol-relative // and non-http schemes are unsafe
        $this->assertNotNull($this->service()->validate('/old', '//evil.com'));
        $this->assertNotNull($this->service()->validate('/old', 'ftp://evil'));
    }

    public function testAcceptsInternalPath(): void
    {
        $this->assertNull($this->service()->validate('/old-page', '/new-page'));
    }

    public function testAcceptsAbsoluteHttps(): void
    {
        $this->assertNull($this->service()->validate('/go', 'https://example.com/page'));
    }
}
