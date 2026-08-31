<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Auth\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Requires an authenticated user. Guests are redirected to the login route
 * (HTML) or refused with 401 (JSON/AJAX). Gates the /admin area from Phase 2.
 */
final class Authenticate implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->guest()) {
            if ($request->wantsJson()) {
                throw new HttpException(401, 'Authentication required.');
            }
            return Response::redirect('/admin/login');
        }

        return $next($request);
    }
}
