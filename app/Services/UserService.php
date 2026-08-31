<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Support\PasswordPolicy;
use App\Support\Validator;

/**
 * User-management business logic (Phase 2). Enforces super-admin protection,
 * mass-assignment safety (explicit field allowlists), password policy, and audit
 * logging. Authorization itself is enforced by middleware + controller checks;
 * this layer is the last line (defence in depth) for integrity rules.
 */
final class UserService
{
    private const SUPER_ADMIN = 'super_admin';

    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly UserSessionRepository $sessions,
        private readonly AuditService $audit,
        private readonly Config $config,
    ) {
    }

    /** @param array<string,mixed> $input @return array<string,string> */
    public function validate(array $input, ?int $exceptId = null, bool $requirePassword = true): array
    {
        $v = new Validator();
        $rules = [
            'name'     => 'required|max:150',
            'email'    => 'required|email|max:190',
            'phone'    => 'max:40',
            'username' => 'max:60',
        ];
        if ($requirePassword) {
            $rules['password'] = 'required';
        }
        $v->validate($input, $rules);
        $errors = $v->errors();

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '' && !isset($errors['email']) && $this->users->emailExists($email, $exceptId)) {
            $errors['email'] = 'That email is already in use.';
        }

        if ($requirePassword && !isset($errors['password'])) {
            $pwErr = PasswordPolicy::validate((string) ($input['password'] ?? ''), $this->minLen(), $email);
            if ($pwErr !== null) {
                $errors['password'] = $pwErr;
            }
        }
        return $errors;
    }

    /**
     * @param array<string,mixed> $input @param array<int,int> $roleIds
     * @return array{ok:bool,errors?:array<string,string>,id?:int}
     */
    public function create(array $input, array $roleIds, int $actorId): array
    {
        $errors = $this->validate($input, null, true);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        // Only a Super Admin may grant the Super Admin role (anti-escalation).
        $roleIds = $this->stripSuperAdminUnlessActorIsSuper($roleIds, $actorId);

        $id = $this->users->create([
            'name'                 => mb_substr(trim((string) $input['name']), 0, 150),
            'email'                => strtolower(trim((string) $input['email'])),
            'username'             => $this->nullable($input['username'] ?? '', 60),
            'phone'                => $this->nullable($input['phone'] ?? '', 40),
            'password_hash'        => password_hash((string) $input['password'], $this->algo()),
            'is_active'            => !empty($input['is_active']) ? 1 : 1,
            'must_change_password' => !empty($input['must_change_password']) ? 1 : 0,
        ]);

        $this->users->setRoles($id, $this->validRoleIds($roleIds));
        $this->audit->log('USER_CREATED', ['entity_type' => 'user', 'entity_id' => $id, 'meta' => ['email' => $input['email']]]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param array<string,mixed> $input @return array{ok:bool,errors?:array<string,string>}
     */
    public function update(int $id, array $input): array
    {
        $user = $this->users->findActive($id);
        if ($user === null) {
            return ['ok' => false, 'errors' => ['_form' => 'User not found.']];
        }
        $errors = $this->validate($input, $id, false);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->users->updateProfile($id, [
            'name'     => mb_substr(trim((string) $input['name']), 0, 150),
            'email'    => strtolower(trim((string) $input['email'])),
            'username' => $this->nullable($input['username'] ?? '', 60),
            'phone'    => $this->nullable($input['phone'] ?? '', 40),
        ]);
        $this->audit->log('USER_UPDATED', ['entity_type' => 'user', 'entity_id' => $id]);

        return ['ok' => true];
    }

    /** @param array<int,int> $roleIds @return array{ok:bool,error?:string} */
    public function setRoles(int $id, array $roleIds, int $actorId): array
    {
        $user = $this->users->findActive($id);
        if ($user === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }

        // Only a Super Admin may grant the Super Admin role (anti-escalation).
        $roleIds = $this->stripSuperAdminUnlessActorIsSuper($roleIds, $actorId);
        $newRoleIds = $this->validRoleIds($roleIds);
        $wasSuper = $this->users->hasRoleKey($id, self::SUPER_ADMIN);
        $willBeSuper = $this->idsIncludeRoleKey($newRoleIds, self::SUPER_ADMIN);

        // If a non-super actor edits a user who IS super_admin, preserve that role
        // (they may not strip it either).
        if ($wasSuper && !$willBeSuper && !$this->users->hasRoleKey($actorId, self::SUPER_ADMIN)) {
            return ['ok' => false, 'error' => 'Only a Super Admin can change the Super Admin role.'];
        }

        // Protect the last active Super Admin from losing the role.
        if ($wasSuper && !$willBeSuper && $this->isLastActiveSuperAdmin($id)) {
            return ['ok' => false, 'error' => 'You cannot remove the Super Admin role from the last active Super Admin.'];
        }

        $this->users->setRoles($id, $newRoleIds);
        $this->audit->log('ROLE_CHANGED', ['entity_type' => 'user', 'entity_id' => $id, 'meta' => ['role_ids' => array_values($newRoleIds)]]);
        return ['ok' => true];
    }

    public function setActive(int $id, bool $active, int $actorId): array
    {
        $user = $this->users->findActive($id);
        if ($user === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        if (!$active && $id === $actorId) {
            return ['ok' => false, 'error' => 'You cannot deactivate your own account.'];
        }
        if (!$active && $this->users->hasRoleKey($id, self::SUPER_ADMIN) && $this->isLastActiveSuperAdmin($id)) {
            return ['ok' => false, 'error' => 'You cannot deactivate the last active Super Admin.'];
        }

        $this->users->setActive($id, $active);
        if (!$active) {
            $this->sessions->revokeAllForUserExcept($id, ''); // kick active sessions
        }
        $this->audit->log($active ? 'USER_ACTIVATED' : 'USER_DEACTIVATED', ['entity_type' => 'user', 'entity_id' => $id]);
        return ['ok' => true];
    }

    public function delete(int $id, int $actorId): array
    {
        $user = $this->users->findActive($id);
        if ($user === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        if ($id === $actorId) {
            return ['ok' => false, 'error' => 'You cannot delete your own account.'];
        }
        if ($this->users->hasRoleKey($id, self::SUPER_ADMIN) && $this->isLastActiveSuperAdmin($id)) {
            return ['ok' => false, 'error' => 'You cannot delete the last active Super Admin.'];
        }

        $this->users->softDelete($id);
        $this->sessions->revokeAllForUserExcept($id, '');
        $this->audit->log('USER_DELETED', ['entity_type' => 'user', 'entity_id' => $id, 'meta' => ['email' => $user['email']]]);
        return ['ok' => true];
    }

    public function adminResetPassword(int $id, string $newPassword): array
    {
        $user = $this->users->findActive($id);
        if ($user === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        $err = PasswordPolicy::validate($newPassword, $this->minLen(), (string) $user['email']);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $this->users->updatePasswordHash($id, password_hash($newPassword, $this->algo()));
        $this->users->setMustChangePassword($id, true);
        $this->sessions->revokeAllForUserExcept($id, '');
        $this->audit->log('PASSWORD_RESET_COMPLETED', ['entity_type' => 'user', 'entity_id' => $id, 'meta' => ['by' => 'admin']]);
        return ['ok' => true];
    }

    public function isLastActiveSuperAdmin(int $userId): bool
    {
        return $this->users->hasRoleKey($userId, self::SUPER_ADMIN)
            && $this->users->countActiveWithRole(self::SUPER_ADMIN) <= 1;
    }

    // --- helpers -------------------------------------------------------------

    /**
     * Remove the super_admin role from an assignment unless the actor is a super_admin.
     * @param array<int,int> $roleIds @return array<int,int>
     */
    private function stripSuperAdminUnlessActorIsSuper(array $roleIds, int $actorId): array
    {
        if ($this->users->hasRoleKey($actorId, self::SUPER_ADMIN)) {
            return $roleIds;
        }
        $superRole = $this->roles->findByKey(self::SUPER_ADMIN);
        if ($superRole === null) {
            return $roleIds;
        }
        $superId = (int) $superRole['id'];
        return array_values(array_filter($roleIds, static fn ($rid): bool => (int) $rid !== $superId));
    }

    /** @param array<int,int> $roleIds @return array<int,int> only existing role ids */
    private function validRoleIds(array $roleIds): array
    {
        $valid = [];
        foreach ($roleIds as $rid) {
            $rid = (int) $rid;
            if ($rid > 0 && $this->roles->find($rid) !== null) {
                $valid[] = $rid;
            }
        }
        return $valid;
    }

    /** @param array<int,int> $roleIds */
    private function idsIncludeRoleKey(array $roleIds, string $key): bool
    {
        foreach ($roleIds as $rid) {
            $role = $this->roles->find((int) $rid);
            if ($role !== null && $role['key'] === $key) {
                return true;
            }
        }
        return false;
    }

    private function nullable(mixed $v, int $max): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    private function minLen(): int { return (int) $this->config->get('security.password.min_length', 10); }
    private function algo(): mixed  { return $this->config->get('security.password.algo', PASSWORD_DEFAULT); }
}
