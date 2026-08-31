<?php
/** @var array $products @var int $total @var int $page @var int $totalPages
 *  @var string $q @var int $catId @var int $taId @var int $dfId
 *  @var array $categories @var array $areas @var array $dosages */
$this->layout('site.layout');
$partials = dirname(__DIR__) . '/partials';
$qs = array_filter(['q' => $q, 'category' => $catId ?: '', 'ta' => $taId ?: '', 'dosage' => $dfId ?: '']);
?>
<?php $this->start('content'); ?>
<section class="border-b border-slate-100 bg-gradient-to-b from-brand-50/60 to-white">
  <div class="container-x py-12 lg:py-16">
    <span class="eyebrow mb-2">Catalogue</span>
    <h1 class="text-3xl font-semibold sm:text-4xl">Products</h1>
    <p class="mt-3 max-w-2xl text-slate-600">Browse our product range. Use the filters to narrow by category, therapeutic area or dosage form.</p>
  </div>
</section>

<section class="container-x py-10">
  <form method="get" action="/products" class="mb-8 grid gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-card md:grid-cols-5">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search products…" class="field md:col-span-2">
    <select name="category" class="field"><option value="">All categories</option>
      <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select name="ta" class="field"><option value="">All therapeutic areas</option>
      <?php foreach ($areas as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $taId === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
    <div class="flex gap-2">
      <select name="dosage" class="field flex-1"><option value="">All forms</option>
        <?php foreach ($dosages as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $dfId === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
      <button class="btn btn-primary shrink-0">Filter</button>
    </div>
  </form>

  <?php if (empty($products)): ?>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-16 text-center">
      <p class="text-slate-500">No products match your search.</p>
      <?php if ($qs !== []): ?><a href="/products" class="mt-3 inline-block text-brand-600 hover:text-brand-700">Clear filters</a><?php endif; ?>
    </div>
  <?php else: ?>
    <p class="mb-4 text-sm text-slate-500"><?= e($total) ?> product<?= $total === 1 ? '' : 's' ?></p>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($products as $p): include $partials . '/product_card.php'; endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-10 flex flex-wrap justify-center gap-2" aria-label="Pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="/products?<?= e(http_build_query($qs + ['page' => $i])) ?>" class="rounded-lg border px-4 py-2 text-sm <?= $i === $page ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php $this->stop(); ?>
