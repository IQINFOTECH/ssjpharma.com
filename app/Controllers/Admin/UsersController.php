<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;

/**
 * User management (Phase 2 §1/§13). Every action authenticates + authorises
 * (middleware) AND re-checks the permission here (defence in depth). Integrity
 * rules (super-admin protection, self-lockout, mass-assignment) live in UserService.
 */
final class UsersController extends AdminController
{
    private function users(): UserRepository { return $this->container->get(UserRepository::class); }
    private function roles(): RoleRepository  { return $this->container->get(RoleRepository::class); }
    private function service(): UserService   { return $this->container->get(UserService::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('users.view');

        $search = trim((string) $request->query('q', ''));
        $roleKey = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $result = $this->users()->paginateForAdmin($search, $roleKey, $status, $perPage, ($page - 1) * $perPage);
        $rows = $result['rows'];
        foreach ($rows as &$row) {
            $row['roles'] = $this->users()->rolesForUser((int) $row['id']);
        }
        unset($row);

        return $this->adminView('admin.users.index', [
            'title'      => 'Users',
            'rows'       => $rows,
            'total'      => $result['total'],
            'search'     => $search,
            'roleKey'    => $roleKey,
            'status'     => $status,
            'page'       => $page,
            'totalPages' => (int) max(1, ceil($result['total'] / $perPage)),
            'allRoles'   => $this->roles()->all(),
            'canEdit'    => $this->can('users.edit'),
            'canDelete'  => $this->can('users.delete'),
            'canActivate'=> $this->can('users.activate'),
        ], 'users');
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('users.create');
        return $this->adminView('admin.users.form', [
            'title'    => 'New User',
            'user'     => null,
            'userRoles'=> [],
            'allRoles' => $this->roles()->all(),
            'errors'   => [],
            'old'      => [],
        ], 'users');
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('users.create');

        $input = $this->input($request);
        $input['password'] = (string) $request->input('password', '');
        $input['must_change_password'] = $request->input('must_change_password') ? 1 : 0;
        $roleIds = array_map('intval', (array) $request->input('roles', []));

        $result = $this->service()->create($input, $roleIds, (int) $this->currentUserId());
        if (!$result['ok']) {
            $this->session()->flash('user_errors', $result['errors'] ?? []);
            $this->session()->flash('user_old', $input);
            return Response::redirect('/admin/users/create');
        }
        $this->flash('success', 'User created.');
        return Response::redirect('/admin/users/' . $result['id'] . '/edit');
    }

    public function edit(Request $request): Response
    {
        $this->requirePermission('users.view');
        $id = (int) $request->route('id');
        $user = $this->users()->findActive($id);
        if ($user === null) {
            throw new HttpException(404);
        }

        return $this->adminView('admin.users.form', [
            'title'     => 'Edit: ' . $user['name'],
            'user'      => $user,
            'userRoles' => $this->users()->roleIds($id),
            'allRoles'  => $this->roles()->all(),
            'errors'    => (array) $this->session()->getFlash('user_errors', []),
            'old'       => (array) $this->session()->getFlash('user_old', []),
            'isSelf'    => $id === $this->currentUserId(),
            'canEdit'   => $this->can('users.edit'),
        ], 'users');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('users.edit');
        $id = (int) $request->route('id');
        if ($this->users()->findActive($id) === null) {
            throw new HttpException(404);
        }

        $input = $this->input($request);
        $result = $this->service()->update($id, $input);
        if (!$result['ok']) {
            $this->session()->flash('user_errors', $result['errors'] ?? []);
            $this->session()->flash('user_old', $input);
            return Response::redirect('/admin/users/' . $id . '/edit');
        }

        // Role changes: never allow a user to change their OWN roles here.
        if ($id !== $this->currentUserId()) {
            $roleIds = array_map('intval', (array) $request->input('roles', []));
            $roleResult = $this->service()->setRoles($id, $roleIds, (int) $this->currentUserId());
            if (!$roleResult['ok']) {
                $this->flash('error', $roleResult['error'] ?? 'Could not update roles.');
                return Response::redirect('/admin/users/' . $id . '/edit');
            }
        }

        $this->flash('success', 'User updated.');
        return Response::redirect('/admin/users/' . $id . '/edit');
    }

    public function setActive(Request $request): Response
    {
        $this->requirePermission('users.activate');
        $id = (int) $request->route('id');
        $active = (string) $request->input('active', '1') === '1';
        $result = $this->service()->setActive($id, $active, (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? ('User ' . ($active ? 'activated.' : 'deactivated.')) : ($result['error'] ?? 'Action failed.'));
        return Response::redirect('/admin/users');
    }

    public function resetPassword(Request $request): Response
    {
        $this->requirePermission('users.edit');
        $id = (int) $request->route('id');
        $new = (string) $request->input('new_password', '');
        $result = $this->service()->adminResetPassword($id, $new);
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Password reset. The user must change it at next login.' : ($result['error'] ?? 'Reset failed.'));
        return Response::redirect('/admin/users/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        $this->requirePermission('users.delete');
        $id = (int) $request->route('id');
        $result = $this->service()->delete($id, (int) $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'User deleted.' : ($result['error'] ?? 'Delete failed.'));
        return Response::redirect($result['ok'] ? '/admin/users' : '/admin/users/' . $id . '/edit');
    }

    public function activity(Request $request): Response
    {
        $this->requirePermission('users.view');
        $id = (int) $request->route('id');
        $user = $this->users()->findActive($id);
        if ($user === null) {
            throw new HttpException(404);
        }
        /** @var AuditRepository $audit */
        $audit = $this->container->get(AuditRepository::class);
        return $this->adminView('admin.users.activity', [
            'title'  => 'Activity: ' . $user['name'],
            'user'   => $user,
            'events' => $audit->forUser($id, 100),
        ], 'users');
    }

    /** @return array<string,mixed> whitelisted profile fields only (mass-assignment safe) */
    private function input(Request $request): array
    {
        return [
            'name'     => (string) $request->input('name', ''),
            'email'    => (string) $request->input('email', ''),
            'username' => (string) $request->input('username', ''),
            'phone'    => (string) $request->input('phone', ''),
        ];
    }
}
