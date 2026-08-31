<?php

declare(strict_types=1);

/**
 * Front controller — the single public entry point.
 *
 * With the preferred layout the domain document root points here (/public).
 * With the fallback layout the root .htaccess rewrites traffic into /public.
 * Everything above /public is denied by .htaccess (SECURITY_PLAN §14).
 */

/** @var App\Core\App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$request = App\Core\Request::capture();

// Make the current request resolvable (audit/session services read ip + UA).
$app->container()->instance(App\Core\Request::class, $request);

$app->run($request)->send();
