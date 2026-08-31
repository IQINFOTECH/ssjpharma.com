<?php
/** @var array $rows @var int $total @var string $search @var string $status @var int $page @var int $totalPages */
$this->layout('admin.layout');
$badge = static fn (string $s): string => match ($s) {
    'published' => 'badge-green', 'draft' => 'badge-amber', default => 'badge-slate',
};
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-semibold text-brand-900">Pages</h1>
    <p class="mt-1 text-sm text-slate-500"><?= e($total) ?> page<?= $total === 1 ? '' : 's' ?></p>
  </div>
  <a href="/admin/pages/create" class="btn btn-primary">New Page</a>
</div>

<form method="get" action="/admin/pages" class="mb-4 flex flex-wrap gap-3">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search title or slug…" class="input-admin max-w-xs">
  <select name="status" class="input-admin max-w-[180px]">
    <option value="">All statuses</option>
    <?php foreach (['published', 'draft', 'archived'] as $s): ?>
      <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost">Filter</button>
</form>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr>
        <th class="px-5 py-3">Title</th><th class="px-5 py-3">Slug</th>
        <th class="px-5 py-3">Status</th><th class="px-5 py-3">Updated</th><th class="px-5 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No pages found.</td></tr>
      <?php else: foreach ($rows as $row): ?>
        <tr>
          <td class="px-5 py-3 font-medium text-brand-900">
            <?= e($row['title']) ?>
            <?php if ((int) $row['is_home'] === 1): ?><span class="badge badge-slate ml-1">Home</span><?php endif; ?>
          </td>
          <td class="px-5 py-3 font-mono text-xs text-slate-500">/<?= e($row['slug']) ?></td>
          <td class="px-5 py-3"><span class="badge <?= $badge($row['status']) ?>"><?= ucfirst($row['status']) ?></span></td>
          <td class="px-5 py-3 text-slate-500"><?= e(substr((string) $row['updated_at'], 0, 16)) ?></td>
          <td class="px-5 py-3 text-right"><a href="/admin/pages/<?= (int) $row['id'] ?>/edit" class="font-medium text-brand-600 hover:text-brand-700">Edit</a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="mt-4 flex items-center gap-2">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="/admin/pages?<?= e(http_build_query(array_filter(['q' => $search, 'status' => $status, 'page' => $p]))) ?>"
       class="rounded-lg border px-3 py-1.5 text-sm <?= $p === $page ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php $this->stop(); ?>
