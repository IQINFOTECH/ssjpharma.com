<?php
/** @var array $emailTemplates @var array $waTemplates @var bool $canTest */
$this->layout('admin.layout');
?>
<?php $this->start('content'); ?>
<div class="mb-6"><h1 class="text-2xl font-semibold text-brand-900">Templates</h1>
  <p class="mt-1 text-sm text-slate-500">Email &amp; WhatsApp message templates. Placeholders like <code>{{lead.name}}</code> are filled safely at send time.</p></div>

<div class="mb-8 rounded-xl border border-slate-200 bg-white">
  <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-brand-900">Email templates</h2></div>
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Key</th><th class="px-5 py-3">Subject</th><th class="px-5 py-3">Active</th><th class="px-5 py-3"></th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach ($emailTemplates as $t): ?>
        <tr>
          <td class="px-5 py-3 font-medium text-brand-900"><?= e($t['name']) ?></td>
          <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= e($t['key']) ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e(mb_strimwidth((string) $t['subject'], 0, 50, '…')) ?></td>
          <td class="px-5 py-3"><span class="badge <?= (int) $t['is_active'] === 1 ? 'badge-green' : 'badge-slate' ?>"><?= (int) $t['is_active'] === 1 ? 'Active' : 'Off' ?></span></td>
          <td class="px-5 py-3 text-right"><a href="/admin/communications/email-templates/<?= (int) $t['id'] ?>/edit" class="text-brand-600 hover:underline">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="rounded-xl border border-slate-200 bg-white">
  <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-brand-900">WhatsApp templates</h2>
    <p class="mt-1 text-xs text-slate-400">Used only to pre-fill wa.me chat links. No messages are ever sent automatically.</p></div>
  <table class="w-full text-sm">
    <thead class="text-left text-xs uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Key</th><th class="px-5 py-3">Message</th><th class="px-5 py-3">Active</th><th class="px-5 py-3"></th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach ($waTemplates as $t): ?>
        <tr>
          <td class="px-5 py-3 font-medium text-brand-900"><?= e($t['name']) ?></td>
          <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= e($t['key']) ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e(mb_strimwidth((string) $t['message'], 0, 50, '…')) ?></td>
          <td class="px-5 py-3"><span class="badge <?= (int) $t['is_active'] === 1 ? 'badge-green' : 'badge-slate' ?>"><?= (int) $t['is_active'] === 1 ? 'Active' : 'Off' ?></span></td>
          <td class="px-5 py-3 text-right"><a href="/admin/communications/whatsapp-templates/<?= (int) $t['id'] ?>/edit" class="text-brand-600 hover:underline">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php $this->stop(); ?>
