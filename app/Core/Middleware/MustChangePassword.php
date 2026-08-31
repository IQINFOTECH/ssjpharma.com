<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Auth\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Forces a user flagged must_change_password to set a new one before using the
 * admin. The password-change and logout routes are exempt to avoid a lock-out.
 */
final class MustChangePassword implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->user();
        $exempt = in_array($request->path(), ['/admin/password', '/admin/logout'], true);

        if ($user !== null && !empty($user['must_change_password']) && !$exempt) {
            return Response::redirect('/admin/password');
        }

        return $next($request);
    }
}
