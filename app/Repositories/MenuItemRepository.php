<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class MenuItemRepository extends Repository
{
    protected string $table = 'menu_items';

    /**
     * Active items for a menu, joined to the page slug/status so URLs resolve
     * without N+1. Ordered for tree building.
     *
     * @return array<int,array<string,mixed>>
     */
    public function activeForMenu(int $menuId): array
    {
        return $this->db->select(
            "SELECT mi.*, p.`slug` AS page_slug, p.`status` AS page_status, p.`deleted_at` AS page_deleted
             FROM `menu_items` mi
             LEFT JOIN `pages` p ON p.`id` = mi.`page_id`
             WHERE mi.`menu_id` = :mid AND mi.`is_active` = 1
             ORDER BY mi.`sort_order` ASC, mi.`id` ASC",
            ['mid' => $menuId]
        );
    }

    /** @return array<int,array<string,mixed>> all items (admin) */
    public function allForMenu(int $menuId): array
    {
        return $this->db->select(
            "SELECT mi.*, p.`slug` AS page_slug FROM `menu_items` mi
             LEFT JOIN `pages` p ON p.`id` = mi.`page_id`
             WHERE mi.`menu_id` = :mid ORDER BY mi.`sort_order` ASC, mi.`id` ASC",
            ['mid' => $menuId]
        );
    }

    public function findScoped(int $id, int $menuId): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `menu_items` WHERE `id` = :id AND `menu_id` = :mid LIMIT 1",
            ['id' => $id, 'mid' => $menuId]
        );
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `menu_items`
             (`menu_id`,`parent_id`,`label`,`page_id`,`url`,`is_external`,`open_new_tab`,`sort_order`,`is_active`)
             VALUES
             (:menu_id,:parent_id,:label,:page_id,:url,:is_external,:open_new_tab,:sort_order,:is_active)",
            $data
        );
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $this->db->statement(
            "UPDATE `menu_items` SET
              `parent_id`=:parent_id, `label`=:label, `page_id`=:page_id, `url`=:url,
              `is_external`=:is_external, `open_new_tab`=:open_new_tab, `sort_order`=:sort_order, `is_active`=:is_active
             WHERE `id`=:id",
            $data
        );
    }

    public function delete(int $id, int $menuId): void
    {
        $this->db->statement(
            "DELETE FROM `menu_items` WHERE `id` = :id AND `menu_id` = :mid",
            ['id' => $id, 'mid' => $menuId]
        );
    }
}
