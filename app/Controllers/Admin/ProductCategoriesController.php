<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProductCategoryRepository;
use App\Services\ProductCategoryService;

/**
 * Product-category admin (nested). view=products.view, create=products.create,
 * edit=products.edit, publish=products.publish, delete=products.delete.
 */
final class ProductCategoriesController extends AdminController
{
    private function repo(): ProductCategoryRepository { return $this->container->get(ProductCategoryRepository::class); }
    private function service(): ProductCategoryService  { return $this->container->get(ProductCategoryService::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('products.view');
        $rows = $this->repo()->allForAdmin();
        // Build a name lookup + product counts for display.
        $byId = [];
        foreach ($rows as $r) { $byId[(int) $r['id']] = $r['name']; }
        foreach ($rows as &$r) {
            $r['parent_name'] = $r['parent_id'] ? ($byId[(int) $r['parent_id']] ?? '—') : '';
            $r['product_count'] = $this->repo()->productCount((int) $r['id']);
        }
        unset($r);
        return $this->adminView('admin.product_categories.index', [
            'title'     => 'Product Categories',
            'rows'      => $rows,
            'canCreate' => $this->can('products.create'),
            'canDelete' => $this->can('products.delete'),
        ], 'product_categories');
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('products.create');
        return $this->adminView('admin.product_categories.form', [
            'title'    => 'New Category',
            'category' => null,
            'parents'  => $this->repo()->allForAdmin(),
        ], 'product_categories');
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('products.create');
        $result = $this->service()->create($this->input($request), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Category created.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect($result['ok'] ? '/admin/product-categories/' . $result['id'] . '/edit' : '/admin/product-categories/create');
    }

    public function edit(Request $request): Response
    {
        $this->requirePermission('products.view');
        $id = (int) $request->route('id');
        $category = $this->repo()->findById($id);
        if ($category === null) {
            throw new HttpException(404);
        }
        // Exclude self + descendants from parent options (cycle prevention in UI).
        $blocked = $this->repo()->idWithDescendants($id);
        $parents = array_values(array_filter($this->repo()->allForAdmin(), static fn ($c) => !in_array((int) $c['id'], $blocked, true)));
        return $this->adminView('admin.product_categories.form', [
            'title'    => 'Edit: ' . $category['name'],
            'category' => $category,
            'parents'  => $parents,
            'canEdit'  => $this->can('products.edit'),
        ], 'product_categories');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        $result = $this->service()->update($id, $this->input($request), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Category updated.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/product-categories/' . $id . '/edit');
    }

    public function status(Request $request): Response
    {
        $this->requirePermission('products.publish');
        $id = (int) $request->route('id');
        $result = $this->service()->setStatus($id, (string) $request->input('status', 'draft'), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Status updated.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/product-categories/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        $this->requirePermission('products.delete');
        $id = (int) $request->route('id');
        $result = $this->service()->delete($id, (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Category archived.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/product-categories');
    }

    /** @return array<string,mixed> */
    private function input(Request $request): array
    {
        return [
            'parent_id'        => (int) $request->input('parent_id', 0),
            'name'             => (string) $request->input('name', ''),
            'slug'             => (string) $request->input('slug', ''),
            'description'      => (string) $request->input('description', ''),
            'image_id'         => (int) $request->input('image_id', 0),
            'meta_title'       => (string) $request->input('meta_title', ''),
            'meta_description' => (string) $request->input('meta_description', ''),
            'status'           => (string) $request->input('status', 'draft'),
            'sort_order'       => (int) $request->input('sort_order', 0),
            'is_demo'          => $request->input('is_demo') ? 1 : 0,
        ];
    }
}
