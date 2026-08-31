<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PageRepository;
use App\Repositories\PageSectionRepository;
use App\Services\RedirectService;
use App\Services\SectionRegistry;

/**
 * CMS-driven public page renderer. Resolves the URL → published page → sections,
 * builds SEO/structured data, and renders. Unpublished/missing pages 404. Old
 * URLs are honoured via the CMS redirect table BEFORE 404-ing.
 */
final class PageController extends SiteController
{
    private function pages(): PageRepository            { return $this->container->get(PageRepository::class); }
    private function sections(): PageSectionRepository  { return $this->container->get(PageSectionRepository::class); }

    /** GET / */
    public function home(Request $request): Response
    {
        $page = $this->pages()->findPublishedHome();
        if ($page === null) {
            // No home page configured yet — render a minimal, honest placeholder.
            $seo = $this->seo()->forPage(['title' => $this->settings()->websiteName()], '/');
            return $this->renderSite('site.empty', $seo, ['heading' => $this->settings()->companyName()]);
        }
        return $this->renderPage($page, '/', $request);
    }

    /** GET /{path:.+} — redirects, then a published page, else 404. */
    public function show(Request $request): Response
    {
        $path = '/' . trim((string) $request->route('path', ''), '/');

        // 1. CMS redirects take priority over pages.
        $redirect = $this->container->get(RedirectService::class)->resolve($path);
        if ($redirect !== null) {
            return Response::redirect($redirect['to'], $redirect['code']);
        }

        // 2. Only single-segment slugs are pages in Phase 1.
        $slug = trim($path, '/');
        if ($slug === '' || str_contains($slug, '/')) {
            throw new HttpException(404);
        }

        $page = $this->pages()->findPublishedBySlug($slug);
        if ($page === null) {
            throw new HttpException(404);
        }

        // Never surface the home page at /home — it lives at /.
        if ((int) $page['is_home'] === 1) {
            return Response::redirect('/', 301);
        }

        return $this->renderPage($page, $path, $request);
    }

    /** @param array<string,mixed> $page */
    private function renderPage(array $page, string $path, Request $request): Response
    {
        $rawSections = $this->sections()->visibleForPage((int) $page['id']);
        $sections = [];
        foreach ($rawSections as $row) {
            $type = (string) $row['type'];
            if (!SectionRegistry::exists($type)) {
                continue; // unknown/deprecated type — skip safely
            }
            $data = [];
            if (!empty($row['data'])) {
                $decoded = json_decode((string) $row['data'], true);
                $data = is_array($decoded) ? $decoded : [];
            }
            $sections[] = ['type' => $type, 'data' => $data];
        }

        $seo = $this->seo()->forPage($page, $path);

        $breadcrumbs = [];
        if ((int) $page['is_home'] !== 1) {
            $breadcrumbs = [
                ['name' => 'Home', 'url' => $this->settings()->websiteUrl() . '/'],
                ['name' => (string) $page['title'], 'url' => $seo['canonical']],
            ];
        }

        $isContact = ($page['template'] ?? 'default') === 'contact';

        // AEO: emit FAQPage JSON-LD from any FAQ section's real Q&A pairs.
        $extraJsonLd = [];
        $faqItems = [];
        foreach ($sections as $s) {
            if ($s['type'] === 'faq' && !empty($s['data']['items']) && is_array($s['data']['items'])) {
                $faqItems = array_merge($faqItems, $s['data']['items']);
            }
        }
        if ($faqItems !== []) {
            $block = $this->schema()->faq($faqItems);
            if (!empty($block['mainEntity'])) {
                $extraJsonLd[] = $block;
            }
        }

        return $this->renderSite('site.page', $seo, [
            'page'       => $page,
            'sections'   => $sections,
            'isContact'  => $isContact,
            'contactForm'=> $isContact ? $this->contactFormContext($page, $request) : null,
        ], $breadcrumbs, 200, $extraJsonLd);
    }

    /** @param array<string,mixed> $page @return array<string,mixed> */
    private function contactFormContext(array $page, Request $request): array
    {
        $captcha = $this->container->get(\App\Services\CaptchaService::class);
        /** @var \App\Core\Session $session */
        $session = $this->container->get(\App\Core\Session::class);

        // Product enquiry: a ?product= link (e.g. from a product page/card) binds the
        // form to a PUBLISHED product. The id is validated here and re-validated on
        // submit; an invalid/unpublished id falls back to a plain enquiry.
        $formKey = (string) $page['slug'];
        $productCtx = null;
        $productId = (int) $request->query('product', 0);
        if ($productId > 0) {
            $product = $this->container->get(\App\Repositories\ProductRepository::class)->findPublishedById($productId);
            if ($product !== null) {
                $formKey = 'product-enquiry';
                $productCtx = ['id' => (int) $product['id'], 'name' => (string) $product['name']];
            }
        }
        // Marker the submit handler uses to re-show flashed errors (matches
        // ContactController: 'product-<id>' for a product enquiry, else the slug).
        $flashKey = $productCtx !== null ? ('product-' . $productCtx['id']) : (string) $page['slug'];

        // Pull flashed validation errors/old input from a failed POST (PRG).
        $errors = [];
        $old = [];
        if ($session->get('contact_form_key') === $flashKey) {
            $errors = (array) $session->getFlash('contact_errors', []);
            $old = (array) $session->getFlash('contact_old', []);
            $session->forget('contact_form_key');
        }

        return [
            // enquiry type + source are derived server-side from form_key (EnquiryType);
            // the client no longer sends a "source" field.
            'form_key'         => $formKey,
            'product'          => $productCtx,
            'errors'           => $errors,
            'old'              => $old,
            'captcha_enabled'  => $captcha->isEnabled(),
            'captcha_site_key' => $captcha->siteKey(),
        ];
    }
}
