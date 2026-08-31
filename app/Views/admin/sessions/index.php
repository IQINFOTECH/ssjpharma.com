<?php
/** @var array $rows @var int $total @var bool $seeAll @var string $currentSid
 *  @var bool $canRevokeOthers @var int $page @var int $totalPages */
$this->layout('admin.layout');
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-brand-900">Active Sessions</h1>
  <p class="mt-1 text-sm text-slate-500"><?= $seeAll ? 'All active admin sessions.' : 'Your active sessions across devices.' ?></p>
</div>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
      <tr>
        <?php if ($seeAll): ?><th class="px-5 py-3">User</th><?php endif; ?>
        <th class="px-5 py-3">IP</th><th class="px-5 py-3">Device</th><th class="px-5 py-3">Last active</th><th class="px-5 py-3 text-right"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No active sessions.</td></tr>
      <?php else: foreach ($rows as $s): $isCurrent = ($s['session_id'] ?? '') === $currentSid; ?>
        <tr>
          <?php if ($seeAll): ?><td class="px-5 py-3 text-slate-700"><?= e($s['user_name'] ?? ('#' . $s['user_id'])) ?></td><?php endif; ?>
          <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= e($s['ip'] ?? '—') ?></td>
          <td class="px-5 py-3 text-xs text-slate-500"><?= e(mb_substr((string) ($s['user_agent'] ?? '—'), 0, 60)) ?></td>
          <td class="px-5 py-3 text-slate-500">
            <?= e(substr((string) $s['last_activity_at'], 0, 16)) ?>
            <?php if ($isCurrent): ?><span class="badge badge-green ml-1">This device</span><?php endif; ?>
          </td>
          <td class="px-5 py-3 text-right">
            <?php if (!$isCurrent): ?>
              <form method="post" action="/admin/sessions/<?= (int) $s['id'] ?>/revoke" class="js-confirm" data-confirm="Revoke this session?">
                <?= csrf_field() ?>
                <button class="text-red-500 hover:text-red-700">Revoke</button>
              </form>
            <?php else: ?>
              <span class="text-xs text-slate-300">current</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php $this->stop(); ?>
