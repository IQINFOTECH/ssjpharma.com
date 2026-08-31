<?php
/** Statistics band. Values are admin-entered (no fabricated numbers). @var array $section */
$d = $section;
$items = is_array($d['items'] ?? null) ? $d['items'] : [];
if ($items === []) { return; }
?>
<section class="py-14 lg:py-20">
  <div class="container-x">
    <?php if (!empty($d['heading'])): ?><h2 class="mb-10 text-center text-2xl font-semibold sm:text-3xl"><?= e($d['heading']) ?></h2><?php endif; ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-<?= min(4, max(1, count($items))) ?>">
      <?php foreach ($items as $item): if (!is_array($item)) continue; ?>
        <div class="rounded-2xl border border-slate-100 bg-white p-6 text-center shadow-card">
          <div class="font-display text-3xl font-bold text-brand-600 lg:text-4xl"><?= e($item['value'] ?? '') ?></div>
          <div class="mt-2 text-sm text-slate-600"><?= e($item['label'] ?? '') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
