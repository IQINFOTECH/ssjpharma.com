<?php
/**
 * CMS-driven footer. Company details + social come from settings; link columns
 * from the footer menu. Nothing hardcoded.
 * @var App\Services\SettingsService $settings
 * @var array $footerMenu
 * @var int $currentYear
 */
$company = $settings->companyName();
$address = $settings->fullAddress();
$email   = $settings->get('company_email');
$phone   = $settings->get('company_phone');
$waLink  = $whatsappLink ?? '';
$socials = $settings->socialLinks();
?>
<footer class="mt-0 bg-brand-900 text-slate-300">
  <div class="container-x grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
    <!-- Company column -->
    <div>
      <div class="mb-4 flex items-center gap-2.5">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white p-1"><img src="<?= e(asset('brand-mark.svg')) ?>" alt="" width="32" height="32" class="h-full w-full"></span>
        <span class="font-display text-lg font-semibold text-white"><?= e($company) ?></span>
      </div>
      <?php if ($settings->get('company_description') !== ''): ?>
      <p class="max-w-xs text-sm leading-relaxed text-slate-400"><?= e($settings->get('company_description')) ?></p>
      <?php endif; ?>
    </div>

    <!-- Menu columns from CMS -->
    <?php foreach ($footerMenu as $column): ?>
      <div>
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white"><?= e($column['label']) ?></h3>
        <ul class="space-y-2.5 text-sm">
          <?php foreach (($column['children'] ?: []) as $child): ?>
            <li><a href="<?= e($child['url']) ?>" class="text-slate-400 hover:text-white transition"<?= $child['open_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($child['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>

    <!-- Contact column from settings -->
    <div>
      <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">Contact</h3>
      <ul class="space-y-2.5 text-sm text-slate-400">
        <?php if ($address !== ''): ?><li><?= nl2br(e($address)) ?></li><?php endif; ?>
        <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>" class="hover:text-white"><?= e($email) ?></a></li><?php endif; ?>
        <?php if ($phone !== ''): ?><li><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="hover:text-white"><?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($waLink !== ''): ?><li><a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="hover:text-white">WhatsApp</a></li><?php endif; ?>
      </ul>
      <?php if ($socials !== []): ?>
      <div class="mt-4 flex gap-3">
        <?php foreach ($socials as $label => $url): ?>
          <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="text-slate-400 hover:text-white text-sm" aria-label="<?= e($label) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="border-t border-white/10">
    <div class="container-x flex flex-col items-center justify-between gap-2 py-6 text-xs text-slate-400 sm:flex-row">
      <p>&copy; <?= e($currentYear) ?> <?= e($company) ?>. All rights reserved.</p>
      <p>Enquiries welcome via the contact form.</p>
    </div>
  </div>
</footer>
