<?php
/** Admin login (standalone). @var string|null $error @var string $oldEmail */
?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Sign in · <?= e($siteName ?? 'SSJ Pharmaceuticals') ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-full bg-slate-50">
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex items-center justify-center gap-2.5">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-900 text-lg font-bold text-white">S</span>
        <span class="font-display text-lg font-semibold text-brand-900">SSJ Admin</span>
      </div>
      <div class="card">
        <h1 class="text-xl font-semibold text-brand-900">Sign in</h1>
        <p class="mt-1 text-sm text-slate-500">Access the SSJ Pharmaceuticals CMS.</p>

        <?php if (!empty($error)): ?>
          <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/admin/login" class="mt-5 space-y-4">
          <?= csrf_field() ?>
          <div>
            <label class="field-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="field" value="<?= e($oldEmail ?? '') ?>" required autofocus autocomplete="username">
          </div>
          <div>
            <label class="field-label" for="password">Password</label>
            <input id="password" type="password" name="password" class="field" required autocomplete="current-password">
          </div>
          <button type="submit" class="btn btn-primary w-full">Sign in</button>
        </form>
        <p class="mt-4 text-center text-sm"><a href="/admin/forgot-password" class="text-brand-600 hover:text-brand-700">Forgot your password?</a></p>
      </div>
      <p class="mt-6 text-center text-xs text-slate-400">Authorised personnel only.</p>
    </div>
  </div>
</body>
</html>
