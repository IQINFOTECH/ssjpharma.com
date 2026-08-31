<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\TherapeuticAreaRepository;
use App\Services\TherapeuticAreaService;

/**
 * Therapeutic-area admin. Same permission mapping as categories.
 */
final class TherapeuticAreasController extends AdminController
{
    private function repo(): TherapeuticAreaRepository { return $this->container->get(TherapeuticAreaRepository::class); }
    private function service(): TherapeuticAreaService  { return $this->container->get(TherapeuticAreaService::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('products.view');
        $rows = $this->repo()->allForAdmin();
        foreach ($rows as &$r) { $r['product_count'] = $this->repo()->productCount((int) $r['id']); }
        unset($r);
        return $this->adminView('admin.therapeutic_areas.index', [
            'title'     => 'Therapeutic Areas',
            'rows'      => $rows,
            'canCreate' => $this->can('products.create'),
            'canDelete' => $this->can('products.delete'),
        ], 'therapeutic_areas');
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('products.create');
        return $this->adminView('admin.therapeutic_areas.form', ['title' => 'New Therapeutic Area', 'area' => null], 'therapeutic_areas');
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('products.create');
        $result = $this->service()->create($this->input($request), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Therapeutic area created.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect($result['ok'] ? '/admin/therapeutic-areas/' . $result['id'] . '/edit' : '/admin/therapeutic-areas/create');
    }

    public function edit(Request $request): Response
    {
        $this->requirePermission('products.view');
        $id = (int) $request->route('id');
        $area = $this->repo()->findById($id);
        if ($area === null) {
            throw new HttpException(404);
        }
        return $this->adminView('admin.therapeutic_areas.form', ['title' => 'Edit: ' . $area['name'], 'area' => $area, 'canEdit' => $this->can('products.edit')], 'therapeutic_areas');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        $result = $this->service()->update($id, $this->input($request), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Updated.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/therapeutic-areas/' . $id . '/edit');
    }

    public function status(Request $request): Response
    {
        $this->requirePermission('products.publish');
        $id = (int) $request->route('id');
        $result = $this->service()->setStatus($id, (string) $request->input('status', 'draft'), (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Status updated.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/therapeutic-areas/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        $this->requirePermission('products.delete');
        $id = (int) $request->route('id');
        $result = $this->service()->delete($id, (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Archived.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/therapeutic-areas');
    }

    /** @return array<string,mixed> */
    private function input(Request $request): array
    {
        return [
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
