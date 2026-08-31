<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MediaRepository;
use App\Repositories\SettingRepository;

/**
 * Central access to CMS-managed global settings. Loads the whole key/value set
 * once per request (settings are small and read on every page).
 */
final class SettingsService
{
    /** @var array<string,string>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly SettingRepository $settings,
        private readonly MediaRepository $media,
    ) {
    }

    /** @return array<string,string> */
    private function all(): array
    {
        return $this->cache ??= $this->settings->allKeyed();
    }

    public function get(string $key, string $default = ''): string
    {
        $val = $this->all()[$key] ?? '';
        return $val === '' ? $default : $val;
    }

    public function bool(string $key): bool
    {
        return in_array(strtolower($this->get($key)), ['1', 'true', 'yes', 'on'], true);
    }

    /** Resolve a media-id setting to a public URL, or '' if unset/missing. */
    public function mediaUrl(string $key): string
    {
        $id = (int) $this->get($key);
        if ($id <= 0) {
            return '';
        }
        $row = $this->media->findActive($id);
        return $row['url_path'] ?? '';
    }

    public function refresh(): void
    {
        $this->cache = null;
    }

    // --- Convenience accessors used across templates -------------------------

    public function companyName(): string { return $this->get('company_name', 'SSJ Pharmaceuticals'); }
    public function websiteName(): string { return $this->get('website_name', $this->companyName()); }
    public function websiteUrl(): string  { return rtrim($this->get('website_url', (string) config('app.url')), '/'); }

    public function whatsappNumber(): string  { return preg_replace('/\D+/', '', $this->get('whatsapp_number')) ?? ''; }
    public function whatsappMessage(): string { return $this->get('whatsapp_default_message'); }

    public function gaId(): string { return $this->get('analytics_ga_id'); }

    /** @return array<string,string> non-empty social links (label => url) */
    public function socialLinks(): array
    {
        $out = [];
        foreach (['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'] as $k => $label) {
            $url = $this->get('social_' . $k);
            if ($url !== '') {
                $out[$label] = $url;
            }
        }
        return $out;
    }
}
