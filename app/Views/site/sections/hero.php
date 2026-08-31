<?php
/** Hero section. @var array $section */
$d = $section;
$align = ($d['align'] ?? 'left') === 'center' ? 'center' : 'left';
$small = ($d['size'] ?? '') === 'small';
$img = media_url($d['image_id'] ?? null);
$hasImg = $img !== '' && $align !== 'center';
?>
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50/60 to-white">
  <div class="container-x <?= $small ? 'py-12 lg:py-16' : 'py-16 lg:py-24' ?>">
    <div class="grid items-center gap-10 <?= $hasImg ? 'lg:grid-cols-2' : '' ?>">
      <div class="<?= $align === 'center' ? 'mx-auto max-w-3xl text-center' : 'max-w-2xl' ?>">
        <?php if (!empty($d['eyebrow'])): ?><span class="eyebrow mb-3"><?= e($d['eyebrow']) ?></span><?php endif; ?>
        <h1 class="text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl"><?= e($d['heading'] ?? '') ?></h1>
        <?php if (!empty($d['subheading'])): ?>
        <p class="mt-5 text-lg leading-relaxed text-slate-600 <?= $align === 'center' ? 'mx-auto' : '' ?>"><?= nl2br(e($d['subheading'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($d['primary_label']) || !empty($d['secondary_label'])): ?>
        <div class="mt-8 flex flex-wrap gap-3 <?= $align === 'center' ? 'justify-center' : '' ?>">
          <?php if (!empty($d['primary_label'])): ?>
            <a href="<?= e($d['primary_url'] ?? '#') ?>" class="btn btn-primary"><?= e($d['primary_label']) ?></a>
          <?php endif; ?>
          <?php if (!empty($d['secondary_label'])): ?>
            <a href="<?= e($d['secondary_url'] ?? '#') ?>" class="btn btn-ghost"><?= e($d['secondary_label']) ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($hasImg): ?>
      <div class="relative">
        <img src="<?= e($img) ?>" alt="" class="w-full rounded-2xl object-cover shadow-card">
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
