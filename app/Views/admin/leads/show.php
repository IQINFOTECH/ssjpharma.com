<?php
/** @var array $lead @var array $activities @var array $statuses @var array $priorities @var array $users
 *  @var bool $canEdit @var bool $canAssign @var bool $canDelete @var bool $canStatus @var bool $canPriority @var bool $canNotes */
$this->layout('admin.layout');
$id = (int) $lead['id'];
$row = static function (string $label, ?string $value): string {
    if ($value === null || trim((string) $value) === '') return '';
    return '<div class="flex gap-3 border-b border-slate-50 py-2.5"><dt class="w-36 shrink-0 text-sm text-slate-400">' . e($label)
        . '</dt><dd class="text-sm text-slate-700">' . nl2br(e($value)) . '</dd></div>';
};
$prBadge = static fn (string $p): string => match ($p) {
    'urgent' => 'bg-red-100 text-red-700', 'high' => 'bg-amber-100 text-amber-800',
    'low' => 'bg-slate-100 text-slate-500', default => 'bg-slate-100 text-slate-700',
};
$location = trim(implode(', ', array_filter([$lead['city'] ?? '', $lead['state'] ?? '', $lead['country'] ?? ''])));
$utms = array_filter([
    'utm_source' => $lead['utm_source'] ?? '', 'utm_medium' => $lead['utm_medium'] ?? '',
    'utm_campaign' => $lead['utm_campaign'] ?? '', 'utm_term' => $lead['utm_term'] ?? '', 'utm_content' => $lead['utm_content'] ?? '',
]);
$notifBadge = match ($lead['notification_status'] ?? 'pending') {
    'sent' => 'badge-green', 'failed' => 'bg-red-100 text-red-700', 'skipped' => 'badge-slate', default => 'badge-amber',
};
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <a href="/admin/leads" class="text-sm text-slate-500 hover:text-brand-600">← Leads</a>
    <h1 class="mt-1 text-2xl font-semibold text-brand-900"><?= e($lead['name']) ?>
      <span class="ml-2 align-middle font-mono text-sm text-slate-400"><?= e($lead['reference']) ?></span>
      <?php if ((int) $lead['is_spam'] === 1): ?><span class="badge badge-slate ml-1 align-middle">Spam</span><?php endif; ?></h1>
  </div>
  <div class="flex items-center gap-2">
    <span class="badge <?= $notifBadge ?>">Notify: <?= e($lead['notification_status'] ?? 'pending') ?></span>
    <?php if ($canEdit): ?>
    <form method="post" action="/admin/leads/<?= $id ?>/contacted" class="inline"><?= csrf_field() ?><button class="btn btn-ghost">Mark contacted</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <!-- Details -->
  <div class="lg:col-span-2 space-y-6">
    <div class="rounded-xl border border-slate-200 bg-white p-6">
      <h2 class="mb-3 text-lg font-semibold text-brand-900">Contact & Enquiry</h2>
      <dl>
        <?= $row('Enquiry Type', ucfirst((string) $lead['enquiry_type'])) ?>
        <?= $row('Email', $lead['email'] ?? '') ?>
        <?= $row('Phone', $lead['phone'] ?? '') ?>
        <?= $row('WhatsApp', $lead['whatsapp'] ?? '') ?>
        <?= $row('Company', $lead['company'] ?? '') ?>
        <?= $row('Business Type', $lead['business_type'] ?? '') ?>
        <?= $row('Location', $location) ?>
        <?= $row('Preferred Contact', $lead['preferred_contact'] ?? '') ?>
        <?php if (!empty($lead['product_id'])): ?>
          <?= $row('Product', ($lead['product_name'] ?? $lead['product_name_snapshot'] ?? '') . (($lead['product_status'] ?? '') === 'published' ? '' : ' (unpublished)')) ?>
        <?php elseif (!empty($lead['product_name_snapshot'])): ?>
          <?= $row('Product', $lead['product_name_snapshot']) ?>
        <?php endif; ?>
        <?= $row('Requirement', $lead['requirement'] ?? '') ?>
        <?= $row('Message', $lead['message'] ?? '') ?>
      </dl>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6">
      <h2 class="mb-3 text-lg font-semibold text-brand-900">Source & Attribution</h2>
      <dl>
        <?= $row('Source', $lead['source_name'] ?? '') ?>
        <?= $row('Landing Page', $lead['landing_page'] ?? '') ?>
        <?= $row('Source URL', $lead['source_url'] ?? '') ?>
        <?= $row('Referrer', $lead['referrer'] ?? '') ?>
        <?php foreach ($utms as $k => $v): ?><?= $row(strtoupper(str_replace('utm_', 'UTM ', $k)), $v) ?><?php endforeach; ?>
      </dl>
    </div>

    <!-- Activity timeline -->
    <div class="rounded-xl border border-slate-200 bg-white p-6">
      <h2 class="mb-4 text-lg font-semibold text-brand-900">Activity</h2>
      <?php if ($canNotes): ?>
      <form method="post" action="/admin/leads/<?= $id ?>/notes" class="mb-5 flex gap-2">
        <?= csrf_field() ?>
        <input name="note" class="input-admin" placeholder="Add an internal note…" maxlength="5000" required>
        <button class="btn btn-primary shrink-0">Add note</button>
      </form>
      <?php endif; ?>
      <ol class="space-y-3">
        <?php if (empty($activities)): ?><li class="text-sm text-slate-400">No activity yet.</li><?php endif; ?>
        <?php foreach ($activities as $a): ?>
          <li class="flex gap-3">
            <span class="mt-1 h-2 w-2 shrink-0 rounded-full <?= $a['type'] === 'note' ? 'bg-brand-500' : ($a['type'] === 'email_failed' ? 'bg-red-500' : 'bg-slate-300') ?>"></span>
            <div>
              <div class="text-sm text-slate-700"><?= e($a['description'] ?: ucfirst(str_replace('_', ' ', $a['type']))) ?></div>
              <div class="text-xs text-slate-400"><?= e(substr((string) $a['created_at'], 0, 16)) ?><?php if (!empty($a['user_name'])): ?> · <?= e($a['user_name']) ?><?php endif; ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>

  <!-- Management -->
  <div class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
      <h3 class="mb-3 text-sm font-semibold text-brand-900">Management</h3>
      <?php if ($canStatus): ?>
      <form method="post" action="/admin/leads/<?= $id ?>/status" class="mb-3">
        <?= csrf_field() ?>
        <label class="field-label text-xs">Status</label>
        <div class="flex gap-2">
          <select name="status_id" class="input-admin">
            <?php foreach ($statuses as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $lead['status_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-ghost shrink-0">Set</button>
        </div>
      </form>
      <?php else: ?>
        <p class="mb-3 text-sm">Status: <span class="badge" style="background:<?= e(($lead['status_color'] ?? '#64748b')) ?>1a;color:<?= e($lead['status_color'] ?? '#64748b') ?>"><?= e($lead['status_name'] ?? 'New') ?></span></p>
      <?php endif; ?>

      <?php if ($canPriority): ?>
      <form method="post" action="/admin/leads/<?= $id ?>/priority" class="mb-3">
        <?= csrf_field() ?>
        <label class="field-label text-xs">Priority</label>
        <div class="flex gap-2">
          <select name="priority" class="input-admin">
            <?php foreach ($priorities as $p): ?><option value="<?= $p ?>" <?= $lead['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-ghost shrink-0">Set</button>
        </div>
      </form>
      <?php else: ?>
        <p class="mb-3 text-sm">Priority: <span class="badge <?= $prBadge($lead['priority']) ?>"><?= ucfirst($lead['priority']) ?></span></p>
      <?php endif; ?>

      <?php if ($canEdit): ?>
      <form method="post" action="/admin/leads/<?= $id ?>/followup" class="mb-3">
        <?= csrf_field() ?>
        <label class="field-label text-xs">Follow-up date</label>
        <div class="flex gap-2">
          <input type="date" name="follow_up_date" value="<?= e($lead['follow_up_date'] ?? '') ?>" class="input-admin">
          <button class="btn btn-ghost shrink-0">Set</button>
        </div>
        <?php if (!empty($lead['follow_up_date'])): ?><p class="mt-1 text-xs text-slate-400">Clear the field and Set to remove.</p><?php endif; ?>
      </form>
      <?php elseif (!empty($lead['follow_up_date'])): ?>
        <p class="mb-3 text-sm">Follow-up: <?= e($lead['follow_up_date']) ?></p>
      <?php endif; ?>

      <?php if ($canAssign): ?>
      <form method="post" action="/admin/leads/<?= $id ?>/assign">
        <?= csrf_field() ?>
        <label class="field-label text-xs">Assigned to</label>
        <div class="flex gap-2">
          <select name="assigned_user_id" class="input-admin">
            <option value="0">— Unassigned —</option>
            <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) ($lead['assigned_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-ghost shrink-0">Set</button>
        </div>
      </form>
      <?php else: ?>
        <p class="mt-2 text-sm text-slate-600">Assigned: <?= e($lead['assigned_name'] ?: 'Unassigned') ?></p>
      <?php endif; ?>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">
      <p><span class="text-slate-400">Created:</span> <?= e(substr((string) $lead['created_at'], 0, 16)) ?></p>
      <p class="mt-1"><span class="text-slate-400">Updated:</span> <?= e(substr((string) $lead['updated_at'], 0, 16)) ?></p>
      <p class="mt-1"><span class="text-slate-400">Last contacted:</span> <?= e($lead['last_contacted_at'] ? substr((string) $lead['last_contacted_at'], 0, 16) : '—') ?></p>
      <p class="mt-1"><span class="text-slate-400">Consent:</span> <?= (int) $lead['consent'] === 1 ? 'Yes' : 'No' ?><?php if (!empty($lead['consent_at'])): ?> (<?= e(substr((string) $lead['consent_at'], 0, 16)) ?>, v<?= e($lead['privacy_version'] ?? '') ?>)<?php endif; ?></p>
    </div>

    <?php if ($canDelete): ?>
    <form method="post" action="/admin/leads/<?= $id ?>/delete" class="rounded-xl border border-red-200 bg-red-50/50 p-5" onsubmit="return confirm('Delete this lead?');">
      <?= csrf_field() ?><?= method_field('DELETE') ?>
      <h3 class="text-sm font-semibold text-red-700">Delete lead</h3>
      <button class="btn mt-2 border border-red-300 bg-white text-red-600 hover:bg-red-50">Delete</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php $this->stop(); ?>
