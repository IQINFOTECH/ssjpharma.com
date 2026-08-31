<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MediaRepository;

/**
 * Builds the SEO/meta payload for a public page, falling back to global settings
 * when page-level fields are empty (SEO_FOUNDATION requirement).
 */
final class SeoService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MediaRepository $media,
    ) {
    }

    /**
     * @param array<string,mixed> $page a pages row (may be empty for system pages)
     * @return array<string,string> keys: title, description, canonical, og_title,
     *   og_description, og_image, robots, site_name, url
     */
    public function forPage(array $page, string $currentPath): array
    {
        $siteName = $this->settings->websiteName();
        $baseUrl  = $this->settings->websiteUrl();

        $title = $this->firstNonEmpty(
            (string) ($page['meta_title'] ?? ''),
            (string) ($page['title'] ?? ''),
            $this->settings->get('seo_default_title', $siteName),
        );
        // Suffix the site name (unless the title already is the site name).
        $fullTitle = ($title !== '' && $title !== $siteName) ? "{$title} — {$siteName}" : $siteName;

        $description = $this->firstNonEmpty(
            (string) ($page['meta_description'] ?? ''),
            $this->settings->get('seo_default_description'),
        );

        $canonical = (string) ($page['canonical_url'] ?? '');
        if ($canonical === '') {
            $canonical = $baseUrl . ($currentPath === '/' ? '/' : rtrim($currentPath, '/'));
        }

        $ogImage = $this->resolveImage((int) ($page['og_image_id'] ?? 0));
        if ($ogImage === '') {
            $ogImage = $this->resolveImageBySetting('seo_default_og_image');
        }
        if ($ogImage !== '' && !str_starts_with($ogImage, 'http')) {
            $ogImage = $baseUrl . $ogImage;
        }

        $robots = (string) ($page['robots'] ?? '');
        if ($robots === '') {
            $robots = 'index,follow';
        }

        return [
            'title'          => $fullTitle,
            'description'    => $description,
            'canonical'      => $canonical,
            'og_title'       => $fullTitle,
            'og_description' => $description,
            'og_image'       => $ogImage,
            'robots'         => $robots,
            'site_name'      => $siteName,
            'url'            => $canonical,
        ];
    }

    private function resolveImage(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $row = $this->media->findActive($id);
        return $row['url_path'] ?? '';
    }

    private function resolveImageBySetting(string $key): string
    {
        return $this->settings->mediaUrl($key);
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $v) {
            if (trim($v) !== '') {
                return trim($v);
            }
        }
        return '';
    }
}
