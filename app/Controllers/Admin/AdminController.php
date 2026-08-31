<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Auth\Rbac;
use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Response;
use App\Core\Session;

/**
 * Base for admin controllers. Provides RBAC helpers (permissions are re-checked
 * here even though the Authorize middleware also gates routes — defence in depth,
 * SECURITY_PLAN §7) and admin-layout rendering.
 */
abstract class AdminController extends Controller
{
    protected function auth(): Auth        { return $this->container->get(Auth::class); }
    protected function rbac(): Rbac        { return $this->container->get(Rbac::class); }
    protected function session(): Session  { return $this->container->get(Session::class); }

    protected function currentUserId(): ?int
    {
        return $this->auth()->id();
    }

    /** Re-assert a permission inside the controller/service layer. */
    protected function requirePermission(string $permission): void
    {
        if ($this->rbac()->cannot($permission)) {
            throw new HttpException(403, 'You do not have permission to perform this action.');
        }
    }

    protected function flash(string $type, string $message): void
    {
        $this->session()->flash('admin_flash', ['type' => $type, 'message' => $message]);
    }

    /** Convenience audit logger (actor/ip/UA are filled from the request context). */
    protected function audit(string $event, array $opts = []): void
    {
        $this->container->get(\App\Services\AuditService::class)->log($event, $opts);
    }

    protected function can(string $permission): bool
    {
        return $this->rbac()->can($permission);
    }

    /**
     * Render an admin view within the admin layout.
     * @param array<string,mixed> $data
     */
    protected function adminView(string $template, array $data = [], string $active = '', int $status = 200): Response
    {
        $user = $this->auth()->user() ?? [];
        $data = array_merge([
            'active'       => $active,
            'currentUser'  => $user,
            'flash'        => $this->session()->getFlash('admin_flash'),
            'siteName'     => (string) config('app.name', 'SSJ Pharmaceuticals'),
        ], $data);

        // Admin pages carry user/lead data — never cache them (browser, bfcache, proxy).
        return $this->view($template, $data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    /** Verify the request's CSRF-protected write already ran; helper for redirects. */
    protected function back(string $to): Response
    {
        return Response::redirect($to);
    }
}
