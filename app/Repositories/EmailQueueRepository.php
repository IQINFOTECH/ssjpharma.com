<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Outbound email queue store. Concurrency-safe WITHOUT SELECT ... FOR UPDATE
 * SKIP LOCKED (unavailable on older shared-hosting MariaDB): a worker claims rows
 * with a single atomic UPDATE stamping its unique token, then reads back only the
 * rows it stamped. A second worker's UPDATE cannot match rows already flipped to
 * "processing", so no message is ever sent twice.
 */
final class EmailQueueRepository extends Repository
{
    protected string $table = 'email_queue';

    private const INSERT_COLS = [
        'lead_id', 'template_key', 'recipient_email', 'recipient_name',
        'reply_to_email', 'reply_to_name', 'subject', 'body_html', 'body_text',
        'status', 'max_attempts', 'available_at',
    ];

    public function enqueue(array $data): int
    {
        $data['status'] = $data['status'] ?? 'pending';
        $data['max_attempts'] = $data['max_attempts'] ?? 5;
        $data['available_at'] = $data['available_at'] ?? date('Y-m-d H:i:s');
        $cols = '`' . implode('`,`', self::INSERT_COLS) . '`';
        $ph = ':' . implode(',:', self::INSERT_COLS);
        $bind = [];
        foreach (self::INSERT_COLS as $c) {
            $bind[$c] = $data[$c] ?? null;
        }
        return (int) $this->db->insert("INSERT INTO `email_queue` ({$cols}) VALUES ({$ph})", $bind);
    }

    /** Reset messages stuck in "processing" (crashed worker) back to pending. */
    public function reclaimStale(int $seconds): int
    {
        $seconds = max(60, $seconds);
        return $this->db->affectingStatement(
            "UPDATE `email_queue` SET `status`='pending', `locked_by`=NULL, `locked_at`=NULL
             WHERE `status`='processing' AND `locked_at` < (NOW() - INTERVAL {$seconds} SECOND)"
        );
    }

    /**
     * Atomically claim up to $limit due messages for this worker and return them.
     * Increments attempts (each claim is one delivery attempt).
     * @return array<int,array<string,mixed>>
     */
    public function claimBatch(string $worker, int $limit): array
    {
        $limit = max(1, min($limit, 200));
        $this->db->affectingStatement(
            "UPDATE `email_queue`
             SET `status`='processing', `locked_by`=:w, `locked_at`=NOW(), `attempts`=`attempts`+1, `last_attempt_at`=NOW()
             WHERE `status`='pending' AND `available_at` <= NOW()
             ORDER BY `available_at` ASC, `id` ASC
             LIMIT {$limit}",
            ['w' => $worker]
        );
        return $this->db->select(
            "SELECT * FROM `email_queue` WHERE `locked_by`=:w AND `status`='processing' ORDER BY `id` ASC",
            ['w' => $worker]
        );
    }

    public function markSent(int $id): void
    {
        $this->db->statement(
            "UPDATE `email_queue` SET `status`='sent', `sent_at`=NOW(), `locked_by`=NULL, `locked_at`=NULL, `last_error`=NULL WHERE `id`=:id",
            ['id' => $id]
        );
    }

    /** Temporary failure: back to pending with a backoff delay. */
    public function markRetry(int $id, int $delaySeconds, string $error): void
    {
        $delaySeconds = max(1, $delaySeconds);
        $this->db->statement(
            "UPDATE `email_queue` SET `status`='pending', `available_at`=(NOW() + INTERVAL {$delaySeconds} SECOND),
                    `locked_by`=NULL, `locked_at`=NULL, `last_error`=:e WHERE `id`=:id",
            ['e' => mb_substr($error, 0, 255), 'id' => $id]
        );
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->statement(
            "UPDATE `email_queue` SET `status`='failed', `locked_by`=NULL, `locked_at`=NULL, `last_error`=:e WHERE `id`=:id",
            ['e' => mb_substr($error, 0, 255), 'id' => $id]
        );
    }

    // --- Admin ---------------------------------------------------------------

    public function findById(int $id): ?array
    {
        return $this->db->selectOne("SELECT * FROM `email_queue` WHERE `id`=:id LIMIT 1", ['id' => $id]);
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int} */
    public function paginate(?string $status, int $limit, int $offset): array
    {
        $where = '1=1';
        $params = [];
        if ($status !== null && $status !== '') {
            $where = '`status` = :st';
            $params['st'] = $status;
        }
        $total = (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `email_queue` WHERE {$where}", $params)['c'] ?? 0);
        $limit = max(1, $limit); $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT `id`,`lead_id`,`template_key`,`recipient_email`,`recipient_name`,`subject`,`status`,
                    `attempts`,`max_attempts`,`available_at`,`sent_at`,`last_attempt_at`,`last_error`,`created_at`
             FROM `email_queue` WHERE {$where} ORDER BY `id` DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,int> status → count */
    public function statusCounts(): array
    {
        $out = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
        foreach ($this->db->select("SELECT `status`, COUNT(*) c FROM `email_queue` GROUP BY `status`") as $r) {
            $out[(string) $r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /** Admin: requeue a failed/cancelled message with fresh attempts. */
    public function requeue(int $id): void
    {
        $this->db->statement(
            "UPDATE `email_queue` SET `status`='pending', `attempts`=0, `available_at`=NOW(),
                    `locked_by`=NULL, `locked_at`=NULL, `last_error`=NULL
             WHERE `id`=:id AND `status` IN ('failed','cancelled')",
            ['id' => $id]
        );
    }

    /** Admin: cancel a message that has not been sent yet. */
    public function cancel(int $id): void
    {
        $this->db->statement(
            "UPDATE `email_queue` SET `status`='cancelled', `locked_by`=NULL, `locked_at`=NULL
             WHERE `id`=:id AND `status` IN ('pending','failed')",
            ['id' => $id]
        );
    }
}
