<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Append-only audit store. No update/delete methods are provided — audit records
 * are immutable to the application (SECURITY_PLAN / Phase 2 §12).
 */
final class AuditRepository extends Repository
{
    protected string $table = 'audit_log';

    public function record(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `audit_log` (`user_id`,`event`,`entity_type`,`entity_id`,`ip`,`user_agent`,`meta`)
             VALUES (:user_id,:event,:entity_type,:entity_id,:ip,:user_agent,:meta)",
            $data
        );
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) { $where[] = 'a.`user_id` = :uid'; $params['uid'] = (int) $filters['user_id']; }
        if (!empty($filters['event']))   { $where[] = 'a.`event` = :ev';    $params['ev'] = (string) $filters['event']; }
        if (!empty($filters['entity']))  { $where[] = 'a.`entity_type` = :et'; $params['et'] = (string) $filters['entity']; }
        if (!empty($filters['from']))    { $where[] = 'a.`created_at` >= :from'; $params['from'] = $filters['from'] . ' 00:00:00'; }
        if (!empty($filters['to']))      { $where[] = 'a.`created_at` <= :to';   $params['to'] = $filters['to'] . ' 23:59:59'; }
        if (!empty($filters['q'])) {
            // Free-text over event + entity_type (structured filters cover user/entity/date).
            // meta (JSON) is intentionally excluded to avoid cross-collation LIKE errors.
            $where[] = 'LOWER(CONCAT_WS(\' \', a.`event`, COALESCE(a.`entity_type`,\'\'))) LIKE :q';
            $params['q'] = '%' . strtolower((string) $filters['q']) . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `audit_log` a {$whereSql}", $params)['c'] ?? 0);

        $limit = max(1, $limit); $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT a.*, u.`name` AS user_name, u.`email` AS user_email
             FROM `audit_log` a LEFT JOIN `users` u ON u.id = a.`user_id`
             {$whereSql} ORDER BY a.`id` DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> recent events for one user */
    public function forUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, $limit);
        return $this->db->select(
            "SELECT * FROM `audit_log` WHERE `user_id` = :u OR (`entity_type` = 'user' AND `entity_id` = :u2)
             ORDER BY `id` DESC LIMIT {$limit}",
            ['u' => $userId, 'u2' => $userId]
        );
    }

    /** @return array<int,string> distinct event names (for filter dropdowns) */
    public function distinctEvents(): array
    {
        $rows = $this->db->select("SELECT DISTINCT `event` FROM `audit_log` ORDER BY `event`");
        return array_map(static fn (array $r): string => (string) $r['event'], $rows);
    }
}
