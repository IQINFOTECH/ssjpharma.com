<?php
/** @var array $roles @var bool $canCreate @var bool $canEdit @var bool $canDelete */
$this->layout('admin.layout');
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-brand-900">Roles &amp; Permissions</h1>
  <p class="mt-1 text-sm text-slate-500">Roles are database-driven and configurable. Super Admin always has all permissions.</p>
</div>

<?php if ($canCreate): ?>
<form method="post" action="/admin/roles" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5">
  <?= csrf_field() ?>
  <div><label class="field-label text-xs">New role name</label><input name="name" class="input-admin" placeholder="e.g. Editor" required></div>
  <div class="flex-1 min-w-[220px]"><label class="field-label text-xs">Description</label><input name="description" class="input-admin"></div>
  <button class="btn btn-primary">Create role</button>
</form>
<?php endif; ?>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr><th class="px-5 py-3">Role</th><th class="px-5 py-3">Users</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach ($roles as $r): ?>
        <tr>
          <td class="px-5 py-3">
            <span class="font-medium text-brand-900"><?= e($r['name']) ?></span>
            <div class="font-mono text-xs text-slate-400"><?= e($r['key']) ?></div>
          </td>
          <td class="px-5 py-3 text-slate-600"><?= (int) $r['users'] ?></td>
          <td class="px-5 py-3"><span class="badge <?= (int) $r['is_system'] === 1 ? 'badge-slate' : 'badge-amber' ?>"><?= (int) $r['is_system'] === 1 ? 'System' : 'Custom' ?></span></td>
          <td class="px-5 py-3"><span class="badge <?= (int) $r['is_active'] === 1 ? 'badge-green' : 'badge-slate' ?>"><?= (int) $r['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
          <td class="px-5 py-3">
            <div class="flex items-center justify-end gap-3">
              <a href="/admin/roles/<?= (int) $r['id'] ?>/edit" class="font-medium text-brand-600 hover:text-brand-700"><?= $canEdit ? 'Edit' : 'View' ?></a>
              <?php if ($canDelete && (int) $r['is_system'] === 0): ?>
                <form method="post" action="/admin/roles/<?= (int) $r['id'] ?>/delete" class="js-confirm" data-confirm="Delete this role?">
                  <?= csrf_field() ?><?= method_field('DELETE') ?>
                  <button class="text-red-500 hover:text-red-700">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php $this->stop(); ?>
