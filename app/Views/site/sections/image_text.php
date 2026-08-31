<?php
/** Image + Text section. @var array $section */
use App\Support\HtmlSanitizer;
$d = $section;
$img = media_url($d['image_id'] ?? null);
$imgRight = ($d['image_side'] ?? 'right') === 'right';
?>
<section class="py-14 lg:py-20">
  <div class="container-x grid items-center gap-10 lg:grid-cols-2">
    <?php if ($img !== '' && !$imgRight): ?>
      <div><img src="<?= e($img) ?>" alt="" class="w-full rounded-2xl object-cover shadow-card"></div>
    <?php endif; ?>
    <div>
      <?php if (!empty($d['heading'])): ?><h2 class="mb-4 text-2xl font-semibold sm:text-3xl"><?= e($d['heading']) ?></h2><?php endif; ?>
      <?php if (!empty($d['body'])): ?><div class="prose-cms"><?= HtmlSanitizer::clean((string) $d['body']) ?></div><?php endif; ?>
    </div>
    <?php if ($img !== '' && $imgRight): ?>
      <div><img src="<?= e($img) ?>" alt="" class="w-full rounded-2xl object-cover shadow-card"></div>
    <?php endif; ?>
  </div>
</section>
