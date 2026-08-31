<?php

declare(strict_types=1);

/**
 * Outbound email queue worker (Phase 5 delivery). Run by cron every few minutes:
 *
 *   php bin/process-email-queue.php
 *
 * Reclaims crashed messages, then claims and delivers due messages. Delivery obeys
 * MAIL_DELIVERY_MODE (smtp | log | disabled), so a box without SMTP configured
 * drains the queue in "log" mode without sending real mail. Concurrency-safe: two
 * copies running at once never send the same message twice. Exit 0 always (a
 * delivery failure is recorded on the row, not thrown), so cron never alarms.
 */

use App\Core\App;
use App\Core\Logger;
use App\Services\EmailQueueWorker;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("process-email-queue.php is a CLI script.\n");
}

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$c = $app->container();
/** @var EmailQueueWorker $worker */
$worker = $c->get(EmailQueueWorker::class);
/** @var Logger $logger */
$logger = $c->get(Logger::class);

try {
    $s = $worker->run();
    echo "email queue: claimed={$s['claimed']} sent={$s['sent']} retried={$s['retried']} failed={$s['failed']}\n";
    exit(0);
} catch (\Throwable $e) {
    $logger->error('email_queue.fatal', ['error' => $e->getMessage()]);
    fwrite(STDERR, "email queue worker error (see logs)\n");
    exit(1);
}
