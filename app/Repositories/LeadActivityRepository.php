<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Per-lead activity timeline (created / status / priority / assignment / notes /
 * email results). Internal-only; never exposed publicly. Notes are activities of
 * type 'note'.
 */
final class LeadActivityRepository extends Repository
{
    protected string $table = 'lead_activities';

    public function add(int $leadId, ?int $userId, string $type, ?string $description = null, ?array $meta = null): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `lead_activities` (`lead_id`,`user_id`,`type`,`description`,`meta`)
             VALUES (:l,:u,:t,:d,:m)",
            [
                'l' => $leadId,
                'u' => $userId,
                't' => mb_substr($type, 0, 40),
                'd' => $description !== null ? mb_substr($description, 0, 5000) : null,
                'm' => $meta === null || $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]
        );
    }

    /** @return array<int,array<string,mixed>> newest-first timeline with actor names */
    public function forLead(int $leadId): array
    {
        return $this->db->select(
            "SELECT a.*, u.`name` AS user_name FROM `lead_activities` a
             LEFT JOIN `users` u ON u.id = a.user_id
             WHERE a.`lead_id` = :l ORDER BY a.`id` DESC",
            ['l' => $leadId]
        );
    }
}
