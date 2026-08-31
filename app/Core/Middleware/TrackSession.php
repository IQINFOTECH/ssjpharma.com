<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Auth\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserSessionRepository;
use Closure;

/**
 * Keeps the session registry fresh and enforces remote revocation: if the current
 * session has been revoked (e.g. from /admin/sessions or a password reset), the
 * user is logged out immediately. Runs after Authenticate in the admin group.
 */
final class TrackSession implements MiddlewareInterface
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Session $session,
        private readonly UserSessionRepository $sessions,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $this->auth->id();
        $sid = $this->session->id();

        if ($userId !== null && $sid !== '') {
            if ($this->sessions->isRevoked($sid)) {
                $this->auth->logout();
                if ($request->wantsJson()) {
                    return Response::json(['error' => true, 'status' => 401], 401);
                }
                return Response::redirect('/admin/login');
            }
            $this->sessions->touch($sid, $userId, $request->ip(), $request->userAgent());
        }

        return $next($request);
    }
}
