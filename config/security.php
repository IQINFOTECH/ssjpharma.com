<?php

declare(strict_types=1);

/**
 * Security & session configuration.
 * See docs/SECURITY_PLAN.md for the rationale behind each control.
 */
return [
    'session' => [
        'name'       => env('SESSION_NAME', 'ssjp_session'),
        'lifetime'   => (int) env('SESSION_LIFETIME', 120), // minutes (idle timeout)
        // Secure cookie flag. FORCED true in production regardless of SESSION_SECURE
        // so a mis-set dev .env can never send the admin cookie over plaintext.
        'secure'     => env('APP_ENV', 'production') === 'production'
                            ? true
                            : (bool) env('SESSION_SECURE', true),
        'http_only'  => true,
        'same_site'  => 'Lax', // Lax is applied site-wide (adequate for the admin flow)
        'save_path'  => dirname(__DIR__) . '/storage/sessions',
    ],

    'csrf' => [
        'token_name'  => '_token',
        'header_name' => 'X-CSRF-Token',
        'field_name'  => '_token',
    ],

    'password' => [
        // PASSWORD_DEFAULT tracks PHP's strongest algorithm; rehash-on-login handles upgrades.
        'algo'        => PASSWORD_DEFAULT,
        'min_length'  => 10,
    ],

    // Login throttling (enforced from Phase 2).
    'throttle' => [
        'max_attempts'   => 5,
        'decay_minutes'  => 15,
    ],

    // Security headers applied by App\Core\Middleware\SecurityHeaders.
    'headers' => [
        'X-Frame-Options'        => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'geolocation=(), camera=(), microphone=(), payment=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ],

    // Content-Security-Policy. Self-hosted assets only (no CDNs). Analytics/WhatsApp
    // hosts are appended dynamically only when those features are enabled.
    'csp' => [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
    ],
];
