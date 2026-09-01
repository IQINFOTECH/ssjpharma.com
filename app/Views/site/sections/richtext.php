<?php
/** Rich text section. Body is sanitised on save (HtmlSanitizer). @var array $section */
use App\Support\HtmlSanitizer;
$d = $section;
?>
<section class="section-pad">
  <div class="container-x">
    <?php if (!empty($d['heading'])): ?>
      <h2 class="mb-6 text-2xl font-semibold sm:text-3xl"><?= e($d['heading']) ?></h2>
    <?php endif; ?>
    <?php if (!empty($d['body'])): ?>
      <div class="prose-cms"><?= HtmlSanitizer::clean((string) $d['body']) ?></div>
    <?php endif; ?>
  </div>
</section>
