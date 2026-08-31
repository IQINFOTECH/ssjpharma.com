<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use App\Repositories\UserRepository;

/**
 * Admin authentication (login/logout + forced password change).
 * Full RBAC user-management, password reset and throttling are Phase 2.
 */
final class AuthController extends Controller
{
    private function session(): Session { return $this->container->get(Session::class); }

    public function showLogin(Request $request): Response
    {
        if ($this->container->get(\App\Auth\Auth::class)->check()) {
            return Response::redirect('/admin');
        }
        $flash = $this->session()->getFlash('login_error');
        return $this->view('admin.auth.login', [
            'error'    => $flash,
            'oldEmail' => (string) $this->session()->getFlash('login_email', ''),
            'siteName' => (string) config('app.name', 'SSJ Pharmaceuticals'),
        ]);
    }

    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        // Per-IP rate limit (defends against spraying many emails from one IP),
        // in addition to the per-(email+ip) throttle inside AuthService.
        $limiter = new \App\Support\RateLimiter();
        if (!$limiter->attempt('login:' . $request->ip(), 20, 600)) {
            $this->session()->flash('login_error', 'Too many attempts. Please try again shortly.');
            $this->session()->flash('login_email', $email);
            return Response::redirect('/admin/login');
        }

        /** @var AuthService $authService */
        $authService = $this->container->get(AuthService::class);

        if ($email === '' || $password === '') {
            $this->session()->flash('login_error', 'Invalid email or password.');
            $this->session()->flash('login_email', $email);
            return Response::redirect('/admin/login');
        }

        $result = $authService->attempt($email, $password, $request);
        if (!($result['ok'] ?? false)) {
            $this->session()->flash('login_error', $result['error'] ?? 'Invalid email or password.');
            $this->session()->flash('login_email', $email);
            return Response::redirect('/admin/login');
        }

        $user = $this->container->get(\App\Auth\Auth::class)->user() ?? [];
        if (!empty($user['must_change_password'])) {
            return Response::redirect('/admin/password');
        }

        return Response::redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        $this->container->get(AuthService::class)->logout();
        return Response::redirect('/admin/login');
    }

    public function showPasswordChange(Request $request): Response
    {
        return $this->view('admin.auth.password', [
            'error'    => $this->session()->getFlash('pw_error'),
            'siteName' => (string) config('app.name', 'SSJ Pharmaceuticals'),
        ]);
    }

    public function updatePassword(Request $request): Response
    {
        $auth = $this->container->get(\App\Auth\Auth::class);
        $userId = $auth->id();
        if ($userId === null) {
            return Response::redirect('/admin/login');
        }

        $current = (string) $request->input('current_password', '');
        $new     = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');
        $minLen  = (int) config('security.password.min_length', 10);

        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        $row = $users->find($userId);

        if ($row === null || !password_verify($current, (string) $row['password_hash'])) {
            $this->session()->flash('pw_error', 'Your current password is incorrect.');
            return Response::redirect('/admin/password');
        }
        if ($new !== $confirm) {
            $this->session()->flash('pw_error', 'New password and confirmation do not match.');
            return Response::redirect('/admin/password');
        }
        $policyError = \App\Support\PasswordPolicy::validate($new, $minLen, (string) $row['email']);
        if ($policyError !== null) {
            $this->session()->flash('pw_error', $policyError);
            return Response::redirect('/admin/password');
        }

        $users->updatePasswordHash($userId, password_hash($new, config('security.password.algo', PASSWORD_DEFAULT)));
        $users->setMustChangePassword($userId, false);

        // Refresh the session identity flag (regenerates the session id).
        $user = $auth->user() ?? [];
        $user['must_change_password'] = false;
        $auth->login($user);

        // Re-register the new session id and revoke any other active sessions.
        $sessions = $this->container->get(\App\Repositories\UserSessionRepository::class);
        $sid = $this->container->get(\App\Core\Session::class)->id();
        if ($sid !== '') {
            $sessions->touch($sid, $userId, $request->ip(), $request->userAgent());
            $sessions->revokeAllForUserExcept($userId, $sid);
        }
        $this->container->get(\App\Services\AuditService::class)->log('PASSWORD_CHANGED', ['entity_type' => 'user', 'entity_id' => $userId]);

        $this->session()->flash('admin_flash', ['type' => 'success', 'message' => 'Password updated.']);
        return Response::redirect('/admin');
    }
}
