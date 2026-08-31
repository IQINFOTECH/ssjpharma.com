<?php
/** @var array $products @var int $total @var int $page @var int $totalPages
 *  @var string $q @var int $catId @var int $taId @var int $dfId
 *  @var array $categories @var array $areas @var array $dosages */
$this->layout('site.layout');
$partials = dirname(__DIR__) . '/partials';
$qs = array_filter(['q' => $q, 'category' => $catId ?: '', 'ta' => $taId ?: '', 'dosage' => $dfId ?: '']);

// Name lookup for the active-filter chips.
$nm = static function (array $arr, int $id): string {
    foreach ($arr as $r) { if ((int) $r['id'] === $id) { return (string) $r['name']; } }
    return '';
};
$without = static function (array $qs, string $key): string {
    unset($qs[$key]);
    return '/products' . ($qs !== [] ? '?' . http_build_query($qs) : '');
};
$chips = [];
if ($q !== '')  { $chips[] = ['label' => '“' . $q . '”',       'url' => $without($qs, 'q')]; }
if ($catId)     { $chips[] = ['label' => $nm($categories, $catId), 'url' => $without($qs, 'category')]; }
if ($taId)      { $chips[] = ['label' => $nm($areas, $taId),        'url' => $without($qs, 'ta')]; }
if ($dfId)      { $chips[] = ['label' => $nm($dosages, $dfId),      'url' => $without($qs, 'dosage')]; }
?>
<?php $this->start('content'); ?>
<!-- Hero -->
<section class="relative overflow-hidden border-b border-slate-100 bg-gradient-to-b from-brand-50 to-white">
  <div class="pointer-events-none absolute -right-24 -top-20 h-80 w-80 rounded-full bg-teal-500/10 blur-3xl" aria-hidden="true"></div>
  <div class="container-x relative py-12 lg:py-16">
    <span class="eyebrow mb-3">Catalogue</span>
    <h1 class="text-balance text-4xl font-semibold tracking-tight sm:text-5xl">Products</h1>
    <p class="mt-4 max-w-2xl text-lg text-slate-600">Browse our product range. Filter by category, therapeutic area or dosage form, and send an enquiry directly.</p>
  </div>
</section>

<section class="container-x py-10">
  <div class="grid gap-8 lg:grid-cols-[230px_1fr]">
    <!-- Filter rail -->
    <aside class="lg:sticky lg:top-24 lg:self-start">
      <form method="get" action="/products" class="space-y-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-card">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Filters</p>
        <div>
          <label class="field-label text-xs" for="f-q">Search</label>
          <input id="f-q" type="text" name="q" value="<?= e($q) ?>" placeholder="Product name…" class="field">
        </div>
        <div>
          <label class="field-label text-xs" for="f-cat">Category</label>
          <select id="f-cat" name="category" class="field"><option value="">All categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div>
          <label class="field-label text-xs" for="f-ta">Therapeutic area</label>
          <select id="f-ta" name="ta" class="field"><option value="">All areas</option>
            <?php foreach ($areas as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $taId === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div>
          <label class="field-label text-xs" for="f-df">Dosage form</label>
          <select id="f-df" name="dosage" class="field"><option value="">All forms</option>
            <?php foreach ($dosages as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $dfId === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="flex gap-2 pt-1">
          <button class="btn btn-primary flex-1 py-2 text-sm">Filter</button>
          <?php if ($qs !== []): ?><a href="/products" class="btn btn-ghost py-2 text-sm">Reset</a><?php endif; ?>
        </div>
      </form>
    </aside>

    <!-- Results -->
    <div>
      <?php if (empty($products)): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-16 text-center">
          <p class="text-slate-500">No products match your search.</p>
          <?php if ($qs !== []): ?><a href="/products" class="mt-3 inline-block font-medium text-brand-600 hover:text-brand-700">Clear filters</a><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-2">
          <p class="text-sm text-slate-500"><?= e($total) ?> product<?= $total === 1 ? '' : 's' ?></p>
          <?php foreach ($chips as $chip): ?>
            <a href="<?= e($chip['url']) ?>" class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 hover:bg-brand-100"><?= e($chip['label']) ?> <span aria-hidden="true">✕</span></a>
          <?php endforeach; ?>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
          <?php foreach ($products as $p): include $partials . '/product_card.php'; endforeach; ?>
          <!-- Catalogue-level enquiry CTA — fills sparse rows and gives a direct enquiry path. -->
          <a href="/contact-us" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-brand-200 bg-brand-50/40 p-8 text-center transition hover:border-brand-300 hover:bg-brand-50">
            <svg class="h-8 w-8 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <h3 class="mt-3 font-display text-lg font-semibold text-brand-900">Can't find what you need?</h3>
            <p class="mt-1 max-w-xs text-sm text-slate-600">Tell us your requirement and our team will get back to you.</p>
            <span class="btn btn-primary mt-4 py-2 text-sm">Send an enquiry</span>
          </a>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-10 flex flex-wrap justify-center gap-2" aria-label="Pagination">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="/products?<?= e(http_build_query($qs + ['page' => $i])) ?>" class="rounded-lg border px-4 py-2 text-sm <?= $i === $page ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php $this->stop(); ?>
