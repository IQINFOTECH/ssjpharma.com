<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Daily follow-up digest idempotency (Phase 5). One row per (user, day) with a
 * UNIQUE key — so a cron running twice cannot produce a second digest. The claim
 * is DB-backed (INSERT that races safely), never a filesystem lock.
 */
final class CommunicationDigestRepository extends Repository
{
    protected string $table = 'communication_digests';

    /**
     * Attempt to claim today's digest slot for a user. Returns true only for the
     * caller that actually inserted the row (INSERT IGNORE → affected rows = 1);
     * a duplicate returns false and must NOT send again.
     */
    public function claim(int $userId, string $date, int $leadCount): bool
    {
        $affected = $this->db->affectingStatement(
            "INSERT IGNORE INTO `communication_digests` (`user_id`,`digest_date`,`lead_count`,`status`)
             VALUES (:u,:d,:c,'queued')",
            ['u' => $userId, 'd' => $date, 'c' => $leadCount]
        );
        return $affected === 1;
    }

    public function markStatus(int $userId, string $date, string $status): void
    {
        $this->db->statement(
            "UPDATE `communication_digests` SET `status`=:s WHERE `user_id`=:u AND `digest_date`=:d",
            ['s' => $status, 'u' => $userId, 'd' => $date]
        );
    }
}
