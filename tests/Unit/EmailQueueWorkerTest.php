<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EmailQueueWorker;
use PHPUnit\Framework\TestCase;

/**
 * Covers EmailQueueWorker::decide() — the retry / backoff / permanent-failure
 * logic (pure, no DB). This is the risk-carrying part of the queue worker.
 */
final class EmailQueueWorkerTest extends TestCase
{
    private function worker(): EmailQueueWorker
    {
        // decide() touches none of the dependencies, so nulls-via-reflection is
        // unnecessary — construct with a bare instance built without wiring.
        return (new \ReflectionClass(EmailQueueWorker::class))->newInstanceWithoutConstructor();
    }

    public function testSuccessMarksSent(): void
    {
        $d = $this->worker()->decide(['ok' => true, 'permanent' => false, 'error' => null], 1, 5);
        $this->assertSame('sent', $d['action']);
        $this->assertSame(0, $d['delay']);
    }

    public function testPermanentFailureDoesNotRetry(): void
    {
        $d = $this->worker()->decide(['ok' => false, 'permanent' => true, 'error' => 'Invalid recipient address'], 1, 5);
        $this->assertSame('failed', $d['action']);
        $this->assertSame('Invalid recipient address', $d['error']);
    }

    public function testTransientFailureRetriesWithFirstBackoff(): void
    {
        $d = $this->worker()->decide(['ok' => false, 'permanent' => false, 'error' => 'SMTP timeout'], 1, 5);
        $this->assertSame('retry', $d['action']);
        $this->assertSame(EmailQueueWorker::BACKOFF[0], $d['delay']);
    }

    public function testBackoffGrowsWithAttempts(): void
    {
        $this->assertSame(EmailQueueWorker::BACKOFF[2], $this->worker()->decide(['ok' => false, 'permanent' => false, 'error' => 'x'], 3, 5)['delay']);
    }

    public function testBackoffIsClampedToScheduleLength(): void
    {
        // attempts beyond the schedule length reuse the last (longest) backoff,
        // but only while attempts < maxAttempts.
        $d = $this->worker()->decide(['ok' => false, 'permanent' => false, 'error' => 'x'], 5, 9);
        $this->assertSame('retry', $d['action']);
        $this->assertSame(EmailQueueWorker::BACKOFF[count(EmailQueueWorker::BACKOFF) - 1], $d['delay']);
    }

    public function testExhaustedAttemptsFailPermanently(): void
    {
        $d = $this->worker()->decide(['ok' => false, 'permanent' => false, 'error' => 'still failing'], 5, 5);
        $this->assertSame('failed', $d['action']);
        $this->assertSame(0, $d['delay']);
    }
}
