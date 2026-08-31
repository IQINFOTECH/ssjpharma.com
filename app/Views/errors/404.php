<?php
/**
 * Standalone 404 — intentionally self-contained (no menus/DB) so it renders even
 * when the database is unavailable. Uses the compiled site CSS for brand styling.
 * @var int $status
 */
?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,follow">
  <title>Page not found</title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-full bg-white">
  <main class="mx-auto flex min-h-screen max-w-content flex-col items-center justify-center px-6 text-center">
    <span class="eyebrow mb-3">Error 404</span>
    <h1 class="text-4xl font-semibold text-brand-900">Page not found</h1>
    <p class="mt-4 max-w-md text-slate-600">The page you are looking for does not exist or may have moved.</p>
    <div class="mt-8 flex gap-3">
      <a href="/" class="btn btn-primary">Return home</a>
      <a href="/contact-us" class="btn btn-ghost">Contact us</a>
    </div>
  </main>
</body>
</html>
