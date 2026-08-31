<?php
/** @var array $row */
$this->layout('admin.layout');
$meta = null;
if (!empty($row['meta'])) { $decoded = json_decode((string) $row['meta'], true); $meta = is_array($decoded) ? $decoded : null; }
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <a href="/admin/audit-logs" class="text-sm text-slate-500 hover:text-brand-600">← Audit Log</a>
  <h1 class="mt-1 text-2xl font-semibold text-brand-900">Event #<?= (int) $row['id'] ?></h1>
</div>
<div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
  <dl class="grid grid-cols-3 gap-y-3 text-sm">
    <dt class="text-slate-400">Event</dt><dd class="col-span-2"><span class="badge badge-slate"><?= e($row['event']) ?></span></dd>
    <dt class="text-slate-400">When</dt><dd class="col-span-2 text-slate-700"><?= e((string) $row['created_at']) ?></dd>
    <dt class="text-slate-400">Actor ID</dt><dd class="col-span-2 text-slate-700"><?= e($row['user_id'] ?? 'System') ?></dd>
    <dt class="text-slate-400">Entity</dt><dd class="col-span-2 text-slate-700"><?= e(($row['entity_type'] ?? '—') . ($row['entity_id'] ? ' #' . $row['entity_id'] : '')) ?></dd>
    <dt class="text-slate-400">IP</dt><dd class="col-span-2 font-mono text-xs text-slate-600"><?= e($row['ip'] ?? '—') ?></dd>
    <dt class="text-slate-400">User agent</dt><dd class="col-span-2 break-all text-xs text-slate-500"><?= e($row['user_agent'] ?? '—') ?></dd>
  </dl>
  <?php if ($meta !== null && $meta !== []): ?>
  <div class="mt-4">
    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Metadata</p>
    <pre class="overflow-x-auto rounded-lg bg-slate-50 p-3 text-xs text-slate-700"><?= e(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </div>
  <?php endif; ?>
</div>
<?php $this->stop(); ?>
