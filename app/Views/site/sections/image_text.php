<?php
/** Image + Text section. @var array $section */
use App\Support\HtmlSanitizer;
$d = $section;
$img = media_url($d['image_id'] ?? null);
$imgRight = ($d['image_side'] ?? 'right') === 'right';
$frame = '<div class="relative"><div class="pointer-events-none absolute -inset-3 rounded-[1.75rem] bg-gradient-to-br from-brand-500/10 to-teal-500/10" aria-hidden="true"></div><img src="' . e($img) . '" alt="" class="relative w-full rounded-3xl object-cover shadow-card"></div>';
?>
<section class="py-16 lg:py-24">
  <div class="container-x grid items-center gap-12 lg:grid-cols-2">
    <?php if ($img !== '' && !$imgRight): ?><?= $frame ?><?php endif; ?>
    <div>
      <?php if (!empty($d['eyebrow'])): ?><span class="eyebrow mb-3"><?= e($d['eyebrow']) ?></span><?php endif; ?>
      <?php if (!empty($d['heading'])): ?><h2 class="mb-4 text-2xl font-semibold sm:text-3xl lg:text-4xl"><?= e($d['heading']) ?></h2><?php endif; ?>
      <?php if (!empty($d['body'])): ?><div class="prose-cms"><?= HtmlSanitizer::clean((string) $d['body']) ?></div><?php endif; ?>
    </div>
    <?php if ($img !== '' && $imgRight): ?><?= $frame ?><?php endif; ?>
  </div>
</section>
