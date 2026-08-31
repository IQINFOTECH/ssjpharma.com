<?php
/**
 * Public site master layout. All dynamic values escaped via e().
 * Receives: seo, settings, headerMenu, mobileMenu, footerMenu, whatsappLink,
 *           gaId, jsonLd[], breadcrumbs, currentYear.
 * @var App\Core\View $this
 * @var array $seo
 * @var App\Services\SettingsService $settings
 */
$favicon = $settings->mediaUrl('company_favicon');
$ogImage = $seo['og_image'] ?? '';
?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seo['title'] ?? $settings->websiteName()) ?></title>
    <?php if (!empty($seo['description'])): ?>
    <meta name="description" content="<?= e($seo['description']) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= e($seo['robots'] ?? 'index,follow') ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php $gsc = $settings->get('analytics_gsc_verification'); if ($gsc !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $gsc)): ?>
    <meta name="google-site-verification" content="<?= e($gsc) ?>">
    <?php endif; ?>
    <?php $bingv = $settings->get('analytics_bing_verification'); if ($bingv !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $bingv)): ?>
    <meta name="msvalidate.01" content="<?= e($bingv) ?>">
    <?php endif; ?>
    <?php if (!empty($seo['canonical'])): ?>
    <link rel="canonical" href="<?= e($seo['canonical']) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($seo['site_name'] ?? $settings->websiteName()) ?>">
    <meta property="og:title" content="<?= e($seo['og_title'] ?? $seo['title'] ?? '') ?>">
    <?php if (!empty($seo['og_description'])): ?>
    <meta property="og:description" content="<?= e($seo['og_description']) ?>">
    <?php endif; ?>
    <?php if (!empty($seo['url'])): ?>
    <meta property="og:url" content="<?= e($seo['url']) ?>">
    <?php endif; ?>
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php else: ?>
    <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= e($seo['title'] ?? '') ?>">
    <?php if (!empty($seo['description'])): ?>
    <meta name="twitter:description" content="<?= e($seo['description']) ?>">
    <?php endif; ?>

    <?php if ($favicon !== ''): ?>
    <link rel="icon" href="<?= e($favicon) ?>">
    <?php else: ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('brand-mark.svg')) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,480&family=Inter:wght@400;450;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">

    <?php foreach (($jsonLd ?? []) as $block): ?>
    <script type="application/ld+json"><?= $block /* pre-encoded, safe JSON */ ?></script>
    <?php endforeach; ?>
</head>
<?php $ga = ($gaId ?? ''); $gaValid = $ga !== '' && preg_match('/^[A-Za-z0-9\-]+$/', $ga); ?>
<body class="min-h-full flex flex-col"<?= $gaValid ? ' data-ga-id="' . e($ga) . '"' : '' ?>>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded focus:bg-brand-900 focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

    <?php include __DIR__ . '/partials/header.php'; ?>

    <main id="main" class="flex-1">
        <?= $this->section('content') ?>
    </main>

    <?php include __DIR__ . '/partials/footer.php'; ?>
    <?php include __DIR__ . '/partials/whatsapp.php'; ?>

    <!-- All JS (incl. GA4 bootstrap + conversion events) lives in app.js so the
         strict CSP (script-src 'self') needs no inline-script exceptions. -->
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
