<?php
/** FAQ accordion (native exclusive <details name>; degrades to multi-open). @var array $section */
$d = $section;
$items = is_array($d['items'] ?? null) ? $d['items'] : [];
if ($items === []) { return; }
?>
<section class="py-12 lg:py-16">
  <div class="container-x">
    <?php if (!empty($d['eyebrow'])): ?><span class="eyebrow mb-3"><?= e($d['eyebrow']) ?></span><?php endif; ?>
    <?php if (!empty($d['heading'])): ?><h2 class="mb-8 text-2xl font-semibold sm:text-3xl lg:text-4xl"><?= e($d['heading']) ?></h2><?php endif; ?>
    <div class="divide-y divide-slate-200">
      <?php foreach ($items as $item): if (!is_array($item)) continue; ?>
        <details name="faq" class="group py-2">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 font-display text-lg font-medium text-brand-900">
            <span><?= e($item['question'] ?? '') ?></span>
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full border border-slate-200 text-brand-500 transition group-open:rotate-45 group-open:border-brand-500 group-open:bg-brand-500 group-open:text-white" aria-hidden="true">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
            </span>
          </summary>
          <?php if (!empty($item['answer'])): ?>
          <p class="pb-5 text-[15px] leading-relaxed text-slate-600"><?= nl2br(e($item['answer'])) ?></p>
          <?php endif; ?>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
