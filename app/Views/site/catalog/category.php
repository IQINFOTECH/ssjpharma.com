<?php
/** @var array $category @var array $subcategories @var array $products @var int $total @var int $page @var int $totalPages @var array $breadcrumbs */
use App\Support\HtmlSanitizer;
$this->layout('site.layout');
$partials = dirname(__DIR__) . '/partials';
?>
<?php $this->start('content'); ?>
<nav class="container-x pt-6 text-sm text-slate-500" aria-label="Breadcrumb">
  <ol class="flex flex-wrap items-center gap-1.5">
    <?php foreach ($breadcrumbs as $i => $c): ?>
      <li class="flex items-center gap-1.5"><?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= e(parse_url($c['url'], PHP_URL_PATH) ?: '/') ?>" class="hover:text-brand-600"><?= e($c['name']) ?></a><span class="text-slate-300">/</span><?php else: ?><span class="text-slate-700"><?= e($c['name']) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>
</nav>

<section class="container-x py-8 lg:py-12">
  <span class="eyebrow mb-2">Category</span>
  <h1 class="text-3xl font-semibold sm:text-4xl"><?= e($category['name']) ?></h1>
  <?php if (!empty($category['description'])): ?><div class="prose-cms mt-4 max-w-3xl"><?= HtmlSanitizer::clean((string) $category['description']) ?></div><?php endif; ?>

  <?php if (!empty($subcategories)): ?>
  <div class="mt-6 flex flex-wrap gap-2">
    <?php foreach ($subcategories as $sub): ?><a href="/product-category/<?= e($sub['slug']) ?>" class="rounded-full border border-slate-200 px-4 py-1.5 text-sm text-slate-600 hover:border-brand-300 hover:text-brand-600"><?= e($sub['name']) ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="container-x pb-12">
  <?php if (empty($products)): ?>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-16 text-center text-slate-500">No products in this category yet.</div>
  <?php else: ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($products as $p): include $partials . '/product_card.php'; endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <nav class="mt-10 flex flex-wrap justify-center gap-2" aria-label="Pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="/product-category/<?= e($category['slug']) ?>?page=<?= $i ?>" class="rounded-lg border px-4 py-2 text-sm <?= $i === $page ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $i ?></a><?php endfor; ?>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php $this->stop(); ?>
