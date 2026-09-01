<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Services\MenuService;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\StructuredDataService;
use App\Services\WhatsAppService;

/**
 * Base for public-facing controllers. Assembles the shared layout context
 * (settings, dynamic menus, WhatsApp link, SEO, structured data) so templates
 * never hardcode navigation or company details.
 */
abstract class SiteController extends Controller
{
    protected function settings(): SettingsService      { return $this->container->get(SettingsService::class); }
    protected function menus(): MenuService              { return $this->container->get(MenuService::class); }
    protected function seo(): SeoService                 { return $this->container->get(SeoService::class); }
    protected function schema(): StructuredDataService   { return $this->container->get(StructuredDataService::class); }
    protected function whatsapp(): WhatsAppService       { return $this->container->get(WhatsAppService::class); }

    /**
     * Shared layout data for every public page.
     * @param array<string,string> $seo
     * @param array<int,array{name:string,url:string}> $breadcrumbs
     * @return array<string,mixed>
     */
    protected function layoutData(array $seo, array $breadcrumbs = [], array $extraJsonLd = []): array
    {
        $settings = $this->settings();

        $jsonLd = [
            $this->schema()->encode($this->schema()->organization()),
            $this->schema()->encode($this->schema()->website()),
        ];
        if ($breadcrumbs !== []) {
            $jsonLd[] = $this->schema()->encode($this->schema()->breadcrumbs($breadcrumbs));
        }
        foreach ($extraJsonLd as $block) {
            $jsonLd[] = $this->schema()->encode($block);
        }

        // Footer legal links — rendered ONLY when those pages actually exist,
        // so the footer never links to a 404.
        $pages = $this->container->get(\App\Repositories\PageRepository::class);
        $legalLinks = [];
        foreach (['privacy-policy' => 'Privacy Policy', 'terms-and-conditions' => 'Terms & Conditions'] as $slug => $label) {
            if ($pages->findPublishedBySlug($slug) !== null) {
                $legalLinks['/' . $slug] = $label;
            }
        }

        return [
            'seo'            => $seo,
            'settings'       => $settings,
            'headerMenu'     => $this->menus()->tree('header'),
            'mobileMenu'     => $this->menus()->tree('mobile') ?: $this->menus()->tree('header'),
            'footerMenu'     => $this->menus()->tree('footer'),
            'legalLinks'     => $legalLinks,
            'whatsappLink'   => $this->whatsapp()->link(),
            'gaId'           => $settings->gaId(),
            'jsonLd'         => $jsonLd,
            'breadcrumbs'    => $breadcrumbs,
            'currentYear'    => date('Y'),
        ];
    }

    /**
     * Render a public view inside the site layout.
     * @param array<string,mixed> $viewData
     */
    protected function renderSite(string $template, array $seo, array $viewData = [], array $breadcrumbs = [], int $status = 200, array $extraJsonLd = []): \App\Core\Response
    {
        $data = array_merge($this->layoutData($seo, $breadcrumbs, $extraJsonLd), $viewData);
        return $this->view($template, $data, $status);
    }
}
