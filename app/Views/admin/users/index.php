<?php
/** @var array $rows @var int $total @var string $search @var string $roleKey @var string $status
 *  @var int $page @var int $totalPages @var array $allRoles
 *  @var bool $canEdit @var bool $canDelete @var bool $canActivate */
$this->layout('admin.layout');
$curId = (int) ($currentUser['id'] ?? 0);
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-semibold text-brand-900">Users</h1>
    <p class="mt-1 text-sm text-slate-500"><?= e($total) ?> user<?= $total === 1 ? '' : 's' ?></p>
  </div>
  <?php if (can('users.create')): ?><a href="/admin/users/create" class="btn btn-primary">New User</a><?php endif; ?>
</div>

<form method="get" action="/admin/users" class="mb-4 flex flex-wrap gap-3">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name/email…" class="input-admin max-w-xs">
  <select name="role" class="input-admin max-w-[180px]">
    <option value="">All roles</option>
    <?php foreach ($allRoles as $r): ?>
      <option value="<?= e($r['key']) ?>" <?= $roleKey === $r['key'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status" class="input-admin max-w-[150px]">
    <option value="">Any status</option>
    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
  </select>
  <button class="btn btn-ghost">Filter</button>
</form>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr>
        <th class="px-5 py-3">Name</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Roles</th>
        <th class="px-5 py-3">Status</th><th class="px-5 py-3">Last login</th><th class="px-5 py-3 text-right">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">No users found.</td></tr>
      <?php else: foreach ($rows as $u): $uid = (int) $u['id']; ?>
        <tr>
          <td class="px-5 py-3 font-medium text-brand-900"><?= e($u['name']) ?><?php if ($uid === $curId): ?> <span class="badge badge-slate ml-1">You</span><?php endif; ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e($u['email']) ?></td>
          <td class="px-5 py-3">
            <?php foreach (($u['roles'] ?? []) as $r): ?><span class="badge badge-slate mr-1"><?= e($r['name']) ?></span><?php endforeach; ?>
          </td>
          <td class="px-5 py-3">
            <span class="badge <?= (int) $u['is_active'] === 1 ? 'badge-green' : 'badge-slate' ?>"><?= (int) $u['is_active'] === 1 ? 'Active' : 'Inactive' ?></span>
          </td>
          <td class="px-5 py-3 text-slate-500"><?= e($u['last_login_at'] ? substr((string) $u['last_login_at'], 0, 16) : '—') ?></td>
          <td class="px-5 py-3">
            <div class="flex items-center justify-end gap-3">
              <a href="/admin/users/<?= $uid ?>/edit" class="font-medium text-brand-600 hover:text-brand-700"><?= $canEdit ? 'Edit' : 'View' ?></a>
              <a href="/admin/users/<?= $uid ?>/activity" class="text-slate-500 hover:text-brand-600">Activity</a>
              <?php if ($canActivate && $uid !== $curId): ?>
                <form method="post" action="/admin/users/<?= $uid ?>/active" class="inline" onsubmit="return confirm('<?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this user?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="active" value="<?= (int) $u['is_active'] === 1 ? '0' : '1' ?>">
                  <button class="<?= (int) $u['is_active'] === 1 ? 'text-amber-600' : 'text-green-600' ?> hover:underline"><?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                </form>
              <?php endif; ?>
              <?php if ($canDelete && $uid !== $curId): ?>
                <form method="post" action="/admin/users/<?= $uid ?>/delete" class="inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                  <?= csrf_field() ?><?= method_field('DELETE') ?>
                  <button class="text-red-500 hover:text-red-700">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="mt-4 flex gap-2">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="/admin/users?<?= e(http_build_query(array_filter(['q' => $search, 'role' => $roleKey, 'status' => $status, 'page' => $p]))) ?>"
       class="rounded-lg border px-3 py-1.5 text-sm <?= $p === $page ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php $this->stop(); ?>
