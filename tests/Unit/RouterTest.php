<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private function request(string $method, string $path): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $path;
        return Request::capture();
    }

    public function testMatchesStaticRoute(): void
    {
        $router = new Router();
        $router->get('/health', ['H', 'index']);

        $matched = $router->match($this->request('GET', '/health'));

        $this->assertSame(['H', 'index'], $matched['handler']);
        $this->assertSame([], $matched['params']);
    }

    public function testMatchesDynamicSegment(): void
    {
        $router = new Router();
        $router->get('/products/{category}/{slug}', ['P', 'show']);

        $matched = $router->match($this->request('GET', '/products/antibiotics/amox-500'));

        $this->assertSame('antibiotics', $matched['params']['category']);
        $this->assertSame('amox-500', $matched['params']['slug']);
    }

    public function testConstrainedSegmentRejectsBadInput(): void
    {
        $router = new Router();
        $router->get('/lead/{id:\d+}', ['L', 'show']);

        $this->expectException(HttpException::class);
        $router->match($this->request('GET', '/lead/not-a-number'));
    }

    public function testUnknownPathIs404(): void
    {
        $router = new Router();
        $router->get('/', ['H', 'index']);

        try {
            $router->match($this->request('GET', '/nope'));
            $this->fail('Expected 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testWrongMethodIs405(): void
    {
        $router = new Router();
        $router->get('/leads', ['L', 'index']);

        try {
            $router->match($this->request('POST', '/leads'));
            $this->fail('Expected 405');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->getStatusCode());
        }
    }

    public function testGroupPrefixAndMiddleware(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'admin', 'middleware' => ['auth']], function (Router $r): void {
            $r->get('/leads', ['L', 'index'], ['middleware' => ['can:leads.view.own']]);
        });

        $matched = $router->match($this->request('GET', '/admin/leads'));
        $this->assertSame(['auth', 'can:leads.view.own'], $matched['middleware']);
    }

    public function testNamedRouteUrlGeneration(): void
    {
        $router = new Router();
        $router->get('/products/{slug}', ['P', 'show'], ['name' => 'product.show']);

        $this->assertSame('/products/amox-500', $router->url('product.show', ['slug' => 'amox-500']));
    }
}
