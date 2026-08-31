<?php
/** @var string $subject @var string $html */
$this->layout('admin.layout');
$srcdoc = htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
?>
<?php $this->start('content'); ?>
<div class="mb-4">
  <h1 class="text-2xl font-semibold text-brand-900">Template preview</h1>
  <p class="mt-1 text-sm text-slate-500">Rendered with clearly-labelled <strong>demo</strong> values. Nothing has been sent.</p>
</div>
<div class="rounded-xl border border-slate-200 bg-white p-6">
  <h2 class="mb-2 text-sm font-semibold text-brand-900">Subject</h2>
  <p class="mb-4 text-sm text-slate-700"><?= e($subject) ?></p>
  <h2 class="mb-2 text-sm font-semibold text-brand-900">Body (sandboxed)</h2>
  <iframe sandbox class="h-[32rem] w-full rounded-lg border border-slate-200" srcdoc="<?= $srcdoc ?>" title="Preview"></iframe>
</div>
<?php $this->stop(); ?>
