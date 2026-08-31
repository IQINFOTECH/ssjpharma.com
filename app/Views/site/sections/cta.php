<?php
/** Call-to-action band. @var array $section */
$d = $section;
$dark = ($d['style'] ?? 'primary') === 'dark';
?>
<section class="py-14 lg:py-20">
  <div class="container-x">
    <div class="rounded-3xl <?= $dark ? 'bg-brand-900' : 'bg-brand-500' ?> px-8 py-12 text-center text-white lg:px-16 lg:py-16">
      <?php if (!empty($d['heading'])): ?><h2 class="text-2xl font-semibold text-white sm:text-3xl"><?= e($d['heading']) ?></h2><?php endif; ?>
      <?php if (!empty($d['text'])): ?><p class="mx-auto mt-3 max-w-2xl text-white/85"><?= e($d['text']) ?></p><?php endif; ?>
      <?php if (!empty($d['button_label'])): ?>
      <div class="mt-8">
        <a href="<?= e($d['button_url'] ?? '#') ?>" class="btn bg-white text-brand-700 hover:bg-slate-100"><?= e($d['button_label']) ?></a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
