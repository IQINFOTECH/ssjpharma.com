<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class DosageFormRepository extends Repository
{
    protected string $table = 'dosage_forms';

    /** @return array<int,array<string,mixed>> */
    public function allOrdered(): array
    {
        return $this->db->select("SELECT * FROM `dosage_forms` ORDER BY `sort_order` ASC, `name` ASC");
    }

    /** @return array<int,array<string,mixed>> active only (for public filters + product forms) */
    public function active(): array
    {
        return $this->db->select("SELECT * FROM `dosage_forms` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `name` ASC");
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = "SELECT * FROM `dosage_forms` WHERE `slug` = :s";
        $p = ['s' => $slug];
        if ($exceptId !== null) { $sql .= " AND `id` <> :id"; $p['id'] = $exceptId; }
        return $this->db->selectOne($sql . ' LIMIT 1', $p);
    }

    public function create(string $name, string $slug, int $sortOrder): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `dosage_forms` (`name`,`slug`,`sort_order`,`is_active`) VALUES (:n,:s,:o,1)",
            ['n' => $name, 's' => $slug, 'o' => $sortOrder]
        );
    }

    public function update(int $id, string $name, int $sortOrder, bool $isActive): void
    {
        $this->db->statement(
            "UPDATE `dosage_forms` SET `name`=:n, `sort_order`=:o, `is_active`=:a WHERE `id`=:id",
            ['n' => $name, 'o' => $sortOrder, 'a' => $isActive ? 1 : 0, 'id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->statement("DELETE FROM `dosage_forms` WHERE `id` = :id", ['id' => $id]);
    }

    public function inUse(int $id): int
    {
        return (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `products` WHERE `dosage_form_id` = :id AND `deleted_at` IS NULL", ['id' => $id])['c'] ?? 0);
    }
}
