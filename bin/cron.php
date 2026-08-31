<?php

declare(strict_types=1);

/**
 * Scheduled-tasks entry point (invoked by cPanel Cron).
 *
 * Phase 0: stub only — logs a heartbeat so the cron wiring can be verified on
 * the host. Real jobs (email-queue flush, follow-up reminders, sitemap refresh,
 * log rotation) are added in later phases (DEVELOPMENT_PLAN Phase 5/6).
 *
 * Suggested cPanel cron (every 5 minutes):
 *     php /home/USER/ssjpharma/bin/cron.php >> /home/USER/ssjpharma/storage/logs/cron.log 2>&1
 */

use App\Core\App;
use App\Core\Logger;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("cron.php is a CLI script.\n");
}

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Logger $logger */
$logger = $app->container()->get(Logger::class);

$logger->info('cron.heartbeat at {time}', ['time' => date(DATE_ATOM)]);

// TODO(Phase 5+): dispatch scheduled jobs here.

echo "cron ok\n";
exit(0);
