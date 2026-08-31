<?php
/** Testimonials (placeholder — admin-entered only, nothing fabricated). @var array $section */
$d = $section;
$items = is_array($d['items'] ?? null) ? $d['items'] : [];
if ($items === []) { return; }
?>
<section class="bg-brand-50/50 py-14 lg:py-20">
  <div class="container-x">
    <?php if (!empty($d['heading'])): ?><h2 class="mb-10 text-2xl font-semibold sm:text-3xl"><?= e($d['heading']) ?></h2><?php endif; ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($items as $item): if (!is_array($item)) continue; ?>
        <figure class="card">
          <blockquote class="text-slate-700">&ldquo;<?= e($item['quote'] ?? '') ?>&rdquo;</blockquote>
          <figcaption class="mt-4 text-sm">
            <span class="font-semibold text-brand-900"><?= e($item['author'] ?? '') ?></span>
            <?php if (!empty($item['role'])): ?><span class="text-slate-500"> — <?= e($item['role']) ?></span><?php endif; ?>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
