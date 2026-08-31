<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;
use App\Auth\Rbac;
use App\Core\Exceptions\HttpException;
use App\Core\Middleware\Authenticate;
use App\Core\Middleware\Authorize;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Middleware\SecurityHeaders;
use App\Core\Middleware\StartSession;
use App\Core\Middleware\VerifyCsrf;
use Closure;
use Throwable;

/**
 * Application kernel. Owns the container + router and runs the request through
 * the middleware pipeline to a controller, translating any error into a clean
 * Response. Central place that maps middleware aliases to instances.
 */
final class App
{
    /** Global middleware, outermost first. */
    private const GLOBAL_MIDDLEWARE = [
        SecurityHeaders::class,
        StartSession::class,
        VerifyCsrf::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
    ) {
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function run(Request $request): Response
    {
        try {
            $matched = $this->router->match($request);
            $request->setRouteParams($matched['params']);

            $pipeline = $this->buildPipeline(
                array_merge(self::GLOBAL_MIDDLEWARE, $matched['middleware']),
                fn (Request $req): Response => $this->dispatch($matched['handler'], $req),
            );

            return $pipeline($request);
        } catch (HttpException $e) {
            return $this->renderError($request, $e->getStatusCode(), $e);
        } catch (Throwable $e) {
            $this->logException($e);
            return $this->renderError($request, 500, $e);
        }
    }

    /**
     * @param array<int,string> $middleware
     */
    private function buildPipeline(array $middleware, Closure $core): Closure
    {
        return array_reduce(
            array_reverse($middleware),
            function (Closure $next, string $alias): Closure {
                return function (Request $request) use ($next, $alias): Response {
                    return $this->resolveMiddleware($alias)->handle($request, $next);
                };
            },
            $core,
        );
    }

    private function resolveMiddleware(string $alias): MiddlewareInterface
    {
        // Parameterised form:  can:leads.view.all
        if (str_contains($alias, ':')) {
            [$name, $arg] = explode(':', $alias, 2);

            if ($name === 'can') {
                return new Authorize($this->container->get(Rbac::class), $arg);
            }
        }

        return match ($alias) {
            SecurityHeaders::class => $this->container->get(SecurityHeaders::class),
            StartSession::class    => $this->container->get(StartSession::class),
            VerifyCsrf::class      => $this->container->get(VerifyCsrf::class),
            'auth'                 => new Authenticate($this->container->get(Auth::class)),
            'must_change'          => new \App\Core\Middleware\MustChangePassword($this->container->get(Auth::class)),
            'track_session'        => new \App\Core\Middleware\TrackSession(
                $this->container->get(Auth::class),
                $this->container->get(\App\Core\Session::class),
                $this->container->get(\App\Repositories\UserSessionRepository::class),
            ),
            default                => $this->container->get($alias),
        };
    }

    private function dispatch(mixed $handler, Request $request): Response
    {
        if ($handler instanceof Closure) {
            $result = $handler($request, $this->container);
            return $this->toResponse($result);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class($this->container);
            $result = $controller->{$method}($request);
            return $this->toResponse($result);
        }

        throw new HttpException(500, 'Invalid route handler.');
    }

    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    private function renderError(Request $request, int $status, Throwable $e): Response
    {
        $debug = (bool) $this->container->get(Config::class)->get('app.debug', false);

        if ($request->wantsJson()) {
            $payload = ['error' => true, 'status' => $status];
            if ($debug) {
                $payload['message'] = $e->getMessage();
            }
            return Response::json($payload, $status);
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        $template = is_file(dirname(__DIR__) . "/Views/errors/{$status}.php")
            ? "errors.{$status}"
            : 'errors.500';

        try {
            $html = $view->render($template, [
                'status'  => $status,
                'debug'   => $debug,
                'message' => $debug ? $e->getMessage() : '',
                'trace'   => $debug ? $e->getTraceAsString() : '',
            ]);
        } catch (Throwable) {
            $html = "<h1>{$status}</h1><p>An error occurred.</p>";
        }

        return Response::html($html, $status);
    }

    private function logException(Throwable $e): void
    {
        if ($this->container->has(Logger::class)) {
            $this->container->get(Logger::class)->error(
                '{class}: {message} in {file}:{line}',
                [
                    'class'   => $e::class,
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ],
            );
        }
    }
}
