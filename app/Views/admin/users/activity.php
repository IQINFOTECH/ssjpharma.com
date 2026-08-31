<?php
/** @var array $user @var array $events */
$this->layout('admin.layout');
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <a href="/admin/users/<?= (int) $user['id'] ?>/edit" class="text-sm text-slate-500 hover:text-brand-600">← <?= e($user['name']) ?></a>
  <h1 class="mt-1 text-2xl font-semibold text-brand-900">Activity</h1>
</div>
<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr><th class="px-5 py-3">When</th><th class="px-5 py-3">Event</th><th class="px-5 py-3">Entity</th><th class="px-5 py-3">IP</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($events)): ?>
        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">No activity recorded.</td></tr>
      <?php else: foreach ($events as $ev): ?>
        <tr>
          <td class="px-5 py-3 text-slate-500"><?= e(substr((string) $ev['created_at'], 0, 19)) ?></td>
          <td class="px-5 py-3"><span class="badge badge-slate"><?= e($ev['event']) ?></span></td>
          <td class="px-5 py-3 text-slate-600"><?= e(($ev['entity_type'] ?? '') . ($ev['entity_id'] ? ' #' . $ev['entity_id'] : '')) ?></td>
          <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= e($ev['ip'] ?? '—') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php $this->stop(); ?>
