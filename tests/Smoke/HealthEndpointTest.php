<?php

declare(strict_types=1);

namespace Tests\Smoke;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end smoke test: boots the real kernel and dispatches /health through
 * the full router + middleware + controller pipeline.
 */
final class HealthEndpointTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        // Boot the real application kernel.
        $this->app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
    }

    protected function tearDown(): void
    {
        // The bootstrap installs global handlers; restore so PHPUnit stays clean.
        restore_error_handler();
        restore_exception_handler();
    }

    private function get(string $path): Response
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $path;
        $_SERVER['HTTP_ACCEPT']    = 'application/json';
        return $this->app->run(Request::capture());
    }

    public function testHealthReturnsJsonPayload(): void
    {
        $response = $this->get('/health');

        // Without a configured DB the endpoint reports 'degraded' (503) but must
        // still respond cleanly with the expected structure — never a fatal.
        $this->assertContains($response->status(), [200, 503]);

        ob_start();
        $response->send();
        $body = (string) ob_get_clean();

        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertSame('ssjpharma', $data['service']);
    }

    public function testUnknownRouteReturns404(): void
    {
        $response = $this->get('/this-route-does-not-exist');
        $this->assertSame(404, $response->status());
    }
}
