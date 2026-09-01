<?php

declare(strict_types=1);

/**
 * Global helper functions. Loaded via Composer's "files" autoload (and by the
 * bootstrap fallback). All are guarded so double-inclusion is harmless.
 */

use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Router;
use App\Core\View;

if (!function_exists('app')) {
    /** Resolve a service from the container (or the container itself). */
    function app(?string $id = null): mixed
    {
        $container = Container::getInstance();
        return $id === null ? $container : $container->get($id);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = Container::getInstance()->get(Config::class);
        return $config->get($key, $default);
    }
}

if (!function_exists('e')) {
    /** Escape a value for safe HTML output (UTF-8). Use everywhere in views. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        /** @var View $view */
        $view = Container::getInstance()->get(View::class);
        return $view->render($template, $data);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        /** @var Csrf $csrf */
        $csrf = Container::getInstance()->get(Csrf::class);
        return $csrf->token();
    }
}

if (!function_exists('csrf_field')) {
    /** Hidden input carrying the CSRF token — put in every form. */
    function csrf_field(): string
    {
        $name = (string) config('security.csrf.field_name', '_token');
        return '<input type="hidden" name="' . e($name) . '" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    /** Spoof PUT/PATCH/DELETE from an HTML form. */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('asset')) {
    /** Absolute URL to a file under public/assets, with a cache-busting ?v=mtime. */
    function asset(string $path): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $rel  = ltrim($path, '/');
        // Version by file mtime so a deploy invalidates far-future-cached assets.
        $file = dirname(__DIR__, 2) . '/public/assets/' . $rel;
        $ver  = is_file($file) ? '?v=' . filemtime($file) : '';
        return $base . '/assets/' . $rel . $ver;
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        /** @var Router $router */
        $router = Container::getInstance()->get(Router::class);
        return $router->url($name, $params);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);
        return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('logger')) {
    function logger(): \App\Core\Logger
    {
        return Container::getInstance()->get(\App\Core\Logger::class);
    }
}

if (!function_exists('can')) {
    /** True if the current user holds the permission (super_admin = wildcard). */
    function can(string $permission): bool
    {
        try {
            return Container::getInstance()->get(\App\Auth\Rbac::class)->can($permission);
        } catch (\Throwable) {
            return false;
        }
    }
}

if (!function_exists('media_url')) {
    /**
     * Resolve a section image reference to its public URL, or '' if missing.
     * Accepts an active Media id (numeric) OR a pasted same-origin path
     * (/assets/…, /uploads/…) or absolute http(s) URL, which is returned as-is.
     */
    function media_url(int|string|null $id): string
    {
        if ($id === null) {
            return '';
        }
        $val = is_string($id) ? trim($id) : (string) $id;
        if ($val === '') {
            return '';
        }
        // Bundled asset path — route through asset() so it gets the cache-busting
        // ?v=mtime (static files are served with a one-year immutable cache, so a
        // raw path would keep showing the OLD file after a deploy).
        if (str_starts_with($val, '/assets/')) {
            return asset(substr($val, strlen('/assets/')));
        }
        // Pasted absolute path or URL — use verbatim (CSP still governs loading).
        if ($val[0] === '/' || str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) {
            return $val;
        }
        if (!ctype_digit($val)) {
            return '';
        }
        $mediaId = (int) $val;
        if ($mediaId <= 0) {
            return '';
        }
        /** @var \App\Repositories\MediaRepository $repo */
        $repo = Container::getInstance()->get(\App\Repositories\MediaRepository::class);
        $row = $repo->findActive($mediaId);
        return $row['url_path'] ?? '';
    }
}
