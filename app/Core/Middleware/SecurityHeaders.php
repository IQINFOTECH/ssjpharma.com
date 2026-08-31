<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Services\SettingsService;
use Closure;

/**
 * Applies security headers + CSP to every response. See SECURITY_PLAN §3.
 * HSTS is only emitted over HTTPS. The CSP is widened to allow Google Analytics
 * hosts ONLY when a GA id is actually configured (otherwise it stays 'self').
 */
final class SecurityHeaders implements MiddlewareInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ?SettingsService $settings = null,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ((array) $this->config->get('security.headers', []) as $name => $value) {
            $response->header($name, (string) $value);
        }

        $csp = (array) $this->config->get('security.csp', []);
        $csp = $this->applyAnalytics($csp);
        if ($csp !== []) {
            $response->header('Content-Security-Policy', implode('; ', $csp));
        }

        if ($request->isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }

    /**
     * When GA is configured, allow its hosts on the relevant directives only.
     * @param array<int,string> $csp
     * @return array<int,string>
     */
    private function applyAnalytics(array $csp): array
    {
        if ($this->settings === null) {
            return $csp;
        }

        try {
            $ga = $this->settings->gaId();
        } catch (\Throwable) {
            return $csp; // settings/DB unavailable — keep the strict base policy
        }

        $turnstile = false;
        try {
            $turnstile = $this->settings->bool('turnstile_enabled');
        } catch (\Throwable) {
            $turnstile = false;
        }

        if ($ga === '' && !$turnstile) {
            return $csp;
        }

        $gtm = 'https://www.googletagmanager.com';
        $gan = 'https://www.google-analytics.com';
        $cf  = 'https://challenges.cloudflare.com';

        foreach ($csp as $i => $directive) {
            if (str_starts_with($directive, 'script-src')) {
                if ($ga !== '')     { $csp[$i] .= ' ' . $gtm; }
                if ($turnstile)     { $csp[$i] .= ' ' . $cf; }
            } elseif (str_starts_with($directive, 'connect-src')) {
                if ($ga !== '')     { $csp[$i] .= ' ' . $gan . ' ' . $gtm; }
            } elseif (str_starts_with($directive, 'img-src')) {
                if ($ga !== '')     { $csp[$i] .= ' ' . $gan; }
            } elseif (str_starts_with($directive, 'frame-ancestors')) {
                // add a frame-src for the Turnstile iframe
                if ($turnstile && !$this->hasDirective($csp, 'frame-src')) {
                    $csp[] = 'frame-src ' . $cf;
                }
            }
        }
        return $csp;
    }

    /** @param array<int,string> $csp */
    private function hasDirective(array $csp, string $name): bool
    {
        foreach ($csp as $d) {
            if (str_starts_with($d, $name)) {
                return true;
            }
        }
        return false;
    }
}
