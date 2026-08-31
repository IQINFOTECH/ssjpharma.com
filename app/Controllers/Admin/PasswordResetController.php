<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PasswordResetService;
use App\Support\RateLimiter;

/**
 * Public (unauthenticated) password-reset flow. Rate-limited. Never reveals
 * whether an account exists. Raw tokens live only in the emailed link.
 */
final class PasswordResetController extends Controller
{
    private function session(): Session { return $this->container->get(Session::class); }

    public function showForgot(Request $request): Response
    {
        return $this->view('admin.auth.forgot', [
            'sent'     => (bool) $this->session()->getFlash('reset_sent'),
            'error'    => $this->session()->getFlash('reset_error'),
            'siteName' => (string) config('app.name', 'SSJ Pharmaceuticals'),
        ]);
    }

    public function forgot(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $ip = $request->ip();

        // Rate limit reset requests (filesystem window + DB backstop) — anti-abuse.
        $limiter = new RateLimiter();
        $repo = $this->container->get(\App\Repositories\PasswordResetRepository::class);
        $withinFsLimit = $limiter->attempt('pwreset:' . $ip, 5, 900);   // 5 / 15 min
        $withinDbLimit = $repo->countRecentByIp($ip, 900) < 10;

        if ($withinFsLimit && $withinDbLimit && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            /** @var PasswordResetService $service */
            $service = $this->container->get(PasswordResetService::class);
            $service->request($email, $ip);
        }

        // ALWAYS the same response (no enumeration, no rate-limit oracle).
        $this->session()->flash('reset_sent', true);
        return Response::redirect('/admin/forgot-password');
    }

    public function showReset(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        return $this->view('admin.auth.reset', [
            'token'    => $token,
            'error'    => $this->session()->getFlash('reset_error'),
            'siteName' => (string) config('app.name', 'SSJ Pharmaceuticals'),
        ]);
    }

    public function reset(Request $request): Response
    {
        $token   = (string) $request->input('token', '');
        $new     = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');

        // Light per-IP rate limit on completion attempts (token brute-force guard).
        $limiter = new RateLimiter();
        if (!$limiter->attempt('pwreset-complete:' . $request->ip(), 10, 900)) {
            $this->session()->flash('reset_error', 'Too many attempts. Please try again later.');
            return Response::redirect('/admin/reset-password?token=' . urlencode($token));
        }

        /** @var PasswordResetService $service */
        $service = $this->container->get(PasswordResetService::class);
        $error = $service->complete($token, $new, $confirm);

        if ($error !== null) {
            $this->session()->flash('reset_error', $error);
            return Response::redirect('/admin/reset-password?token=' . urlencode($token));
        }

        $this->session()->flash('login_error', 'Your password has been reset. Please sign in.');
        return Response::redirect('/admin/login');
    }
}
