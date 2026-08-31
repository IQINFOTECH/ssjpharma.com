<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\EmailQueueRepository;
use App\Repositories\LeadActivityRepository;

/**
 * Drains the outbound email queue (Phase 5 delivery worker). Run by cron.
 *
 * Enqueue and delivery are deliberately separate: lead submission only ever
 * ENQUEUES (so a mail problem can never lose a lead), and this worker performs
 * the actual SMTP out of band. Claiming is concurrency-safe via the repository's
 * atomic token-stamp (no double send). Delivery obeys MAIL_DELIVERY_MODE, so a
 * dev box in "log" mode drains the queue without sending real mail.
 *
 * The retry/backoff decision — the part that carries the real risk — lives in the
 * pure decide() method so it can be unit-tested without a database.
 */
final class EmailQueueWorker
{
    /** Backoff (seconds) applied before the Nth retry: ~1m, 5m, 15m, 1h, 3h. */
    public const BACKOFF = [60, 300, 900, 3600, 10800];

    public function __construct(
        private readonly EmailQueueRepository $queue,
        private readonly MailService $mail,
        private readonly LeadActivityRepository $activities,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Decide what to do with a message given the delivery outcome and how many
     * attempts it has now had (attempts is already incremented by claimBatch, so
     * the first try is attempt 1 → BACKOFF[0]). Pure; no side effects.
     *
     * @param array{ok:bool,permanent:bool,error:?string} $outcome
     * @return array{action:'sent'|'retry'|'failed',delay:int,error:?string}
     */
    public function decide(array $outcome, int $attempts, int $maxAttempts): array
    {
        if (!empty($outcome['ok'])) {
            return ['action' => 'sent', 'delay' => 0, 'error' => null];
        }
        $error = (string) ($outcome['error'] ?? 'Unknown delivery error');
        // Permanent (e.g. invalid recipient) or attempts exhausted → give up.
        if (!empty($outcome['permanent']) || $attempts >= $maxAttempts) {
            return ['action' => 'failed', 'delay' => 0, 'error' => $error];
        }
        $idx = min(max($attempts, 1) - 1, count(self::BACKOFF) - 1);
        return ['action' => 'retry', 'delay' => self::BACKOFF[$idx], 'error' => $error];
    }

    /**
     * Reclaim crashed messages, then claim + deliver due messages in batches until
     * the queue is drained or the safety cap is reached.
     *
     * @return array{claimed:int,sent:int,retried:int,failed:int}
     */
    public function run(int $batchSize = 25, int $maxBatches = 40, int $staleSeconds = 900): array
    {
        $stats = ['claimed' => 0, 'sent' => 0, 'retried' => 0, 'failed' => 0];
        $this->queue->reclaimStale($staleSeconds);
        $worker = bin2hex(random_bytes(8));

        for ($b = 0; $b < $maxBatches; $b++) {
            $rows = $this->queue->claimBatch($worker, $batchSize);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $stats['claimed']++;
                $id = (int) $row['id'];
                $leadId = $row['lead_id'] !== null ? (int) $row['lead_id'] : null;

                $outcome = $this->mail->attempt([
                    'to'            => (string) ($row['recipient_email'] ?? ''),
                    'name'          => $row['recipient_name'] ?? null,
                    'subject'       => (string) ($row['subject'] ?? ''),
                    'html'          => $row['body_html'] ?? null,
                    'text'          => $row['body_text'] ?? null,
                    'reply_to'      => $row['reply_to_email'] ?? null,
                    'reply_to_name' => $row['reply_to_name'] ?? null,
                ]);
                $decision = $this->decide($outcome, (int) $row['attempts'], (int) $row['max_attempts']);

                if ($decision['action'] === 'sent') {
                    $this->queue->markSent($id);
                    $stats['sent']++;
                    if ($leadId !== null) {
                        $this->activities->add($leadId, null, 'email_sent', 'Sent "' . (string) $row['template_key'] . '" to ' . (string) $row['recipient_email'] . '.');
                    }
                } elseif ($decision['action'] === 'retry') {
                    $this->queue->markRetry($id, $decision['delay'], (string) $decision['error']);
                    $stats['retried']++;
                } else {
                    $this->queue->markFailed($id, (string) $decision['error']);
                    $stats['failed']++;
                    if ($leadId !== null) {
                        $this->activities->add($leadId, null, 'email_failed', 'Delivery failed for "' . (string) $row['template_key'] . '": ' . (string) $decision['error']);
                    }
                }
            }
        }

        $this->logger->info('email_queue.run', $stats);
        return $stats;
    }
}
