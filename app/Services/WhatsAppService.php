<?php

declare(strict_types=1);

namespace App\Services;

/**
 * WhatsApp click-to-chat (wa.me) link builder — ADR-001: no Cloud API, no
 * automation platforms. Number + default message come from CMS settings, so
 * the whole feature is admin-configurable and safe to add API sending later
 * behind this same service without touching call sites.
 */
final class WhatsAppService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function isConfigured(): bool
    {
        return $this->settings->whatsappNumber() !== '';
    }

    /** Build a https://wa.me/<number>?text=<message> link, or '' if unconfigured. */
    public function link(?string $message = null): string
    {
        $number = $this->settings->whatsappNumber();
        if ($number === '') {
            return '';
        }
        $text = $message ?? $this->settings->whatsappMessage();
        $url = 'https://wa.me/' . $number;
        if ($text !== '') {
            $url .= '?text=' . rawurlencode($text);
        }
        return $url;
    }
}
