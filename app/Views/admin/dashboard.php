<?php
/** @var array $counts @var array $recentLeads @var array|null $leadMetrics @var bool $canViewLeads */
$this->layout('admin.layout');
$cards = [
    ['label' => 'Published pages', 'value' => $counts['pages_published'], 'url' => '/admin/pages?status=published'],
    ['label' => 'Draft pages',     'value' => $counts['pages_draft'],     'url' => '/admin/pages?status=draft'],
    ['label' => 'Media files',     'value' => $counts['media'],           'url' => '/admin/media'],
    ['label' => 'Active redirects','value' => $counts['redirects'],       'url' => '/admin/redirects'],
];
// Lead figure is scoped (Phase 4.1) — shown only to users who may see leads,
// and reflects only the leads within their visibility scope.
if (($canViewLeads ?? false) && !empty($leadMetrics)) {
    $cards[] = ['label' => 'Leads (visible)', 'value' => $leadMetrics['total'], 'url' => '/admin/leads'];
}
?>
<?php $this->start('content'); ?>
<div class="mb-8">
  <h1 class="text-2xl font-semibold text-brand-900">Dashboard</h1>
  <p class="mt-1 text-sm text-slate-500">Welcome back, <?= e($currentUser['name'] ?? 'there') ?>.</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 <?= count($cards) >= 5 ? 'lg:grid-cols-5' : 'lg:grid-cols-4' ?>">
  <?php foreach ($cards as $c): ?>
    <a href="<?= e($c['url']) ?>" class="rounded-xl border border-slate-200 bg-white p-5 transition hover:shadow-card">
      <div class="text-3xl font-bold text-brand-700"><?= e($c['value']) ?></div>
      <div class="mt-1 text-sm text-slate-500"><?= e($c['label']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!empty($leadMetrics)): ?>
<div class="mt-6">
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ([
        ['New leads', $leadMetrics['new'], '/admin/leads'],
        ['Open leads', $leadMetrics['open'], '/admin/leads'],
        ['Today', $leadMetrics['today'], '/admin/leads'],
        ['Unassigned', $leadMetrics['unassigned'], '/admin/leads?assigned=none'],
    ] as $m): ?>
      <a href="<?= e($m[2]) ?>" class="rounded-xl border border-brand-100 bg-brand-50/40 p-5 transition hover:shadow-card">
        <div class="text-2xl font-bold text-brand-700"><?= (int) $m[1] ?></div>
        <div class="mt-1 text-sm text-slate-500"><?= e($m[0]) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mt-8 rounded-xl border border-slate-200 bg-white">
  <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
    <h2 class="font-semibold text-brand-900">Recent enquiries</h2>
    <?php if ($canViewLeads ?? false): ?><a href="/admin/leads" class="text-sm text-brand-600 hover:underline">All leads →</a><?php endif; ?>
  </div>
  <?php if (empty($recentLeads)): ?>
    <p class="px-5 py-8 text-center text-sm text-slate-400">No enquiries yet.</p>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
        <tr><th class="px-5 py-3">Ref</th><th class="px-5 py-3">Name</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Received</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($recentLeads as $lead): ?>
          <tr class="<?= ($canViewLeads ?? false) ? 'js-row-link cursor-pointer hover:bg-slate-50' : '' ?>"<?php if ($canViewLeads ?? false): ?> data-href="/admin/leads/<?= (int) $lead['id'] ?>"<?php endif; ?>>
            <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= e($lead['reference']) ?></td>
            <td class="px-5 py-3 text-brand-900"><?= e($lead['name']) ?></td>
            <td class="px-5 py-3 text-slate-500"><?= e(ucfirst((string) ($lead['enquiry_type'] ?? 'general'))) ?></td>
            <td class="px-5 py-3 text-slate-600"><?= e($lead['email']) ?></td>
            <td class="px-5 py-3 text-slate-500"><?= e(substr((string) $lead['created_at'], 0, 16)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php $this->stop(); ?>
