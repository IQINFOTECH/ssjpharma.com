<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Middleware contract. Each middleware may inspect/short-circuit the request or
 * pass it along by calling $next($request), then optionally post-process the
 * returned Response.
 */
interface MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response;
}
