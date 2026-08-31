<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Auth\Rbac;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * RBAC gate. Denies (403) unless the current user holds the required permission.
 * Used as the parameterised route middleware "can:<permission>". Services re-check
 * the same permission (defence in depth) — this is the first of the two layers.
 */
final class Authorize implements MiddlewareInterface
{
    public function __construct(
        private readonly Rbac $rbac,
        private readonly string $permission,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->permission !== '' && $this->rbac->cannot($this->permission)) {
            throw new HttpException(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
