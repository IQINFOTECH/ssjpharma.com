<?php
/** @var array $rows @var int $total @var array $filters @var int $page @var int $totalPages
 *  @var array $categories @var array $areas @var array $dosages @var bool $canCreate @var bool $canPublish @var bool $canDelete */
$this->layout('admin.layout');
$badge = static fn (string $s): string => match ($s) { 'published' => 'badge-green', 'draft' => 'badge-amber', default => 'badge-slate' };
$qs = array_filter(['q' => $filters['q'], 'category' => $filters['category'] ?: '', 'ta' => $filters['ta'] ?: '', 'status' => $filters['status'], 'featured' => $filters['featured'], 'dosage' => $filters['dosage'] ?: '']);
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div><h1 class="text-2xl font-semibold text-brand-900">Products</h1>
    <p class="mt-1 text-sm text-slate-500"><?= e($total) ?> product<?= $total === 1 ? '' : 's' ?></p></div>
  <?php if ($canCreate): ?><a href="/admin/products/create" class="btn btn-primary">New Product</a><?php endif; ?>
</div>

<form method="get" action="/admin/products" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-6">
  <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Name / generic / code…" class="input-admin md:col-span-2">
  <select name="category" class="input-admin"><option value="">All categories</option>
    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $filters['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="ta" class="input-admin"><option value="">All therapeutic areas</option>
    <?php foreach ($areas as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $filters['ta'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <select name="dosage" class="input-admin"><option value="">All dosage forms</option>
    <?php foreach ($dosages as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $filters['dosage'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
  <select name="status" class="input-admin"><option value="">Any status</option>
    <?php foreach (['published','draft','archived'] as $s): ?><option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
  <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="featured" value="1" class="rounded border-slate-300 text-brand-600" <?= $filters['featured'] === '1' ? 'checked' : '' ?>> Featured only</label>
  <div class="md:col-span-6"><button class="btn btn-ghost">Filter</button> <a href="/admin/products" class="btn btn-ghost">Reset</a></div>
</form>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr><th class="px-5 py-3">Product</th><th class="px-5 py-3">Code</th><th class="px-5 py-3">Category</th><th class="px-5 py-3">Therapeutic Area</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Featured</th><th class="px-5 py-3">Updated</th><th class="px-5 py-3 text-right">Actions</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">No products found.</td></tr>
      <?php else: foreach ($rows as $p): $pid = (int) $p['id']; ?>
        <tr>
          <td class="px-5 py-3 font-medium text-brand-900"><?= e($p['name']) ?><?php if ((int) $p['is_demo'] === 1): ?> <span class="badge badge-amber ml-1">Demo</span><?php endif; ?></td>
          <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= e($p['code'] ?: '—') ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e($p['category_name'] ?: '—') ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e($p['ta_names'] ?: '—') ?></td>
          <td class="px-5 py-3"><span class="badge <?= $badge($p['status']) ?>"><?= ucfirst($p['status']) ?></span></td>
          <td class="px-5 py-3"><?= (int) $p['is_featured'] === 1 ? '★' : '—' ?></td>
          <td class="px-5 py-3 text-slate-500"><?= e(substr((string) $p['updated_at'], 0, 16)) ?></td>
          <td class="px-5 py-3">
            <div class="flex items-center justify-end gap-3">
              <a href="/admin/products/<?= $pid ?>/edit" class="font-medium text-brand-600 hover:text-brand-700">Edit</a>
              <?php if ($canCreate): ?>
                <form method="post" action="/admin/products/<?= $pid ?>/duplicate" class="inline"><?= csrf_field() ?><button class="text-slate-500 hover:text-brand-600">Duplicate</button></form>
              <?php endif; ?>
              <?php if ($canPublish): ?>
                <form method="post" action="/admin/products/<?= $pid ?>/status" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="status" value="<?= $p['status'] === 'published' ? 'draft' : 'published' ?>">
                  <button class="<?= $p['status'] === 'published' ? 'text-amber-600' : 'text-green-600' ?> hover:underline"><?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button>
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
<div class="mt-4 flex flex-wrap gap-2">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="/admin/products?<?= e(http_build_query($qs + ['page' => $p])) ?>" class="rounded-lg border px-3 py-1.5 text-sm <?= $p === $page ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php $this->stop(); ?>
