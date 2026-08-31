<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DosageFormRepository;
use App\Support\Str;

/**
 * Managed dosage-form picklist (§6). View = products.view, manage = products.edit.
 * These are structural options only and imply no manufacturing.
 */
final class DosageFormsController extends AdminController
{
    private function repo(): DosageFormRepository { return $this->container->get(DosageFormRepository::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('products.view');
        return $this->adminView('admin.dosage_forms.index', [
            'title' => 'Dosage Forms',
            'rows'  => $this->repo()->allOrdered(),
            'canManage' => $this->can('products.edit'),
        ], 'dosage_forms');
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->flash('error', 'Name is required.');
            return Response::redirect('/admin/dosage-forms');
        }
        $slug = $this->uniqueSlug($name, null);
        $this->repo()->create(mb_substr($name, 0, 80), $slug, (int) $request->input('sort_order', 0));
        $this->audit('PRODUCT_UPDATED', ['entity_type' => 'dosage_form', 'meta' => ['created' => $name]]);
        $this->flash('success', 'Dosage form added.');
        return Response::redirect('/admin/dosage-forms');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        if ($this->repo()->find($id) === null) {
            $this->flash('error', 'Not found.');
            return Response::redirect('/admin/dosage-forms');
        }
        $name = trim((string) $request->input('name', '')) ?: 'Unnamed';
        $this->repo()->update($id, mb_substr($name, 0, 80), (int) $request->input('sort_order', 0), (bool) $request->input('is_active'));
        $this->flash('success', 'Dosage form updated.');
        return Response::redirect('/admin/dosage-forms');
    }

    public function delete(Request $request): Response
    {
        $this->requirePermission('products.edit');
        $id = (int) $request->route('id');
        if ($this->repo()->inUse($id) > 0) {
            $this->flash('error', 'This dosage form is used by products and cannot be deleted. Deactivate it instead.');
            return Response::redirect('/admin/dosage-forms');
        }
        $this->repo()->delete($id);
        $this->flash('success', 'Dosage form deleted.');
        return Response::redirect('/admin/dosage-forms');
    }

    private function uniqueSlug(string $base, ?int $exceptId): string
    {
        $slug = Str::slug($base) ?: 'form';
        $candidate = $slug; $i = 2;
        while ($this->repo()->findBySlug($candidate, $exceptId) !== null) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }
}
