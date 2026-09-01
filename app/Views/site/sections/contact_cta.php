<?php
/** Closing CTA — editorial hairline row (Concept 1). @var array $section */
$d = $section;
if (empty($d['heading']) && empty($d['button_label'])) { return; }
?>
<section class="section-pad">
  <div class="container-x">
    <div class="flex flex-col gap-5 rounded-2xl border border-slate-200 px-7 py-7 sm:flex-row sm:items-center sm:justify-between sm:px-9">
      <div class="max-w-2xl">
        <?php if (!empty($d['heading'])): ?>
        <h2 class="text-balance font-display text-xl font-semibold text-brand-900 sm:text-2xl"><?= e($d['heading']) ?></h2>
        <?php endif; ?>
        <?php if (!empty($d['text'])): ?>
        <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($d['text']) ?></p>
        <?php endif; ?>
      </div>
      <?php if (!empty($d['button_label'])): ?>
      <a href="<?= e($d['button_url'] ?? '/contact-us') ?>" class="btn btn-primary shrink-0"><?= e($d['button_label']) ?></a>
      <?php endif; ?>
    </div>
  </div>
</section>
