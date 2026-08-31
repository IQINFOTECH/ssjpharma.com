<?php
/** Hero section. @var array $section */
$d = $section;
$align = ($d['align'] ?? 'left') === 'center' ? 'center' : 'left';
$small = ($d['size'] ?? '') === 'small';
$img = media_url($d['image_id'] ?? null);
$hasImg = $img !== '' && $align !== 'center';
?>
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-brand-50/40 to-white">
  <div class="pointer-events-none absolute -right-32 -top-24 h-[30rem] w-[30rem] rounded-full bg-teal-500/10 blur-3xl" aria-hidden="true"></div>
  <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-brand-500/10 blur-3xl" aria-hidden="true"></div>
  <div class="container-x relative <?= $small ? 'py-12 lg:py-16' : 'py-20 lg:py-28' ?>">
    <div class="grid items-center gap-12 <?= $hasImg ? 'lg:grid-cols-2' : '' ?>">
      <div class="<?= $align === 'center' ? 'mx-auto max-w-3xl text-center' : 'max-w-2xl' ?>">
        <?php if (!empty($d['eyebrow'])): ?><span class="eyebrow mb-4"><?= e($d['eyebrow']) ?></span><?php endif; ?>
        <h1 class="text-balance font-semibold leading-[1.06] tracking-tight <?= $small ? 'text-3xl sm:text-4xl' : 'text-4xl sm:text-5xl lg:text-6xl' ?>"><?= e($d['heading'] ?? '') ?></h1>
        <?php if (!empty($d['subheading'])): ?>
        <p class="mt-6 text-lg leading-relaxed text-slate-600 lg:text-xl <?= $align === 'center' ? 'mx-auto max-w-2xl' : 'max-w-xl' ?>"><?= nl2br(e($d['subheading'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($d['primary_label']) || !empty($d['secondary_label'])): ?>
        <div class="mt-9 flex flex-wrap gap-3 <?= $align === 'center' ? 'justify-center' : '' ?>">
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
        <div class="pointer-events-none absolute -inset-3 rounded-[1.75rem] bg-gradient-to-br from-brand-500/15 to-teal-500/15" aria-hidden="true"></div>
        <img src="<?= e($img) ?>" alt="" class="relative w-full rounded-3xl object-cover shadow-card">
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
