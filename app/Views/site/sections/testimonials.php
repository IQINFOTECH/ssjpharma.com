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
        <figure class="card flex flex-col">
          <svg class="mb-3 h-8 w-8 text-teal-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.5 7C6.5 7 5 9.2 5 12.2V17h5v-5H7.6c0-1.7.8-2.6 2.4-2.7L9.5 7zm9 0c-3 0-4.5 2.2-4.5 5.2V17h5v-5h-2.4c0-1.7.8-2.6 2.4-2.7L18.5 7z"/></svg>
          <blockquote class="text-lg leading-relaxed text-brand-900"><?= e($item['quote'] ?? '') ?></blockquote>
          <figcaption class="mt-5 text-sm">
            <span class="font-semibold text-brand-900"><?= e($item['author'] ?? '') ?></span>
            <?php if (!empty($item['role'])): ?><span class="text-slate-500"> — <?= e($item['role']) ?></span><?php endif; ?>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
