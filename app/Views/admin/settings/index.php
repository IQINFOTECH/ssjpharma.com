<?php
/** @var array $groups  group => rows[] */
$this->layout('admin.layout');
$groupLabels = [
    'company' => 'Company', 'website' => 'Website', 'social' => 'Social',
    'lead' => 'Leads', 'whatsapp' => 'WhatsApp', 'analytics' => 'Analytics', 'security' => 'Security',
];
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-brand-900">Settings</h1>
  <p class="mt-1 text-sm text-slate-500">Global site configuration. Secrets (SMTP, API keys) are set in <code class="rounded bg-slate-100 px-1">.env</code>, not here.</p>
</div>

<form method="post" action="/admin/settings" class="space-y-8">
  <?= csrf_field() ?>
  <?php foreach ($groups as $group => $rows): ?>
    <div class="rounded-xl border border-slate-200 bg-white">
      <div class="border-b border-slate-100 px-5 py-4">
        <h2 class="font-semibold text-brand-900"><?= e($groupLabels[$group] ?? ucfirst($group)) ?></h2>
      </div>
      <div class="grid gap-5 p-5 sm:grid-cols-2">
        <?php foreach ($rows as $row): $key = $row['key']; $val = (string) ($row['value'] ?? ''); $name = 'settings[' . e($key) . ']'; ?>
          <div class="<?= in_array($row['type'], ['text'], true) ? 'sm:col-span-2' : '' ?>">
            <label class="field-label" for="s_<?= e($key) ?>"><?= e($row['label'] ?? $key) ?></label>
            <?php if ($row['type'] === 'text'): ?>
              <textarea id="s_<?= e($key) ?>" name="<?= $name ?>" rows="3" class="input-admin"><?= e($val) ?></textarea>
            <?php elseif ($row['type'] === 'bool'): ?>
              <label class="mt-1 inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="<?= $name ?>" value="1" class="rounded border-slate-300 text-brand-600" <?= $val === '1' ? 'checked' : '' ?>> Enabled
              </label>
            <?php elseif ($row['type'] === 'media'): ?>
              <input id="s_<?= e($key) ?>" name="<?= $name ?>" value="<?= e($val) ?>" class="input-admin" placeholder="Media ID">
              <p class="mt-1 text-xs text-slate-400">Media ID from the <a href="/admin/media" class="text-brand-600">library</a>.</p>
            <?php else: ?>
              <input id="s_<?= e($key) ?>" type="<?= $row['type'] === 'email' ? 'email' : ($row['type'] === 'url' ? 'url' : 'text') ?>" name="<?= $name ?>" value="<?= e($val) ?>" class="input-admin">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="sticky bottom-4">
    <button class="btn btn-primary shadow-lg">Save settings</button>
  </div>
</form>
<?php $this->stop(); ?>
