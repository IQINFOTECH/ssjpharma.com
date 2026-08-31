<?php
/** Forced / voluntary password change (standalone). @var string|null $error */
?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Change password · <?= e($siteName ?? 'SSJ') ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-full bg-slate-50">
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="card">
        <h1 class="text-xl font-semibold text-brand-900">Set a new password</h1>
        <p class="mt-1 text-sm text-slate-500">Please choose a new password to continue.</p>
        <?php if (!empty($error)): ?>
          <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/admin/password" class="mt-5 space-y-4">
          <?= csrf_field() ?>
          <div>
            <label class="field-label" for="cur">Current password</label>
            <input id="cur" type="password" name="current_password" class="field" required autocomplete="current-password">
          </div>
          <div>
            <label class="field-label" for="new">New password</label>
            <input id="new" type="password" name="new_password" class="field" required minlength="10" autocomplete="new-password">
          </div>
          <div>
            <label class="field-label" for="conf">Confirm new password</label>
            <input id="conf" type="password" name="confirm_password" class="field" required minlength="10" autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-primary w-full">Update password</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
