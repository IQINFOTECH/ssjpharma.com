<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Services\RoleService;

/**
 * Roles & the permission matrix (Phase 2 §4). DB-driven and configurable.
 * super_admin is protected (wildcard, immutable). Every write is permission-gated.
 */
final class RolesController extends AdminController
{
    private function roles(): RoleRepository            { return $this->container->get(RoleRepository::class); }
    private function permissions(): PermissionRepository { return $this->container->get(PermissionRepository::class); }
    private function service(): RoleService              { return $this->container->get(RoleService::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('roles.view');
        return $this->adminView('admin.roles.index', [
            'title'      => 'Roles',
            'roles'      => $this->roles()->allWithCounts(),
            'canCreate'  => $this->can('roles.create'),
            'canEdit'    => $this->can('roles.edit'),
            'canDelete'  => $this->can('roles.delete'),
        ], 'roles');
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('roles.create');
        $result = $this->service()->create((string) $request->input('name', ''), (string) $request->input('description', ''));
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Role created.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect($result['ok'] ? '/admin/roles/' . $result['id'] . '/edit' : '/admin/roles');
    }

    public function edit(Request $request): Response
    {
        $this->requirePermission('roles.view');
        $id = (int) $request->route('id');
        $role = $this->roles()->find($id);
        if ($role === null) {
            throw new HttpException(404);
        }
        return $this->adminView('admin.roles.edit', [
            'title'       => 'Role: ' . $role['name'],
            'role'        => $role,
            'grouped'     => $this->permissions()->grouped(),
            'granted'     => $this->roles()->permissionIds($id),
            'isSuperAdmin'=> $role['key'] === 'super_admin',
            'canEdit'     => $this->can('roles.edit'),
        ], 'roles');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('roles.edit');
        $id = (int) $request->route('id');
        $result = $this->service()->update(
            $id,
            (string) $request->input('name', ''),
            (string) $request->input('description', ''),
            (bool) $request->input('is_active'),
        );
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Role updated.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/roles/' . $id . '/edit');
    }

    public function setPermissions(Request $request): Response
    {
        $this->requirePermission('roles.edit');
        $id = (int) $request->route('id');
        $permIds = array_map('intval', (array) $request->input('permissions', []));
        $result = $this->service()->setPermissions($id, $permIds);
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Permissions updated.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/roles/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        $this->requirePermission('roles.delete');
        $id = (int) $request->route('id');
        $result = $this->service()->delete($id);
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Role deleted.' : ($result['error'] ?? 'Failed.'));
        return Response::redirect('/admin/roles');
    }
}
