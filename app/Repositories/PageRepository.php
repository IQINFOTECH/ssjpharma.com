<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class PageRepository extends Repository
{
    protected string $table = 'pages';

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `pages`
             WHERE `slug` = :slug AND `status` = 'published' AND `deleted_at` IS NULL
             LIMIT 1",
            ['slug' => $slug]
        );
    }

    public function findPublishedHome(): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `pages`
             WHERE `is_home` = 1 AND `status` = 'published' AND `deleted_at` IS NULL
             ORDER BY `id` ASC LIMIT 1"
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `pages` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1",
            ['id' => $id]
        );
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = "SELECT * FROM `pages` WHERE `slug` = :slug AND `deleted_at` IS NULL";
        $params = ['slug' => $slug];
        if ($exceptId !== null) {
            $sql .= " AND `id` <> :id";
            $params['id'] = $exceptId;
        }
        return $this->db->selectOne($sql . ' LIMIT 1', $params);
    }

    /** @return array<int,array<string,mixed>> */
    public function allPublishedForSitemap(): array
    {
        return $this->db->select(
            "SELECT `slug`,`is_home`,`updated_at` FROM `pages`
             WHERE `status` = 'published' AND `deleted_at` IS NULL
             AND (`robots` IS NULL OR `robots` NOT LIKE '%noindex%')
             ORDER BY `is_home` DESC, `slug` ASC"
        );
    }

    /**
     * Admin listing with optional search + status filter + pagination.
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginateForAdmin(string $search = '', string $status = '', int $limit = 20, int $offset = 0): array
    {
        $where = ['`deleted_at` IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = 'LOWER(CONCAT_WS(\' \', `title`, `slug`)) LIKE :s';
            $params['s'] = '%' . strtolower($search) . '%';
        }
        if ($status !== '') {
            $where[] = '`status` = :st';
            $params['st'] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `pages` {$whereSql}", $params)['c'] ?? 0);

        $limit  = max(1, $limit);
        $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT * FROM `pages` {$whereSql} ORDER BY `updated_at` DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `pages`
             (`title`,`slug`,`status`,`template`,`content`,`is_home`,`meta_title`,`meta_description`,
              `canonical_url`,`robots`,`og_image_id`,`featured_image_id`,`published_at`,`created_by`,`updated_by`)
             VALUES
             (:title,:slug,:status,:template,:content,:is_home,:meta_title,:meta_description,
              :canonical_url,:robots,:og_image_id,:featured_image_id,:published_at,:created_by,:updated_by)",
            $data
        );
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $this->db->statement(
            "UPDATE `pages` SET
              `title`=:title, `slug`=:slug, `status`=:status, `template`=:template, `content`=:content,
              `is_home`=:is_home, `meta_title`=:meta_title, `meta_description`=:meta_description,
              `canonical_url`=:canonical_url, `robots`=:robots, `og_image_id`=:og_image_id,
              `featured_image_id`=:featured_image_id, `published_at`=:published_at, `updated_by`=:updated_by
             WHERE `id`=:id",
            $data
        );
    }

    public function setStatus(int $id, string $status, ?int $userId = null): void
    {
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $this->db->statement(
            "UPDATE `pages` SET `status`=:s, `published_at`=COALESCE(`published_at`,:p), `updated_by`=:u WHERE `id`=:id",
            ['s' => $status, 'p' => $publishedAt, 'u' => $userId, 'id' => $id]
        );
    }

    public function clearOtherHome(int $exceptId): void
    {
        $this->db->statement("UPDATE `pages` SET `is_home` = 0 WHERE `id` <> :id", ['id' => $exceptId]);
    }

    public function softDelete(int $id): void
    {
        $this->db->statement(
            "UPDATE `pages` SET `deleted_at` = NOW() WHERE `id` = :id",
            ['id' => $id]
        );
    }
}
