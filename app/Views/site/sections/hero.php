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
    // Minimal line icons cycled across features: shield, grid, people, map-pin.
    $icons = [
        '<path d="M12 3l7 3v5c0 5-3.2 8.4-7 10-3.8-1.6-7-5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
        '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
        '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3 2.8-4.6 5.5-4.6s4.9 1.6 5.5 4.6M15.5 5.4a3.2 3.2 0 0 1 0 5.2M17.5 14.6c1.6.7 2.7 2 3 4.4"/>',
        '<path d="M12 21s-6.5-5.4-6.5-10.3A6.5 6.5 0 0 1 12 4a6.5 6.5 0 0 1 6.5 6.7C18.5 15.6 12 21 12 21z"/><circle cx="12" cy="10.6" r="2.3"/>',
    ];
?>
<section class="relative overflow-hidden bg-white">
  <!-- Subtle scientific backdrop: soft blue wash + faint molecular lattice. -->
  <div class="hero-premium-wash pointer-events-none absolute inset-0" aria-hidden="true"></div>
  <svg class="pointer-events-none absolute -right-10 top-8 h-64 w-64 text-[#0757B8] opacity-[0.06]" viewBox="0 0 200 200" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
    <path d="M60 40l40-22 40 22v46l-40 22-40-22zM100 108v46M100 154l-40 22M100 154l40 22M140 86l40 22"/>
    <circle cx="60" cy="40" r="5" fill="currentColor"/><circle cx="140" cy="40" r="5" fill="currentColor"/><circle cx="100" cy="108" r="5" fill="currentColor"/><circle cx="100" cy="154" r="5" fill="currentColor"/><circle cx="180" cy="108" r="5" fill="currentColor"/>
  </svg>

  <div class="container-x relative pt-10 pb-10 lg:pt-14 lg:pb-14">
    <div class="grid items-center gap-12 lg:grid-cols-[11fr_9fr] lg:gap-14">
      <!-- Content -->
      <div class="animate-rise">
        <?php if (!empty($d['eyebrow'])): ?>
        <p class="mb-4 flex items-center gap-2.5 text-xs font-bold uppercase tracking-[0.18em] text-[#062B63]"><span class="h-0.5 w-7 shrink-0 rounded bg-[#E31B23]" aria-hidden="true"></span><?= e($d['eyebrow']) ?></p>
        <?php endif; ?>
        <h1 class="font-sans text-4xl font-extrabold uppercase leading-[1.05] tracking-tight text-[#062B63] sm:text-5xl xl:text-[3.4rem]">
          <?= e($d['heading'] ?? '') ?>
          <?php if ($hl !== ''): ?><span class="block text-[#0757B8]"><?= e($hl) ?></span><?php endif; ?>
        </h1>
        <?php if (!empty($d['subheading'])): ?>
        <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600"><?= nl2br(e($d['subheading'])) ?></p>
        <?php endif; ?>

        <div class="mt-8 flex flex-wrap gap-3">
          <?php if (!empty($d['primary_label'])): ?>
          <a href="<?= e($d['primary_url'] ?? '#') ?>" class="btn min-h-[48px] bg-[#E31B23] px-7 text-white shadow-[0_12px_24px_-14px_rgba(227,27,35,.8)] hover:-translate-y-px hover:bg-[#c4141b] focus-visible:ring-[#E31B23]">
            <?= e($d['primary_label']) ?>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($d['secondary_label'])): ?>
          <a href="<?= e($d['secondary_url'] ?? '#') ?>" class="btn min-h-[48px] border-2 border-[#0757B8] bg-white px-7 text-[#062B63] hover:bg-[#0757B8]/5 focus-visible:ring-[#0757B8]">
            <?= e($d['secondary_label']) ?>
            <svg class="h-4 w-4 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
          <?php endif; ?>
        </div>

        <?php if ($features !== []): ?>
        <ul class="mt-9 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-slate-200 pt-6 sm:flex sm:flex-wrap sm:gap-x-8">
          <?php foreach ($features as $i => $label): ?>
          <li class="flex items-center gap-2.5">
            <svg class="h-5 w-5 shrink-0 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$i % count($icons)] ?></svg>
            <span class="text-sm font-medium text-[#062B63]"><?= e($label) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <!-- Visual: curved navy/red frame + lab imagery + floating badge -->
      <div class="relative animate-rise-2 lg:justify-self-end lg:w-full">
        <div class="pointer-events-none absolute inset-0 translate-x-4 translate-y-5 rounded-tl-[5.5rem] rounded-br-[5.5rem] rounded-tr-3xl rounded-bl-3xl bg-[#062B63]" aria-hidden="true"></div>
        <svg class="pointer-events-none absolute -left-5 -bottom-6 h-40 w-40 text-[#E31B23]" viewBox="0 0 160 160" fill="none" aria-hidden="true">
          <path d="M8 152C8 72 72 8 152 8" stroke="currentColor" stroke-width="10" stroke-linecap="round" opacity=".9"/>
        </svg>
        <div class="relative overflow-hidden rounded-tl-[5.5rem] rounded-br-[5.5rem] rounded-tr-3xl rounded-bl-3xl border-[6px] border-white shadow-[0_30px_60px_-30px_rgba(6,43,99,.55)]">
          <img src="<?= e($img !== '' ? $img : asset('hero-lab.svg')) ?>" alt="<?= e($imgAlt !== '' ? $imgAlt : 'Pharmaceutical laboratory') ?>" width="640" height="560" fetchpriority="high" class="h-auto w-full object-cover">
        </div>
        <?php if ($badge !== ''): ?>
        <div class="absolute -bottom-4 left-8 animate-float">
          <span class="inline-flex min-h-[44px] items-center gap-2 rounded-full bg-[#062B63] py-2.5 pl-4 pr-5 text-xs font-semibold uppercase tracking-wider text-white shadow-[0_14px_28px_-14px_rgba(6,43,99,.8)]">
            <svg class="h-4 w-4 text-[#7cc0ff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 5-3.2 8.4-7 10-3.8-1.6-7-5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
            <?= e($badge) ?>
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
