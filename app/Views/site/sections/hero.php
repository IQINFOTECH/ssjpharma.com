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
  <div class="container-x relative pt-12 pb-8 lg:pt-16 lg:pb-10">
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
<?php elseif ($style === 'premium'):
    // Premium corporate hero — 55/45 split, trust features, curved navy/red frame,
    // floating badge. All copy is CMS-driven; empty fields hide their element.
    $features = [];
    foreach ((array) ($d['features'] ?? []) as $f) {
        if (is_array($f) && trim((string) ($f['label'] ?? '')) !== '') { $features[] = trim((string) $f['label']); }
    }
    $badge = trim((string) ($d['badge_text'] ?? ''));
    $hl = trim((string) ($d['heading_highlight'] ?? ''));
    $imgAlt = trim((string) ($d['image_alt'] ?? ''));
    // Minimal line icons cycled across features: shield, flask, handshake, globe.
    $icons = [
        '<path d="M12 3l7 3v5c0 5-3.2 8.4-7 10-3.8-1.6-7-5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
        '<path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/>',
        '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
        '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
    ];
    // Badge: colour the bullet separators red (CMS text may contain "•").
    $badgeParts = array_values(array_filter(array_map('trim', explode('•', $badge)), static fn ($p) => $p !== ''));
?>
<section class="relative overflow-hidden bg-white">
  <!-- Subtle scientific backdrop: soft blue wash, molecular lattice, fine dots. -->
  <div class="hero-premium-wash pointer-events-none absolute inset-0" aria-hidden="true"></div>
  <svg class="pointer-events-none absolute left-[38%] top-2 hidden h-56 w-56 text-[#0757B8] opacity-[0.08] lg:block" viewBox="0 0 200 200" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
    <path d="M60 40l40-22 40 22v46l-40 22-40-22zM100 108v46M100 154l-40 22M100 154l40 22M140 86l40 22"/>
    <circle cx="60" cy="40" r="5" fill="currentColor"/><circle cx="140" cy="40" r="5" fill="currentColor"/><circle cx="100" cy="108" r="5" fill="currentColor"/><circle cx="100" cy="154" r="5" fill="currentColor"/><circle cx="180" cy="108" r="5" fill="currentColor"/>
  </svg>
  <div class="hero-premium-dots pointer-events-none absolute bottom-0 left-0 h-32 w-48" aria-hidden="true"></div>

  <div class="container-x pt-10 pb-10 lg:flex lg:items-center lg:pt-14 lg:pb-14 <?= $small ? 'lg:min-h-[420px]' : 'lg:min-h-[560px]' ?>">
    <!-- Content (left ~55%) -->
    <div class="relative z-10 animate-rise lg:w-[52%]">
      <?php if ($small): ?><span class="mb-4 block h-1 w-12 rounded bg-[#E31B23]" aria-hidden="true"></span><?php endif; ?>
      <?php if (!empty($d['eyebrow'])): ?>
      <p class="mb-4 text-sm font-extrabold uppercase tracking-[0.06em] text-[#E31B23]"><?= e($d['eyebrow']) ?></p>
      <?php endif; ?>
      <?php if ($small): ?>
      <h1 class="font-sans text-4xl font-extrabold leading-[1.08] tracking-tight text-[#0b1f45] sm:text-5xl">
        <?= e($d['heading'] ?? '') ?><?php if ($hl !== ''): ?> <span class="text-[#0757B8]"><?= e($hl) ?></span><?php endif; ?>
      </h1>
      <?php else: ?>
      <h1 class="font-sans text-4xl font-extrabold uppercase leading-[1.06] tracking-tight text-[#0b1f45] sm:text-5xl xl:text-[3.5rem]">
        <?= e($d['heading'] ?? '') ?>
        <?php if ($hl !== ''): ?><span class="block text-[#0757B8]"><?= e($hl) ?></span><?php endif; ?>
      </h1>
      <span class="mt-5 block h-1 w-16 rounded bg-[#E31B23]" aria-hidden="true"></span>
      <?php endif; ?>
      <?php if (!empty($d['subheading'])): ?>
      <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600"><?= nl2br(e($d['subheading'])) ?></p>
      <?php endif; ?>

      <?php if ($features !== []): ?>
      <ul class="mt-8 grid grid-cols-2 gap-y-7 sm:grid-cols-4 sm:divide-x sm:divide-slate-200">
        <?php foreach ($features as $i => $label): ?>
        <li class="flex flex-col items-center gap-3 px-3 text-center">
          <?php if ($small): ?>
          <svg class="h-7 w-7 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$i % count($icons)] ?></svg>
          <?php else: ?>
          <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#0757B8]/10">
            <svg class="h-6 w-6 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$i % count($icons)] ?></svg>
          </span>
          <?php endif; ?>
          <span class="text-[11px] font-bold uppercase leading-snug tracking-wide text-[#0b1f45]"><?= e($label) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <div class="mt-9 flex flex-wrap gap-4">
        <?php if (!empty($d['primary_label'])): ?>
        <a href="<?= e($d['primary_url'] ?? '#') ?>" class="btn min-h-[48px] bg-[#E31B23] px-7 font-bold uppercase tracking-wide text-white shadow-[0_12px_24px_-14px_rgba(227,27,35,.8)] hover:-translate-y-px hover:bg-[#c4141b] focus-visible:ring-[#E31B23]">
          <?= e($d['primary_label']) ?>
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <?php endif; ?>
        <?php if (!empty($d['secondary_label'])): ?>
        <a href="<?= e($d['secondary_url'] ?? '#') ?>" class="btn min-h-[48px] border-2 border-[#0757B8] bg-white px-7 font-bold uppercase tracking-wide text-[#0757B8] hover:bg-[#0757B8]/5 focus-visible:ring-[#0757B8]">
          <?= e($d['secondary_label']) ?>
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Visual (right ~45%): full-bleed photo panel with curved red/blue arc band. -->
    <div class="relative mt-10 animate-rise-2 lg:absolute lg:inset-y-0 lg:right-0 lg:mt-0 <?= $small ? 'lg:w-[40%]' : 'lg:w-[46%]' ?>">
      <div class="hero-premium-band hero-premium-curve absolute inset-y-0 -left-4 right-0 lg:-left-5" aria-hidden="true"></div>
      <div class="hero-premium-curve relative overflow-hidden border-[6px] border-white lg:h-full <?= $small ? 'h-56 sm:h-72' : 'h-72 sm:h-96' ?>">
        <img src="<?= e($img !== '' ? $img : asset('hero-lab.svg')) ?>" alt="<?= e($imgAlt !== '' ? $imgAlt : 'Pharmaceutical laboratory') ?>" width="640" height="560" fetchpriority="high" class="h-full w-full object-cover">
        <?php if ($badgeParts !== []): ?>
        <div class="absolute bottom-5 right-5 animate-float">
          <span class="inline-flex min-h-[44px] items-center gap-2.5 rounded-full bg-[#062B63]/95 py-2.5 pl-3.5 pr-6 text-[13px] font-bold uppercase tracking-wider text-white shadow-[0_14px_28px_-14px_rgba(4,20,50,.9)]">
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-white/50">
              <svg class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 5-3.2 8.4-7 10-3.8-1.6-7-5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
            </span>
            <?php foreach ($badgeParts as $bi => $part): ?>
              <?php if ($bi > 0): ?><span class="text-[#E31B23]" aria-hidden="true">&bull;</span><?php endif; ?>
              <span><?= e($part) ?></span>
            <?php endforeach; ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php else: // Standard hero ?>
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-brand-50/40 to-white">
  <div class="pointer-events-none absolute -right-32 -top-24 h-[30rem] w-[30rem] rounded-full bg-teal-500/10 blur-3xl" aria-hidden="true"></div>
  <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-brand-500/10 blur-3xl" aria-hidden="true"></div>
  <div class="container-x relative <?= $small ? 'pt-10 pb-8 lg:pt-12 lg:pb-10' : 'pt-12 pb-8 lg:pt-16 lg:pb-10' ?>">
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
