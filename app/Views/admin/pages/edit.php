<?php
/** @var array $page @var array $sections @var array $statuses @var array $sectionTypes */
$this->layout('admin.layout');
$pid = (int) $page['id'];

/** Render one field input for a section. */
$renderField = static function (string $name, array $meta, array $data): string {
    $label = e($meta['label']);
    $val = $data[$name] ?? '';
    $id = 'f_' . $name . '_' . random_int(1000, 9999);
    $html = '<div class="mt-3"><label class="field-label" for="' . $id . '">' . $label . '</label>';
    switch ($meta['type']) {
        case 'textarea':
            $html .= '<textarea id="' . $id . '" name="data[' . e($name) . ']" rows="2" class="input-admin">' . e((string) $val) . '</textarea>';
            break;
        case 'richtext':
            $html .= '<textarea id="' . $id . '" name="data[' . e($name) . ']" rows="5" class="input-admin font-mono text-xs">' . e((string) $val) . '</textarea>';
            $html .= '<p class="mt-1 text-xs text-slate-400">Basic HTML allowed (sanitised on save).</p>';
            break;
        case 'select':
            $html .= '<select id="' . $id . '" name="data[' . e($name) . ']" class="input-admin max-w-xs">';
            foreach (($meta['options'] ?? []) as $opt) {
                $sel = ((string) $val === (string) $opt) ? ' selected' : '';
                $html .= '<option value="' . e($opt) . '"' . $sel . '>' . e(ucfirst($opt)) . '</option>';
            }
            $html .= '</select>';
            break;
        case 'media':
            $html .= '<input id="' . $id . '" name="data[' . e($name) . ']" value="' . e((string) $val) . '" class="input-admin max-w-xs" placeholder="Media ID">';
            $html .= '<p class="mt-1 text-xs text-slate-400">Enter a Media ID (see <a href="/admin/media" class="text-brand-600">Media library</a>).</p>';
            break;
        case 'repeater':
            $json = is_array($val) ? json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '[]';
            $subs = implode(', ', array_keys($meta['subfields'] ?? []));
            $html .= '<textarea id="' . $id . '" name="data[' . e($name) . ']" rows="6" class="input-admin font-mono text-xs">' . e((string) $json) . '</textarea>';
            $html .= '<p class="mt-1 text-xs text-slate-400">JSON array of objects with keys: ' . e($subs) . '</p>';
            break;
        default: // text
            $html .= '<input id="' . $id . '" name="data[' . e($name) . ']" value="' . e((string) $val) . '" class="input-admin">';
    }
    return $html . '</div>';
};
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <a href="/admin/pages" class="text-sm text-slate-500 hover:text-brand-600">← Pages</a>
    <h1 class="mt-1 text-2xl font-semibold text-brand-900"><?= e($page['title']) ?></h1>
  </div>
  <div class="flex items-center gap-2">
    <a href="<?= (int) $page['is_home'] === 1 ? '/' : '/' . e($page['slug']) ?>" target="_blank" rel="noopener" class="btn btn-ghost">Preview ↗</a>
    <form method="post" action="/admin/pages/<?= $pid ?>/status" class="inline">
      <?= csrf_field() ?>
      <?php if ($page['status'] === 'published'): ?>
        <input type="hidden" name="status" value="draft">
        <button class="btn btn-ghost">Unpublish</button>
      <?php else: ?>
        <input type="hidden" name="status" value="published">
        <button class="btn btn-primary">Publish</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <!-- Page detail -->
  <form method="post" action="/admin/pages/<?= $pid ?>" class="lg:col-span-2 space-y-5 rounded-xl border border-slate-200 bg-white p-6">
    <?= csrf_field() ?>
    <?= method_field('PUT') ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label class="field-label" for="title">Title</label>
        <input id="title" name="title" class="input-admin" value="<?= e($page['title']) ?>" maxlength="200">
      </div>
      <div>
        <label class="field-label" for="slug">Slug</label>
        <input id="slug" name="slug" class="input-admin" value="<?= e($page['slug']) ?>" maxlength="190">
      </div>
      <div>
        <label class="field-label" for="status">Status</label>
        <select id="status" name="status" class="input-admin">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $page['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="field-label" for="template">Template</label>
        <select id="template" name="template" class="input-admin">
          <option value="default" <?= $page['template'] === 'default' ? 'selected' : '' ?>>Default</option>
          <option value="contact" <?= $page['template'] === 'contact' ? 'selected' : '' ?>>Contact (enquiry form)</option>
        </select>
      </div>
      <div class="flex items-end">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="is_home" value="1" class="rounded border-slate-300 text-brand-600" <?= (int) $page['is_home'] === 1 ? 'checked' : '' ?>>
          Set as home page
        </label>
      </div>
    </div>

    <details class="rounded-lg border border-slate-200 p-4">
      <summary class="cursor-pointer text-sm font-medium text-brand-900">SEO & metadata</summary>
      <div class="mt-4 space-y-4">
        <div>
          <label class="field-label" for="meta_title">SEO title</label>
          <input id="meta_title" name="meta_title" class="input-admin" value="<?= e($page['meta_title'] ?? '') ?>" maxlength="255">
        </div>
        <div>
          <label class="field-label" for="meta_description">Meta description</label>
          <textarea id="meta_description" name="meta_description" rows="2" class="input-admin" maxlength="320"><?= e($page['meta_description'] ?? '') ?></textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="canonical_url">Canonical URL</label>
            <input id="canonical_url" name="canonical_url" class="input-admin" value="<?= e($page['canonical_url'] ?? '') ?>" maxlength="255">
          </div>
          <div>
            <label class="field-label" for="robots">Robots</label>
            <input id="robots" name="robots" class="input-admin" value="<?= e($page['robots'] ?? '') ?>" placeholder="index,follow" maxlength="60">
          </div>
          <div>
            <label class="field-label" for="og_image_id">OG image (Media ID)</label>
            <input id="og_image_id" name="og_image_id" class="input-admin" value="<?= e($page['og_image_id'] ?? '') ?>">
          </div>
          <div>
            <label class="field-label" for="featured_image_id">Featured image (Media ID)</label>
            <input id="featured_image_id" name="featured_image_id" class="input-admin" value="<?= e($page['featured_image_id'] ?? '') ?>">
          </div>
        </div>
      </div>
    </details>

    <div class="flex gap-3">
      <button class="btn btn-primary">Save page</button>
    </div>
  </form>

  <!-- Meta / danger -->
  <div class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
      <p><span class="text-slate-400">Created:</span> <?= e(substr((string) $page['created_at'], 0, 16)) ?></p>
      <p class="mt-1"><span class="text-slate-400">Updated:</span> <?= e(substr((string) $page['updated_at'], 0, 16)) ?></p>
    </div>
    <?php if ((int) $page['is_home'] !== 1): ?>
    <form method="post" action="/admin/pages/<?= $pid ?>/delete" class="rounded-xl border border-red-200 bg-red-50/50 p-5" onsubmit="return confirm('Delete this page? This cannot be undone.');">
      <?= csrf_field() ?>
      <?= method_field('DELETE') ?>
      <h3 class="text-sm font-semibold text-red-700">Delete page</h3>
      <p class="mt-1 text-xs text-red-600/80">Soft-deletes this page and its sections.</p>
      <button class="btn mt-3 border border-red-300 bg-white text-red-600 hover:bg-red-50">Delete</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Sections editor -->
<div id="sections" class="mt-10">
  <div class="mb-4 flex items-center justify-between">
    <h2 class="text-lg font-semibold text-brand-900">Sections</h2>
    <form method="post" action="/admin/pages/<?= $pid ?>/sections" class="flex items-center gap-2">
      <?= csrf_field() ?>
      <select name="type" class="input-admin max-w-[220px]">
        <?php foreach ($sectionTypes as $key => $def): ?>
          <option value="<?= e($key) ?>"><?= e($def['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary">Add section</button>
    </form>
  </div>

  <?php if (empty($sections)): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-400">No sections yet. Add one above.</div>
  <?php else: foreach ($sections as $sec):
      $type = (string) $sec['type'];
      $def = $sectionTypes[$type] ?? null;
      $data = [];
      if (!empty($sec['data'])) { $decoded = json_decode((string) $sec['data'], true); $data = is_array($decoded) ? $decoded : []; }
  ?>
    <form method="post" action="/admin/sections/<?= (int) $sec['id'] ?>" class="mb-4 rounded-xl border border-slate-200 bg-white p-5">
      <?= csrf_field() ?>
      <?= method_field('PUT') ?>
      <input type="hidden" name="page_id" value="<?= $pid ?>">
      <div class="mb-2 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="badge badge-slate"><?= e($def['label'] ?? $type) ?></span>
          <label class="inline-flex items-center gap-1.5 text-xs text-slate-500">
            <input type="checkbox" name="is_visible" value="1" class="rounded border-slate-300 text-brand-600" <?= (int) $sec['is_visible'] === 1 ? 'checked' : '' ?>> Visible
          </label>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-xs text-slate-500">Order <input type="number" name="sort_order" value="<?= (int) $sec['sort_order'] ?>" class="input-admin ml-1 w-20 py-1"></label>
        </div>
      </div>
      <?php if ($def !== null): foreach ($def['fields'] as $fname => $fmeta): ?>
        <?= $renderField($fname, $fmeta, $data) ?>
      <?php endforeach; else: ?>
        <p class="text-sm text-red-600">Unknown section type.</p>
      <?php endif; ?>
      <div class="mt-4">
        <button class="btn btn-primary">Save section</button>
      </div>
    </form>
    <form method="post" action="/admin/sections/<?= (int) $sec['id'] ?>/delete" class="-mt-3 mb-4 px-5"
          onsubmit="return confirm('Remove this section?');">
      <?= csrf_field() ?>
      <input type="hidden" name="page_id" value="<?= $pid ?>">
      <button class="text-xs font-medium text-red-500 hover:text-red-700">Remove section</button>
    </form>
  <?php endforeach; endif; ?>
</div>
<?php $this->stop(); ?>
