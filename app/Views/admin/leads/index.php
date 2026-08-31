<?php
/** @var array $rows @var int $total @var array $filters @var int $page @var int $totalPages
 *  @var array $statuses @var array $sources @var array $users @var array $products @var array $enquiryTypes
 *  @var array $metrics @var bool $canExport @var string $scopeMode @var bool $canSeeAny */
$this->layout('admin.layout');
$prBadge = static fn (string $p): string => match ($p) {
    'urgent' => 'bg-red-100 text-red-700', 'high' => 'bg-amber-100 text-amber-800',
    'low' => 'bg-slate-100 text-slate-500', default => 'bg-slate-100 text-slate-700',
};
$qs = array_filter([
    'q' => $filters['q'], 'status' => $filters['status'] ?: '', 'priority' => $filters['priority'],
    'source' => $filters['source'] ?: '', 'enquiry_type' => $filters['enquiry_type'], 'assigned' => $filters['assigned'],
    'product' => $filters['product'] ?: '', 'from' => $filters['from'], 'to' => $filters['to'], 'sort' => $filters['sort'],
    'followup' => $filters['followup'] ?? '',
], static fn ($v) => $v !== '' && $v !== 0);
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div><h1 class="text-2xl font-semibold text-brand-900">Leads</h1>
    <p class="mt-1 text-sm text-slate-500">
      <?= e($total) ?> lead<?= $total === 1 ? '' : 's' ?><?php if (($scopeMode ?? '') === 'assigned'): ?> · showing leads assigned to you<?php endif; ?>
    </p></div>
  <?php if ($canExport): ?><a href="/admin/leads/export?<?= e(http_build_query($qs)) ?>" class="btn btn-ghost">Export CSV</a><?php endif; ?>
</div>

<?php if (!($canSeeAny ?? true)): ?>
  <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    You have access to the leads area, but no lead-visibility permission
    (<code>leads.view_all</code> or <code>leads.view_assigned</code>) has been granted to your role, so no leads are shown. Contact an administrator if you need access.
  </div>
<?php endif; ?>

<!-- Lightweight lead dashboard (scoped metrics; efficient aggregate queries) -->
<?php
$statusId = static fn (string $k): int => (int) (array_values(array_filter($statuses, fn($s) => $s['key'] === $k))[0]['id'] ?? 0);
$primary = [
  ['New',        $metrics['new'],        '?status=' . $statusId('new')],
  ['Open',       $metrics['open'],       ''],
  ['Today',      $metrics['today'],      ''],
  ['Unassigned', $metrics['unassigned'], '?assigned=none'],
];
$secondary = [
  ['Contacted',  $metrics['contacted'] ?? 0, '?status=' . $statusId('contacted')],
  ['Qualified',  $metrics['qualified'] ?? 0, '?status=' . $statusId('qualified')],
  ['Converted',  $metrics['converted'] ?? 0, '?status=' . $statusId('converted')],
  ['This week',  $metrics['week'] ?? 0,      ''],
  ['This month', $metrics['month'] ?? 0,     ''],
  ['Product',    $metrics['product'] ?? 0,   '?enquiry_type=product'],
];
?>
<div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php foreach ($primary as $c): ?>
    <a href="/admin/leads<?= e($c[2]) ?>" class="rounded-xl border border-slate-200 bg-white p-5 transition hover:shadow-card">
      <div class="text-3xl font-bold text-brand-700"><?= (int) $c[1] ?></div>
      <div class="mt-1 text-sm text-slate-500"><?= e($c[0]) ?> leads</div>
    </a>
  <?php endforeach; ?>
</div>
<div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
  <?php foreach ($secondary as $c): ?>
    <a href="/admin/leads<?= e($c[2]) ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-3 transition hover:shadow-card">
      <div class="text-xl font-semibold text-brand-800"><?= (int) $c[1] ?></div>
      <div class="mt-0.5 text-xs text-slate-500"><?= e($c[0]) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<!-- Follow-up quick filters (Phase 5) -->
<div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
  <span class="text-slate-400">Follow-ups:</span>
  <?php
  $fuNow = $filters['followup'] ?? '';
  foreach ([
    ['overdue', 'Overdue', $metrics['overdue'] ?? 0, 'text-red-600'],
    ['today',   'Due today', $metrics['due_today'] ?? 0, 'text-amber-700'],
    ['next7',   'Next 7 days', $metrics['upcoming'] ?? 0, 'text-slate-600'],
    ['none',    'No follow-up', null, 'text-slate-500'],
  ] as $b): ?>
    <a href="/admin/leads?followup=<?= e($b[0]) ?>" class="rounded-full border px-3 py-1 <?= $fuNow === $b[0] ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 ' . $b[3] . ' hover:bg-slate-50' ?>">
      <?= e($b[1]) ?><?php if ($b[2] !== null): ?> <span class="font-semibold"><?= (int) $b[2] ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  <?php if ($fuNow !== ''): ?><a href="/admin/leads" class="text-slate-400 hover:text-brand-600">clear</a><?php endif; ?>
