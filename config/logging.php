<?php

declare(strict_types=1);

/**
 * Logging configuration. First-party file logger (ADR-001) — no Monolog dependency.
 * Logs live OUTSIDE the webroot and are denied by .htaccess as a second layer.
 */
return [
    'path'       => dirname(__DIR__) . '/storage/logs',
    'file'       => 'app-' . date('Y-m-d') . '.log',

    // Minimum level to record: debug|info|notice|warning|error|critical|alert|emergency
    'level'      => env('APP_DEBUG', false) ? 'debug' : 'info',

    // Days to retain daily log files (housekeeping via cron).
    'max_days'   => 30,
];
