<?php
/** @var array $rows @var int $total @var array $filters @var int $page @var int $totalPages
 *  @var array $events @var array $users */
$this->layout('admin.layout');
$qs = array_filter([
    'q' => $filters['q'], 'user_id' => $filters['user_id'] ?: '', 'event' => $filters['event'],
    'entity' => $filters['entity'], 'from' => $filters['from'], 'to' => $filters['to'],
]);
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-brand-900">Audit Log</h1>
  <p class="mt-1 text-sm text-slate-500"><?= e($total) ?> event<?= $total === 1 ? '' : 's' ?> · read-only</p>
</div>

<form method="get" action="/admin/audit-logs" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-6">
  <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search…" class="input-admin md:col-span-2">
  <select name="user_id" class="input-admin">
    <option value="">All users</option>
    <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $filters['user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="event" class="input-admin">
    <option value="">All events</option>
    <?php foreach ($events as $ev): ?><option value="<?= e($ev) ?>" <?= $filters['event'] === $ev ? 'selected' : '' ?>><?= e($ev) ?></option><?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?= e($filters['from']) ?>" class="input-admin">
  <input type="date" name="to" value="<?= e($filters['to']) ?>" class="input-admin">
  <div class="md:col-span-6"><button class="btn btn-ghost">Filter</button> <a href="/admin/audit-logs" class="btn btn-ghost">Reset</a></div>
</form>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr><th class="px-5 py-3">When</th><th class="px-5 py-3">Actor</th><th class="px-5 py-3">Event</th><th class="px-5 py-3">Entity</th><th class="px-5 py-3">IP</th><th class="px-5 py-3"></th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">No audit events.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="px-5 py-3 text-slate-500"><?= e(substr((string) $r['created_at'], 0, 19)) ?></td>
          <td class="px-5 py-3 text-slate-700"><?= e($r['user_name'] ?? ($r['user_id'] ? 'User #' . $r['user_id'] : 'System')) ?></td>
          <td class="px-5 py-3"><span class="badge badge-slate"><?= e($r['event']) ?></span></td>
          <td class="px-5 py-3 text-slate-600"><?= e(($r['entity_type'] ?? '') . ($r['entity_id'] ? ' #' . $r['entity_id'] : '')) ?></td>
          <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= e($r['ip'] ?? '—') ?></td>
          <td class="px-5 py-3 text-right"><a href="/admin/audit-logs/<?= (int) $r['id'] ?>" class="text-brand-600 hover:text-brand-700">Details</a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="mt-4 flex flex-wrap gap-2">
  <?php for ($p = max(1, $page - 4); $p <= min($totalPages, $page + 4); $p++): ?>
    <a href="/admin/audit-logs?<?= e(http_build_query($qs + ['page' => $p])) ?>"
       class="rounded-lg border px-3 py-1.5 text-sm <?= $p === $page ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php $this->stop(); ?>
