<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\EmailQueueRepository;
use App\Repositories\EmailTemplateRepository;
use App\Repositories\LeadActivityRepository;
use App\Support\TemplateRenderer;

/**
 * Enqueues outbound email (Phase 5). Callers NEVER send inline — they render a
 * CMS template into a queue row after the lead transaction has committed, and the
 * cron worker performs the actual SMTP. A missing/inactive template is logged and
 * skipped (never fatal), so a template problem can never lose a lead.
 */
final class EmailQueueService
{
    public function __construct(
        private readonly EmailQueueRepository $queue,
        private readonly EmailTemplateRepository $templates,
        private readonly LeadActivityRepository $activities,
        private readonly TemplateRenderer $renderer,
        private readonly SettingsService $settings,
        private readonly Logger $logger,
    ) {
    }

    /** Flat render-context for a lead row (from LeadRepository::findById). */
    public function leadContext(array $lead): array
    {
        $siteUrl = $this->settings->websiteUrl();
        $adminUrl = $siteUrl . '/admin/leads/' . (int) ($lead['id'] ?? 0);
        return [
            'lead.name'         => (string) ($lead['name'] ?? ''),
            'lead.company'      => (string) ($lead['company'] ?? ''),
            'lead.email'        => (string) ($lead['email'] ?? ''),
            'lead.phone'        => (string) ($lead['phone'] ?? ''),
            'lead.whatsapp'     => (string) ($lead['whatsapp'] ?? ''),
            'lead.country'      => (string) ($lead['country'] ?? ''),
            'lead.city'         => (string) ($lead['city'] ?? ''),
            'lead.product_name' => (string) ($lead['product_name'] ?? ($lead['product_name_snapshot'] ?? '')),
            'lead.enquiry_type' => (string) ($lead['enquiry_type'] ?? ''),
            'lead.message'      => (string) ($lead['message'] ?? ''),
            'lead.requirement'  => (string) ($lead['requirement'] ?? ''),
            'lead.source'       => (string) ($lead['source_name'] ?? ''),
            'lead.status'       => (string) ($lead['status_name'] ?? ''),
            'lead.priority'     => (string) ($lead['priority'] ?? ''),
            'lead.follow_up_date' => (string) ($lead['follow_up_date'] ?? ''),
            'lead.reference'    => (string) ($lead['reference'] ?? ''),
            'lead.landing_page' => (string) ($lead['landing_page'] ?? ''),
            'lead.utm_source'   => (string) ($lead['utm_source'] ?? ''),
            'lead.utm_medium'   => (string) ($lead['utm_medium'] ?? ''),
            'lead.utm_campaign' => (string) ($lead['utm_campaign'] ?? ''),
            'lead.url'          => $adminUrl,
            'site.name'         => $this->settings->websiteName(),
            'site.url'          => $siteUrl,
        ];
    }

    /**
     * Render a template + enqueue it for a lead. Returns the queue id, or null if
     * the template is missing/inactive or the recipient is empty.
     */
    public function queueForLead(?int $leadId, string $templateKey, array $context, string $toEmail, ?string $toName = null, ?string $replyTo = null, ?string $replyToName = null): ?int
    {
        if (trim($toEmail) === '') {
            return null;
        }
        $tpl = $this->templates->activeByKey($templateKey);
        if ($tpl === null) {
            $this->logger->warning('email.template.missing', ['template' => $templateKey, 'lead' => $leadId]);
            if ($leadId !== null) {
                $this->activities->add($leadId, null, 'email_failed', 'Email template "' . $templateKey . '" missing; not queued.');
            }
            return null;
        }
        $rendered = $this->renderer->renderTemplate($tpl, $context);
        $id = $this->queue->enqueue([
            'lead_id'         => $leadId,
            'template_key'    => $templateKey,
            'recipient_email' => mb_substr($toEmail, 0, 190),
            'recipient_name'  => $toName !== null ? mb_substr($toName, 0, 150) : null,
            'reply_to_email'  => $replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : null,
            'reply_to_name'   => $replyToName !== null ? mb_substr($replyToName, 0, 150) : null,
            'subject'         => mb_substr($rendered['subject'], 0, 255),
            'body_html'       => $rendered['html'],
            'body_text'       => $rendered['text'],
        ]);
        if ($leadId !== null) {
            $this->activities->add($leadId, null, 'email_queued', 'Queued "' . $templateKey . '" to ' . $toEmail . '.');
        }
        return $id;
    }

    /** Enqueue an already-rendered message (e.g. the follow-up digest). */
    public function queueRendered(?int $leadId, string $templateKey, string $toEmail, ?string $toName, string $subject, string $html, string $text): ?int
    {
        if (trim($toEmail) === '') {
            return null;
        }
        return $this->queue->enqueue([
            'lead_id'         => $leadId,
            'template_key'    => $templateKey,
            'recipient_email' => mb_substr($toEmail, 0, 190),
            'recipient_name'  => $toName !== null ? mb_substr($toName, 0, 150) : null,
            'subject'         => mb_substr(str_replace(["\r", "\n"], ' ', $subject), 0, 255),
            'body_html'       => $html,
            'body_text'       => $text,
        ]);
    }
}
