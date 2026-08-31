<?php
/**
 * Product showcase PLACEHOLDER. Real products arrive with the Product CMS phase —
 * no product data is invented here. Renders an honest empty-state.
 * @var array $section
 */
$d = $section;
?>
<section class="py-14 lg:py-20">
  <div class="container-x">
    <?php if (!empty($d['heading']) || !empty($d['subheading'])): ?>
    <div class="mb-8 max-w-2xl">
      <?php if (!empty($d['heading'])): ?><h2 class="text-2xl font-semibold sm:text-3xl"><?= e($d['heading']) ?></h2><?php endif; ?>
      <?php if (!empty($d['subheading'])): ?><p class="mt-3 text-slate-600"><?= e($d['subheading']) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center">
      <p class="text-sm text-slate-500"><?= e($d['note'] ?? 'Product catalogue coming soon.') ?></p>
    </div>
  </div>
</section>
