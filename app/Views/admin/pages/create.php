<?php
/** New page form. */
$this->layout('admin.layout');
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <a href="/admin/pages" class="text-sm text-slate-500 hover:text-brand-600">← Pages</a>
  <h1 class="mt-1 text-2xl font-semibold text-brand-900">New Page</h1>
</div>

<form method="post" action="/admin/pages" class="max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
  <?= csrf_field() ?>
  <div>
    <label class="field-label" for="title">Title</label>
    <input id="title" name="title" class="input-admin" required maxlength="200" autofocus>
  </div>
  <div>
    <label class="field-label" for="slug">Slug <span class="font-normal text-slate-400">(optional — generated from title)</span></label>
    <input id="slug" name="slug" class="input-admin" maxlength="190" placeholder="about-us">
  </div>
  <div>
    <label class="field-label" for="template">Template</label>
    <select id="template" name="template" class="input-admin max-w-xs">
      <option value="default">Default</option>
      <option value="contact">Contact (adds enquiry form)</option>
    </select>
  </div>
  <div class="flex gap-3 pt-2">
    <button class="btn btn-primary">Create page</button>
    <a href="/admin/pages" class="btn btn-ghost">Cancel</a>
  </div>
  <p class="text-xs text-slate-400">The page is created as a draft. You can add sections and publish it next.</p>
</form>
<?php $this->stop(); ?>
