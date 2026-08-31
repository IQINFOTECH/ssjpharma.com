<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Support\Str;

/**
 * Role & permission-matrix management (Phase 2 §4). System roles are protected;
 * the super_admin role is a code-level wildcard and its grants are locked to
 * "all". Every change is audited.
 */
final class RoleService
{
    private const SUPER_ADMIN = 'super_admin';

    public function __construct(
        private readonly RoleRepository $roles,
        private readonly PermissionRepository $permissions,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public function create(string $name, string $description): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Role name is required.'];
        }
        $key = Str::slug($name, '_');
        if ($key === '') {
            return ['ok' => false, 'error' => 'Role name must contain letters or numbers.'];
        }
        if ($this->roles->findByKey($key) !== null) {
            return ['ok' => false, 'error' => 'A role with a similar name already exists.'];
        }
        $id = $this->roles->create($key, mb_substr($name, 0, 120), mb_substr($description, 0, 255));
        $this->audit->log('ROLE_CREATED', ['entity_type' => 'role', 'entity_id' => $id, 'meta' => ['key' => $key]]);
        return ['ok' => true, 'id' => $id];
    }

    /** @return array{ok:bool,error?:string} */
    public function update(int $id, string $name, string $description, bool $isActive): array
    {
        $role = $this->roles->find($id);
        if ($role === null) {
            return ['ok' => false, 'error' => 'Role not found.'];
        }
        // Never deactivate the super_admin role.
        if ($role['key'] === self::SUPER_ADMIN) {
            $isActive = true;
        }
        $name = trim($name) !== '' ? trim($name) : (string) $role['name'];
        $this->roles->update($id, mb_substr($name, 0, 120), mb_substr($description, 0, 255), $isActive);
        $this->audit->log('ROLE_UPDATED', ['entity_type' => 'role', 'entity_id' => $id]);
        return ['ok' => true];
    }

    /** @param array<int,int> $permissionIds @return array{ok:bool,error?:string} */
    public function setPermissions(int $id, array $permissionIds): array
    {
        $role = $this->roles->find($id);
        if ($role === null) {
            return ['ok' => false, 'error' => 'Role not found.'];
        }
        // super_admin keeps ALL permissions (wildcard) — do not allow narrowing.
        if ($role['key'] === self::SUPER_ADMIN) {
            return ['ok' => false, 'error' => 'The Super Admin role always has all permissions and cannot be changed.'];
        }

        $valid = $this->validPermissionIds($permissionIds);
        $this->roles->setPermissions($id, $valid);
        $this->audit->log('PERMISSION_CHANGED', ['entity_type' => 'role', 'entity_id' => $id, 'meta' => ['count' => count($valid)]]);
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public function delete(int $id): array
    {
        $role = $this->roles->find($id);
        if ($role === null) {
            return ['ok' => false, 'error' => 'Role not found.'];
        }
        if ((int) $role['is_system'] === 1) {
            return ['ok' => false, 'error' => 'System roles cannot be deleted.'];
        }
        $inUse = (int) ($this->roles->allWithCounts() ? $this->usersFor($id) : 0);
        if ($inUse > 0) {
            return ['ok' => false, 'error' => 'This role is assigned to users and cannot be deleted.'];
        }
        $this->roles->delete($id);
        $this->audit->log('ROLE_DELETED', ['entity_type' => 'role', 'entity_id' => $id, 'meta' => ['key' => $role['key']]]);
        return ['ok' => true];
    }

    private function usersFor(int $roleId): int
    {
        foreach ($this->roles->allWithCounts() as $r) {
            if ((int) $r['id'] === $roleId) {
                return (int) $r['users'];
            }
        }
        return 0;
    }

    /** @param array<int,int> $ids @return array<int,int> */
    private function validPermissionIds(array $ids): array
    {
        $all = [];
        foreach ($this->permissions->all() as $p) {
            $all[(int) $p['id']] = true;
        }
        $out = [];
        foreach ($ids as $pid) {
            $pid = (int) $pid;
            if (isset($all[$pid])) {
                $out[] = $pid;
            }
        }
        return $out;
    }
}
