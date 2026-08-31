<?php
/** @var array $rows @var int $total @var string $status @var array $statuses @var array $counts
 *  @var int $page @var int $totalPages @var string $deliveryMode @var bool $canRetry */
$this->layout('admin.layout');
$badge = static fn (string $s): string => match ($s) {
    'sent' => 'badge-green', 'failed' => 'bg-red-100 text-red-700',
    'processing' => 'badge-amber', 'cancelled' => 'badge-slate', default => 'bg-slate-100 text-slate-600',
};
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div><h1 class="text-2xl font-semibold text-brand-900">Email Queue</h1>
    <p class="mt-1 text-sm text-slate-500"><?= (int) $total ?> message<?= $total === 1 ? '' : 's' ?></p></div>
  <div class="text-sm">
    Delivery mode:
    <span class="badge <?= $deliveryMode === 'smtp' ? 'badge-green' : 'badge-amber' ?>"><?= e($deliveryMode) ?></span>
    <?php if ($deliveryMode !== 'smtp'): ?><span class="ml-1 text-slate-400">(no real emails are sent)</span><?php endif; ?>
  </div>
</div>

<div class="mb-4 flex flex-wrap gap-2">
  <a href="/admin/email-queue" class="rounded-lg border px-3 py-1.5 text-sm <?= $status === '' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">All</a>
  <?php foreach ($statuses as $s): ?>
    <a href="/admin/email-queue?status=<?= e($s) ?>" class="rounded-lg border px-3 py-1.5 text-sm <?= $status === $s ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
      <?= ucfirst($s) ?> <span class="text-slate-400"><?= (int) ($counts[$s] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr><th class="px-5 py-3">#</th><th class="px-5 py-3">Recipient</th><th class="px-5 py-3">Subject</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Attempts</th><th class="px-5 py-3">Created</th><th class="px-5 py-3">Last error</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No messages.</td></tr>
      <?php else: foreach ($rows as $m): ?>
        <tr class="cursor-pointer hover:bg-slate-50" onclick="location.href='/admin/email-queue/<?= (int) $m['id'] ?>'">
          <td class="px-5 py-3 text-slate-500">#<?= (int) $m['id'] ?></td>
          <td class="px-5 py-3 text-slate-700"><?= e($m['recipient_email']) ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e(mb_strimwidth((string) $m['subject'], 0, 60, '…')) ?></td>
          <td class="px-5 py-3"><span class="badge <?= $badge((string) $m['status']) ?>"><?= e($m['status']) ?></span></td>
          <td class="px-5 py-3 text-slate-500"><?= (int) $m['attempts'] ?>/<?= (int) $m['max_attempts'] ?></td>
          <td class="px-5 py-3 text-slate-500"><?= e(substr((string) $m['created_at'], 0, 16)) ?></td>
          <td class="px-5 py-3 text-red-500"><?= e(mb_strimwidth((string) ($m['last_error'] ?? ''), 0, 40, '…')) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="mt-4 flex flex-wrap gap-2">
  <?php for ($p = max(1, $page - 4); $p <= min($totalPages, $page + 4); $p++): ?>
    <a href="/admin/email-queue?<?= e(http_build_query(array_filter(['status' => $status, 'page' => $p]))) ?>" class="rounded-lg border px-3 py-1.5 text-sm <?= $p === $page ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php $this->stop(); ?>
