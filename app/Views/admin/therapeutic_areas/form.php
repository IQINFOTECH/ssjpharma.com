<?php
/** @var array|null $area @var bool $canEdit */
$this->layout('admin.layout');
$isEdit = $area !== null;
$v = static fn (string $k, $d = '') => e((string) ($area[$k] ?? $d));
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex items-center justify-between">
  <div><a href="/admin/therapeutic-areas" class="text-sm text-slate-500 hover:text-brand-600">← Therapeutic Areas</a>
    <h1 class="mt-1 text-2xl font-semibold text-brand-900"><?= $isEdit ? e($area['name']) : 'New Therapeutic Area' ?></h1></div>
  <?php if ($isEdit): ?>
  <form method="post" action="/admin/therapeutic-areas/<?= (int) $area['id'] ?>/status" class="inline">
    <?= csrf_field() ?>
    <?php if ($area['status'] === 'published'): ?>
      <input type="hidden" name="status" value="draft"><button class="btn btn-ghost">Unpublish</button>
    <?php else: ?>
      <input type="hidden" name="status" value="published"><button class="btn btn-primary">Publish</button>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>
<form method="post" action="<?= $isEdit ? '/admin/therapeutic-areas/' . (int) $area['id'] : '/admin/therapeutic-areas' ?>" class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
  <?= csrf_field() ?><?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
  <div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2"><label class="field-label">Name</label><input name="name" class="input-admin" value="<?= $v('name') ?>" maxlength="150" required></div>
    <div><label class="field-label">Slug</label><input name="slug" class="input-admin" value="<?= $v('slug') ?>" placeholder="auto"></div>
    <div><label class="field-label">Status</label>
      <select name="status" class="input-admin"><?php foreach (['draft','published','archived'] as $s): ?><option value="<?= $s ?>" <?= ($area['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div><label class="field-label">Sort order</label><input type="number" name="sort_order" class="input-admin" value="<?= $v('sort_order', '0') ?>"></div>
    <div><label class="field-label">Image (Media ID)</label><input name="image_id" class="input-admin" value="<?= $v('image_id') ?>"></div>
    <div class="sm:col-span-2"><label class="field-label">Description</label><textarea name="description" rows="3" class="input-admin"><?= $v('description') ?></textarea></div>
    <div class="flex items-end"><label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_demo" value="1" class="rounded border-slate-300 text-amber-600" <?= (int) ($area['is_demo'] ?? 0) === 1 ? 'checked' : '' ?>> Demo (not production)</label></div>
    <div class="sm:col-span-2"><label class="field-label">SEO title</label><input name="meta_title" class="input-admin" value="<?= $v('meta_title') ?>" maxlength="255"></div>
    <div class="sm:col-span-2"><label class="field-label">Meta description</label><textarea name="meta_description" rows="2" class="input-admin" maxlength="320"><?= $v('meta_description') ?></textarea></div>
  </div>
  <div class="flex gap-3"><button class="btn btn-primary"><?= $isEdit ? 'Save' : 'Create' ?></button><a href="/admin/therapeutic-areas" class="btn btn-ghost">Cancel</a></div>
</form>
<?php if ($isEdit): ?>
<form method="post" action="/admin/therapeutic-areas/<?= (int) $area['id'] ?>/delete" class="mt-4 max-w-2xl" onsubmit="return confirm('Archive this therapeutic area?');">
  <?= csrf_field() ?><?= method_field('DELETE') ?>
  <button class="text-sm font-medium text-red-500 hover:text-red-700">Archive therapeutic area</button>
</form>
<?php endif; ?>
<?php $this->stop(); ?>
