<?php
/** @var array $tpl @var string $preview */
$this->layout('admin.layout');
$id = (int) $tpl['id'];
?>
<?php $this->start('content'); ?>
<div class="mb-6"><a href="/admin/communications/templates" class="text-sm text-slate-500 hover:text-brand-600">← Templates</a>
  <h1 class="mt-1 text-2xl font-semibold text-brand-900">Edit WhatsApp: <?= e($tpl['name']) ?></h1>
  <p class="mt-1 font-mono text-xs text-slate-400"><?= e($tpl['key']) ?></p></div>

<div class="grid gap-6 lg:grid-cols-3">
  <form method="post" action="/admin/communications/whatsapp-templates/<?= $id ?>" class="lg:col-span-2 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
    <?= csrf_field() ?>
    <div><label class="field-label">Name</label><input name="name" class="input-admin" value="<?= e($tpl['name']) ?>" maxlength="120" required></div>
    <div><label class="field-label">Message</label><textarea name="message" rows="5" class="input-admin" maxlength="1000" required><?= e((string) $tpl['message']) ?></textarea>
      <p class="mt-1 text-xs text-slate-400">Placeholders: <code>{{product.name}}</code> <code>{{product.url}}</code> <code>{{site.name}}</code>. Do not include sensitive customer data.</p></div>
    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" <?= (int) $tpl['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
    <div class="pt-2"><button class="btn btn-primary">Save</button></div>
  </form>

  <div class="rounded-xl border border-slate-200 bg-white p-5">
    <h3 class="mb-2 text-sm font-semibold text-brand-900">wa.me preview</h3>
    <?php if ($preview !== ''): ?>
      <p class="break-all text-xs text-slate-600"><?= e($preview) ?></p>
      <a href="<?= e($preview) ?>" target="_blank" rel="noopener" class="btn btn-ghost mt-3">Open demo chat ↗</a>
    <?php else: ?>
      <p class="text-sm text-slate-400">Set a WhatsApp number in Settings to preview the link.</p>
    <?php endif; ?>
    <p class="mt-3 text-xs text-slate-400">wa.me only — the visitor initiates the chat. No message is ever sent automatically.</p>
  </div>
</div>
<?php $this->stop(); ?>
