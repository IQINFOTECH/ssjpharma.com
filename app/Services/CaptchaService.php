<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Cloudflare Turnstile verification (CAPTCHA integration architecture).
 *
 * Configurable via CMS settings (turnstile_enabled, turnstile_site_key) with the
 * SECRET supplied only through .env (TURNSTILE_SECRET) — never exposed. When
 * disabled it is a no-op, so development and unconfigured environments still work.
 */
final class CaptchaService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly Logger $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->bool('turnstile_enabled') && $this->settings->get('turnstile_site_key') !== '';
    }

    public function siteKey(): string
    {
        return $this->settings->get('turnstile_site_key');
    }

    /**
     * Verify the client token. Returns true when the check passes OR when the
     * feature is disabled/misconfigured (fail-open so genuine leads are never
     * lost to configuration gaps — the condition is logged for the admin).
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $secret = (string) env('TURNSTILE_SECRET', '');
        if ($secret === '') {
            $this->logger->warning('captcha.misconfigured.secret_missing');
            return true;
        }
        if (!function_exists('curl_init')) {
            $this->logger->warning('captcha.curl_unavailable');
            return true;
        }
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $ch = curl_init(self::VERIFY_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_POSTFIELDS     => http_build_query(array_filter([
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ])),
            ]);
            $body = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                $this->logger->warning('captcha.request_failed', ['error' => $err]);
                return true; // network hiccup — don't lose the lead
            }

            $data = json_decode((string) $body, true);
            return is_array($data) && (($data['success'] ?? false) === true);
        } catch (\Throwable $e) {
            $this->logger->warning('captcha.exception', ['error' => $e->getMessage()]);
            return true;
        }
    }
}
