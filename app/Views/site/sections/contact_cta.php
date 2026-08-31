<?php
/** Contact CTA (lighter than the CTA band). @var array $section */
$d = $section;
?>
<section class="py-14 lg:py-20">
  <div class="container-x">
    <div class="flex flex-col items-center justify-between gap-6 rounded-2xl border border-brand-100 bg-brand-50/60 px-8 py-10 text-center sm:flex-row sm:text-left">
      <div>
        <?php if (!empty($d['heading'])): ?><h2 class="text-xl font-semibold sm:text-2xl"><?= e($d['heading']) ?></h2><?php endif; ?>
        <?php if (!empty($d['text'])): ?><p class="mt-2 text-slate-600"><?= e($d['text']) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($d['button_label'])): ?>
      <a href="<?= e($d['button_url'] ?? '/contact-us') ?>" class="btn btn-primary shrink-0"><?= e($d['button_label']) ?></a>
      <?php endif; ?>
    </div>
  </div>
</section>
