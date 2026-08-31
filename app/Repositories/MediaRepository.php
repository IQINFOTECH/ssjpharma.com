<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class MediaRepository extends Repository
{
    protected string $table = 'media';

    public function findActive(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `media` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1",
            ['id' => $id]
        );
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(string $search = '', int $limit = 24, int $offset = 0): array
    {
        $where = ['`deleted_at` IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = 'LOWER(CONCAT_WS(\' \', `original_name`, COALESCE(`alt_text`,\'\'))) LIKE :s';
            $params['s'] = '%' . strtolower($search) . '%';
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `media` {$whereSql}", $params)['c'] ?? 0);

        $limit  = max(1, $limit);
        $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT * FROM `media` {$whereSql} ORDER BY `created_at` DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `media`
             (`disk_path`,`url_path`,`original_name`,`mime`,`extension`,`size_bytes`,`width`,`height`,`alt_text`,`title`,`uploaded_by`)
             VALUES
             (:disk_path,:url_path,:original_name,:mime,:extension,:size_bytes,:width,:height,:alt_text,:title,:uploaded_by)",
            $data
        );
    }

    public function updateMeta(int $id, ?string $altText, ?string $title): void
    {
        $this->db->statement(
            "UPDATE `media` SET `alt_text` = :a, `title` = :t WHERE `id` = :id",
            ['a' => $altText, 't' => $title, 'id' => $id]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->statement("UPDATE `media` SET `deleted_at` = NOW() WHERE `id` = :id", ['id' => $id]);
    }
}
