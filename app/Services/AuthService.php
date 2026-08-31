<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;

/**
 * Admin authentication (Phase 2 complete): credential verification + rehash,
 * login throttling, audit logging, and session-registry tracking. Session id is
 * regenerated on login (fixation defence) via Auth::login().
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Auth $auth,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly ThrottleService $throttle,
        private readonly AuditService $audit,
        private readonly UserSessionRepository $sessions,
        private readonly Session $session,
    ) {
    }

    /**
     * @return array{ok:bool,throttled?:bool,error?:string}
     */
    public function attempt(string $email, string $password, Request $request): array
    {
        $email = strtolower(trim($email));
        $ip = $request->ip();

        if ($this->throttle->tooManyAttempts($email, $ip)) {
            $this->audit->log('LOGIN_FAILED', ['meta' => ['email' => $this->mask($email), 'reason' => 'throttled'], 'ip' => $ip]);
            return ['ok' => false, 'throttled' => true, 'error' => 'Too many attempts. Please try again in about ' . $this->throttle->retryAfterMinutes() . ' minutes.'];
        }

        $user = $this->users->findActiveByEmail($email);
        $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinv';

        if (!password_verify($password, $hash) || $user === null) {
            $this->throttle->recordFailure($email, $ip);
            $this->audit->log('LOGIN_FAILED', ['meta' => ['email' => $this->mask($email)], 'ip' => $ip]);
            $this->logger->warning('auth.login.failed', ['email' => $this->mask($email)]);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        $userId = (int) $user['id'];

        // Temporary account lockout (explicit), if set.
        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            $this->audit->log('LOGIN_FAILED', ['entity_type' => 'user', 'entity_id' => $userId, 'meta' => ['reason' => 'locked'], 'ip' => $ip]);
            return ['ok' => false, 'error' => 'This account is temporarily locked. Please try again later.'];
        }

        // Rehash on login if the algorithm/cost changed.
        $algo = $this->config->get('security.password.algo', PASSWORD_DEFAULT);
        if (password_needs_rehash($hash, $algo)) {
            $this->users->updatePasswordHash($userId, password_hash($password, $algo));
        }

        $this->users->touchLogin($userId, $ip);
        $this->throttle->recordSuccess($email, $ip);

        $this->auth->login([
            'id'                   => $userId,
            'name'                 => (string) $user['name'],
            'email'                => (string) $user['email'],
            'roles'                => $this->users->roleKeys($userId),
            'permissions'          => $this->users->permissionKeys($userId),
            'must_change_password' => (bool) $user['must_change_password'],
        ]);

        // Register the (regenerated) session id for the sessions registry.
        $sid = $this->session->id();
        if ($sid !== '') {
            $this->sessions->touch($sid, $userId, $ip, $request->userAgent());
        }

        $this->audit->log('LOGIN_SUCCESS', ['entity_type' => 'user', 'entity_id' => $userId, 'ip' => $ip]);
        $this->logger->info('auth.login.success', ['user_id' => $userId]);
        return ['ok' => true];
    }

    public function logout(): void
    {
        $userId = $this->auth->id();
        $sid = $this->session->id();
        if ($sid !== '') {
            $this->sessions->revokeBySessionId($sid);
        }
        $this->audit->log('LOGOUT', ['entity_type' => 'user', 'entity_id' => $userId]);
        $this->auth->logout();
    }

    private function mask(string $email): string
    {
        return mb_substr($email, 0, 2) . '***';
    }
}
