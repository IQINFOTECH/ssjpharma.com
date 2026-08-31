<?php
/**
 * Public header — logo, dynamic navigation, Enquire CTA, mobile drawer.
 * Navigation comes entirely from the CMS menu system (never hardcoded).
 * @var App\Services\SettingsService $settings
 * @var array $headerMenu
 * @var array $mobileMenu
 * @var string $whatsappLink
 */
$logo = $settings->mediaUrl('company_logo');
$company = $settings->companyName();

$renderTop = static function (array $items): string {
    static $n = 0;
    $html = '';
    foreach ($items as $item) {
        $target = $item['open_new_tab'] ? ' target="_blank" rel="noopener"' : '';
        if (!empty($item['children'])) {
            $panelId = 'nav-dd-' . (++$n);
            $html .= '<div class="relative group js-dropdown">';
            // Keyboard: focus-within reveals the panel; JS keeps aria-expanded honest.
            $html .= '<a href="' . e($item['url']) . '" class="js-dropdown-trigger nav-link inline-flex items-center gap-1" aria-haspopup="true" aria-expanded="false" aria-controls="' . $panelId . '"' . $target . '>'
                   . e($item['label'])
                   . '<svg class="h-3.5 w-3.5 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.5 7.5 10 12l4.5-4.5" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>'
                   . '</a>';
            $html .= '<div id="' . $panelId . '" class="invisible absolute left-0 top-full z-40 mt-2 min-w-[220px] rounded-xl border border-slate-100 bg-white p-2 opacity-0 shadow-card transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">';
            foreach ($item['children'] as $child) {
                $ct = $child['open_new_tab'] ? ' target="_blank" rel="noopener"' : '';
                $html .= '<a href="' . e($child['url']) . '" class="block rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-brand-700"' . $ct . '>' . e($child['label']) . '</a>';
            }
            $html .= '</div></div>';
        } else {
            $html .= '<a href="' . e($item['url']) . '" class="nav-link"' . $target . '>' . e($item['label']) . '</a>';
        }
    }
    return $html;
};
?>
<header class="sticky top-0 z-40 border-b border-slate-100 bg-white/90 backdrop-blur">
  <div class="container-x flex h-16 items-center justify-between gap-4 lg:h-20">
    <a href="/" class="flex items-center gap-2.5" aria-label="<?= e($company) ?> home">
      <?php if ($logo !== ''): ?>
        <img src="<?= e($logo) ?>" alt="<?= e($company) ?>" class="h-9 w-auto lg:h-10">
      <?php else: ?>
        <img src="<?= e(asset('brand-mark.svg')) ?>" alt="" width="40" height="40" class="h-9 w-9 lg:h-10 lg:w-10">
        <span class="font-display text-lg font-semibold text-brand-900"><?= e($company) ?></span>
      <?php endif; ?>
    </a>

    <nav class="hidden items-center gap-7 lg:flex" aria-label="Primary">
      <?= $renderTop($headerMenu) ?>
    </nav>

    <div class="hidden items-center gap-3 lg:flex">
      <?php if ($whatsappLink !== ''): ?>
      <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" data-wa-context="header" class="btn btn-whatsapp">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-.9-.3-1.6-.6-2.8-1.2-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.8 0-1.3.7-2 .9-2.2.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.1.1.3 0 .5l-.4.5-.3.3c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.7-.9c.2-.3.4-.2.6-.1l1.9.9c.2.1.4.2.5.3.1.2.1.6-.1 1.1Z"/></svg>
        WhatsApp
      </a>
      <?php endif; ?>
      <a href="/contact-us" class="btn btn-primary">Enquire Now</a>
    </div>

    <button type="button" class="js-nav-toggle inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 text-brand-900 lg:hidden" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-drawer">
      <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
  </div>
</header>

<!-- Mobile drawer -->
<div id="mobile-drawer" class="fixed inset-0 z-50 hidden lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
  <div class="js-nav-close absolute inset-0 bg-brand-900/40"></div>
  <div class="absolute right-0 top-0 flex h-full w-[86%] max-w-sm flex-col bg-white shadow-xl">
    <div class="flex h-16 items-center justify-between border-b border-slate-100 px-5">
      <span class="font-display font-semibold text-brand-900"><?= e($company) ?></span>
      <button type="button" class="js-nav-close inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200" aria-label="Close menu">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Mobile">
      <?php foreach (($mobileMenu ?: $headerMenu) as $item): ?>
        <a href="<?= e($item['url']) ?>" class="block rounded-lg px-3 py-3 text-base font-medium text-brand-900 hover:bg-slate-50"<?= $item['open_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($item['label']) ?></a>
        <?php foreach (($item['children'] ?? []) as $child): ?>
          <a href="<?= e($child['url']) ?>" class="block rounded-lg px-6 py-2 text-sm text-slate-600 hover:bg-slate-50"><?= e($child['label']) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="border-t border-slate-100 p-4 space-y-2">
      <a href="/contact-us" class="btn btn-primary w-full">Enquire Now</a>
      <?php if ($whatsappLink !== ''): ?>
      <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp w-full">WhatsApp</a>
      <?php endif; ?>
    </div>
  </div>
</div>
