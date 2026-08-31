<?php
/**
 * Admin layout — sidebar + topbar + content. Nav items are gated by permission
 * via can() (server-side authorization is the real gate; this only hides links).
 * @var App\Core\View $this
 * @var string $active
 * @var array $currentUser
 * @var array|null $flash
 */
$icon = static function (string $d): string {
    return '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path d="' . $d . '"/></svg>';
};
$link = static function (string $key, string $active, string $url, string $label, string $svg): string {
    $cls = 'admin-nav-link' . ($active === $key ? ' active' : '');
    return '<a href="' . e($url) . '" class="' . $cls . '">' . $svg . e($label) . '</a>';
};

// [permission, key, url, label, icon-path]
$content = [
    ['pages.view',     'pages',     '/admin/pages',     'Pages',     'M7 3h7l5 5v13H7V3Zm7 0v5h5'],
    ['menus.view',     'menus',     '/admin/menus',     'Menus',     'M4 6h16M4 12h16M4 18h10'],
    ['media.view',     'media',     '/admin/media',     'Media',     'M4 5h16v14H4V5Zm3 9 3-3 4 4 3-2 4 3'],
    ['redirects.view', 'redirects', '/admin/redirects', 'Redirects', 'M4 12h12m0 0-4-4m4 4-4 4M20 5v14'],
];
$catalog = [
    ['products.view', 'products',           '/admin/products',           'Products',         'M20 7 12 3 4 7v10l8 4 8-4V7ZM4 7l8 4 8-4M12 11v10'],
    ['products.view', 'product_categories', '/admin/product-categories', 'Categories',       'M4 6h6v6H4V6Zm10 0h6v6h-6V6ZM4 16h6v4H4v-4Zm10 0h6v4h-6v-4Z'],
    ['products.view', 'therapeutic_areas',  '/admin/therapeutic-areas',  'Therapeutic Areas','M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10Z'],
    ['products.view', 'dosage_forms',       '/admin/dosage-forms',       'Dosage Forms',     'M10 3h4v4h-4V3Zm-3 7h10l-1 10H8L7 10Z'],
];
$admin = [
    ['users.view', 'users', '/admin/users',      'Users',     'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-1a6 6 0 0 1 12 0v1'],
    ['roles.view', 'roles', '/admin/roles',      'Roles',     'M12 2 4 6v6c0 5 3.4 8 8 10 4.6-2 8-5 8-10V6l-8-4Z'],
    ['audit.view', 'audit', '/admin/audit-logs', 'Audit Log', 'M8 6h9M8 12h9M8 18h6M4 6h.01M4 12h.01M4 18h.01'],
];
$title = ($title ?? 'Admin') . ' · ' . e($siteName);
?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-full bg-slate-50 text-ink">
<div class="flex min-h-screen">
  <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-white shadow-lg transition-transform lg:static lg:translate-x-0 lg:shadow-none lg:border-r lg:border-slate-200">
    <div class="flex h-16 items-center gap-2.5 border-b border-slate-100 px-5">
      <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-900 text-sm font-bold text-white">S</span>
      <span class="font-display font-semibold text-brand-900">SSJ Admin</span>
    </div>
    <nav class="flex flex-col gap-1 overflow-y-auto p-3">
      <?php if (can('dashboard.view')): ?>
        <?= $link('dashboard', $active, '/admin', 'Dashboard', $icon('M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6V11h-6v9Zm0-16v5h6V4h-6Z')) ?>
      <?php endif; ?>

      <?php $anyContent = false; foreach ($content as $i) { if (can($i[0])) { $anyContent = true; break; } } ?>
      <?php if ($anyContent): ?>
        <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Content</p>
        <?php foreach ($content as $i): if (can($i[0])): ?><?= $link($i[1], $active, $i[2], $i[3], $icon($i[4])) ?><?php endif; endforeach; ?>
      <?php endif; ?>

      <?php $anyCatalog = false; foreach ($catalog as $i) { if (can($i[0])) { $anyCatalog = true; break; } } ?>
      <?php if ($anyCatalog): ?>
        <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Catalog</p>
        <?php foreach ($catalog as $i): if (can($i[0])): ?><?= $link($i[1], $active, $i[2], $i[3], $icon($i[4])) ?><?php endif; endforeach; ?>
      <?php endif; ?>

      <?php if (can('leads.view')): ?>
        <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">CRM</p>
        <?= $link('leads', $active, '/admin/leads', 'Leads', $icon('M3 5h18M3 12h18M3 19h12')) ?>
      <?php endif; ?>

      <?php if (can('communications.view') || can('communications.manage_templates')): ?>
        <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Communications</p>
        <?php if (can('communications.view')): ?><?= $link('email_queue', $active, '/admin/email-queue', 'Email Queue', $icon('M3 7l9 6 9-6M4 5h16v14H4z')) ?><?php endif; ?>
        <?php if (can('communications.manage_templates')): ?><?= $link('templates', $active, '/admin/communications/templates', 'Templates', $icon('M4 5h16M4 12h16M4 19h10')) ?><?php endif; ?>
      <?php endif; ?>

      <?php $anyAdmin = false; foreach ($admin as $i) { if (can($i[0])) { $anyAdmin = true; break; } } ?>
      <?php if ($anyAdmin): ?>
        <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Administration</p>
        <?php foreach ($admin as $i): if (can($i[0])): ?><?= $link($i[1], $active, $i[2], $i[3], $icon($i[4])) ?><?php endif; endforeach; ?>
      <?php endif; ?>

      <?php if (can('settings.view')): ?>
        <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">System</p>
        <?= $link('settings', $active, '/admin/settings', 'Settings', $icon('M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z')) ?>
      <?php endif; ?>
    </nav>
  </aside>
  <div class="js-admin-overlay fixed inset-0 z-30 hidden bg-brand-900/40 lg:hidden"></div>

  <div class="flex min-w-0 flex-1 flex-col">
    <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-6">
      <button type="button" class="js-admin-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 lg:hidden" aria-label="Toggle menu">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <div class="flex-1"></div>
      <div class="flex items-center gap-4">
        <a href="/" target="_blank" rel="noopener" class="hidden text-sm text-slate-500 hover:text-brand-600 sm:inline">View site ↗</a>
        <a href="/admin/sessions" class="text-sm text-slate-500 hover:text-brand-600">Sessions</a>
        <a href="/admin/profile" class="flex items-center gap-2 text-sm text-slate-700 hover:text-brand-600">
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700"><?= e(strtoupper(substr((string) ($currentUser['name'] ?? 'U'), 0, 1))) ?></span>
          <span class="hidden sm:inline"><?= e($currentUser['name'] ?? 'User') ?></span>
        </a>
        <form method="post" action="/admin/logout" class="inline">
          <?= csrf_field() ?>
          <button class="text-sm font-medium text-slate-500 hover:text-red-600">Logout</button>
        </form>
      </div>
    </header>

    <main class="flex-1 p-4 lg:p-8">
      <?php if (!empty($flash) && is_array($flash)): ?>
        <div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-700' ?>">
          <?= e($flash['message'] ?? '') ?>
        </div>
      <?php endif; ?>

      <?= $this->section('content') ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
