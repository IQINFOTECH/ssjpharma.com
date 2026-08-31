<?php
/** @var array $tpl @var array $placeholders @var bool $canTest */
$this->layout('admin.layout');
$id = (int) $tpl['id'];
?>
<?php $this->start('content'); ?>
<div class="mb-6"><a href="/admin/communications/templates" class="text-sm text-slate-500 hover:text-brand-600">← Templates</a>
  <h1 class="mt-1 text-2xl font-semibold text-brand-900">Edit: <?= e($tpl['name']) ?></h1>
  <p class="mt-1 font-mono text-xs text-slate-400"><?= e($tpl['key']) ?></p></div>

<div class="grid gap-6 lg:grid-cols-3">
  <form method="post" action="/admin/communications/email-templates/<?= $id ?>" class="lg:col-span-2 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
    <?= csrf_field() ?>
    <div><label class="field-label">Name</label><input name="name" class="input-admin" value="<?= e($tpl['name']) ?>" maxlength="120" required></div>
    <div><label class="field-label">Subject</label><input name="subject" class="input-admin" value="<?= e($tpl['subject']) ?>" maxlength="255" required></div>
    <div><label class="field-label">HTML body</label><textarea name="body_html" rows="12" class="input-admin font-mono text-xs"><?= e((string) $tpl['body_html']) ?></textarea></div>
    <div><label class="field-label">Plain-text body</label><textarea name="body_text" rows="6" class="input-admin font-mono text-xs"><?= e((string) $tpl['body_text']) ?></textarea></div>
    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" <?= (int) $tpl['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
    <div class="flex flex-wrap gap-2 pt-2">
      <button class="btn btn-primary">Save</button>
      <button formaction="/admin/communications/email-templates/<?= $id ?>/preview" formtarget="_blank" class="btn btn-ghost">Preview (demo values)</button>
    </div>
  </form>

  <div class="space-y-4">
    <?php if ($canTest): ?>
    <form method="post" action="/admin/communications/email-templates/<?= $id ?>/test" class="rounded-xl border border-slate-200 bg-white p-5">
      <?= csrf_field() ?>
      <h3 class="text-sm font-semibold text-brand-900">Send test</h3>
      <p class="mt-1 text-xs text-slate-500">Queues a test (with demo values) to <strong>your own</strong> account email only. Delivered on the next queue run.</p>
      <button class="btn btn-ghost mt-3">Queue test email</button>
    </form>
    <?php endif; ?>
    <div class="rounded-xl border border-slate-200 bg-white p-5">
      <h3 class="mb-2 text-sm font-semibold text-brand-900">Placeholders</h3>
      <div class="flex flex-wrap gap-1.5">
        <?php foreach ($placeholders as $p): ?><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600"><?= e($p) ?></code><?php endforeach; ?>
      </div>
      <p class="mt-3 text-xs text-slate-400">Values are inserted as text and HTML-escaped — templates cannot run PHP, JavaScript or SQL.</p>
    </div>
  </div>
</div>
<?php $this->stop(); ?>
