<?php
/** Hero section. @var array $section */
$d = $section;
$style = $d['style'] ?? '';
$align = ($d['align'] ?? 'left') === 'center' ? 'center' : 'left';
$small = ($d['size'] ?? '') === 'small';
$img = media_url($d['image_id'] ?? null);
$hasImg = $img !== '' && $align !== 'center';
?>
<?php if ($style === 'collage'): // Concept F — floating photo collage + services marquee (logo blue/red) ?>
<section class="relative overflow-hidden bg-gradient-to-b from-white to-brand-50/70">
  <div class="pointer-events-none absolute -right-24 -top-24 h-[26rem] w-[26rem] rounded-full bg-[#2b4a9e]/10 blur-3xl" aria-hidden="true"></div>
  <div class="container-x relative py-14 lg:py-20">
    <div class="grid items-center gap-10 lg:grid-cols-2">
      <div class="max-w-2xl">
        <?php if (!empty($d['eyebrow'])): ?><span class="eyebrow mb-4 !text-[#d81f26]"><?= e($d['eyebrow']) ?></span><?php endif; ?>
        <h1 class="text-balance text-4xl font-semibold leading-[1.06] tracking-tight text-[#152a5f] sm:text-5xl lg:text-6xl"><?= e($d['heading'] ?? '') ?></h1>
        <?php if (!empty($d['subheading'])): ?>
        <p class="mt-6 max-w-xl text-lg leading-relaxed text-slate-600"><?= nl2br(e($d['subheading'])) ?></p>
        <?php endif; ?>
        <div class="mt-9 flex flex-wrap gap-3">
          <?php if (!empty($d['primary_label'])): ?>
            <a href="<?= e($d['primary_url'] ?? '#') ?>" class="btn bg-[#2b4a9e] text-white shadow-[0_10px_22px_-12px_rgba(43,74,158,.75)] hover:-translate-y-px hover:brightness-95 focus-visible:ring-[#2b4a9e]"><?= e($d['primary_label']) ?></a>
          <?php endif; ?>
          <?php if (!empty($d['secondary_label'])): ?>
            <a href="<?= e($d['secondary_url'] ?? '#') ?>" class="btn border border-[#c9d4ea] bg-white text-[#22357a] hover:border-[#2b4a9e] focus-visible:ring-[#2b4a9e]"><?= e($d['secondary_label']) ?></a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Floating collage: your Media photo drops into the large card. -->
      <div class="relative h-[340px] sm:h-[400px]" aria-hidden="true">
        <div class="absolute left-0 top-[6%] h-[64%] w-[56%] overflow-hidden rounded-2xl shadow-card animate-float">
          <img src="<?= e($img !== '' ? $img : asset('sample-facility.svg')) ?>" alt="" class="h-full w-full object-cover">
          <?php if ($img === ''): ?><span class="absolute bottom-3 left-3 rounded-md border border-white/25 bg-white/15 px-2 py-1 text-[11px] text-white/90 backdrop-blur">Sample · replace in Media</span><?php endif; ?>
        </div>
        <div class="absolute right-[2%] top-0 h-[52%] w-[46%] overflow-hidden rounded-2xl bg-gradient-to-br from-[#22357a] to-[#2b4a9e] shadow-card animate-float-2">
          <?php if ($img === ''): ?><img src="<?= e(asset('sample-product.svg')) ?>" alt="" class="h-full w-full object-cover"><?php endif; ?>
        </div>
        <div class="absolute bottom-0 right-[8%] h-[46%] w-[50%] rounded-2xl bg-gradient-to-br from-teal-600 to-teal-500 shadow-card animate-float-3"></div>
        <div class="absolute left-[42%] top-[46%] z-10 flex items-center gap-2 rounded-xl bg-white px-3.5 py-2.5 text-sm font-semibold text-brand-900 shadow-card animate-float-2">
          <svg class="h-4 w-4 text-teal-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12l5 5L20 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Contract &amp; custom
        </div>
      </div>
    </div>

    <!-- Services marquee -->
    <div class="mt-12 overflow-hidden [mask-image:linear-gradient(90deg,transparent,#000_8%,#000_92%,transparent)]">
      <div class="flex w-max animate-marquee gap-10 whitespace-nowrap text-sm font-semibold uppercase tracking-wide text-slate-400">
        <?php $svcs = ['Contract Manufacturing', 'Bulk Drug Formulation', 'Custom Production', 'Distributor Supply', 'Export-ready'];
        for ($i = 0; $i < 2; $i++): foreach ($svcs as $svc): ?>
          <span class="inline-flex items-center gap-2.5 before:h-1.5 before:w-1.5 before:rounded-full before:bg-teal-500 before:content-['']"><?= e($svc) ?></span>
        <?php endforeach; endfor; ?>
      </div>
    </div>
  </div>
</section>
<?php else: // Standard hero ?>
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-brand-50/40 to-white">
  <div class="pointer-events-none absolute -right-32 -top-24 h-[30rem] w-[30rem] rounded-full bg-teal-500/10 blur-3xl" aria-hidden="true"></div>
  <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-brand-500/10 blur-3xl" aria-hidden="true"></div>
  <div class="container-x relative <?= $small ? 'py-10 lg:py-14' : 'py-16 lg:py-20' ?>">
    <div class="grid items-center gap-12 <?= $hasImg ? 'lg:grid-cols-2' : '' ?>">
      <div class="<?= $align === 'center' ? 'mx-auto max-w-3xl text-center' : 'max-w-2xl' ?>">
        <?php if (!empty($d['eyebrow'])): ?><span class="eyebrow mb-4"><?= e($d['eyebrow']) ?></span><?php endif; ?>
        <h1 class="text-balance font-semibold leading-[1.06] tracking-tight <?= $small ? 'text-3xl sm:text-4xl' : 'text-4xl sm:text-5xl lg:text-6xl' ?>"><?= e($d['heading'] ?? '') ?></h1>
        <?php if (!empty($d['subheading'])): ?>
        <p class="mt-6 text-lg leading-relaxed text-slate-600 lg:text-xl <?= $align === 'center' ? 'mx-auto max-w-2xl' : 'max-w-xl' ?>"><?= nl2br(e($d['subheading'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($d['primary_label']) || !empty($d['secondary_label'])): ?>
        <div class="mt-9 flex flex-wrap gap-3 <?= $align === 'center' ? 'justify-center' : '' ?>">
          <?php if (!empty($d['primary_label'])): ?>
            <a href="<?= e($d['primary_url'] ?? '#') ?>" class="btn btn-primary"><?= e($d['primary_label']) ?></a>
          <?php endif; ?>
          <?php if (!empty($d['secondary_label'])): ?>
            <a href="<?= e($d['secondary_url'] ?? '#') ?>" class="btn btn-ghost"><?= e($d['secondary_label']) ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($hasImg): ?>
      <div class="relative">
        <div class="pointer-events-none absolute -inset-3 rounded-[1.75rem] bg-gradient-to-br from-brand-500/15 to-teal-500/15" aria-hidden="true"></div>
        <img src="<?= e($img) ?>" alt="" class="relative w-full rounded-3xl object-cover shadow-card">
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>
