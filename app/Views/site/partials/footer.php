<?php
/**
 * CMS-driven footer — light premium design (reference). Company details, tagline,
 * description and socials come from Settings; link columns from the footer menu;
 * legal links render only when those pages exist. Nothing hardcoded.
 * @var App\Services\SettingsService $settings
 * @var array $footerMenu
 * @var array $legalLinks
 * @var int $currentYear
 */
$company = $settings->companyName();
$logo    = $settings->mediaUrl('company_logo');
$tagline = $settings->get('company_tagline');
$desc    = $settings->get('company_description');
$address = $settings->fullAddress();
$email   = $settings->get('company_email');
$phone   = $settings->get('company_phone');
$socials = $settings->socialLinks();
$legal   = $legalLinks ?? [];
$menuCols = array_values(array_filter($footerMenu, static fn ($c) => !empty($c['children'])));

// Minimal brand glyphs for the social circles, matched by platform keyword.
$socialIcon = static function (string $label): string {
    $k = strtolower($label);
    if (str_contains($k, 'linkedin'))  { return '<path d="M6.5 8.8v8.7M6.5 5.6v.1M11 17.5v-5.1c0-2 1.4-3.2 3.1-3.2s2.9 1.2 2.9 3.4v4.9" stroke-linecap="round" stroke-linejoin="round"/>'; }
    if (str_contains($k, 'facebook'))  { return '<path d="M13.5 20v-7h2.4l.4-3h-2.8V8.2c0-.9.3-1.5 1.6-1.5h1.3V4.1c-.6-.1-1.4-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8V10H8v3h2.5v7" stroke-linecap="round" stroke-linejoin="round"/>'; }
    if (str_contains($k, 'instagram')) { return '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><circle cx="16.8" cy="7.2" r="1" fill="currentColor" stroke="none"/>'; }
    if (str_contains($k, 'youtube'))   { return '<rect x="3" y="6.5" width="18" height="11" rx="3"/><path d="M10.5 9.8l4 2.2-4 2.2z" fill="currentColor" stroke="none"/>'; }
    return '<circle cx="12" cy="12" r="8"/>';
};
?>
<footer class="relative mt-0 overflow-hidden border-t border-slate-200 bg-[#f4f8fc]">
  <!-- faint molecular pattern, decorative -->
  <svg class="pointer-events-none absolute -left-8 bottom-6 h-48 w-48 text-[#0757B8] opacity-[0.05]" viewBox="0 0 200 200" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
    <path d="M60 40l40-22 40 22v46l-40 22-40-22zM100 108v46M100 154l-40 22M100 154l40 22"/>
    <circle cx="60" cy="40" r="5" fill="currentColor"/><circle cx="140" cy="40" r="5" fill="currentColor"/><circle cx="100" cy="108" r="5" fill="currentColor"/>
  </svg>

  <div class="container-x relative grid gap-10 py-12 lg:grid-cols-[1.4fr_2.1fr_1.2fr] lg:gap-8 lg:py-14">
    <!-- Brand column -->
    <div>
      <div class="flex items-center gap-3">
        <?php if ($logo !== ''): ?>
          <img src="<?= e($logo) ?>" alt="<?= e($company) ?>" class="h-12 w-auto">
        <?php else: ?>
          <img src="<?= e(asset('brand-mark.svg')) ?>" alt="" width="44" height="44" class="h-11 w-11">
          <span class="font-sans text-lg font-extrabold leading-tight text-[#0b1f45]"><?= e($company) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($tagline !== ''): ?>
      <p class="mt-2.5 text-sm font-semibold text-[#0b1f45]">
        <?php $tp = array_values(array_filter(array_map('trim', explode('•', $tagline)), static fn ($p) => $p !== ''));
        foreach ($tp as $ti => $part): ?><?= $ti > 0 ? ' <span class="text-[#E31B23]" aria-hidden="true">&bull;</span> ' : '' ?><?= e($part) ?><?php endforeach; ?>
      </p>
      <?php endif; ?>
      <?php if ($desc !== ''): ?>
      <p class="mt-4 max-w-sm text-sm leading-relaxed text-slate-600"><?= e($desc) ?></p>
      <?php endif; ?>
      <?php if ($socials !== []): ?>
      <div class="mt-5 flex gap-3">
        <?php foreach ($socials as $label => $url): ?>
          <a href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"
             class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-[#0b1f45] shadow-sm transition hover:border-[#0757B8] hover:text-[#0757B8]">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><?= $socialIcon((string) $label) ?></svg>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Menu columns from CMS (columns without any links are skipped) -->
    <div class="grid gap-10 sm:grid-cols-2 lg:flex lg:justify-around lg:gap-8 lg:border-l lg:border-slate-200 lg:pl-10">
      <?php foreach ($menuCols as $column): ?>
      <div>
        <h3 class="mb-4 font-sans text-base font-extrabold text-[#0b1f45]"><?= e($column['label']) ?></h3>
        <ul class="space-y-2.5 text-sm">
          <?php foreach (($column['children'] ?: []) as $child): ?>
          <li>
            <a href="<?= e($child['url']) ?>" class="group inline-flex items-center gap-1.5 text-slate-600 transition hover:text-[#E31B23]"<?= $child['open_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>>
              <svg class="h-3 w-3 text-[#E31B23]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <?= e($child['label']) ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Contact column from settings -->
    <div class="lg:border-l lg:border-slate-200 lg:pl-10">
      <h3 class="mb-4 font-sans text-base font-extrabold text-[#0b1f45]">Contact Us</h3>
      <ul class="space-y-4 text-sm">
        <?php if ($address !== ''): ?>
        <li class="flex items-start gap-3">
          <svg class="mt-0.5 h-[18px] w-[18px] shrink-0 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6.5-5.4-6.5-10.3A6.5 6.5 0 0 1 12 4a6.5 6.5 0 0 1 6.5 6.7C18.5 15.6 12 21 12 21z"/><circle cx="12" cy="10.6" r="2.3"/></svg>
          <span class="leading-relaxed text-slate-600"><?= nl2br(e($address)) ?></span>
        </li>
        <?php endif; ?>
        <?php if ($phone !== ''): ?>
        <li class="flex items-center gap-3">
          <svg class="h-[18px] w-[18px] shrink-0 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.13.97.36 1.9.7 2.8a2 2 0 0 1-.45 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.34 1.84.57 2.8.7a2 2 0 0 1 1.7 2.05z"/></svg>
          <a href="tel:<?= e(preg_replace('/[^+\d]/', '', $phone)) ?>" class="font-semibold text-[#0b1f45] hover:text-[#0757B8]"><?= e($phone) ?></a>
        </li>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
        <li class="flex items-center gap-3">
          <svg class="h-[18px] w-[18px] shrink-0 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
          <a href="mailto:<?= e($email) ?>" class="font-semibold text-[#0b1f45] hover:text-[#0757B8]"><?= e($email) ?></a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="relative border-t border-slate-200">
    <div class="container-x flex flex-col items-center justify-between gap-2 py-5 text-xs text-slate-500 sm:flex-row">
      <p>&copy; <?= e($currentYear) ?> <?= e($company) ?>. All Rights Reserved.</p>
      <?php if ($legal !== []): ?>
      <p class="flex items-center gap-3">
        <?php $li = 0; foreach ($legal as $url => $label): ?>
          <?php if ($li++ > 0): ?><span class="text-slate-300" aria-hidden="true">|</span><?php endif; ?>
          <a href="<?= e($url) ?>" class="hover:text-[#0757B8]"><?= e($label) ?></a>
        <?php endforeach; ?>
      </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Brand strip -->
  <div class="h-1.5 w-full bg-gradient-to-r from-[#E31B23] via-[#E31B23] to-[#062B63]" aria-hidden="true"></div>
</footer>
