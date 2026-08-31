<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class UserRepository extends Repository
{
    protected string $table = 'users';

    public function findActiveByEmail(string $email): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `users` WHERE `email` = :e AND `is_active` = 1 AND `deleted_at` IS NULL LIMIT 1",
            ['e' => strtolower(trim($email))]
        );
    }

    /** Any non-deleted user by email (active or not) — for reset/tracking. */
    public function findByEmailAny(string $email): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `users` WHERE `email` = :e AND `deleted_at` IS NULL LIMIT 1",
            ['e' => strtolower(trim($email))]
        );
    }

    public function findActive(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `users` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1",
            ['id' => $id]
        );
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = "SELECT id FROM `users` WHERE `email` = :e AND `deleted_at` IS NULL";
        $p = ['e' => strtolower(trim($email))];
        if ($exceptId !== null) { $sql .= " AND `id` <> :id"; $p['id'] = $exceptId; }
        return $this->db->selectOne($sql . ' LIMIT 1', $p) !== null;
    }

    /**
     * Admin listing with search + role + status filters + pagination.
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginateForAdmin(string $search, string $roleKey, string $status, int $limit, int $offset): array
    {
        $where = ['u.`deleted_at` IS NULL'];
        $params = [];
        $join = '';

        if ($search !== '') {
            $where[] = 'LOWER(CONCAT_WS(\' \', u.`name`, u.`email`, COALESCE(u.`username`,\'\'))) LIKE :s';
            $params['s'] = '%' . strtolower($search) . '%';
        }
        if ($status === 'active')   { $where[] = 'u.`is_active` = 1'; }
        if ($status === 'inactive') { $where[] = 'u.`is_active` = 0'; }
        if ($roleKey !== '') {
            $join = 'JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id`';
            $where[] = 'r.`key` = :rk';
            $params['rk'] = $roleKey;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->selectOne("SELECT COUNT(DISTINCT u.`id`) c FROM `users` u {$join} {$whereSql}", $params)['c'] ?? 0);

        $limit = max(1, $limit); $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT DISTINCT u.* FROM `users` u {$join} {$whereSql} ORDER BY u.`created_at` DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `users` (`name`,`email`,`username`,`phone`,`password_hash`,`is_active`,`must_change_password`)
             VALUES (:name,:email,:username,:phone,:password_hash,:is_active,:must_change_password)",
            $data
        );
    }

    /** Update profile fields (never touches password/roles here — mass-assignment safe). */
    public function updateProfile(int $id, array $data): void
    {
        $data['id'] = $id;
        $this->db->statement(
            "UPDATE `users` SET `name`=:name, `email`=:email, `username`=:username, `phone`=:phone WHERE `id`=:id",
            $data
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->statement("UPDATE `users` SET `is_active` = :a WHERE `id` = :id", ['a' => $active ? 1 : 0, 'id' => $id]);
    }

    public function setMustChangePassword(int $id, bool $flag): void
    {
        $this->db->statement("UPDATE `users` SET `must_change_password` = :f WHERE `id` = :id", ['f' => $flag ? 1 : 0, 'id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->statement("UPDATE `users` SET `deleted_at` = NOW(), `is_active` = 0 WHERE `id` = :id", ['id' => $id]);
    }

    public function setLockout(int $id, ?string $until): void
    {
        $this->db->statement("UPDATE `users` SET `locked_until` = :u WHERE `id` = :id", ['u' => $until, 'id' => $id]);
    }

    // --- Roles ---------------------------------------------------------------

    /** @return array<int,int> role ids assigned to a user */
    public function roleIds(int $userId): array
    {
        $rows = $this->db->select("SELECT role_id FROM `user_roles` WHERE `user_id` = :u", ['u' => $userId]);
        return array_map(static fn (array $r): int => (int) $r['role_id'], $rows);
    }

    /** Replace a user's roles atomically. @param array<int,int> $roleIds */
    public function setRoles(int $userId, array $roleIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->statement("DELETE FROM `user_roles` WHERE `user_id` = :u", ['u' => $userId]);
            foreach (array_unique($roleIds) as $rid) {
                $rid = (int) $rid;
                if ($rid > 0) {
                    $this->db->statement("INSERT IGNORE INTO `user_roles` (`user_id`,`role_id`) VALUES (:u,:r)", ['u' => $userId, 'r' => $rid]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function hasRoleKey(int $userId, string $roleKey): bool
    {
        return $this->db->selectOne(
            "SELECT 1 FROM `user_roles` ur JOIN `roles` r ON r.id = ur.role_id WHERE ur.user_id = :u AND r.`key` = :k LIMIT 1",
            ['u' => $userId, 'k' => $roleKey]
        ) !== null;
    }

    /** Count ACTIVE, non-deleted users holding a given role — super-admin protection. */
    public function countActiveWithRole(string $roleKey): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(DISTINCT u.id) c FROM `users` u
             JOIN `user_roles` ur ON ur.user_id = u.id
             JOIN `roles` r ON r.id = ur.role_id
             WHERE r.`key` = :k AND u.`is_active` = 1 AND u.`deleted_at` IS NULL",
            ['k' => $roleKey]
        );
        return (int) ($row['c'] ?? 0);
    }

    /** @return array<int,array{id:int,name:string,email:string}> roles+names for display */
    public function rolesForUser(int $userId): array
    {
        return $this->db->select(
            "SELECT r.id, r.`key`, r.`name` FROM `roles` r JOIN `user_roles` ur ON ur.role_id = r.id WHERE ur.user_id = :u ORDER BY r.`name`",
            ['u' => $userId]
        );
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->db->statement(
            "UPDATE `users` SET `password_hash` = :h WHERE `id` = :id",
            ['h' => $hash, 'id' => $id]
        );
    }

    public function touchLogin(int $id, string $ip): void
    {
        $this->db->statement(
            "UPDATE `users` SET `last_login_at` = NOW(), `last_login_ip` = :ip WHERE `id` = :id",
            ['ip' => $ip, 'id' => $id]
        );
    }

    /** @return array<int,string> role keys for a user */
    public function roleKeys(int $userId): array
    {
        $rows = $this->db->select(
            "SELECT r.`key` FROM `roles` r
             JOIN `user_roles` ur ON ur.`role_id` = r.`id`
             WHERE ur.`user_id` = :uid",
            ['uid' => $userId]
        );
        return array_map(static fn (array $r): string => (string) $r['key'], $rows);
    }

    /** @return array<int,string> permission keys for a user (via roles) */
    public function permissionKeys(int $userId): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT p.`key` FROM `permissions` p
             JOIN `role_permissions` rp ON rp.`permission_id` = p.`id`
             JOIN `user_roles` ur ON ur.`role_id` = rp.`role_id`
             WHERE ur.`user_id` = :uid",
            ['uid' => $userId]
        );
        return array_map(static fn (array $r): string => (string) $r['key'], $rows);
    }
}
