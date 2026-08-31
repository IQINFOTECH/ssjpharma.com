<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Login attempt tracking for throttling. NEVER stores passwords.
 */
final class LoginAttemptRepository extends Repository
{
    protected string $table = 'login_attempts';

    public function record(string $email, string $ip, bool $success): void
    {
        $this->db->statement(
            "INSERT INTO `login_attempts` (`email`,`ip`,`success`) VALUES (:e,:ip,:s)",
            ['e' => mb_substr(strtolower($email), 0, 190), 'ip' => $ip, 's' => $success ? 1 : 0]
        );
    }

    /** Failed attempts for an email+ip within the decay window. */
    public function recentFailures(string $email, string $ip, int $seconds): int
    {
        $seconds = max(1, $seconds);
        $row = $this->db->selectOne(
            "SELECT COUNT(*) c FROM `login_attempts`
             WHERE `email` = :e AND `ip` = :ip AND `success` = 0
             AND `created_at` >= (NOW() - INTERVAL {$seconds} SECOND)",
            ['e' => strtolower($email), 'ip' => $ip]
        );
        return (int) ($row['c'] ?? 0);
    }

    /** Clear failures after a successful login (email+ip). */
    public function clearFailures(string $email, string $ip): void
    {
        $this->db->statement(
            "DELETE FROM `login_attempts` WHERE `email` = :e AND `ip` = :ip AND `success` = 0",
            ['e' => strtolower($email), 'ip' => $ip]
        );
    }
}
