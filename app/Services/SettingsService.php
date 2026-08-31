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
    /**
     * Resolve a media setting to a URL. Accepts EITHER a numeric media-library id
     * OR a directly pasted image URL / path (e.g. /uploads/2026/08/logo.png), so
     * the owner can just copy the image path from the library.
     */
    public function mediaUrl(string $key): string
    {
        $val = trim($this->get($key));
        if ($val === '' || $val === '0') {
            return '';
        }
        if (!ctype_digit($val)) {
            // A pasted path/URL — accept same-origin paths and http(s) URLs only.
            return (str_starts_with($val, '/') || str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) ? $val : '';
        }
        $row = $this->media->findActive((int) $val);
        return $row['url_path'] ?? '';
    }

    public function refresh(): void
    {
        $this->cache = null;
    }

    // --- Convenience accessors used across templates -------------------------

    public function companyName(): string { return $this->get('company_name', 'SSJ Pharmaceuticals'); }

    /** Full postal address composed from the granular company_* fields (multi-line). */
    public function fullAddress(): string
    {
        $cityLine = $this->get('company_city');
        $state = $this->get('company_state');
        $postal = $this->get('company_postal');
        if ($state !== '')  { $cityLine = ($cityLine !== '' ? $cityLine . ', ' : '') . $state; }
        if ($postal !== '') { $cityLine = ($cityLine !== '' ? $cityLine . ' ' : '') . $postal; }
        $parts = array_filter([$this->get('company_address'), $cityLine, $this->get('company_country')]);
        return implode("\n", $parts);
    }
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
