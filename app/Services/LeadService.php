<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\LeadActivityRepository;
use App\Repositories\LeadRepository;
use App\Repositories\ProductRepository;
use App\Support\EnquiryType;
use App\Support\Validator;

/**
 * Central lead capture for ALL enquiry sources (general/product/distributor/
 * partnership/CTA). Capture-first with a strict order (§11):
 *   validate → save lead (transaction) → commit → attempt email → log result.
 * A mail failure NEVER loses a lead. Source + enquiry type are derived
 * SERVER-SIDE from the form; UTMs are preserved as-is.
 */
final class LeadService
{
    /** Window in which a repeat enquiry from the same email/phone links to an open lead. */
    private const DEDUP_WINDOW_SECONDS = 86400; // 24h

    public function __construct(
        private readonly LeadRepository $leads,
        private readonly LeadActivityRepository $activities,
        private readonly ProductRepository $products,
        private readonly SettingsService $settings,
        private readonly EmailQueueService $emailQueue,
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    /** @param array<string,mixed> $input @return array<string,string> validation errors */
    public function validate(array $input): array
    {
        $v = new Validator();
        $v->validate($input, [
            'name'    => 'required|max:150',
            'email'   => 'required|email|max:190',
            'phone'   => 'required|phone|max:40',
            'company' => 'max:180',
            'country' => 'max:100',
            'state'   => 'max:100',
            'city'    => 'max:100',
            'business_type' => 'max:100',
            'requirement'   => 'max:255',
            'message' => 'max:5000',
            'preferred_contact' => 'in:email,phone,whatsapp',
            'consent' => 'accepted',
        ], ['consent' => 'Consent']);
        return $v->errors();
    }

    /**
     * Persist a validated lead. Returns the lead id (new or the linked open lead).
     * @param array<string,mixed> $input sanitised form values
     * @param array<string,mixed> $meta  form_key, product_id, ip, ua, utm, referrer,
     *                                    landing_page, source_url, is_spam
     */
    public function create(array $input, array $meta): int
    {
        $enq = EnquiryType::resolve((string) ($meta['form_key'] ?? 'contact-us'));
        $isSpam = !empty($meta['is_spam']);

        $email = mb_substr(trim((string) ($input['email'] ?? '')), 0, 190);
        $phone = $this->nullable($input['phone'] ?? '', 40);

        // Product snapshot — name is fetched from the DB (never trust the client).
        $productId = null;
        $productName = null;
        if (!empty($meta['product_id'])) {
            $p = $this->products->findPublishedById((int) $meta['product_id']);
            if ($p !== null) {
                $productId = (int) $p['id'];
                $productName = mb_substr((string) $p['name'], 0, 200);
            }
        }

        // --- Duplicate handling (§22): link a repeat enquiry to an OPEN lead ---
        if (!$isSpam) {
            $dup = $this->leads->findOpenDuplicate($email, $phone, self::DEDUP_WINDOW_SECONDS);
            if ($dup !== null) {
                $leadId = (int) $dup['id'];
                $this->leads->recordSubmission($leadId, (string) ($meta['form_key'] ?? 'contact'),
                    (string) json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                    $this->nullable($meta['ip'] ?? '', 45), $this->nullable($meta['user_agent'] ?? '', 255), false);
                $this->activities->add($leadId, null, 'repeat_enquiry', 'Repeat enquiry received (linked to this open lead).', [
                    'enquiry_type' => $enq['type'],
                    'product'      => $productName,
                    'message'      => mb_substr((string) ($input['message'] ?? ''), 0, 300),
                ]);
                $this->leads->touchUpdated($leadId);
                $this->notify($leadId, $enq, $email, true);
                return $leadId;
            }
        }

        // --- New lead (transactional: lead + submission + created-activity) ----
        $statusKey = $isSpam ? 'spam' : $this->settings->get('lead_default_status', 'new');
        $priority  = $this->settings->get('lead_default_priority', 'medium');

        $data = [
            'reference'             => $this->leads->nextReference(),
            'name'                  => $this->clean($input['name'] ?? '', 150),
            'company'               => $this->nullable($input['company'] ?? '', 180),
            'email'                 => $email,
            'phone'                 => $phone,
            'whatsapp'              => $this->nullable($input['whatsapp'] ?? '', 40),
            'country'               => $this->nullable($input['country'] ?? '', 100),
            'state'                 => $this->nullable($input['state'] ?? '', 100),
            'city'                  => $this->nullable($input['city'] ?? '', 100),
            'business_type'         => $this->nullable($input['business_type'] ?? '', 100),
            'enquiry_type'          => $enq['type'],
            'product_id'            => $productId,
            'product_name_snapshot' => $productName,
            'message'               => $this->nullable($input['message'] ?? '', 5000),
            'requirement'           => $this->nullable($input['requirement'] ?? '', 255),
            'preferred_contact'     => $this->nullable($input['preferred_contact'] ?? '', 20),
            'priority'              => $priority,
            'consent'               => !empty($input['consent']) ? 1 : 0,
            'consent_at'            => !empty($input['consent']) ? date('Y-m-d H:i:s') : null,
            'privacy_version'       => $this->settings->get('privacy_policy_version', '1.0'),
            'source_id'             => $this->leads->sourceIdByKey($enq['source']),
            'status_id'             => $this->leads->statusIdByKey($statusKey),
            'landing_page'          => $this->nullable($meta['landing_page'] ?? '', 255),
            'source_url'            => $this->nullable($meta['source_url'] ?? ($meta['landing_page'] ?? ''), 255),
            'referrer'              => $this->nullable($meta['referrer'] ?? '', 255),
            'utm_source'            => $this->nullable($meta['utm_source'] ?? '', 120),
            'utm_medium'            => $this->nullable($meta['utm_medium'] ?? '', 120),
            'utm_campaign'          => $this->nullable($meta['utm_campaign'] ?? '', 120),
            'utm_term'              => $this->nullable($meta['utm_term'] ?? '', 120),
            'utm_content'           => $this->nullable($meta['utm_content'] ?? '', 120),
            'ip'                    => $this->nullable($meta['ip'] ?? '', 45),
            'user_agent'            => $this->nullable($meta['user_agent'] ?? '', 255),
            'is_spam'               => $isSpam ? 1 : 0,
        ];

        $this->db->beginTransaction();
        try {
            $leadId = $this->leads->create($data);
            $this->leads->recordSubmission($leadId, (string) ($meta['form_key'] ?? 'contact'),
                (string) json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                $data['ip'], $data['user_agent'], $isSpam);
            $this->activities->add($leadId, null, 'created', 'Lead created from ' . $enq['label'] . '.', [
                'enquiry_type' => $enq['type'], 'source' => $enq['source'], 'product' => $productName,
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error('lead.create.failed', ['error' => $e->getMessage()]);
            throw $e; // caller shows a generic error; nothing was committed
        }

        // --- Notifications happen AFTER commit; failures never lose the lead ---
        if (!$isSpam) {
            $this->notify($leadId, $enq, $email, false);
        } else {
            $this->leads->markNotified($leadId, 'skipped');
        }

        return $leadId;
    }

    /**
     * QUEUE the internal notification (+ optional acknowledgement) after the lead
     * has committed. No SMTP happens here — the cron worker sends. Never throws, so
     * a mail/template problem can never lose the lead (Phase 5 capture-first).
     * @param array{type:string,source:string,label:string} $enq
     */
    private function notify(int $leadId, array $enq, string $enquirerEmail, bool $isRepeat): void
    {
        try {
            $lead = $this->leads->findById($leadId);
            if ($lead === null) {
                return;
            }

            // Recipient precedence: CMS notification email → CMS sales email →
            // MAIL_SALES_INBOX from .env (config/mail.php). Secrets stay in .env.
            $recipient = $this->settings->get('lead_notification_email');
            if ($recipient === '') {
                $recipient = $this->settings->get('lead_sales_email');
            }
            if ($recipient === '') {
                $recipient = (string) config('mail.sales_inbox', '');
            }

            $context = $this->emailQueue->leadContext($lead);
            $queued = false;
            if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $id = $this->emailQueue->queueForLead($leadId, 'lead_internal_notification', $context,
                    $recipient, 'Sales Team',
                    // Reply-To the enquirer (validated) so staff can reply directly.
                    filter_var($enquirerEmail, FILTER_VALIDATE_EMAIL) ? $enquirerEmail : null,
                    (string) $lead['name']);
                $queued = $id !== null;
            } else {
                $this->activities->add($leadId, null, 'email_failed', 'No notification recipient configured.');
            }
            $this->leads->markNotified($leadId, $queued ? 'queued' : 'skipped');

            // Optional customer acknowledgement, if enabled + valid enquirer email.
            if (!$isRepeat && $this->settings->bool('lead_autoreply_enabled')
                && filter_var($enquirerEmail, FILTER_VALIDATE_EMAIL)) {
                $this->emailQueue->queueForLead($leadId, 'lead_customer_acknowledgement', $context,
                    $enquirerEmail, (string) $lead['name']);
            }
        } catch (\Throwable $e) {
            // Notification is best-effort; the lead is already committed.
            $this->logger->error('lead.notify.failed', ['lead' => $leadId, 'error' => $e->getMessage()]);
        }
    }

    private function clean(mixed $v, int $max): string
    {
        return mb_substr(trim((string) $v), 0, $max);
    }

    private function nullable(mixed $v, int $max): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
