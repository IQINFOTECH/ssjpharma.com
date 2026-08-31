<?php
/** @var array $row @var bool $canRetry */
$this->layout('admin.layout');
$id = (int) $row['id'];
$field = static fn (string $l, ?string $v): string =>
    '<div class="flex gap-3 border-b border-slate-50 py-2.5"><dt class="w-40 shrink-0 text-sm text-slate-400">' . e($l)
    . '</dt><dd class="text-sm text-slate-700">' . e((string) ($v ?? '—')) . '</dd></div>';
// The stored HTML body is untrusted for the admin page → render inside a sandboxed
// iframe (no scripts) so nothing in it can execute in the admin context.
$srcdoc = htmlspecialchars((string) ($row['body_html'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex items-center justify-between gap-3">
  <div>
    <a href="/admin/email-queue" class="text-sm text-slate-500 hover:text-brand-600">← Email Queue</a>
    <h1 class="mt-1 text-2xl font-semibold text-brand-900">Message #<?= $id ?></h1>
  </div>
  <?php if ($canRetry): ?>
  <div class="flex gap-2">
    <?php if (in_array($row['status'], ['failed', 'cancelled'], true)): ?>
      <form method="post" action="/admin/email-queue/<?= $id ?>/retry"><?= csrf_field() ?><button class="btn btn-primary">Requeue</button></form>
    <?php endif; ?>
    <?php if (in_array($row['status'], ['pending', 'failed'], true)): ?>
      <form method="post" action="/admin/email-queue/<?= $id ?>/cancel" onsubmit="return confirm('Cancel this message?');"><?= csrf_field() ?><button class="btn btn-ghost">Cancel</button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <div class="lg:col-span-1 rounded-xl border border-slate-200 bg-white p-6">
    <dl>
      <?= $field('Status', (string) $row['status']) ?>
      <?= $field('Template', (string) ($row['template_key'] ?? '')) ?>
      <?= $field('Recipient', (string) $row['recipient_email']) ?>
      <?= $field('Recipient name', (string) ($row['recipient_name'] ?? '')) ?>
      <?= $field('Reply-To', (string) ($row['reply_to_email'] ?? '')) ?>
      <?= $field('Lead ID', $row['lead_id'] !== null ? (string) $row['lead_id'] : null) ?>
      <?= $field('Attempts', (int) $row['attempts'] . ' / ' . (int) $row['max_attempts']) ?>
      <?= $field('Available at', (string) $row['available_at']) ?>
      <?= $field('Last attempt', (string) ($row['last_attempt_at'] ?? '')) ?>
      <?= $field('Sent at', (string) ($row['sent_at'] ?? '')) ?>
      <?= $field('Last error', (string) ($row['last_error'] ?? '')) ?>
      <?= $field('Created', (string) $row['created_at']) ?>
    </dl>
  </div>
  <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="mb-2 text-sm font-semibold text-brand-900">Subject</h2>
    <p class="mb-4 text-sm text-slate-700"><?= e((string) $row['subject']) ?></p>
    <h2 class="mb-2 text-sm font-semibold text-brand-900">HTML body (sandboxed)</h2>
    <iframe sandbox class="h-96 w-full rounded-lg border border-slate-200" srcdoc="<?= $srcdoc ?>" title="Email preview"></iframe>
  </div>
</div>
<?php $this->stop(); ?>
