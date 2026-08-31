<?php
/** @var array $rows @var bool $canCreate */
$this->layout('admin.layout');
$badge = static fn (string $s): string => match ($s) { 'published' => 'badge-green', 'draft' => 'badge-amber', default => 'badge-slate' };
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div><h1 class="text-2xl font-semibold text-brand-900">Therapeutic Areas</h1>
    <p class="mt-1 text-sm text-slate-500"><?= count($rows) ?> area<?= count($rows) === 1 ? '' : 's' ?></p></div>
  <?php if ($canCreate): ?><a href="/admin/therapeutic-areas/create" class="btn btn-primary">New Therapeutic Area</a><?php endif; ?>
</div>
<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Slug</th><th class="px-5 py-3">Products</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right"></th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No therapeutic areas yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="px-5 py-3 font-medium text-brand-900"><?= e($r['name']) ?><?php if ((int) $r['is_demo'] === 1): ?> <span class="badge badge-amber ml-1">Demo</span><?php endif; ?></td>
          <td class="px-5 py-3 font-mono text-xs text-slate-500">/<?= e($r['slug']) ?></td>
          <td class="px-5 py-3 text-slate-600"><?= (int) $r['product_count'] ?></td>
          <td class="px-5 py-3"><span class="badge <?= $badge($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          <td class="px-5 py-3 text-right"><a href="/admin/therapeutic-areas/<?= (int) $r['id'] ?>/edit" class="font-medium text-brand-600 hover:text-brand-700">Edit</a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php $this->stop(); ?>
