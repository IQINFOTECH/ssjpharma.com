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
<section class="container-x section-pad">
  <?php if (!empty($d['heading'])): ?><span class="eyebrow mb-4"><?= e($d['heading']) ?></span><?php endif; ?>
  <div class="flex flex-wrap gap-x-12 gap-y-5 border-t border-slate-200 pt-6">
    <?php foreach ($items as $it): ?>
      <div>
        <div class="font-display text-xl font-semibold text-brand-900 sm:text-2xl"><?= e((string) $it['value']) ?></div>
        <?php if (trim((string) ($it['label'] ?? '')) !== ''): ?>
          <div class="mt-1 font-mono text-[11px] font-medium uppercase tracking-widest text-slate-500"><?= e((string) $it['label']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
