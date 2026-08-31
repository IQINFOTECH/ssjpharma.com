<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Products aggregate: the product row plus its owned sub-collections (images,
 * documents, specifications) and its therapeutic-area links. All queries use
 * prepared statements; LIMIT/OFFSET are int-cast + inlined (EMULATE_PREPARES=false).
 */
final class ProductRepository extends Repository
{
    protected string $table = 'products';

    // --- Lookups -------------------------------------------------------------

    public function findById(int $id): ?array
    {
        return $this->db->selectOne("SELECT * FROM `products` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1", ['id' => $id]);
    }

    /**
     * Content governance (Phase 6): in PRODUCTION, is_demo records are never
     * public — demo pharmaceutical placeholder data must not be reachable or
     * indexable. In non-production (staging/local) demo items remain visible with
     * their badge so the owner can review layout before real data is loaded.
     */
    private function demoCond(string $alias = ''): string
    {
        if (config('app.env') !== 'production') {
            return '';
        }
        $col = $alias === '' ? '`is_demo`' : $alias . '.`is_demo`';
        return ' AND ' . $col . ' = 0';
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `products` WHERE `slug` = :s AND `status` = 'published' AND `deleted_at` IS NULL" . $this->demoCond() . " LIMIT 1",
            ['s' => $slug]
        );
    }

    public function findPublishedById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `products` WHERE `id` = :id AND `status` = 'published' AND `deleted_at` IS NULL" . $this->demoCond() . " LIMIT 1",
            ['id' => $id]
        );
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = "SELECT * FROM `products` WHERE `slug` = :s AND `deleted_at` IS NULL";
        $p = ['s' => $slug];
        if ($exceptId !== null) { $sql .= " AND `id` <> :id"; $p['id'] = $exceptId; }
        return $this->db->selectOne($sql . ' LIMIT 1', $p);
    }

    // --- Admin listing -------------------------------------------------------

    /**
     * @param array{q?:string,category?:int,ta?:int,status?:string,featured?:string,dosage?:int} $f
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginateForAdmin(array $f, int $limit, int $offset): array
    {
        $where = ['p.`deleted_at` IS NULL'];
        $params = [];
        $join = '';

        if (!empty($f['q'])) {
            $where[] = 'LOWER(CONCAT_WS(\' \', p.`name`, COALESCE(p.`generic_name`,\'\'), COALESCE(p.`code`,\'\'))) LIKE :q';
            $params['q'] = '%' . strtolower((string) $f['q']) . '%';
        }
        if (!empty($f['category'])) { $where[] = 'p.`category_id` = :cat'; $params['cat'] = (int) $f['category']; }
        if (!empty($f['dosage']))   { $where[] = 'p.`dosage_form_id` = :df'; $params['df'] = (int) $f['dosage']; }
        if (($f['status'] ?? '') !== '') { $where[] = 'p.`status` = :st'; $params['st'] = (string) $f['status']; }
        if (($f['featured'] ?? '') === '1') { $where[] = 'p.`is_featured` = 1'; }
        if (!empty($f['ta'])) {
            $join = 'JOIN `product_therapeutic_areas` pta ON pta.product_id = p.id';
            $where[] = 'pta.`therapeutic_area_id` = :ta';
            $params['ta'] = (int) $f['ta'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->selectOne("SELECT COUNT(DISTINCT p.id) c FROM `products` p {$join} {$whereSql}", $params)['c'] ?? 0);

        $limit = max(1, $limit); $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT DISTINCT p.*, c.`name` AS category_name, d.`name` AS dosage_name,
                    (SELECT GROUP_CONCAT(t.`name` SEPARATOR ', ') FROM `product_therapeutic_areas` pta2
                     JOIN `therapeutic_areas` t ON t.id = pta2.therapeutic_area_id WHERE pta2.product_id = p.id) AS ta_names
             FROM `products` p {$join}
             LEFT JOIN `product_categories` c ON c.id = p.category_id
             LEFT JOIN `dosage_forms` d ON d.id = p.dosage_form_id
             {$whereSql} ORDER BY p.`updated_at` DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    // --- Public catalog ------------------------------------------------------

    /**
     * @param array{q?:string,category_ids?:array<int,int>,ta?:int,dosage?:int} $f
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginatePublic(array $f, int $limit, int $offset): array
    {
        $where = ["p.`status` = 'published'", 'p.`deleted_at` IS NULL'];
        if (config('app.env') === 'production') { $where[] = 'p.`is_demo` = 0'; }
        $params = [];
        $join = '';

        if (!empty($f['q'])) {
            $where[] = 'LOWER(CONCAT_WS(\' \', p.`name`, COALESCE(p.`generic_name`,\'\'), COALESCE(p.`code`,\'\'))) LIKE :q';
            $params['q'] = '%' . strtolower((string) $f['q']) . '%';
        }
        if (!empty($f['category_ids'])) {
            $in = implode(',', array_map('intval', $f['category_ids']));
            $where[] = "p.`category_id` IN ({$in})";
        }
        if (!empty($f['dosage'])) { $where[] = 'p.`dosage_form_id` = :df'; $params['df'] = (int) $f['dosage']; }
        if (!empty($f['ta'])) {
            $join = 'JOIN `product_therapeutic_areas` pta ON pta.product_id = p.id';
            $where[] = 'pta.`therapeutic_area_id` = :ta';
            $params['ta'] = (int) $f['ta'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->selectOne("SELECT COUNT(DISTINCT p.id) c FROM `products` p {$join} {$whereSql}", $params)['c'] ?? 0);

        $limit = max(1, $limit); $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT DISTINCT p.`id`, p.`name`, p.`slug`, p.`code`, p.`generic_name`, p.`short_description`,
                    p.`is_demo`, d.`name` AS dosage_name, m.`url_path` AS image_url
             FROM `products` p {$join}
             LEFT JOIN `dosage_forms` d ON d.id = p.dosage_form_id
             LEFT JOIN `media` m ON m.id = p.hero_image_id AND m.deleted_at IS NULL
             {$whereSql} ORDER BY p.`is_featured` DESC, p.`sort_order` ASC, p.`name` ASC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Related published products: same category or shared therapeutic area. */
    public function related(int $productId, ?int $categoryId, array $taIds, int $limit = 4): array
    {
        $limit = max(1, $limit);
        $params = ['pid' => $productId];
        $taJoin = '';
        $orConds = [];
        if ($categoryId !== null) { $orConds[] = 'p.`category_id` = :cat'; $params['cat'] = $categoryId; }
        if ($taIds !== []) {
            $in = implode(',', array_map('intval', $taIds));
            $taJoin = "LEFT JOIN `product_therapeutic_areas` pta ON pta.product_id = p.id AND pta.therapeutic_area_id IN ({$in})";
            $orConds[] = 'pta.`therapeutic_area_id` IS NOT NULL';
        }
        if ($orConds === []) {
            return [];
        }
        $orSql = '(' . implode(' OR ', $orConds) . ')';
        return $this->db->select(
            "SELECT DISTINCT p.`id`, p.`name`, p.`slug`, p.`generic_name`, p.`short_description`,
                    d.`name` AS dosage_name, m.`url_path` AS image_url
             FROM `products` p {$taJoin}
             LEFT JOIN `dosage_forms` d ON d.id = p.dosage_form_id
             LEFT JOIN `media` m ON m.id = p.hero_image_id AND m.deleted_at IS NULL
             WHERE p.`status`='published' AND p.`deleted_at` IS NULL AND p.`id` <> :pid AND {$orSql}" . $this->demoCond('p') . "
             ORDER BY p.`is_featured` DESC, p.`sort_order` ASC LIMIT {$limit}",
            $params
        );
    }

    /** @return array<int,array<string,mixed>> featured published products */
    public function featured(int $limit = 6): array
    {
        $limit = max(1, $limit);
        return $this->db->select(
            "SELECT p.`id`, p.`name`, p.`slug`, p.`generic_name`, p.`short_description`, d.`name` AS dosage_name, m.`url_path` AS image_url
             FROM `products` p
             LEFT JOIN `dosage_forms` d ON d.id = p.dosage_form_id
             LEFT JOIN `media` m ON m.id = p.hero_image_id AND m.deleted_at IS NULL
             WHERE p.`is_featured`=1 AND p.`status`='published' AND p.`deleted_at` IS NULL" . $this->demoCond('p') . "
             ORDER BY p.`sort_order` ASC, p.`name` ASC LIMIT {$limit}"
        );
    }

    /** @return array<int,array<string,mixed>> published products for the sitemap */
    public function allPublishedForSitemap(): array
    {
        return $this->db->select(
            "SELECT `slug`,`updated_at` FROM `products`
             WHERE `status`='published' AND `deleted_at` IS NULL" . $this->demoCond() . "
             AND (`robots` IS NULL OR `robots` NOT LIKE '%noindex%') ORDER BY `slug`"
        );
    }

    // --- Writes --------------------------------------------------------------

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `products`
             (`name`,`code`,`slug`,`short_description`,`description`,`status`,`is_featured`,`is_demo`,`sort_order`,
              `generic_name`,`composition`,`strength`,`dosage_form_id`,`pack_size`,`category_id`,`hero_image_id`,
              `meta_title`,`meta_description`,`canonical_url`,`og_image_id`,`robots`,`published_at`,`created_by`,`updated_by`)
             VALUES
             (:name,:code,:slug,:short_description,:description,:status,:is_featured,:is_demo,:sort_order,
              :generic_name,:composition,:strength,:dosage_form_id,:pack_size,:category_id,:hero_image_id,
              :meta_title,:meta_description,:canonical_url,:og_image_id,:robots,:published_at,:created_by,:updated_by)",
            $data
        );
    }

    public function update(int $id, array $data): void
    {
        // Bind EXACTLY the placeholders present in the SQL — callers may pass a
        // superset (e.g. created_by), which would otherwise raise HY093 under
        // PDO::ATTR_EMULATE_PREPARES=false.
        $cols = ['name','code','slug','short_description','description','status','is_featured','is_demo','sort_order',
                 'generic_name','composition','strength','dosage_form_id','pack_size','category_id','hero_image_id',
                 'meta_title','meta_description','canonical_url','og_image_id','robots','published_at','updated_by'];
        $bind = ['id' => $id];
        foreach ($cols as $c) {
            $bind[$c] = $data[$c] ?? null;
        }
        $this->db->statement(
            "UPDATE `products` SET
              `name`=:name,`code`=:code,`slug`=:slug,`short_description`=:short_description,`description`=:description,
              `status`=:status,`is_featured`=:is_featured,`is_demo`=:is_demo,`sort_order`=:sort_order,`generic_name`=:generic_name,
              `composition`=:composition,`strength`=:strength,`dosage_form_id`=:dosage_form_id,`pack_size`=:pack_size,
              `category_id`=:category_id,`hero_image_id`=:hero_image_id,`meta_title`=:meta_title,
              `meta_description`=:meta_description,`canonical_url`=:canonical_url,`og_image_id`=:og_image_id,
              `robots`=:robots,`published_at`=:published_at,`updated_by`=:updated_by
             WHERE `id`=:id",
            $bind
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $this->db->statement(
            "UPDATE `products` SET `status`=:s, `published_at`=COALESCE(`published_at`,:p) WHERE `id`=:id",
            ['s' => $status, 'p' => $publishedAt, 'id' => $id]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->statement("UPDATE `products` SET `deleted_at` = NOW() WHERE `id` = :id", ['id' => $id]);
    }

    // --- Therapeutic-area links ----------------------------------------------

    /** @return array<int,int> */
    public function therapeuticAreaIds(int $productId): array
    {
        $rows = $this->db->select("SELECT therapeutic_area_id FROM `product_therapeutic_areas` WHERE product_id = :p", ['p' => $productId]);
        return array_map(static fn ($r): int => (int) $r['therapeutic_area_id'], $rows);
    }

    /** @return array<int,array<string,mixed>> {id,name,slug} for a product's published TAs */
    public function therapeuticAreas(int $productId, bool $publishedOnly = false): array
    {
        $cond = $publishedOnly ? "AND t.status='published' AND t.deleted_at IS NULL" : '';
        return $this->db->select(
            "SELECT t.id, t.name, t.slug FROM `therapeutic_areas` t
             JOIN `product_therapeutic_areas` pta ON pta.therapeutic_area_id = t.id
             WHERE pta.product_id = :p {$cond} ORDER BY t.name",
            ['p' => $productId]
        );
    }

    /** @param array<int,int> $taIds */
    public function setTherapeuticAreas(int $productId, array $taIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->statement("DELETE FROM `product_therapeutic_areas` WHERE product_id = :p", ['p' => $productId]);
            foreach (array_unique($taIds) as $tid) {
                $tid = (int) $tid;
                if ($tid > 0) {
                    $this->db->statement("INSERT IGNORE INTO `product_therapeutic_areas` (product_id, therapeutic_area_id) VALUES (:p,:t)", ['p' => $productId, 't' => $tid]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // --- Images --------------------------------------------------------------

    public function images(int $productId): array
    {
        return $this->db->select(
            "SELECT pi.*, m.`url_path`, m.`mime` FROM `product_images` pi
             JOIN `media` m ON m.id = pi.media_id
             WHERE pi.product_id = :p ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC",
            ['p' => $productId]
        );
    }

    public function addImage(int $productId, int $mediaId, ?string $alt, bool $isPrimary): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `product_images` (product_id, media_id, alt_text, is_primary, sort_order)
             VALUES (:p,:m,:a,:pr, (SELECT COALESCE(MAX(sort_order),0)+10 FROM `product_images` pi2 WHERE pi2.product_id = :p2))",
            ['p' => $productId, 'm' => $mediaId, 'a' => $alt, 'pr' => $isPrimary ? 1 : 0, 'p2' => $productId]
        );
    }

    public function findImage(int $imageId, int $productId): ?array
    {
        return $this->db->selectOne("SELECT * FROM `product_images` WHERE id = :i AND product_id = :p LIMIT 1", ['i' => $imageId, 'p' => $productId]);
    }

    public function deleteImage(int $imageId, int $productId): void
    {
        $this->db->statement("DELETE FROM `product_images` WHERE id = :i AND product_id = :p", ['i' => $imageId, 'p' => $productId]);
    }

    public function clearPrimaryImages(int $productId): void
    {
        $this->db->statement("UPDATE `product_images` SET is_primary = 0 WHERE product_id = :p", ['p' => $productId]);
    }

    public function setPrimaryImage(int $imageId, int $productId): void
    {
        $this->clearPrimaryImages($productId);
        $this->db->statement("UPDATE `product_images` SET is_primary = 1 WHERE id = :i AND product_id = :p", ['i' => $imageId, 'p' => $productId]);
    }

    // --- Documents -----------------------------------------------------------

    public function documents(int $productId): array
    {
        return $this->db->select(
            "SELECT pd.*, m.`url_path`, m.`size_bytes` FROM `product_documents` pd
             JOIN `media` m ON m.id = pd.media_id
             WHERE pd.product_id = :p ORDER BY pd.sort_order ASC, pd.id ASC",
            ['p' => $productId]
        );
    }

    public function addDocument(int $productId, int $mediaId, string $displayName, string $docType, ?int $userId): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `product_documents` (product_id, media_id, display_name, doc_type, uploaded_by, sort_order)
             VALUES (:p,:m,:dn,:dt,:u, (SELECT COALESCE(MAX(sort_order),0)+10 FROM `product_documents` pd2 WHERE pd2.product_id = :p2))",
            ['p' => $productId, 'm' => $mediaId, 'dn' => $displayName, 'dt' => $docType, 'u' => $userId, 'p2' => $productId]
        );
    }

    public function findDocument(int $docId, int $productId): ?array
    {
        return $this->db->selectOne("SELECT * FROM `product_documents` WHERE id = :i AND product_id = :p LIMIT 1", ['i' => $docId, 'p' => $productId]);
    }

    public function deleteDocument(int $docId, int $productId): void
    {
        $this->db->statement("DELETE FROM `product_documents` WHERE id = :i AND product_id = :p", ['i' => $docId, 'p' => $productId]);
    }

    // --- Specifications ------------------------------------------------------

    public function specifications(int $productId): array
    {
        return $this->db->select("SELECT * FROM `product_specifications` WHERE product_id = :p ORDER BY sort_order ASC, id ASC", ['p' => $productId]);
    }

    public function replaceSpecifications(int $productId, array $specs): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->statement("DELETE FROM `product_specifications` WHERE product_id = :p", ['p' => $productId]);
            $order = 0;
            foreach ($specs as $spec) {
                $title = trim((string) ($spec['title'] ?? ''));
                $value = trim((string) ($spec['value'] ?? ''));
                if ($title === '' || $value === '') { continue; }
                $order += 10;
                $this->db->statement(
                    "INSERT INTO `product_specifications` (product_id, title, value, unit, sort_order) VALUES (:p,:t,:v,:u,:o)",
                    ['p' => $productId, 't' => mb_substr($title, 0, 150), 'v' => mb_substr($value, 0, 255),
                     'u' => ($u = trim((string) ($spec['unit'] ?? ''))) !== '' ? mb_substr($u, 0, 40) : null, 'o' => $order]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
