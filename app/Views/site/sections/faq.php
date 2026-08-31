<?php
/** FAQ accordion. @var array $section */
$d = $section;
$items = is_array($d['items'] ?? null) ? $d['items'] : [];
if ($items === []) { return; }
?>
<section class="py-14 lg:py-20">
  <div class="container-x max-w-3xl">
    <?php if (!empty($d['heading'])): ?><h2 class="mb-8 text-2xl font-semibold sm:text-3xl"><?= e($d['heading']) ?></h2><?php endif; ?>
    <div class="divide-y divide-slate-200 rounded-2xl border border-slate-200">
      <?php foreach ($items as $item): if (!is_array($item)) continue; ?>
        <details class="group px-5 py-4">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-brand-900">
            <span><?= e($item['question'] ?? '') ?></span>
            <svg class="h-5 w-5 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path d="M6 9l6 6 6-6"/></svg>
          </summary>
          <?php if (!empty($item['answer'])): ?>
          <p class="mt-3 text-sm leading-relaxed text-slate-600"><?= nl2br(e($item['answer'])) ?></p>
          <?php endif; ?>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
