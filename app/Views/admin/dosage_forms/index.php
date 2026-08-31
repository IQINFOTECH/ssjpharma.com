<?php
/** @var array $rows @var bool $canManage */
$this->layout('admin.layout');
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-brand-900">Dosage Forms</h1>
  <p class="mt-1 text-sm text-slate-500">Picklist options for products. These are structural values only and do not imply manufacturing.</p>
</div>

<?php if ($canManage): ?>
<form method="post" action="/admin/dosage-forms" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5">
  <?= csrf_field() ?>
  <div><label class="field-label text-xs">Name</label><input name="name" class="input-admin" placeholder="e.g. Tablet" required></div>
  <div><label class="field-label text-xs">Sort</label><input type="number" name="sort_order" value="0" class="input-admin w-24"></div>
  <button class="btn btn-primary">Add</button>
</form>
<?php endif; ?>

<div class="rounded-xl border border-slate-200 bg-white">
  <?php if (empty($rows)): ?>
    <p class="px-5 py-8 text-center text-sm text-slate-400">No dosage forms yet.</p>
  <?php else: foreach ($rows as $r): ?>
    <div class="border-b border-slate-100 px-5 py-3">
      <?php if ($canManage): ?>
      <form method="post" action="/admin/dosage-forms/<?= (int) $r['id'] ?>" class="grid grid-cols-12 items-center gap-3">
        <?= csrf_field() ?><?= method_field('PUT') ?>
        <input name="name" value="<?= e($r['name']) ?>" class="input-admin col-span-5 py-1">
        <input type="number" name="sort_order" value="<?= (int) $r['sort_order'] ?>" class="input-admin col-span-2 py-1">
        <label class="col-span-3 inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-brand-600" <?= (int) $r['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
        <div class="col-span-2 text-right"><button class="btn btn-ghost px-3 py-1 text-xs">Save</button></div>
      </form>
      <form method="post" action="/admin/dosage-forms/<?= (int) $r['id'] ?>/delete" class="js-confirm mt-1 text-right" data-confirm="Delete this dosage form?">
        <?= csrf_field() ?>
        <button class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
      </form>
      <?php else: ?>
        <span class="text-sm text-brand-900"><?= e($r['name']) ?></span>
        <span class="badge <?= (int) $r['is_active'] === 1 ? 'badge-green' : 'badge-slate' ?> ml-2"><?= (int) $r['is_active'] === 1 ? 'Active' : 'Inactive' ?></span>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php $this->stop(); ?>
