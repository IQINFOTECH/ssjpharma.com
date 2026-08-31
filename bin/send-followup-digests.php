<?php

declare(strict_types=1);

/**
 * Daily follow-up digest (Phase 5). Run by cron once a day.
 *
 *   php bin/send-followup-digests.php
 *
 * One digest per assignee per day, containing ONLY that assignee's own open leads
 * whose follow_up_date is due (<= today). Visibility is enforced by the query
 * (assigned_user_id = the recipient) — a digest can never contain another user's
 * leads. Idempotent via a DB unique key (communication_digests): a second cron run
 * the same day produces no duplicate. Digests are QUEUED (not sent inline); the
 * email-queue worker performs delivery.
 */

use App\Core\App;
use App\Core\Logger;
use App\Repositories\CommunicationDigestRepository;
use App\Repositories\EmailTemplateRepository;
use App\Repositories\LeadRepository;
use App\Services\EmailQueueService;
use App\Services\SettingsService;
use App\Support\TemplateRenderer;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("send-followup-digests.php is a CLI script.\n");
}

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$c = $app->container();
/** @var SettingsService $settings */
$settings = $c->get(SettingsService::class);
/** @var LeadRepository $leads */
$leads = $c->get(LeadRepository::class);
/** @var CommunicationDigestRepository $digests */
$digests = $c->get(CommunicationDigestRepository::class);
/** @var EmailTemplateRepository $templates */
$templates = $c->get(EmailTemplateRepository::class);
/** @var EmailQueueService $emailQueue */
$emailQueue = $c->get(EmailQueueService::class);
/** @var TemplateRenderer $renderer */
$renderer = $c->get(TemplateRenderer::class);
/** @var Logger $logger */
$logger = $c->get(Logger::class);

if (!$settings->bool('lead_followup_digest_enabled')) {
    echo "follow-up digests disabled (lead_followup_digest_enabled=0)\n";
    exit(0);
}

$tpl = $templates->activeByKey('followup_due_digest');
if ($tpl === null) {
    fwrite(STDERR, "followup_due_digest template missing/inactive\n");
    exit(1);
}

$today   = date('Y-m-d');
$siteUrl = $settings->websiteUrl();
$esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$queued = 0; $skipped = 0;

try {
    foreach ($leads->assigneesWithDueFollowUps($today) as $assignee) {
        $uid = (int) $assignee['id'];
        $rows = $leads->dueFollowUpsForAssignee($uid, $today);
        if ($rows === []) {
            continue;
        }
        // DB-backed idempotency: only the first run of the day claims the slot.
        if (!$digests->claim($uid, $today, count($rows))) {
            $skipped++;
            continue;
        }

        // Build the leads block: HTML (per-field escaped) + plain text.
        $html = '<table cellpadding="6" style="font-family:Arial,sans-serif;border-collapse:collapse;color:#334155">'
              . '<tr style="color:#64748b"><th align="left">Lead</th><th align="left">Company</th><th align="left">Product</th>'
              . '<th align="left">Status</th><th align="left">Priority</th><th align="left">Follow-up</th></tr>';
        $text = '';
        foreach ($rows as $r) {
            $product = (string) ($r['product_name'] ?? ($r['product_name_snapshot'] ?? ''));
            $url = $siteUrl . '/admin/leads/' . (int) $r['id'];
            $html .= '<tr>'
                . '<td><a href="' . $esc($url) . '">' . $esc((string) $r['name']) . '</a> <span style="color:#94a3b8">' . $esc((string) $r['reference']) . '</span></td>'
                . '<td>' . $esc((string) ($r['company'] ?? '')) . '</td>'
                . '<td>' . $esc($product) . '</td>'
                . '<td>' . $esc((string) ($r['status_name'] ?? '')) . '</td>'
                . '<td>' . $esc(ucfirst((string) $r['priority'])) . '</td>'
                . '<td>' . $esc((string) $r['follow_up_date']) . '</td>'
                . '</tr>';
            $text .= '- ' . (string) $r['name'] . ' (' . (string) $r['reference'] . ') '
                . ($product !== '' ? '[' . $product . '] ' : '')
                . (string) ($r['status_name'] ?? '') . ', ' . ucfirst((string) $r['priority'])
                . ', due ' . (string) $r['follow_up_date'] . ' — ' . $url . "\n";
        }
        $html .= '</table>';

        $context = [
            'assignee.name'        => (string) $assignee['name'],
            'followups.count'      => (string) count($rows),
            'followups.rows'       => $html,   // raw (built above, per-field escaped)
            'followups.rows_text'  => $text,
            'site.name'            => $settings->websiteName(),
            'site.url'             => $siteUrl,
        ];
        // Only followups.rows is inserted as raw HTML; every other value is escaped.
        $rendered = $renderer->renderTemplate($tpl, $context, ['followups.rows']);

        $id = $emailQueue->queueRendered(null, 'followup_due_digest',
            (string) $assignee['email'], (string) $assignee['name'],
            $rendered['subject'], $rendered['html'], $rendered['text']);

        $digests->markStatus($uid, $today, $id !== null ? 'queued' : 'failed');
        $queued++;
    }
} catch (\Throwable $e) {
    $logger->error('followup_digest.error', ['error' => $e->getMessage()]);
    fwrite(STDERR, "follow-up digest error (see logs)\n");
    exit(1);
}

$logger->info('followup_digest.run', compact('queued', 'skipped'));
echo "follow-up digests: queued={$queued} skipped(already-sent)={$skipped}\n";
exit(0);
