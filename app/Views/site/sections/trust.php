<?php
/** Trust strip — a slim band of verified figures / short labels. @var array $section
 *  Admin enters items; only items with a non-empty value render. No fabricated data. */
$d = $section;
$items = array_values(array_filter(
    (array) ($d['items'] ?? []),
    static fn ($it) => is_array($it) && trim((string) ($it['value'] ?? '')) !== ''
));
if ($items === []) { return; }
?>
<section class="container-x py-6">
  <?php if (!empty($d['heading'])): ?><span class="eyebrow mb-3"><?= e($d['heading']) ?></span><?php endif; ?>
  <div class="flex flex-wrap items-stretch divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card sm:divide-x sm:divide-y-0">
    <?php foreach ($items as $it): ?>
      <div class="flex-1 basis-1/2 px-6 py-5 text-center sm:basis-0 sm:text-left">
        <div class="font-display text-2xl font-semibold text-brand-900"><?= e((string) $it['value']) ?></div>
        <?php if (trim((string) ($it['label'] ?? '')) !== ''): ?>
          <div class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500"><?= e((string) $it['label']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
