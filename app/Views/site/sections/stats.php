<?php
/** Statistics band. Values are admin-entered (no fabricated numbers). @var array $section */
$d = $section;
$items = is_array($d['items'] ?? null) ? $d['items'] : [];
if ($items === []) { return; }
$cols = min(4, max(1, count($items)));
?>
<section class="py-12 lg:py-16">
  <div class="container-x">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-900 to-brand-700 px-8 py-12 sm:px-12 lg:py-16">
      <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-teal-500/20 blur-3xl" aria-hidden="true"></div>
      <?php if (!empty($d['heading'])): ?>
      <h2 class="relative mb-10 text-center text-2xl font-semibold text-white sm:text-3xl"><?= e($d['heading']) ?></h2>
      <?php endif; ?>
      <div class="relative grid gap-8 text-center sm:grid-cols-2 lg:grid-cols-<?= $cols ?>">
        <?php foreach ($items as $item): if (!is_array($item)) continue; ?>
          <div>
            <div class="font-display text-4xl font-semibold tabular-nums text-teal-400 lg:text-5xl"><?= e($item['value'] ?? '') ?></div>
            <div class="mt-2 text-sm text-brand-100"><?= e($item['label'] ?? '') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