</div>

<!-- Filters -->
<form method="get" action="/admin/leads" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-6">
  <?php if (($filters['followup'] ?? '') !== ''): ?><input type="hidden" name="followup" value="<?= e($filters['followup']) ?>"><?php endif; ?>
  <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Name/email/phone/ref…" class="input-admin md:col-span-2">
  <select name="status" class="input-admin"><option value="">All statuses</option>
    <?php foreach ($statuses as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $filters['status'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  <select name="priority" class="input-admin"><option value="">All priorities</option>
    <?php foreach (['urgent','high','medium','low'] as $p): ?><option value="<?= $p ?>" <?= $filters['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select>
  <select name="enquiry_type" class="input-admin"><option value="">All types</option>
    <?php foreach ($enquiryTypes as $t): ?><option value="<?= e($t) ?>" <?= $filters['enquiry_type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select>
  <select name="assigned" class="input-admin"><option value="">Any assignee</option><option value="none" <?= $filters['assigned'] === 'none' ? 'selected' : '' ?>>Unassigned</option>
    <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $filters['assigned'] === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
  <select name="source" class="input-admin"><option value="">All sources</option>
    <?php foreach ($sources as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $filters['source'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= e($filters['from']) ?>" class="input-admin">
  <input type="date" name="to" value="<?= e($filters['to']) ?>" class="input-admin">
  <div class="md:col-span-6"><button class="btn btn-ghost">Filter</button> <a href="/admin/leads" class="btn btn-ghost">Reset</a></div>
</form>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr><th class="px-5 py-3">Lead</th><th class="px-5 py-3">Company</th><th class="px-5 py-3">Enquiry</th><th class="px-5 py-3">Product</th><th class="px-5 py-3">Source</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Priority</th><th class="px-5 py-3">Assigned</th><th class="px-5 py-3">Follow-up</th><th class="px-5 py-3">Created</th><th class="px-5 py-3 text-right">Action</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($rows)): ?>
        <tr><td colspan="11" class="px-5 py-10 text-center text-slate-400">No leads found.</td></tr>
      <?php else: foreach ($rows as $l): ?>
        <tr class="js-row-link cursor-pointer hover:bg-slate-50" data-href="/admin/leads/<?= (int) $l['id'] ?>">
          <td class="px-5 py-3">
            <div class="font-medium text-brand-900"><a href="/admin/leads/<?= (int) $l['id'] ?>" class="hover:underline"><?= e($l['name']) ?></a><?php if ((int) $l['is_spam'] === 1): ?> <span class="badge badge-slate ml-1">Spam</span><?php endif; ?></div>
            <div class="text-xs text-slate-400"><?= e($l['email'] ?: $l['phone'] ?: $l['reference']) ?></div>
          </td>
          <td class="px-5 py-3 text-slate-600"><?= e($l['company'] ?: '—') ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e(ucfirst($l['enquiry_type'])) ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e($l['product_name'] ?: ($l['product_name_snapshot'] ?: '—')) ?></td>
          <td class="px-5 py-3 text-slate-500"><?= e($l['source_name'] ?: '—') ?></td>
          <td class="px-5 py-3"><span class="badge" style="background:<?= e(($l['status_color'] ?? '#64748b')) ?>1a;color:<?= e($l['status_color'] ?? '#64748b') ?>"><?= e($l['status_name'] ?: 'New') ?></span></td>
          <td class="px-5 py-3"><span class="badge <?= $prBadge($l['priority']) ?>"><?= ucfirst($l['priority']) ?></span></td>
          <td class="px-5 py-3 text-slate-600"><?= e($l['assigned_name'] ?: '—') ?></td>
          <?php $fu = $l['follow_up_date'] ?? null; $overdue = $fu && $fu < date('Y-m-d'); ?>
          <td class="px-5 py-3 <?= $overdue ? 'font-medium text-red-600' : 'text-slate-500' ?>"><?= $fu ? e($fu) : '—' ?></td>
          <td class="px-5 py-3 text-slate-500"><?= e(substr((string) $l['created_at'], 0, 16)) ?></td>
          <td class="px-5 py-3 text-right whitespace-nowrap">
            <a href="/admin/leads/<?= (int) $l['id'] ?>" class="text-xs font-medium text-brand-600 hover:text-brand-700">View</a>
            <?php if ($canDelete ?? false): ?>
            <form method="post" action="/admin/leads/<?= (int) $l['id'] ?>/delete" class="js-confirm ml-3 inline" data-confirm="Delete this lead? This cannot be undone.">
              <?= csrf_field() ?><?= method_field('DELETE') ?>
              <button class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="mt-4 flex flex-wrap gap-2">
  <?php for ($p = max(1, $page - 4); $p <= min($totalPages, $page + 4); $p++): ?>
    <a href="/admin/leads?<?= e(http_build_query($qs + ['page' => $p])) ?>" class="rounded-lg border px-3 py-1.5 text-sm <?= $p === $page ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php $this->stop(); ?>
