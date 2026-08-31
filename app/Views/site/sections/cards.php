<?php
/** Cards section. @var array $section */
$d = $section;
$cards = is_array($d['cards'] ?? null) ? $d['cards'] : [];
if ($cards === [] && empty($d['heading'])) { return; }
?>
<section class="py-14 lg:py-20">
  <div class="container-x">
    <?php if (!empty($d['heading']) || !empty($d['subheading'])): ?>
    <div class="mb-10 max-w-2xl">
      <?php if (!empty($d['heading'])): ?><h2 class="text-2xl font-semibold sm:text-3xl"><?= e($d['heading']) ?></h2><?php endif; ?>
      <?php if (!empty($d['subheading'])): ?><p class="mt-3 text-slate-600"><?= e($d['subheading']) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($cards as $card): if (!is_array($card)) continue; ?>
        <?php $url = trim((string) ($card['url'] ?? '')); $tag = $url !== '' ? 'a' : 'div'; ?>
        <<?= $tag ?> <?= $url !== '' ? 'href="' . e($url) . '"' : '' ?> class="card block transition hover:shadow-lg">
          <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path d="M5 12l5 5L20 7"/></svg>
          </div>
          <h3 class="text-lg font-semibold text-brand-900"><?= e($card['title'] ?? '') ?></h3>
          <?php if (!empty($card['text'])): ?><p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($card['text']) ?></p><?php endif; ?>
        </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
