<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class RedirectRepository extends Repository
{
    protected string $table = 'redirects';

    public function findActiveByPath(string $fromPath): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `redirects` WHERE `from_path` = :p AND `is_active` = 1 LIMIT 1",
            ['p' => $fromPath]
        );
    }

    public function findByPath(string $fromPath, ?int $exceptId = null): ?array
    {
        $sql = "SELECT * FROM `redirects` WHERE `from_path` = :p";
        $params = ['p' => $fromPath];
        if ($exceptId !== null) {
            $sql .= " AND `id` <> :id";
            $params['id'] = $exceptId;
        }
        return $this->db->selectOne($sql . ' LIMIT 1', $params);
    }

    /** @return array<int,array<string,mixed>> */
    public function allActive(): array
    {
        return $this->db->select("SELECT `from_path`,`to_url` FROM `redirects` WHERE `is_active` = 1");
    }

    /** @return array<int,array<string,mixed>> */
    public function allForAdmin(): array
    {
        return $this->db->select("SELECT * FROM `redirects` ORDER BY `created_at` DESC");
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `redirects` (`from_path`,`to_url`,`code`,`is_active`,`created_by`)
             VALUES (:from_path,:to_url,:code,:is_active,:created_by)",
            $data
        );
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $this->db->statement(
            "UPDATE `redirects` SET `from_path`=:from_path,`to_url`=:to_url,`code`=:code,`is_active`=:is_active WHERE `id`=:id",
            $data
        );
    }

    public function incrementHit(int $id): void
    {
        $this->db->statement("UPDATE `redirects` SET `hits` = `hits` + 1 WHERE `id` = :id", ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->statement("DELETE FROM `redirects` WHERE `id` = :id", ['id' => $id]);
    }
}
