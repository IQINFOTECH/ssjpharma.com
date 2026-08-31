<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class ProductCategoryRepository extends Repository
{
    protected string $table = 'product_categories';

    public function findById(int $id): ?array
    {
        return $this->db->selectOne("SELECT * FROM `product_categories` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1", ['id' => $id]);
    }

    /** Production hides is_demo records from public surfaces (content governance, Phase 6). */
    private function demoCond(): string
    {
        return config('app.env') === 'production' ? ' AND `is_demo` = 0' : '';
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `product_categories` WHERE `slug` = :s AND `status` = 'published' AND `deleted_at` IS NULL" . $this->demoCond() . " LIMIT 1",
            ['s' => $slug]
        );
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = "SELECT * FROM `product_categories` WHERE `slug` = :s AND `deleted_at` IS NULL";
        $p = ['s' => $slug];
        if ($exceptId !== null) { $sql .= " AND `id` <> :id"; $p['id'] = $exceptId; }
        return $this->db->selectOne($sql . ' LIMIT 1', $p);
    }

    /** @return array<int,array<string,mixed>> all non-deleted (admin) */
    public function allForAdmin(): array
    {
        return $this->db->select("SELECT * FROM `product_categories` WHERE `deleted_at` IS NULL ORDER BY `sort_order` ASC, `name` ASC");
    }

    /** @return array<int,array<string,mixed>> published categories (public nav/filters) */
    public function allPublished(): array
    {
        return $this->db->select("SELECT * FROM `product_categories` WHERE `status` = 'published' AND `deleted_at` IS NULL" . $this->demoCond() . " ORDER BY `sort_order` ASC, `name` ASC");
    }

    /** @return array<int,array<string,mixed>> published direct children of a category */
    public function publishedChildren(int $parentId): array
    {
        return $this->db->select(
            "SELECT * FROM `product_categories` WHERE `parent_id` = :p AND `status` = 'published' AND `deleted_at` IS NULL" . $this->demoCond() . " ORDER BY `sort_order` ASC, `name` ASC",
            ['p' => $parentId]
        );
    }

    /** IDs of a category plus all its descendants (for "products in category" incl. subcats). */
    public function idWithDescendants(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        // Bounded breadth-first walk (avoids recursion; safe for practical depths).
        for ($depth = 0; $depth < 20 && $frontier !== []; $depth++) {
            $in = implode(',', array_map('intval', $frontier));
            $rows = $this->db->select("SELECT id FROM `product_categories` WHERE `parent_id` IN ({$in}) AND `deleted_at` IS NULL");
            $frontier = array_map(static fn ($r): int => (int) $r['id'], $rows);
            foreach ($frontier as $cid) { $ids[] = $cid; }
        }
        return array_values(array_unique($ids));
    }

    /** True if $candidateParent is $id itself or any of its descendants (cycle guard). */
    public function wouldCreateCycle(int $id, int $candidateParent): bool
    {
        if ($candidateParent === $id) {
            return true;
        }
        return in_array($candidateParent, $this->idWithDescendants($id), true);
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `product_categories`
             (`parent_id`,`name`,`slug`,`description`,`image_id`,`meta_title`,`meta_description`,`status`,`sort_order`,`is_demo`)
             VALUES (:parent_id,:name,:slug,:description,:image_id,:meta_title,:meta_description,:status,:sort_order,:is_demo)",
            $data
        );
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $this->db->statement(
            "UPDATE `product_categories` SET
              `parent_id`=:parent_id, `name`=:name, `slug`=:slug, `description`=:description, `image_id`=:image_id,
              `meta_title`=:meta_title, `meta_description`=:meta_description, `status`=:status, `sort_order`=:sort_order
             WHERE `id`=:id",
            $data
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->statement("UPDATE `product_categories` SET `status`=:s WHERE `id`=:id", ['s' => $status, 'id' => $id]);
    }

    public function softDelete(int $id): void
    {
        // Detach children to top-level to avoid orphaned hierarchy.
        $this->db->statement("UPDATE `product_categories` SET `parent_id` = NULL WHERE `parent_id` = :id", ['id' => $id]);
        $this->db->statement("UPDATE `product_categories` SET `deleted_at` = NOW() WHERE `id` = :id", ['id' => $id]);
    }

    public function productCount(int $id): int
    {
        return (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `products` WHERE `category_id` = :id AND `deleted_at` IS NULL", ['id' => $id])['c'] ?? 0);
    }
}
