<?php

declare(strict_types=1);

/**
 * Mail configuration. Provider-agnostic SMTP (ADR-001).
 * Real credentials are supplied via .env at deployment — never hardcoded.
 * The mail transport (PHPMailer) is wired in Phase 5; these values are read then.
 */
return [
    'mailer' => env('MAIL_MAILER', 'smtp'),

    // Delivery mode (Phase 5): smtp | log | disabled. Blank = auto (smtp when
    // configured, otherwise log). Prevents accidental production sends in dev.
    'delivery_mode' => env('MAIL_DELIVERY_MODE', ''),

    'smtp' => [
        'host'       => env('SMTP_HOST', ''),
        'port'       => (int) env('SMTP_PORT', 587),
        'username'   => env('SMTP_USERNAME', ''),
        'password'   => env('SMTP_PASSWORD', ''),
        'encryption' => env('SMTP_ENCRYPTION', 'tls'), // tls | ssl
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', ''),
        'name'    => env('MAIL_FROM_NAME', 'SSJ Pharmaceuticals'),
    ],

    // Internal recipient for lead / contact notifications.
    'sales_inbox' => env('MAIL_SALES_INBOX', ''),
];
