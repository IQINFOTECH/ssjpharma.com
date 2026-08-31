<?php
/** @var array $rows */
$this->layout('admin.layout');
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-brand-900">Redirects</h1>
  <p class="mt-1 text-sm text-slate-500">301/302 redirects with loop and open-redirect protection.</p>
</div>

<form method="post" action="/admin/redirects" class="mb-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-5 md:grid-cols-12 md:items-end">
  <?= csrf_field() ?>
  <div class="md:col-span-4">
    <label class="field-label text-xs">From path</label>
    <input name="from_path" class="input-admin" placeholder="/old-page" required>
  </div>
  <div class="md:col-span-5">
    <label class="field-label text-xs">To URL</label>
    <input name="to_url" class="input-admin" placeholder="/new-page or https://…" required>
  </div>
  <div class="md:col-span-2">
    <label class="field-label text-xs">Type</label>
    <select name="code" class="input-admin">
      <option value="301">301 Permanent</option>
      <option value="302">302 Temporary</option>
    </select>
  </div>
  <div class="md:col-span-1"><button class="btn btn-primary w-full py-2">Add</button></div>
</form>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="grid grid-cols-12 gap-3 border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
    <div class="col-span-4">From</div><div class="col-span-4">To</div><div class="col-span-1">Type</div>
    <div class="col-span-1">Active</div><div class="col-span-2 text-right">Actions</div>
  </div>
  <?php if (empty($rows)): ?>
    <p class="px-5 py-10 text-center text-sm text-slate-400">No redirects.</p>
  <?php else: foreach ($rows as $r): ?>
    <div class="relative border-b border-slate-100 px-5 py-3">
      <form method="post" action="/admin/redirects/<?= (int) $r['id'] ?>" class="grid grid-cols-12 items-center gap-3">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <input name="from_path" value="<?= e($r['from_path']) ?>" class="input-admin col-span-4 py-1 font-mono text-xs">
        <input name="to_url" value="<?= e($r['to_url']) ?>" class="input-admin col-span-4 py-1 font-mono text-xs">
        <select name="code" class="input-admin col-span-1 py-1">
          <option value="301" <?= (int) $r['code'] === 301 ? 'selected' : '' ?>>301</option>
          <option value="302" <?= (int) $r['code'] === 302 ? 'selected' : '' ?>>302</option>
        </select>
        <div class="col-span-1"><input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-brand-600" <?= (int) $r['is_active'] === 1 ? 'checked' : '' ?>></div>
        <div class="col-span-2 text-right"><button class="btn btn-ghost px-3 py-1 text-xs">Save</button></div>
      </form>
      <form method="post" action="/admin/redirects/<?= (int) $r['id'] ?>/delete" class="mt-1 text-right" onsubmit="return confirm('Delete redirect?');">
        <?= csrf_field() ?>
        <button class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
        <span class="ml-2 text-xs text-slate-400"><?= (int) $r['hits'] ?> hits</span>
      </form>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php $this->stop(); ?>
