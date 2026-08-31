<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Registry of active admin sessions (for /admin/sessions view + revoke).
 * Session ids are hashed before storage so the store never holds a live id.
 */
final class UserSessionRepository extends Repository
{
    protected string $table = 'user_sessions';

    public function touch(string $sessionId, int $userId, ?string $ip, ?string $ua): void
    {
        $sid = $this->hash($sessionId);
        $this->db->statement(
            "INSERT INTO `user_sessions` (`session_id`,`user_id`,`ip`,`user_agent`,`last_activity_at`)
             VALUES (:sid,:u,:ip,:ua,NOW())
             ON DUPLICATE KEY UPDATE `last_activity_at` = NOW(), `ip` = VALUES(`ip`), `user_agent` = VALUES(`user_agent`)",
            ['sid' => $sid, 'u' => $userId, 'ip' => $ip, 'ua' => $ua]
        );
    }

    public function isRevoked(string $sessionId): bool
    {
        $row = $this->db->selectOne(
            "SELECT `revoked_at` FROM `user_sessions` WHERE `session_id` = :sid LIMIT 1",
            ['sid' => $this->hash($sessionId)]
        );
        return $row !== null && $row['revoked_at'] !== null;
    }

    /** @return array<int,array<string,mixed>> non-revoked sessions for a user */
    public function activeForUser(int $userId): array
    {
        return $this->db->select(
            "SELECT * FROM `user_sessions` WHERE `user_id` = :u AND `revoked_at` IS NULL ORDER BY `last_activity_at` DESC",
            ['u' => $userId]
        );
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int} all sessions (admin) */
    public function paginateAll(int $limit, int $offset): array
    {
        $total = (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `user_sessions` WHERE `revoked_at` IS NULL")['c'] ?? 0);
        $limit = max(1, $limit); $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT s.*, u.`name` AS user_name, u.`email` AS user_email
             FROM `user_sessions` s JOIN `users` u ON u.id = s.`user_id`
             WHERE s.`revoked_at` IS NULL ORDER BY s.`last_activity_at` DESC LIMIT {$limit} OFFSET {$offset}"
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function findById(int $id): ?array
    {
        return $this->db->selectOne("SELECT * FROM `user_sessions` WHERE `id` = :id LIMIT 1", ['id' => $id]);
    }

    public function revokeById(int $id): void
    {
        $this->db->statement("UPDATE `user_sessions` SET `revoked_at` = NOW() WHERE `id` = :id AND `revoked_at` IS NULL", ['id' => $id]);
    }

    public function revokeBySessionId(string $sessionId): void
    {
        $this->db->statement("UPDATE `user_sessions` SET `revoked_at` = NOW() WHERE `session_id` = :sid", ['sid' => $this->hash($sessionId)]);
    }

    /** Revoke all OTHER sessions of a user except the current one. */
    public function revokeAllForUserExcept(int $userId, string $currentSessionId): void
    {
        $this->db->statement(
            "UPDATE `user_sessions` SET `revoked_at` = NOW() WHERE `user_id` = :u AND `session_id` <> :sid AND `revoked_at` IS NULL",
            ['u' => $userId, 'sid' => $this->hash($currentSessionId)]
        );
    }

    private function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }
}
