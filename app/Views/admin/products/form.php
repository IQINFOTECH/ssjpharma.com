<?php
/** @var array|null $product @var array $productTas @var array $specs @var array $images @var array $documents
 *  @var array $categories @var array $areas @var array $dosages @var array $statuses
 *  @var array $errors @var array $old @var bool $canEdit @var bool $canPublish @var bool $canDelete */
$this->layout('admin.layout');
$isEdit = $product !== null;
$pid = $isEdit ? (int) $product['id'] : 0;
$val = static fn (string $k, $d = '') => e((string) ($old[$k] ?? ($product[$k] ?? $d)));
$err = static function (string $k) use ($errors): string { return isset($errors[$k]) ? '<p class="error-text">' . e($errors[$k]) . '</p>' : ''; };
$tas = array_map('intval', $productTas);
$tabs = [
    'basic' => 'Basic', 'info' => 'Product Info', 'cats' => 'Categories & Areas',
    'images' => 'Images', 'docs' => 'Documents', 'seo' => 'SEO', 'publish' => 'Publishing',
];
?>
<?php $this->start('content'); ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div><a href="/admin/products" class="text-sm text-slate-500 hover:text-brand-600">← Products</a>
    <h1 class="mt-1 text-2xl font-semibold text-brand-900"><?= $isEdit ? e($product['name']) : 'New Product' ?>
      <?php if ($isEdit && (int) $product['is_demo'] === 1): ?><span class="badge badge-amber ml-1 align-middle">Demo</span><?php endif; ?></h1></div>
  <?php if ($isEdit): ?>
  <div class="flex items-center gap-2">
    <a href="/products/<?= e($product['slug']) ?>" target="_blank" rel="noopener" class="btn btn-ghost">Preview ↗</a>
    <?php if ($canPublish): ?>
    <form method="post" action="/admin/products/<?= $pid ?>/status" class="inline">
      <?= csrf_field() ?>
      <?php if ($product['status'] === 'published'): ?>
        <input type="hidden" name="status" value="draft"><button class="btn btn-ghost">Unpublish</button>
      <?php else: ?>
        <input type="hidden" name="status" value="published"><button class="btn btn-primary">Publish</button>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($errors['_form'])): ?><div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($errors['_form']) ?></div><?php endif; ?>

<!-- Tabs -->
<div class="mb-5 flex flex-wrap gap-1 border-b border-slate-200">
  <?php foreach ($tabs as $key => $label): ?>
    <button type="button" class="js-tab -mb-px border-b-2 px-4 py-2.5 text-sm font-medium <?= $key === 'basic' ? 'border-brand-500 text-brand-700' : 'border-transparent text-slate-500 hover:text-brand-600' ?>" data-tab="<?= $key ?>"><?= e($label) ?></button>
  <?php endforeach; ?>
</div>

