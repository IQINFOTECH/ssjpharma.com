<?php
/** Forgot-password (standalone). @var bool $sent @var string|null $error */
?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Reset password · <?= e($siteName ?? 'SSJ') ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-full bg-slate-50">
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="card">
        <h1 class="text-xl font-semibold text-brand-900">Reset your password</h1>
        <?php if ($sent): ?>
          <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
            If an account exists for that email, a reset link has been sent. Please check your inbox.
          </div>
          <p class="mt-4 text-sm"><a href="/admin/login" class="text-brand-600">Back to sign in</a></p>
        <?php else: ?>
          <p class="mt-1 text-sm text-slate-500">Enter your email and we'll send a reset link.</p>
          <?php if (!empty($error)): ?><div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
          <form method="post" action="/admin/forgot-password" class="mt-5 space-y-4">
            <?= csrf_field() ?>
            <div>
              <label class="field-label" for="email">Email</label>
              <input id="email" type="email" name="email" class="field" required autofocus autocomplete="username">
            </div>
            <button class="btn btn-primary w-full">Send reset link</button>
          </form>
          <p class="mt-4 text-sm"><a href="/admin/login" class="text-brand-600">Back to sign in</a></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
