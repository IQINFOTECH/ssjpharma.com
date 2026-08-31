<?php
/**
 * Base layout. Minimal Phase-0 shell — no site chrome or branding yet
 * (built in Phase 1). All dynamic values are escaped via e().
 *
 * @var App\Core\View $this
 */
$appName = $appName ?? 'SSJ Pharmaceuticals';
$title   = $title ?? $appName;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?></title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
               margin: 0; min-height: 100vh; display: grid; place-items: center;
               background: #0b1f3a; color: #eef2f7; }
        .card { max-width: 34rem; padding: 2.5rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 .5rem; letter-spacing: .01em; }
        p  { opacity: .8; line-height: 1.6; margin: .35rem 0; }
        .tag { display: inline-block; margin-top: 1rem; padding: .25rem .75rem;
               border: 1px solid rgba(255,255,255,.25); border-radius: 999px;
               font-size: .75rem; opacity: .7; }
        code { background: rgba(255,255,255,.1); padding: .1rem .35rem; border-radius: .25rem; }
    </style>
</head>
<body>
    <main class="card">
        <?= $this->section('content') ?>
    </main>
</body>
</html>
