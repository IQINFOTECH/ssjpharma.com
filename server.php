<?php

declare(strict_types=1);

/**
 * Dev-only router for PHP's built-in web server (`php -S`).
 *
 * Production uses Apache + public/.htaccess (document root = /public); this file
 * is never web-served there because it sits above the document root. It exists
 * only so `php -S 127.0.0.1:8000 -t public server.php` behaves like the app:
 * real files under public/ (CSS, JS, images, uploads) are served directly, and
 * everything else — including extensionless routes, /sitemap.xml and /robots.txt
 * — is dispatched through the front controller.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$public = __DIR__ . '/public';
$target = $public . $path;

// Serve an existing static asset as-is (but never a raw PHP file).
if ($path !== '/' && is_file($target) && !preg_match('#\.php$#i', $path)) {
    return false;
}

require $public . '/index.php';
