<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class RoleRepository extends Repository
{
    protected string $table = 'roles';

    /** @return array<int,array<string,mixed>> */
    public function all(int $limit = 200, int $offset = 0): array
    {
        return $this->db->select("SELECT * FROM `roles` ORDER BY `is_system` DESC, `name` ASC");
    }

    public function findByKey(string $key): ?array
    {
        return $this->db->selectOne("SELECT * FROM `roles` WHERE `key` = :k LIMIT 1", ['k' => $key]);
    }

    /** @return array<int,array{id:int,name:string,key:string,users:int}> roles with user counts */
    public function allWithCounts(): array
    {
        return $this->db->select(
            "SELECT r.*, (SELECT COUNT(*) FROM `user_roles` ur WHERE ur.role_id = r.id) AS users
             FROM `roles` r ORDER BY r.`is_system` DESC, r.`name` ASC"
        );
    }

    public function create(string $key, string $name, string $description): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `roles` (`key`,`name`,`description`,`is_system`,`is_active`) VALUES (:k,:n,:d,0,1)",
            ['k' => $key, 'n' => $name, 'd' => $description]
        );
    }

    public function update(int $id, string $name, string $description, bool $isActive): void
    {
        $this->db->statement(
            "UPDATE `roles` SET `name`=:n, `description`=:d, `is_active`=:a WHERE `id`=:id",
            ['n' => $name, 'd' => $description, 'a' => $isActive ? 1 : 0, 'id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->statement("DELETE FROM `roles` WHERE `id` = :id AND `is_system` = 0", ['id' => $id]);
    }

    /** @return array<int,int> permission ids granted to a role */
    public function permissionIds(int $roleId): array
    {
        $rows = $this->db->select("SELECT permission_id FROM `role_permissions` WHERE `role_id` = :r", ['r' => $roleId]);
        return array_map(static fn (array $x): int => (int) $x['permission_id'], $rows);
    }

    /** Replace a role's permissions atomically. @param array<int,int> $permissionIds */
    public function setPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->statement("DELETE FROM `role_permissions` WHERE `role_id` = :r", ['r' => $roleId]);
            foreach (array_unique($permissionIds) as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $this->db->statement("INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`) VALUES (:r,:p)", ['r' => $roleId, 'p' => $pid]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function countPermissions(int $roleId): int
    {
        return (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `role_permissions` WHERE role_id = :r", ['r' => $roleId])['c'] ?? 0);
    }
}
