<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PageRepository;
use App\Services\SettingsService;

/**
 * Generates sitemap.xml and robots.txt. The sitemap lists ONLY published,
 * indexable public pages — never /admin, private URLs, or unpublished content.
 */
final class SeoController extends Controller
{
    public function sitemap(Request $request): Response
    {
        /** @var SettingsService $settings */
        $settings = $this->container->get(SettingsService::class);
        /** @var PageRepository $pages */
        $pages = $this->container->get(PageRepository::class);

        $base = $settings->websiteUrl();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $add = static function (string $loc, ?string $updated) use (&$xml): void {
            $lastmod = !empty($updated) ? date('Y-m-d', strtotime((string) $updated)) : date('Y-m-d');
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= '    <lastmod>' . $lastmod . "</lastmod>\n";
            $xml .= "  </url>\n";
        };

        // Published CMS pages
        foreach ($pages->allPublishedForSitemap() as $row) {
            $add((int) $row['is_home'] === 1 ? $base . '/' : $base . '/' . $row['slug'], $row['updated_at'] ?? null);
        }
        // Products index + published products / categories / therapeutic areas
        $add($base . '/products', null);
        foreach ($this->container->get(\App\Repositories\ProductRepository::class)->allPublishedForSitemap() as $row) {
            $add($base . '/products/' . $row['slug'], $row['updated_at'] ?? null);
        }
        foreach ($this->container->get(\App\Repositories\ProductCategoryRepository::class)->allPublished() as $row) {
            $add($base . '/product-category/' . $row['slug'], $row['updated_at'] ?? null);
        }
        foreach ($this->container->get(\App\Repositories\TherapeuticAreaRepository::class)->allPublished() as $row) {
            $add($base . '/therapeutic-area/' . $row['slug'], $row['updated_at'] ?? null);
        }

        $xml .= '</urlset>' . "\n";

        return Response::make($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(Request $request): Response
    {
        /** @var SettingsService $settings */
        $settings = $this->container->get(SettingsService::class);
        $base = $settings->websiteUrl();

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Allow: /',
            '',
            'Sitemap: ' . $base . '/sitemap.xml',
            '',
        ];

        return Response::make(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
