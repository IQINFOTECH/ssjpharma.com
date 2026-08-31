<?php
/**
 * Product card. Expects $p (a public product row). Shows only useful fields.
 * @var array $p
 */
$url = '/products/' . $p['slug'];
?>
<article class="card-lift group flex flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
  <a href="<?= e($url) ?>" class="flex h-44 items-center justify-center bg-slate-50">
    <?php if (!empty($p['image_url'])): ?>
      <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy" class="h-full w-full object-contain p-4">
    <?php else: ?>
      <span class="text-4xl text-slate-300" aria-hidden="true">&#9877;</span>
    <?php endif; ?>
  </a>
  <div class="flex flex-1 flex-col p-5">
    <?php if (!empty($p['is_demo'])): ?><span class="eyebrow mb-1 text-amber-600">Demo — replace before production</span><?php endif; ?>
    <h3 class="text-base font-semibold text-brand-900"><a href="<?= e($url) ?>" class="hover:text-brand-600"><?= e($p['name']) ?></a></h3>
    <?php if (!empty($p['generic_name'])): ?><p class="mt-0.5 text-sm text-slate-500"><?= e($p['generic_name']) ?></p><?php endif; ?>
    <?php if (!empty($p['dosage_name'])): ?><span class="mt-2 inline-block w-fit rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700"><?= e($p['dosage_name']) ?></span><?php endif; ?>
    <?php if (!empty($p['short_description'])): ?><p class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-600"><?= e($p['short_description']) ?></p><?php endif; ?>
    <div class="mt-auto flex items-center gap-2 pt-4">
      <a href="<?= e($url) ?>" class="btn btn-ghost px-3.5 py-1.5 text-xs">View</a>
      <a href="<?= e($url) ?>#enquire" class="btn btn-primary px-3.5 py-1.5 text-xs">Enquire</a>
    </div>
  </div>
</article>
<?php $section = null; /* avoid leaking */ ?>
