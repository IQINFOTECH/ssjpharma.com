<?php
/** @var array $role @var array $grouped @var array $granted @var bool $isSuperAdmin @var bool $canEdit */
$this->layout('admin.layout');
$grantedSet = array_flip(array_map('intval', $granted));
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <a href="/admin/roles" class="text-sm text-slate-500 hover:text-brand-600">← Roles</a>
  <h1 class="mt-1 text-2xl font-semibold text-brand-900"><?= e($role['name']) ?></h1>
  <p class="text-sm text-slate-400 font-mono"><?= e($role['key']) ?></p>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <!-- Role detail -->
  <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
    <?= csrf_field() ?><?= method_field('PUT') ?>
    <div>
      <label class="field-label" for="name">Name</label>
      <input id="name" name="name" class="input-admin" value="<?= e($role['name']) ?>" maxlength="120" <?= $canEdit ? '' : 'disabled' ?>>
    </div>
    <div>
      <label class="field-label" for="description">Description</label>
      <textarea id="description" name="description" rows="2" class="input-admin" <?= $canEdit ? '' : 'disabled' ?>><?= e($role['description'] ?? '') ?></textarea>
    </div>
    <?php if (!$isSuperAdmin): ?>
    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
      <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-brand-600" <?= (int) $role['is_active'] === 1 ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>> Active
    </label>
    <?php endif; ?>
    <?php if ($canEdit): ?><button class="btn btn-primary">Save details</button><?php endif; ?>
  </form>

  <!-- Permission matrix -->
  <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="mb-1 text-lg font-semibold text-brand-900">Permissions</h2>
    <?php if ($isSuperAdmin): ?>
      <p class="mb-4 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-sm text-brand-700">Super Admin always has <strong>all</strong> permissions. This cannot be changed.</p>
    <?php else: ?>
      <p class="mb-4 text-sm text-slate-500">Select the permissions granted to this role.</p>
    <?php endif; ?>

    <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>/permissions">
      <?= csrf_field() ?>
      <div class="grid gap-5 sm:grid-cols-2">
        <?php foreach ($grouped as $group => $perms): ?>
          <div class="rounded-lg border border-slate-100 p-4">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500"><?= e($group) ?></h3>
            <div class="space-y-1.5">
              <?php foreach ($perms as $p): $checked = $isSuperAdmin || isset($grantedSet[(int) $p['id']]); ?>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                  <input type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>" class="rounded border-slate-300 text-brand-600"
                         <?= $checked ? 'checked' : '' ?> <?= ($isSuperAdmin || !$canEdit) ? 'disabled' : '' ?>>
                  <span title="<?= e($p['key']) ?>"><?= e($p['name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($canEdit && !$isSuperAdmin): ?>
        <div class="mt-5"><button class="btn btn-primary">Save permissions</button></div>
      <?php endif; ?>
    </form>
  </div>
</div>
<?php $this->stop(); ?>
