<?php
/** @var array $rows @var int $total @var string $search @var int $page @var int $totalPages */
$this->layout('admin.layout');
$isImage = static fn (string $mime): bool => str_starts_with($mime, 'image/');
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-semibold text-brand-900">Media</h1>
    <p class="mt-1 text-sm text-slate-500"><?= e($total) ?> file<?= $total === 1 ? '' : 's' ?></p>
  </div>
  <form method="get" action="/admin/media">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search…" class="input-admin max-w-xs">
  </form>
</div>

<!-- Upload -->
<form method="post" action="/admin/media" enctype="multipart/form-data" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5">
  <?= csrf_field() ?>
  <div>
    <label class="field-label" for="file">Upload file</label>
    <input id="file" type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.pdf" class="text-sm">
  </div>
  <div class="flex-1 min-w-[200px]">
    <label class="field-label" for="alt">Alt text (optional)</label>
    <input id="alt" name="alt_text" class="input-admin" maxlength="255">
  </div>
  <button class="btn btn-primary">Upload</button>
  <p class="w-full text-xs text-slate-400">Allowed: JPG, PNG, WEBP, GIF, SVG (sanitised), PDF. Max 8 MB.</p>
</form>

<?php if (empty($rows)): ?>
  <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center text-sm text-slate-400">No media yet.</div>
<?php else: ?>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    <?php foreach ($rows as $m): ?>
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex h-40 items-center justify-center bg-slate-50">
          <?php if ($isImage((string) $m['mime'])): ?>
            <img src="<?= e($m['url_path']) ?>" alt="<?= e($m['alt_text'] ?? '') ?>" class="h-full w-full object-contain">
          <?php else: ?>
            <span class="text-4xl">📄</span>
          <?php endif; ?>
        </div>
        <div class="p-3">
          <p class="truncate text-xs font-medium text-brand-900" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></p>
          <p class="mt-0.5 text-xs text-slate-400">
            ID <?= (int) $m['id'] ?> · <?= e(strtoupper($m['extension'])) ?>
            <?php if ($m['width']): ?> · <?= (int) $m['width'] ?>×<?= (int) $m['height'] ?><?php endif; ?>
            · <?= e(number_format($m['size_bytes'] / 1024, 0)) ?> KB
          </p>
          <form method="post" action="/admin/media/<?= (int) $m['id'] ?>" class="mt-2 flex gap-2">
            <?= csrf_field() ?>
            <?= method_field('PUT') ?>
            <input name="alt_text" value="<?= e($m['alt_text'] ?? '') ?>" placeholder="Alt text" class="input-admin py-1 text-xs">
            <button class="btn btn-ghost px-3 py-1 text-xs">Save</button>
          </form>
          <div class="mt-2 flex items-center justify-between">
            <input readonly value="<?= e($m['url_path']) ?>" class="js-select-on-click w-full rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-500">
          </div>
          <form method="post" action="/admin/media/<?= (int) $m['id'] ?>/delete" class="js-confirm mt-2" data-confirm="Delete this file?">
            <?= csrf_field() ?>
            <button class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="mt-6 flex gap-2">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="/admin/media?<?= e(http_build_query(array_filter(['q' => $search, 'page' => $p]))) ?>"
         class="rounded-lg border px-3 py-1.5 text-sm <?= $p === $page ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
<?php $this->stop(); ?>
