<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * First-party router (no nikic/fast-route dependency, ADR-001).
 *
 * Supports static and dynamic segments ({id}, {slug:[a-z0-9-]+}), per-route and
 * grouped middleware, and named routes for URL generation. Small and explicit.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,vars:array<int,string>,handler:mixed,middleware:array<int,string>,name:?string}> */
    private array $routes = [];

    /** @var array<string,string> name => path template */
    private array $names = [];

    /** group context stack */
    private string $prefix = '';
    /** @var array<int,string> */
    private array $groupMiddleware = [];

    public function get(string $path, mixed $handler, array $opts = []): void    { $this->add('GET', $path, $handler, $opts); }
    public function post(string $path, mixed $handler, array $opts = []): void   { $this->add('POST', $path, $handler, $opts); }
    public function put(string $path, mixed $handler, array $opts = []): void    { $this->add('PUT', $path, $handler, $opts); }
    public function patch(string $path, mixed $handler, array $opts = []): void  { $this->add('PATCH', $path, $handler, $opts); }
    public function delete(string $path, mixed $handler, array $opts = []): void { $this->add('DELETE', $path, $handler, $opts); }

    /**
     * @param array{prefix?:string,middleware?:array<int,string>} $attributes
     */
    public function group(array $attributes, callable $routes): void
    {
        $previousPrefix     = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix          .= '/' . trim($attributes['prefix'] ?? '', '/');
        $this->prefix           = rtrim($this->prefix, '/');
        $this->groupMiddleware  = array_merge($this->groupMiddleware, $attributes['middleware'] ?? []);

        $routes($this);

        $this->prefix          = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function add(string $method, string $path, mixed $handler, array $opts = []): void
    {
        $path = $this->prefix . '/' . trim($path, '/');
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }

        [$regex, $vars] = $this->compile($path);

        $name = $opts['name'] ?? null;
        if ($name !== null) {
            $this->names[$name] = $path;
        }

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $path,
            'regex'      => $regex,
            'vars'       => $vars,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $opts['middleware'] ?? []),
            'name'       => $name,
        ];
    }

    /**
     * @return array{handler:mixed,middleware:array<int,string>,params:array<string,string>}
     * @throws HttpException 404 (no path) / 405 (path exists, wrong method)
     */
    public function match(Request $request): array
    {
        $path          = $request->path();
        $method         = $request->method();
        $pathMatched    = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($route['vars'] as $var) {
                if (isset($m[$var])) {
                    $params[$var] = $m[$var];
                }
            }

            return [
                'handler'    => $route['handler'],
                'middleware' => $route['middleware'],
                'params'     => $params,
            ];
        }

        throw new HttpException($pathMatched ? 405 : 404);
    }

    public function url(string $name, array $params = []): string
    {
        $template = $this->names[$name] ?? '/';

        foreach ($params as $key => $value) {
            $template = preg_replace('/\{' . preg_quote((string) $key, '/') . '(:[^}]+)?\}/', (string) $value, $template);
        }

        return $template;
    }

    /**
     * Compile a path template to a regex + ordered variable names.
     * Supports {name} and {name:pattern}.
     *
     * @return array{0:string,1:array<int,string>}
     */
    private function compile(string $path): array
    {
        $vars = [];

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            function (array $m) use (&$vars): string {
                $vars[] = $m[1];
                $pattern = $m[2] ?? '[^/]+';
                return '(?P<' . $m[1] . '>' . $pattern . ')';
            },
            $path,
        );

        return ['#^' . $regex . '$#', $vars];
    }
}
