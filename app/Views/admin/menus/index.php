<?php
/** @var array $menus  each ['menu'=>row,'items'=>rows]  @var array $pages */
$this->layout('admin.layout');

$pageOptions = static function (array $pages, int $selected): string {
    $html = '<option value="0">— No page —</option>';
    foreach ($pages as $p) {
        $sel = $selected === (int) $p['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $p['id'] . '"' . $sel . '>' . e($p['title']) . '</option>';
    }
    return $html;
};
$parentOptions = static function (array $items, int $selected, int $selfId): string {
    $html = '<option value="0">— Top level —</option>';
    foreach ($items as $it) {
        if ((int) $it['id'] === $selfId || $it['parent_id'] !== null) { continue; } // only top-level as parents
        $sel = $selected === (int) $it['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $it['id'] . '"' . $sel . '>' . e($it['label']) . '</option>';
    }
    return $html;
};
?>
<?php $this->start('content'); ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-brand-900">Menus</h1>
  <p class="mt-1 text-sm text-slate-500">Navigation is data-driven — edit labels, links, order and nesting here.</p>
</div>

<div class="space-y-8">
<?php foreach ($menus as $block): $menu = $block['menu']; $items = $block['items']; ?>
  <div class="rounded-xl border border-slate-200 bg-white">
    <div class="border-b border-slate-100 px-5 py-4">
      <h2 class="font-semibold text-brand-900"><?= e($menu['name']) ?> <span class="ml-1 font-mono text-xs text-slate-400"><?= e($menu['key']) ?></span></h2>
    </div>

    <div class="divide-y divide-slate-100">
      <?php if (empty($items)): ?>
        <p class="px-5 py-6 text-sm text-slate-400">No items yet.</p>
      <?php else: foreach ($items as $item): $iid = (int) $item['id']; ?>
        <form id="mi-edit-<?= $iid ?>" method="post" action="/admin/menu-items/<?= $iid ?>" class="grid gap-3 px-5 py-4 md:grid-cols-12 md:items-end">
          <?= csrf_field() ?>
          <?= method_field('PUT') ?>
          <input type="hidden" name="menu_id" value="<?= (int) $menu['id'] ?>">
          <div class="md:col-span-3">
            <label class="field-label text-xs">Label</label>
            <input name="label" value="<?= e($item['label']) ?>" class="input-admin">
          </div>
          <div class="md:col-span-2">
            <label class="field-label text-xs">Page</label>
            <select name="page_id" class="input-admin"><?= $pageOptions($pages, (int) ($item['page_id'] ?? 0)) ?></select>
          </div>
          <div class="md:col-span-3">
            <label class="field-label text-xs">or URL</label>
            <input name="url" value="<?= e($item['url'] ?? '') ?>" class="input-admin" placeholder="/path or https://">
          </div>
          <div class="md:col-span-2">
            <label class="field-label text-xs">Parent</label>
            <select name="parent_id" class="input-admin"><?= $parentOptions($items, (int) ($item['parent_id'] ?? 0), $iid) ?></select>
          </div>
          <div class="md:col-span-1">
            <label class="field-label text-xs">Order</label>
            <input type="number" name="sort_order" value="<?= (int) $item['sort_order'] ?>" class="input-admin">
          </div>
          <div class="flex items-center gap-3 md:col-span-7">
            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-brand-600" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="open_new_tab" value="1" class="rounded border-slate-300 text-brand-600" <?= (int) $item['open_new_tab'] === 1 ? 'checked' : '' ?>> New tab</label>
          </div>
          <div class="flex items-center justify-end gap-4 md:col-span-5">
            <button class="btn btn-ghost py-2">Save</button>
            <!-- Delete submits its own form (below) via the HTML5 form= attribute,
                 so it stays a distinct POST while sitting in this row's action bar. -->
            <button type="submit" form="mi-del-<?= $iid ?>" class="text-xs font-medium text-red-500 hover:text-red-700">Delete item</button>
          </div>
        </form>
        <form id="mi-del-<?= $iid ?>" method="post" action="/admin/menu-items/<?= $iid ?>/delete" class="hidden" onsubmit="return confirm('Remove this menu item?');">
          <?= csrf_field() ?>
          <input type="hidden" name="menu_id" value="<?= (int) $menu['id'] ?>">
        </form>
      <?php endforeach; endif; ?>
    </div>

    <!-- Add item -->
    <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4">
      <form method="post" action="/admin/menus/<?= (int) $menu['id'] ?>/items" class="grid gap-3 md:grid-cols-12 md:items-end">
        <?= csrf_field() ?>
        <div class="md:col-span-3">
          <label class="field-label text-xs">New item label</label>
          <input name="label" class="input-admin" placeholder="Label" required>
        </div>
        <div class="md:col-span-2">
          <label class="field-label text-xs">Page</label>
          <select name="page_id" class="input-admin"><?= $pageOptions($pages, 0) ?></select>
        </div>
        <div class="md:col-span-3">
          <label class="field-label text-xs">or URL</label>
          <input name="url" class="input-admin" placeholder="/path or https://">
        </div>
        <div class="md:col-span-2">
          <label class="field-label text-xs">Parent</label>
          <select name="parent_id" class="input-admin"><?= $parentOptions($items, 0, 0) ?></select>
        </div>
        <div class="md:col-span-1">
          <label class="field-label text-xs">Order</label>
          <input type="number" name="sort_order" value="0" class="input-admin">
        </div>
        <input type="hidden" name="is_active" value="1">
        <div class="md:col-span-1"><button class="btn btn-primary py-2 w-full">Add</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php $this->stop(); ?>
