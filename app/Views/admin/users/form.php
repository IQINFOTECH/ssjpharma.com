<?php
/** Create/Edit user. @var array|null $user @var array $userRoles @var array $allRoles
 *  @var array $errors @var array $old @var bool $isSelf @var bool $canEdit */
$this->layout('admin.layout');
$isEdit = $user !== null;
$val = static fn (string $k, $d = '') => e((string) ($old[$k] ?? ($user[$k] ?? $d)));
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="error-text">' . e($errors[$k]) . '</p>' : '';
};
$isSelf = $isSelf ?? false;
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <a href="/admin/users" class="text-sm text-slate-500 hover:text-brand-600">← Users</a>
  <h1 class="mt-1 text-2xl font-semibold text-brand-900"><?= $isEdit ? e($user['name']) : 'New User' ?></h1>
</div>

<?php if (!empty($errors['_form'])): ?>
  <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
  <form method="post" action="<?= $isEdit ? '/admin/users/' . (int) $user['id'] : '/admin/users' ?>" class="lg:col-span-2 space-y-5 rounded-xl border border-slate-200 bg-white p-6">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label class="field-label" for="name">Name</label>
        <input id="name" name="name" class="input-admin" value="<?= $val('name') ?>" maxlength="150" required>
        <?= $err('name') ?>
      </div>
      <div>
        <label class="field-label" for="email">Email</label>
        <input id="email" type="email" name="email" class="input-admin" value="<?= $val('email') ?>" maxlength="190" required>
        <?= $err('email') ?>
      </div>
      <div>
        <label class="field-label" for="username">Username <span class="font-normal text-slate-400">(optional)</span></label>
        <input id="username" name="username" class="input-admin" value="<?= $val('username') ?>" maxlength="60">
        <?= $err('username') ?>
      </div>
      <div>
        <label class="field-label" for="phone">Phone <span class="font-normal text-slate-400">(optional)</span></label>
        <input id="phone" name="phone" class="input-admin" value="<?= $val('phone') ?>" maxlength="40">
        <?= $err('phone') ?>
      </div>
      <?php if (!$isEdit): ?>
      <div>
        <label class="field-label" for="password">Password</label>
        <input id="password" type="password" name="password" class="input-admin" minlength="10" required autocomplete="new-password">
        <?= $err('password') ?>
        <p class="mt-1 text-xs text-slate-400">Minimum 10 characters.</p>
      </div>
      <div class="flex items-end">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="must_change_password" value="1" class="rounded border-slate-300 text-brand-600" checked> Require password change at first login
        </label>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <span class="field-label">Roles</span>
      <?php if ($isSelf): ?>
        <p class="mb-2 text-xs text-amber-600">You cannot change your own roles.</p>
      <?php endif; ?>
      <div class="grid gap-2 sm:grid-cols-2">
        <?php foreach ($allRoles as $r): $checked = in_array((int) $r['id'], array_map('intval', $userRoles), true); ?>
          <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm <?= $isSelf ? 'opacity-60' : '' ?>">
            <input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>" class="rounded border-slate-300 text-brand-600" <?= $checked ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
            <span><?= e($r['name']) ?><?php if ((int) $r['is_active'] === 0): ?> <span class="text-xs text-slate-400">(inactive)</span><?php endif; ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!$isEdit || $canEdit): ?>
    <div class="flex gap-3 pt-2">
      <button class="btn btn-primary"><?= $isEdit ? 'Save user' : 'Create user' ?></button>
      <a href="/admin/users" class="btn btn-ghost">Cancel</a>
    </div>
    <?php endif; ?>
  </form>

  <?php if ($isEdit && $canEdit): ?>
  <div class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
      <h3 class="text-sm font-semibold text-brand-900">Reset password</h3>
      <p class="mt-1 text-xs text-slate-500">Sets a new password; the user must change it at next login. Active sessions are revoked.</p>
      <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/reset-password" class="js-confirm mt-3 space-y-2" data-confirm="Reset this user's password?">
        <?= csrf_field() ?>
        <input type="password" name="new_password" class="input-admin" minlength="10" placeholder="New password" required autocomplete="new-password">
        <button class="btn btn-ghost w-full">Reset password</button>
      </form>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
      <p><span class="text-slate-400">Last login:</span> <?= e($user['last_login_at'] ? substr((string) $user['last_login_at'], 0, 16) : '—') ?></p>
      <p class="mt-1"><span class="text-slate-400">Created:</span> <?= e(substr((string) $user['created_at'], 0, 16)) ?></p>
      <p class="mt-1"><a href="/admin/users/<?= (int) $user['id'] ?>/activity" class="text-brand-600">View activity →</a></p>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php $this->stop(); ?>
