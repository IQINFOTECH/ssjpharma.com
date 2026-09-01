<?php
/** Cards section — editorial numbered columns (Concept 1). @var array $section */
$d = $section;
$cards = is_array($d['cards'] ?? null) ? $d['cards'] : [];
if ($cards === [] && empty($d['heading'])) { return; }
?>
<section class="section-pad">
  <div class="container-x">
    <?php if (!empty($d['heading']) || !empty($d['subheading'])): ?>
    <div class="mb-8 max-w-2xl">
      <?php if (!empty($d['heading'])): ?><h2 class="text-2xl font-semibold sm:text-3xl"><?= e($d['heading']) ?></h2><?php endif; ?>
      <?php if (!empty($d['subheading'])): ?><p class="mt-3 text-slate-600"><?= e($d['subheading']) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="grid border-t border-slate-200 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($cards as $i => $card): if (!is_array($card)) continue; ?>
        <?php $url = trim((string) ($card['url'] ?? '')); $tag = $url !== '' ? 'a' : 'div'; ?>
        <<?= $tag ?> <?= $url !== '' ? 'href="' . e($url) . '"' : '' ?> class="group block border-b border-slate-200 px-1 py-6 sm:px-5 sm:[&:not(:first-child)]:border-l">
          <span class="font-mono text-xs font-semibold tracking-widest text-teal-600"><?= str_pad((string) ((int) $i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h3 class="mt-2 font-display text-lg font-semibold text-brand-900 <?= $url !== '' ? 'group-hover:text-brand-600' : '' ?>"><?= e($card['title'] ?? '') ?></h3>
          <?php if (!empty($card['text'])): ?><p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($card['text']) ?></p><?php endif; ?>
          <?php if ($url !== ''): ?><span class="mt-3 inline-block text-sm font-medium text-brand-600">Learn more →</span><?php endif; ?>
        </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
