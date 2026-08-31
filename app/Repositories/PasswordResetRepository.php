<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Password-reset tokens. Only the SHA-256 HASH of the token is stored; the raw
 * token exists only in the emailed link (SECURITY_PLAN §5/§9-Phase2).
 */
final class PasswordResetRepository extends Repository
{
    protected string $table = 'password_resets';

    public function invalidateForUser(int $userId): void
    {
        $this->db->statement(
            "UPDATE `password_resets` SET `used_at` = NOW() WHERE `user_id` = :u AND `used_at` IS NULL",
            ['u' => $userId]
        );
    }

    public function create(int $userId, string $tokenHash, string $expiresAt, ?string $ip): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `password_resets` (`user_id`,`token_hash`,`expires_at`,`ip`) VALUES (:u,:t,:e,:ip)",
            ['u' => $userId, 't' => $tokenHash, 'e' => $expiresAt, 'ip' => $ip]
        );
    }

    /** Find a still-valid (unused, unexpired) token row by its hash. */
    public function findValidByHash(string $tokenHash): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `password_resets`
             WHERE `token_hash` = :t AND `used_at` IS NULL AND `expires_at` > NOW() LIMIT 1",
            ['t' => $tokenHash]
        );
    }

    public function markUsed(int $id): void
    {
        $this->db->statement("UPDATE `password_resets` SET `used_at` = NOW() WHERE `id` = :id", ['id' => $id]);
    }

    /** Count reset requests from an IP in the last N seconds (rate limiting). */
    public function countRecentByIp(string $ip, int $seconds): int
    {
        $seconds = max(1, $seconds);
        $row = $this->db->selectOne(
            "SELECT COUNT(*) c FROM `password_resets` WHERE `ip` = :ip AND `created_at` >= (NOW() - INTERVAL {$seconds} SECOND)",
            ['ip' => $ip]
        );
        return (int) ($row['c'] ?? 0);
    }
}
