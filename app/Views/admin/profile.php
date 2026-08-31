<?php
/** @var array $user @var array $roles @var array $errors @var string|null $pwError */
$this->layout('admin.layout');
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="error-text">' . e($errors[$k]) . '</p>' : '';
};
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-brand-900">My Profile</h1>
</div>

<div class="grid gap-6 lg:grid-cols-2">
  <form method="post" action="/admin/profile" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
    <?= csrf_field() ?>
    <div>
      <label class="field-label" for="name">Name</label>
      <input id="name" name="name" class="input-admin" value="<?= e($user['name'] ?? '') ?>" maxlength="150" required>
      <?= $err('name') ?>
    </div>
    <div>
      <label class="field-label" for="email">Email</label>
      <input id="email" type="email" name="email" class="input-admin" value="<?= e($user['email'] ?? '') ?>" maxlength="190" required>
      <?= $err('email') ?>
    </div>
    <div>
      <label class="field-label" for="username">Username</label>
      <input id="username" name="username" class="input-admin" value="<?= e($user['username'] ?? '') ?>" maxlength="60">
    </div>
    <div>
      <label class="field-label" for="phone">Phone</label>
      <input id="phone" name="phone" class="input-admin" value="<?= e($user['phone'] ?? '') ?>" maxlength="40">
    </div>
    <div>
      <span class="field-label">Roles</span>
      <div class="flex flex-wrap gap-1.5">
        <?php foreach ($roles as $r): ?><span class="badge badge-slate"><?= e($r['name']) ?></span><?php endforeach; ?>
        <?php if (empty($roles)): ?><span class="text-sm text-slate-400">No roles assigned.</span><?php endif; ?>
      </div>
      <p class="mt-1 text-xs text-slate-400">Roles can only be changed by an administrator.</p>
    </div>
    <button class="btn btn-primary">Save profile</button>
  </form>

  <form method="post" action="/admin/password" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
    <?= csrf_field() ?>
    <h2 class="text-lg font-semibold text-brand-900">Change password</h2>
    <?php if (!empty($pwError)): ?><div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($pwError) ?></div><?php endif; ?>
    <div>
      <label class="field-label" for="cur">Current password</label>
      <input id="cur" type="password" name="current_password" class="input-admin" required autocomplete="current-password">
    </div>
    <div>
      <label class="field-label" for="new">New password</label>
      <input id="new" type="password" name="new_password" class="input-admin" minlength="10" required autocomplete="new-password">
    </div>
    <div>
      <label class="field-label" for="conf">Confirm new password</label>
      <input id="conf" type="password" name="confirm_password" class="input-admin" minlength="10" required autocomplete="new-password">
    </div>
    <p class="text-xs text-slate-400">Changing your password signs out your other sessions.</p>
    <button class="btn btn-primary">Update password</button>
  </form>
</div>
<?php $this->stop(); ?>
