<?php
/** Bold call-to-action band. @var array $section */
$d = $section;
if (empty($d['heading']) && empty($d['button_label'])) { return; }
$dark = ($d['style'] ?? 'primary') === 'dark';
?>
<section class="py-12 lg:py-16">
  <div class="container-x">
    <div class="relative overflow-hidden rounded-3xl <?= $dark ? 'bg-gradient-to-br from-brand-900 to-brand-700' : 'bg-gradient-to-br from-brand-600 to-brand-500' ?> px-8 py-14 text-center text-white sm:px-14">
      <div class="pointer-events-none absolute right-0 top-0 h-64 w-64 rounded-full bg-teal-500/25 blur-3xl" aria-hidden="true"></div>
      <div class="relative mx-auto max-w-2xl">
        <?php if (!empty($d['heading'])): ?><h2 class="text-balance text-2xl font-semibold text-white sm:text-3xl lg:text-4xl"><?= e($d['heading']) ?></h2><?php endif; ?>
        <?php if (!empty($d['text'])): ?><p class="mx-auto mt-4 max-w-xl text-brand-100"><?= e($d['text']) ?></p><?php endif; ?>
        <?php if (!empty($d['button_label'])): ?>
        <div class="mt-8">
          <a href="<?= e($d['button_url'] ?? '#') ?>" class="btn bg-white text-brand-900 shadow-[0_10px_24px_-12px_rgba(0,0,0,.5)] hover:-translate-y-px hover:bg-brand-50 focus-visible:ring-white"><?= e($d['button_label']) ?></a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
