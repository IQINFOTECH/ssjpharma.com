<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\View;

/**
 * SMTP mail (ADR-001, PHPMailer). Credentials come from .env only, never
 * hardcoded or logged. PHPMailer validates all addresses and rejects
 * header/newline injection.
 *
 * Delivery mode (MAIL_DELIVERY_MODE, Phase 5) protects against accidental
 * production sends during development:
 *   - smtp      → really send over SMTP
 *   - log       → do not send; log the attempt (safe default in dev)
 *   - disabled  → silently succeed without sending
 * When unset, the mode is "smtp" if SMTP is configured, otherwise "log" — so an
 * unconfigured dev box never tries (and never retry-storms) real delivery.
 */
final class MailService
{
    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly View $view,
    ) {
    }

    public function isConfigured(): bool
    {
        $smtp = (array) $this->config->get('mail.smtp', []);
        $from = (array) $this->config->get('mail.from', []);
        return !empty($smtp['host']) && !empty($smtp['username']) && !empty($from['address']);
    }

    public function deliveryMode(): string
    {
        $mode = strtolower(trim((string) $this->config->get('mail.delivery_mode', '')));
        if (in_array($mode, ['smtp', 'log', 'disabled'], true)) {
            return $mode;
        }
        return $this->isConfigured() ? 'smtp' : 'log';
    }

    /**
     * Attempt a single delivery. Never throws. Returns the outcome so the queue
     * worker can decide retry vs. permanent failure.
     * @param array{to:string,name?:?string,subject:string,html?:?string,text?:?string,reply_to?:?string,reply_to_name?:?string} $m
     * @return array{ok:bool,permanent:bool,error:?string}
     */
    public function attempt(array $m): array
    {
        $to = (string) ($m['to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            // Invalid address: permanent — never retry indefinitely.
            return ['ok' => false, 'permanent' => true, 'error' => 'Invalid recipient address'];
        }
        $subject = str_replace(["\r", "\n"], ' ', (string) ($m['subject'] ?? ''));
        $mode = $this->deliveryMode();

        if ($mode === 'disabled') {
            return ['ok' => true, 'permanent' => false, 'error' => null];
        }
        if ($mode === 'log') {
            $this->logger->info('mail.delivery.log_mode', ['to' => $to, 'subject' => $subject]);
            return ['ok' => true, 'permanent' => false, 'error' => null];
        }
        // mode === smtp
        if (!$this->isConfigured()) {
            return ['ok' => false, 'permanent' => false, 'error' => 'SMTP not configured'];
        }
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return ['ok' => false, 'permanent' => false, 'error' => 'Mail library unavailable'];
        }
        try {
            $this->rawSend($to, (string) ($m['name'] ?? ''), $subject,
                (string) ($m['html'] ?? ''), (string) ($m['text'] ?? ''),
                $m['reply_to'] ?? null, (string) ($m['reply_to_name'] ?? ''));
            return ['ok' => true, 'permanent' => false, 'error' => null];
        } catch (\Throwable $e) {
            // Log/return WITHOUT body or credentials.
            $this->logger->error('mail.send.failed', ['subject' => $subject, 'error' => $e->getMessage()]);
            return ['ok' => false, 'permanent' => false, 'error' => mb_substr($e->getMessage(), 0, 255)];
        }
    }

    /** Back-compat direct send (used by password reset). Mode-aware; never throws. */
    public function send(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null, ?string $replyTo = null, ?string $replyToName = null): bool
    {
        return $this->attempt([
            'to' => $toEmail, 'subject' => $subject, 'html' => $htmlBody,
            'text' => $textBody ?? strip_tags($htmlBody), 'reply_to' => $replyTo, 'reply_to_name' => $replyToName,
        ])['ok'];
    }

    /** Render a PHP view template into HTML and send it (back-compat). */
    public function sendView(string $toEmail, string $subject, string $template, array $data = [], ?string $replyTo = null, ?string $replyToName = null): bool
    {
        try {
            $html = $this->view->render($template, $data);
        } catch (\Throwable $e) {
            $this->logger->error('mail.render.failed', ['template' => $template, 'error' => $e->getMessage()]);
            return false;
        }
        return $this->send($toEmail, $subject, $html, null, $replyTo, $replyToName);
    }

    /** The only method that actually talks to SMTP. Throws on failure. */
    private function rawSend(string $to, string $toName, string $subject, string $html, string $text, ?string $replyTo, string $replyToName): void
    {
        $smtp = (array) $this->config->get('mail.smtp', []);
        $from = (array) $this->config->get('mail.from', []);

        $mailerClass = \PHPMailer\PHPMailer\PHPMailer::class;
        $mail = new $mailerClass(true);
        $mail->isSMTP();
        $mail->Host     = (string) $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $smtp['username'];
        $mail->Password = (string) $smtp['password'];
        $mail->Port     = (int) ($smtp['port'] ?? 587);
        if (!empty($smtp['encryption'])) {
            $mail->SMTPSecure = (string) $smtp['encryption'];
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string) $from['address'], (string) ($from['name'] ?? ''));
        $mail->addAddress($to, str_replace(["\r", "\n"], ' ', $toName));
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo, str_replace(["\r", "\n"], ' ', $replyToName));
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html !== '' ? $html : $text;
        $mail->AltBody = $text !== '' ? $text : strip_tags($html);
        $mail->send();
    }
}
