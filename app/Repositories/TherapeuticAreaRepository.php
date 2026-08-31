<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class TherapeuticAreaRepository extends Repository
{
    protected string $table = 'therapeutic_areas';

    public function findById(int $id): ?array
    {
        return $this->db->selectOne("SELECT * FROM `therapeutic_areas` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1", ['id' => $id]);
    }

    /** Production hides is_demo records from public surfaces (content governance, Phase 6). */
    private function demoCond(): string
    {
        return config('app.env') === 'production' ? ' AND `is_demo` = 0' : '';
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `therapeutic_areas` WHERE `slug` = :s AND `status` = 'published' AND `deleted_at` IS NULL" . $this->demoCond() . " LIMIT 1",
            ['s' => $slug]
        );
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = "SELECT * FROM `therapeutic_areas` WHERE `slug` = :s AND `deleted_at` IS NULL";
        $p = ['s' => $slug];
        if ($exceptId !== null) { $sql .= " AND `id` <> :id"; $p['id'] = $exceptId; }
        return $this->db->selectOne($sql . ' LIMIT 1', $p);
    }

    public function allForAdmin(): array
    {
        return $this->db->select("SELECT * FROM `therapeutic_areas` WHERE `deleted_at` IS NULL ORDER BY `sort_order` ASC, `name` ASC");
    }

    public function allPublished(): array
    {
        return $this->db->select("SELECT * FROM `therapeutic_areas` WHERE `status` = 'published' AND `deleted_at` IS NULL" . $this->demoCond() . " ORDER BY `sort_order` ASC, `name` ASC");
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `therapeutic_areas`
             (`name`,`slug`,`description`,`image_id`,`meta_title`,`meta_description`,`status`,`sort_order`,`is_demo`)
             VALUES (:name,:slug,:description,:image_id,:meta_title,:meta_description,:status,:sort_order,:is_demo)",
            $data
        );
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $this->db->statement(
            "UPDATE `therapeutic_areas` SET
              `name`=:name, `slug`=:slug, `description`=:description, `image_id`=:image_id,
              `meta_title`=:meta_title, `meta_description`=:meta_description, `status`=:status, `sort_order`=:sort_order
             WHERE `id`=:id",
            $data
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->statement("UPDATE `therapeutic_areas` SET `status`=:s WHERE `id`=:id", ['s' => $status, 'id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->statement("UPDATE `therapeutic_areas` SET `deleted_at` = NOW() WHERE `id` = :id", ['id' => $id]);
    }

    public function productCount(int $id): int
    {
        return (int) ($this->db->selectOne(
            "SELECT COUNT(*) c FROM `product_therapeutic_areas` pta JOIN `products` p ON p.id = pta.product_id
             WHERE pta.therapeutic_area_id = :id AND p.deleted_at IS NULL", ['id' => $id]
        )['c'] ?? 0);
    }
}
