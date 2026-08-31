<?php

declare(strict_types=1);

/**
 * Core application configuration.
 * Values resolve from .env with safe defaults so the app never hard-fails on a
 * missing key. Secrets must come from .env, never from this file.
 */
return [
    'name'     => env('APP_NAME', 'SSJ Pharmaceuticals'),
    'env'      => env('APP_ENV', 'production'),
    // Debug is force-disabled in production (defence in depth) so a mis-set .env
    // can never leak stack traces / SQL / paths publicly. Matches bootstrap/app.php.
    'debug'    => env('APP_ENV', 'production') !== 'production' && (bool) env('APP_DEBUG', false),
    'url'      => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),
    'key'      => env('APP_KEY', ''),

    // Default language. English only at launch (ADR-001). Kept as config so a
    // future locale switch is additive, not a rewrite.
    'locale'   => 'en',
];
