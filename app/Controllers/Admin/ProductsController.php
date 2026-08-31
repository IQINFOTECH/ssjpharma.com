<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\DosageFormRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TherapeuticAreaRepository;
use App\Services\ProductService;

/**
 * Product admin. Permission map: view=products.view, create=products.create,
 * edit=products.edit (incl. images/docs/specs), publish=products.publish,
 * delete/archive=products.delete. Every action re-checks the permission.
 */
final class ProductsController extends AdminController
{
    private const STATUSES = ['draft', 'in_review', 'approved', 'published', 'archived'];

    /** Which permission each status transition requires (workflow gating, Phase 6). */
    private const STATUS_PERMISSION = [
        'draft'     => 'products.edit',
        'in_review' => 'products.edit',
        'approved'  => 'products.review',
        'published' => 'products.publish',
        'archived'  => 'products.archive',
    ];

    private function products(): ProductRepository           { return $this->container->get(ProductRepository::class); }
    private function categories(): ProductCategoryRepository { return $this->container->get(ProductCategoryRepository::class); }
    private function areas(): TherapeuticAreaRepository      { return $this->container->get(TherapeuticAreaRepository::class); }
    private function dosageForms(): DosageFormRepository     { return $this->container->get(DosageFormRepository::class); }
    private function service(): ProductService               { return $this->container->get(ProductService::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('products.view');
        $filters = [
            'q'        => trim((string) $request->query('q', '')),
            'category' => (int) $request->query('category', 0),
            'ta'       => (int) $request->query('ta', 0),
            'status'   => in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : '',
            'featured' => $request->query('featured') === '1' ? '1' : '',
            'dosage'   => (int) $request->query('dosage', 0),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $result = $this->products()->paginateForAdmin($filters, $perPage, ($page - 1) * $perPage);

        return $this->adminView('admin.products.index', [
            'title'      => 'Products',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'filters'    => $filters,
            'page'       => $page,
            'totalPages' => (int) max(1, ceil($result['total'] / $perPage)),
            'categories' => $this->categories()->allForAdmin(),
            'areas'      => $this->areas()->allForAdmin(),
            'dosages'    => $this->dosageForms()->allOrdered(),
            'canCreate'  => $this->can('products.create'),
            'canPublish' => $this->can('products.publish'),
            'canDelete'  => $this->can('products.delete'),
        ], 'products');
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('products.create');
        return $this->formView(null, [], []);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('products.create');
        $result = $this->service()->create($this->input($request), $this->taIds($request), $this->specs($request), (int) $this->currentUserId());
        if (!$result['ok']) {
            $this->session()->flash('product_errors', $result['errors'] ?? []);
            $this->session()->flash('product_old', $this->input($request));
            return Response::redirect('/admin/products/create');
        }
        $this->flash('success', 'Product created as draft. Add images, documents and publish below.');
        return Response::redirect('/admin/products/' . $result['id'] . '/edit');
    }

    public function edit(Request $request): Response
    {
        $this->requirePermission('products.view');
        $id = (int) $request->route('id');
        $product = $this->products()->findById($id);
        if ($product === null) {
            throw new HttpException(404);
        }
        return $this->formView($product,
            $this->products()->therapeuticAreaIds($id),
            $this->products()->specifications($id),
        );
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        if ($this->products()->findById($id) === null) {
            throw new HttpException(404);
        }
        $result = $this->service()->update($id, $this->input($request), $this->taIds($request), $this->specs($request), (int) $this->currentUserId());
        if (!$result['ok']) {
            $this->session()->flash('product_errors', $result['errors'] ?? []);
            return Response::redirect('/admin/products/' . $id . '/edit');
        }
        $this->flash('success', 'Product saved.');
        return Response::redirect('/admin/products/' . $id . '/edit');
    }

    public function status(Request $request): Response
    {
        $id = (int) $request->route('id');
        $status = (string) $request->input('status', 'draft');
        // Gate by the permission the TARGET status requires (draft→edit, approved→review,
        // published→publish, archived→archive). Content is never auto-approved/published.
        $this->requirePermission(self::STATUS_PERMISSION[$status] ?? 'products.publish');
        $result = $this->service()->setStatus($id, $status, (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Status updated.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/products/' . $id . '/edit');
    }

    public function duplicate(Request $request): Response
    {
        $this->requirePermission('products.create');
        $id = (int) $request->route('id');
        $result = $this->service()->duplicate($id, (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Product duplicated as draft.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect($result['ok'] ? '/admin/products/' . $result['id'] . '/edit' : '/admin/products');
    }

    public function destroy(Request $request): Response
    {
        $this->requirePermission('products.delete');
        $id = (int) $request->route('id');
        $result = $this->service()->delete($id, (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Product archived.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/products');
    }

    // --- Images --------------------------------------------------------------

    public function uploadImage(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        $file = $_FILES['image'] ?? null;
        if (!is_array($file)) {
            $this->flash('error', 'No file received.');
            return Response::redirect('/admin/products/' . $id . '/edit#images');
        }
        $alt = trim((string) $request->input('alt_text', ''));
        $result = $this->service()->addImage($id, $file, $alt !== '' ? $alt : null, (bool) $request->input('is_primary'), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Image uploaded.' : ($result['error'] ?? 'Upload failed.'));
        return Response::redirect('/admin/products/' . $id . '/edit#images');
    }

    public function setPrimaryImage(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        $result = $this->service()->setPrimaryImage($id, (int) $request->route('imageId'), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Primary image set.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/products/' . $id . '/edit#images');
    }

    public function deleteImage(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        $result = $this->service()->deleteImage($id, (int) $request->route('imageId'), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Image removed.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/products/' . $id . '/edit#images');
    }

    // --- Documents -----------------------------------------------------------

    public function uploadDocument(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        $file = $_FILES['document'] ?? null;
        if (!is_array($file)) {
            $this->flash('error', 'No file received.');
            return Response::redirect('/admin/products/' . $id . '/edit#documents');
        }
        $result = $this->service()->addDocument($id, $file, (string) $request->input('display_name', ''), (string) $request->input('doc_type', 'document'), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Document uploaded.' : ($result['error'] ?? 'Upload failed.'));
        return Response::redirect('/admin/products/' . $id . '/edit#documents');
    }

    public function deleteDocument(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        $result = $this->service()->deleteDocument($id, (int) $request->route('docId'), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Document removed.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/products/' . $id . '/edit#documents');
    }

    // --- Helpers -------------------------------------------------------------

    private function formView(?array $product, array $taIds, array $specs): Response
    {
        $id = $product ? (int) $product['id'] : 0;
        return $this->adminView('admin.products.form', [
            'title'      => $product ? 'Edit: ' . $product['name'] : 'New Product',
            'product'    => $product,
            'productTas' => $taIds,
            'specs'      => $specs,
            'images'     => $id ? $this->products()->images($id) : [],
            'documents'  => $id ? $this->products()->documents($id) : [],
            'categories' => $this->categories()->allForAdmin(),
            'areas'      => $this->areas()->allForAdmin(),
            'dosages'    => $this->dosageForms()->allOrdered(),
            'statuses'   => self::STATUSES,
            'errors'     => (array) $this->session()->getFlash('product_errors', []),
            'old'        => (array) $this->session()->getFlash('product_old', []),
            'canEdit'    => $this->can('products.edit'),
            'canPublish' => $this->can('products.publish'),
            'canDelete'  => $this->can('products.delete'),
        ], 'products');
    }

    /** @return array<string,mixed> whitelisted product fields (mass-assignment safe) */
    private function input(Request $request): array
    {
        return [
            'name'             => (string) $request->input('name', ''),
            'code'             => (string) $request->input('code', ''),
            'slug'             => (string) $request->input('slug', ''),
            'short_description'=> (string) $request->input('short_description', ''),
            'description'      => (string) $request->input('description', ''),
            'status'           => (string) $request->input('status', 'draft'),
            'is_featured'      => $request->input('is_featured') ? 1 : 0,
            'is_demo'          => $request->input('is_demo') ? 1 : 0,
            'sort_order'       => (int) $request->input('sort_order', 0),
            'generic_name'     => (string) $request->input('generic_name', ''),
            'composition'      => (string) $request->input('composition', ''),
            'strength'         => (string) $request->input('strength', ''),
            'dosage_form_id'   => (int) $request->input('dosage_form_id', 0),
            'pack_size'        => (string) $request->input('pack_size', ''),
            'category_id'      => (int) $request->input('category_id', 0),
            'meta_title'       => (string) $request->input('meta_title', ''),
            'meta_description' => (string) $request->input('meta_description', ''),
            'canonical_url'    => (string) $request->input('canonical_url', ''),
            'og_image_id'      => (int) $request->input('og_image_id', 0),
            'robots'           => (string) $request->input('robots', ''),
        ];
    }

    /** @return array<int,int> */
    private function taIds(Request $request): array
    {
        return array_map('intval', (array) $request->input('therapeutic_areas', []));
    }

    /** @return array<int,array{title:string,value:string,unit:string}> */
    private function specs(Request $request): array
    {
        $titles = (array) $request->input('spec_title', []);
        $values = (array) $request->input('spec_value', []);
        $units  = (array) $request->input('spec_unit', []);
        $out = [];
        foreach ($titles as $i => $t) {
            $out[] = ['title' => (string) $t, 'value' => (string) ($values[$i] ?? ''), 'unit' => (string) ($units[$i] ?? '')];
        }
        return $out;
    }
}
