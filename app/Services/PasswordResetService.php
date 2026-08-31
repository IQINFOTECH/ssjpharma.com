<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Support\PasswordPolicy;

/**
 * Secure password reset (Phase 2 §9). Raw token appears ONLY in the emailed link;
 * the DB stores its SHA-256 hash. Tokens are cryptographically random, expiring,
 * single-use. No user enumeration: request() behaves identically whether or not
 * the email exists. On completion, all of the user's sessions are revoked.
 */
final class PasswordResetService
{
    private const TTL_MINUTES = 60;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetRepository $resets,
        private readonly UserSessionRepository $sessions,
        private readonly MailService $mail,
        private readonly AuditService $audit,
        private readonly SettingsService $settings,
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Begin a reset. ALWAYS returns void with no signal about account existence.
     */
    public function request(string $email, string $ip): void
    {
        $user = $this->users->findByEmailAny($email);

        // Audit the request regardless (actor unknown → anonymous), without leaking existence.
        $this->audit->log('PASSWORD_RESET_REQUESTED', [
            'entity_type' => 'user',
            'entity_id'   => $user['id'] ?? null,
            'ip'          => $ip,
            'meta'        => ['email_domain' => $this->emailDomain($email)],
        ]);

        if ($user === null || (int) $user['is_active'] !== 1) {
            return; // silent — no enumeration
        }

        $userId = (int) $user['id'];
        $this->resets->invalidateForUser($userId);

        $rawToken = bin2hex(random_bytes(32));           // 64 hex chars, in the link only
        $tokenHash = hash('sha256', $rawToken);          // stored
        $expires = date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60);
        $this->resets->create($userId, $tokenHash, $expires, $ip);

        $link = $this->settings->websiteUrl() . '/admin/reset-password?token=' . $rawToken;
        $sent = $this->sendEmail((string) $user['email'], (string) $user['name'], $link);

        // Dev convenience ONLY: if mail is not configured on a local/dev box, log the
        // link so developers can proceed. NEVER in production, and never the bare token.
        if (!$sent && $this->isDev()) {
            $this->logger->info('password_reset.dev_link', ['link' => $link]);
        }
    }

    /**
     * Complete a reset with the raw token. Returns null on success or an error string.
     */
    public function complete(string $rawToken, string $newPassword, string $confirm): ?string
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || !ctype_xdigit($rawToken)) {
            return 'This reset link is invalid or has expired.';
        }
        if ($newPassword !== $confirm) {
            return 'The passwords do not match.';
        }

        $row = $this->resets->findValidByHash(hash('sha256', $rawToken));
        if ($row === null) {
            return 'This reset link is invalid or has expired.';
        }

        $userId = (int) $row['user_id'];
        $user = $this->users->findActive($userId);
        if ($user === null) {
            return 'This reset link is invalid or has expired.';
        }

        $policyError = PasswordPolicy::validate($newPassword, (int) $this->config->get('security.password.min_length', 10), (string) $user['email']);
        if ($policyError !== null) {
            return $policyError;
        }

        $this->users->updatePasswordHash($userId, password_hash($newPassword, $this->config->get('security.password.algo', PASSWORD_DEFAULT)));
        $this->users->setMustChangePassword($userId, false);
        $this->resets->markUsed((int) $row['id']);
        $this->resets->invalidateForUser($userId);

        // Invalidate all existing sessions for this user (force re-login everywhere).
        $this->sessions->revokeAllForUserExcept($userId, '');

        $this->audit->log('PASSWORD_RESET_COMPLETED', ['entity_type' => 'user', 'entity_id' => $userId]);

        return null;
    }

    private function sendEmail(string $email, string $name, string $link): bool
    {
        $subject = 'Reset your ' . $this->settings->companyName() . ' admin password';
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $html = '<p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
              . '<p>A password reset was requested for your admin account. This link expires in '
              . self::TTL_MINUTES . ' minutes and can be used once:</p>'
              . '<p><a href="' . $safeLink . '">' . $safeLink . '</a></p>'
              . '<p>If you did not request this, you can safely ignore this email.</p>';
        return $this->mail->send($email, $subject, $html);
    }

    private function isDev(): bool
    {
        return (string) $this->config->get('app.env', 'production') === 'local'
            && (bool) $this->config->get('app.debug', false);
    }

    private function emailDomain(string $email): string
    {
        $at = strrpos($email, '@');
        return $at !== false ? substr($email, $at + 1) : '';
    }
}
