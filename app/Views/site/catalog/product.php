<?php
/** @var array $product @var array $images @var array $documents @var array $specs @var array $tas
 *  @var array|null $category @var array|null $dosage @var array $related @var string $waLink @var array $enquiryForm
 *  @var array $breadcrumbs */
use App\Support\HtmlSanitizer;
$this->layout('site.layout');
$partials = dirname(__DIR__) . '/partials';
$sectionsDir = dirname(__DIR__) . '/sections';
$hero = media_url((int) ($product['hero_image_id'] ?? 0));
// Product information rows — only those with content (no empty "Label: —").
$info = array_filter([
    'Generic Name' => $product['generic_name'] ?? '',
    'Composition'  => $product['composition'] ?? '',
    'Strength'     => $product['strength'] ?? '',
    'Dosage Form'  => $dosage['name'] ?? '',
    'Pack Size'    => $product['pack_size'] ?? '',
], static fn ($v) => trim((string) $v) !== '');
?>
<?php $this->start('content'); ?>
<?php if ($isPreview ?? false): ?>
<div class="border-b border-amber-200 bg-amber-50">
  <div class="container-x flex flex-wrap items-center gap-x-2 py-2.5 text-sm text-amber-800">
    <strong>Draft preview.</strong> This product is <em><?= e(ucfirst((string) ($product['status'] ?? 'unpublished'))) ?></em> — visible only to signed-in staff, hidden from the public and search engines. Publish it to make it live.
  </div>
</div>
<?php endif; ?>

<!-- Breadcrumbs -->
<nav class="container-x pt-6 text-sm text-slate-500" aria-label="Breadcrumb">
  <ol class="flex flex-wrap items-center gap-1.5">
    <?php foreach ($breadcrumbs as $i => $crumb): ?>
      <li class="flex items-center gap-1.5">
        <?php if ($i < count($breadcrumbs) - 1): ?>
          <a href="<?= e(parse_url($crumb['url'], PHP_URL_PATH) ?: '/') ?>" class="hover:text-brand-600"><?= e($crumb['name']) ?></a><span class="text-slate-300">/</span>
        <?php else: ?><span class="text-slate-700"><?= e($crumb['name']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>

<!-- Hero -->
<section class="container-x py-8 lg:py-12">
  <div class="grid gap-10 lg:grid-cols-2">
    <div>
      <div class="relative">
        <div class="pointer-events-none absolute -inset-2.5 rounded-[1.5rem] bg-gradient-to-br from-brand-500/10 to-teal-500/10" aria-hidden="true"></div>
        <div class="relative flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-slate-100 bg-white">
          <?php if ($hero !== ''): ?><img src="<?= e($hero) ?>" alt="<?= e($product['name']) ?>" class="h-full w-full object-contain p-6">
          <?php else: ?><span class="text-6xl text-slate-200" aria-hidden="true">&#9877;</span><?php endif; ?>
        </div>
      </div>
      <?php if (count($images) > 1): ?>
      <div class="mt-4 grid grid-cols-5 gap-2">
        <?php foreach (array_slice($images, 0, 5) as $img): ?>
          <div class="flex h-16 items-center justify-center rounded-lg border border-slate-100 bg-slate-50"><img src="<?= e($img['url_path']) ?>" alt="<?= e($img['alt_text'] ?? '') ?>" loading="lazy" class="h-full w-full object-contain p-1"></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <!-- Category + therapeutic-area chips -->
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <?php if ($category !== null): ?><a href="/product-category/<?= e($category['slug']) ?>" class="inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100"><?= e($category['name']) ?></a><?php endif; ?>
        <?php foreach ($tas as $t): ?><a href="/therapeutic-area/<?= e($t['slug']) ?>" class="inline-flex rounded-full bg-teal-500/10 px-3 py-1 text-xs font-semibold text-teal-600 hover:bg-teal-500/20"><?= e($t['name']) ?></a><?php endforeach; ?>
        <?php if (!empty($product['is_demo'])): ?><span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Demo</span><?php endif; ?>
      </div>

      <h1 class="text-balance text-3xl font-semibold leading-tight tracking-tight sm:text-4xl lg:text-[2.75rem]"><?= e($product['name']) ?></h1>
      <?php if (!empty($product['generic_name'])): ?><p class="mt-2 text-lg text-slate-600"><?= e($product['generic_name']) ?></p><?php endif; ?>
      <?php if (!empty($product['code'])): ?><p class="mt-2 font-mono text-xs uppercase tracking-wider text-slate-400">Code · <?= e($product['code']) ?></p><?php endif; ?>
      <?php if (!empty($product['short_description'])): ?><p class="mt-5 leading-relaxed text-slate-600"><?= e($product['short_description']) ?></p><?php endif; ?>

      <!-- Key facts (only fields the CMS actually holds) -->
      <?php if ($info !== []): ?>
      <dl class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card">
        <?php foreach ($info as $label => $value): ?>
        <div class="flex justify-between gap-4 border-b border-slate-50 px-4 py-3 last:border-0">
          <dt class="shrink-0 text-sm text-slate-500"><?= e($label) ?></dt>
          <dd class="text-right text-sm font-semibold text-brand-900"><?= nl2br(e($value)) ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
      <?php endif; ?>

      <div class="mt-6 flex flex-wrap gap-3">
        <a href="/contact-us?product=<?= (int) $product['id'] ?>" class="btn btn-primary">Enquire about this product</a>
        <?php if ($waLink !== ''): ?><a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp" data-wa-context="product">WhatsApp Enquiry</a><?php endif; ?>
      </div>
      <p class="mt-4 text-xs text-slate-400">For business, distribution &amp; bulk enquiries — send your requirement and our team will respond.</p>
    </div>
  </div>
</section>

<!-- Description -->
<?php if (!empty($product['description'])): ?>
<section class="container-x py-6">
  <h2 class="mb-4 text-xl font-semibold">Description</h2>
  <div class="prose-cms"><?= HtmlSanitizer::clean((string) $product['description']) ?></div>
</section>
<?php endif; ?>

<!-- Specification -->
<?php if (!empty($specs)): ?>
<section class="container-x py-6">
  <h2 class="mb-4 text-xl font-semibold">Specification</h2>
  <div class="overflow-x-auto rounded-2xl border border-slate-100">
    <table class="w-full text-sm">
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($specs as $s): ?>
          <tr>
            <th class="bg-slate-50 px-5 py-3 text-left font-medium text-slate-600"><?= e($s['title']) ?></th>
            <td class="px-5 py-3 text-slate-700"><?= e($s['value']) ?><?php if (!empty($s['unit'])): ?> <span class="text-slate-400"><?= e($s['unit']) ?></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- Documents -->
<?php if (!empty($documents)): ?>
<section class="container-x py-6">
  <h2 class="mb-4 text-xl font-semibold">Documents</h2>
  <ul class="space-y-2">
    <?php foreach ($documents as $doc): ?>
      <li><a href="<?= e($doc['url_path']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-brand-700 hover:bg-slate-50">
        <span aria-hidden="true">&#128196;</span> <?= e($doc['display_name']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<!-- Related products -->
<?php if (!empty($related)): ?>
<section class="container-x py-10">
  <h2 class="mb-6 text-xl font-semibold">Related Products</h2>
  <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($related as $p): include $partials . '/product_card.php'; endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php $this->stop(); ?>