<form method="post" action="<?= $isEdit ? '/admin/products/' . $pid : '/admin/products' ?>" class="space-y-6">
  <?= csrf_field() ?><?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

  <!-- TAB: Basic -->
  <section data-panel="basic" class="rounded-xl border border-slate-200 bg-white p-6">
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2"><label class="field-label">Product Name <span class="text-red-500">*</span></label><input name="name" class="input-admin" value="<?= $val('name') ?>" maxlength="200" required><?= $err('name') ?></div>
      <div><label class="field-label">Product Code</label><input name="code" class="input-admin" value="<?= $val('code') ?>" maxlength="80"><?= $err('code') ?></div>
      <div><label class="field-label">Slug</label><input name="slug" class="input-admin" value="<?= $val('slug') ?>" maxlength="200" placeholder="auto from name"></div>
      <div class="sm:col-span-2"><label class="field-label">Short Description</label><textarea name="short_description" rows="2" class="input-admin" maxlength="500"><?= $val('short_description') ?></textarea></div>
      <div class="sm:col-span-2"><label class="field-label">Full Description <span class="font-normal text-slate-400">(basic HTML; sanitised)</span></label><textarea name="description" rows="6" class="input-admin font-mono text-xs"><?= $val('description') ?></textarea></div>
      <div class="flex items-center gap-4">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_featured" value="1" class="rounded border-slate-300 text-brand-600" <?= !empty($old['is_featured']) || (int) ($product['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>> Featured</label>
        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_demo" value="1" class="rounded border-slate-300 text-amber-600" <?= !empty($old['is_demo']) || (int) ($product['is_demo'] ?? 0) === 1 ? 'checked' : '' ?>> Demo (not production)</label>
      </div>
      <div><label class="field-label">Sort order</label><input type="number" name="sort_order" class="input-admin" value="<?= $val('sort_order', '0') ?>"></div>
    </div>
  </section>

  <!-- TAB: Product Info -->
  <section data-panel="info" class="hidden rounded-xl border border-slate-200 bg-white p-6">
    <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">These fields are OPTIONAL. Only enter information supplied by SSJ Pharmaceuticals. Do not invent compositions, strengths, or claims.</p>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="field-label">Generic Name</label><input name="generic_name" class="input-admin" value="<?= $val('generic_name') ?>" maxlength="255"></div>
      <div><label class="field-label">Strength</label><input name="strength" class="input-admin" value="<?= $val('strength') ?>" maxlength="120"></div>
      <div><label class="field-label">Dosage Form</label>
        <select name="dosage_form_id" class="input-admin"><option value="0">—</option>
          <?php foreach ($dosages as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) ($product['dosage_form_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="field-label">Pack Size</label><input name="pack_size" class="input-admin" value="<?= $val('pack_size') ?>" maxlength="120"></div>
      <div class="sm:col-span-2"><label class="field-label">Composition</label><textarea name="composition" rows="3" class="input-admin"><?= $val('composition') ?></textarea></div>
    </div>

    <div class="mt-6">
      <div class="mb-2 flex items-center justify-between"><span class="field-label mb-0">Specifications</span></div>
      <p class="mb-2 text-xs text-slate-400">Structured key / value / unit. Examples only — enter real data.</p>
      <div id="spec-rows" class="space-y-2">
        <?php $specRows = $specs ?: [['title' => '', 'value' => '', 'unit' => '']];
        foreach ($specRows as $s): ?>
          <div class="grid grid-cols-12 gap-2 js-spec-row">
            <input name="spec_title[]" value="<?= e($s['title'] ?? '') ?>" placeholder="Title" class="input-admin col-span-5 py-1">
            <input name="spec_value[]" value="<?= e($s['value'] ?? '') ?>" placeholder="Value" class="input-admin col-span-4 py-1">
            <input name="spec_unit[]" value="<?= e($s['unit'] ?? '') ?>" placeholder="Unit" class="input-admin col-span-2 py-1">
            <button type="button" class="js-spec-remove col-span-1 text-red-400 hover:text-red-600">✕</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" id="spec-add" class="mt-2 text-sm font-medium text-brand-600 hover:text-brand-700">+ Add specification</button>
    </div>
  </section>

  <!-- TAB: Categories & Areas -->
  <section data-panel="cats" class="hidden rounded-xl border border-slate-200 bg-white p-6">
    <div class="grid gap-6 sm:grid-cols-2">
      <div>
        <label class="field-label">Category</label>
        <select name="category_id" class="input-admin"><option value="0">—</option>
          <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
      </div>
      <div>
        <span class="field-label">Therapeutic Areas</span>
        <div class="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-3">
          <?php if (empty($areas)): ?><p class="text-sm text-slate-400">None yet.</p><?php endif; ?>
          <?php foreach ($areas as $a): ?>
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="therapeutic_areas[]" value="<?= (int) $a['id'] ?>" class="rounded border-slate-300 text-brand-600" <?= in_array((int) $a['id'], $tas, true) ? 'checked' : '' ?>> <?= e($a['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- TAB: SEO -->
  <section data-panel="seo" class="hidden rounded-xl border border-slate-200 bg-white p-6">
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2"><label class="field-label">SEO Title</label><input name="meta_title" class="input-admin" value="<?= $val('meta_title') ?>" maxlength="255"></div>
      <div class="sm:col-span-2"><label class="field-label">Meta Description</label><textarea name="meta_description" rows="2" class="input-admin" maxlength="320"><?= $val('meta_description') ?></textarea></div>
      <div><label class="field-label">Canonical URL</label><input name="canonical_url" class="input-admin" value="<?= $val('canonical_url') ?>" maxlength="255"></div>
      <div><label class="field-label">Robots</label><input name="robots" class="input-admin" value="<?= $val('robots') ?>" placeholder="index,follow" maxlength="60"></div>
      <div><label class="field-label">OG Image (Media ID)</label><input name="og_image_id" class="input-admin" value="<?= $val('og_image_id') ?>"></div>
    </div>
  </section>

  <!-- TAB: Publishing -->
  <section data-panel="publish" class="hidden rounded-xl border border-slate-200 bg-white p-6">
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="field-label">Status</label>
        <select name="status" class="input-admin"><?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= ($product['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
        <p class="mt-1 text-xs text-slate-400">New products start as Draft.</p>
      </div>
      <?php if ($isEdit): ?>
      <div class="text-sm text-slate-500">
        <p><span class="text-slate-400">Created:</span> <?= e(substr((string) $product['created_at'], 0, 16)) ?></p>
        <p class="mt-1"><span class="text-slate-400">Published:</span> <?= e($product['published_at'] ? substr((string) $product['published_at'], 0, 16) : '—') ?></p>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <div class="flex gap-3"><button class="btn btn-primary"><?= $isEdit ? 'Save product' : 'Save draft' ?></button><a href="/admin/products" class="btn btn-ghost">Cancel</a></div>
</form>

<!-- TAB: Images (separate forms — outside the main product form) -->
<section data-panel="images" id="images" class="hidden">
  <?php if (!$isEdit): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-400">Save the product first, then add images.</div>
  <?php else: ?>
    <form method="post" action="/admin/products/<?= $pid ?>/images" enctype="multipart/form-data" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5">
      <?= csrf_field() ?>
      <div><label class="field-label">Image</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required class="text-sm"></div>
      <div class="flex-1 min-w-[180px]"><label class="field-label">Alt text</label><input name="alt_text" class="input-admin" maxlength="255"></div>
      <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="is_primary" value="1" class="rounded border-slate-300 text-brand-600"> Primary</label>
      <button class="btn btn-primary">Upload</button>
      <p class="w-full text-xs text-slate-400">JPG, PNG, WEBP. Validated server-side.</p>
    </form>
    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
      <?php foreach ($images as $img): ?>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
          <div class="flex h-36 items-center justify-center bg-slate-50"><img src="<?= e($img['url_path']) ?>" alt="<?= e($img['alt_text'] ?? '') ?>" class="h-full w-full object-contain"></div>
          <div class="p-3">
            <?php if ((int) $img['is_primary'] === 1): ?><span class="badge badge-green">Primary</span><?php else: ?>
              <form method="post" action="/admin/products/<?= $pid ?>/images/<?= (int) $img['id'] ?>/primary" class="inline"><?= csrf_field() ?><button class="text-xs text-brand-600 hover:text-brand-700">Set primary</button></form>
            <?php endif; ?>
            <form method="post" action="/admin/products/<?= $pid ?>/images/<?= (int) $img['id'] ?>/delete" class="js-confirm mt-1" data-confirm="Remove image?"><?= csrf_field() ?><button class="text-xs text-red-500 hover:text-red-700">Delete</button></form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($images)): ?><p class="text-sm text-slate-400">No images yet.</p><?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<!-- TAB: Documents -->
<section data-panel="docs" id="documents" class="hidden">
  <?php if (!$isEdit): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-400">Save the product first, then add documents.</div>
  <?php else: ?>
    <form method="post" action="/admin/products/<?= $pid ?>/documents" enctype="multipart/form-data" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5">
      <?= csrf_field() ?>
      <div><label class="field-label">PDF file</label><input type="file" name="document" accept=".pdf" required class="text-sm"></div>
      <div class="flex-1 min-w-[180px]"><label class="field-label">Display name</label><input name="display_name" class="input-admin" maxlength="200" placeholder="e.g. Specification Sheet"></div>
      <div><label class="field-label">Type</label>
        <select name="doc_type" class="input-admin">
          <option value="spec_sheet">Specification Sheet</option><option value="brochure">Brochure</option>
          <option value="technical">Technical Document</option><option value="document">Document</option>
        </select></div>
      <button class="btn btn-primary">Upload</button>
      <p class="w-full text-xs text-slate-400">PDF only. Do not label as a regulatory certificate unless it is one.</p>
    </form>
    <div class="rounded-xl border border-slate-200 bg-white">
      <?php if (empty($documents)): ?><p class="px-5 py-8 text-center text-sm text-slate-400">No documents yet.</p><?php else: foreach ($documents as $doc): ?>
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
          <div><a href="<?= e($doc['url_path']) ?>" target="_blank" rel="noopener" class="font-medium text-brand-700 hover:underline"><?= e($doc['display_name']) ?></a>
            <span class="ml-2 text-xs text-slate-400"><?= e(strtoupper(str_replace('_', ' ', $doc['doc_type']))) ?> · <?= e(number_format($doc['size_bytes'] / 1024, 0)) ?> KB</span></div>
          <form method="post" action="/admin/products/<?= $pid ?>/documents/<?= (int) $doc['id'] ?>/delete" class="js-confirm" data-confirm="Remove document?"><?= csrf_field() ?><button class="text-xs text-red-500 hover:text-red-700">Delete</button></form>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($isEdit && ($canDelete ?? true)): ?>
<form method="post" action="/admin/products/<?= $pid ?>/delete" class="js-confirm mt-6" data-confirm="Archive this product?">
  <?= csrf_field() ?><?= method_field('DELETE') ?>
  <button class="text-sm font-medium text-red-500 hover:text-red-700">Archive product</button>
</form>
<?php endif; ?>

<?php $this->stop(); ?>
