<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class PageSectionRepository extends Repository
{
    protected string $table = 'page_sections';

    /** @return array<int,array<string,mixed>> ordered visible sections for the public renderer */
    public function visibleForPage(int $pageId): array
    {
        return $this->db->select(
            "SELECT * FROM `page_sections`
             WHERE `page_id` = :pid AND `is_visible` = 1
             ORDER BY `sort_order` ASC, `id` ASC",
            ['pid' => $pageId]
        );
    }

    /** @return array<int,array<string,mixed>> all sections (admin editor) */
    public function allForPage(int $pageId): array
    {
        return $this->db->select(
            "SELECT * FROM `page_sections` WHERE `page_id` = :pid ORDER BY `sort_order` ASC, `id` ASC",
            ['pid' => $pageId]
        );
    }

    public function create(int $pageId, string $type, string $dataJson, int $sortOrder, bool $visible = true): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `page_sections` (`page_id`,`type`,`data`,`sort_order`,`is_visible`)
             VALUES (:pid,:type,:data,:sort,:vis)",
            ['pid' => $pageId, 'type' => $type, 'data' => $dataJson, 'sort' => $sortOrder, 'vis' => $visible ? 1 : 0]
        );
    }

    public function update(int $id, string $dataJson, int $sortOrder, bool $visible): void
    {
        $this->db->statement(
            "UPDATE `page_sections` SET `data`=:data, `sort_order`=:sort, `is_visible`=:vis WHERE `id`=:id",
            ['data' => $dataJson, 'sort' => $sortOrder, 'vis' => $visible ? 1 : 0, 'id' => $id]
        );
    }

    public function delete(int $id, int $pageId): void
    {
        // pageId scoping guards against cross-page deletion (IDOR).
        $this->db->statement(
            "DELETE FROM `page_sections` WHERE `id` = :id AND `page_id` = :pid",
            ['id' => $id, 'pid' => $pageId]
        );
    }

    public function deleteForPage(int $pageId): void
    {
        $this->db->statement("DELETE FROM `page_sections` WHERE `page_id` = :pid", ['pid' => $pageId]);
    }
}
