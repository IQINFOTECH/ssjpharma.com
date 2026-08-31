<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DosageFormRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TherapeuticAreaRepository;

/**
 * Public product catalog: listing (with search + filters + pagination), product
 * detail, category pages and therapeutic-area pages. Only PUBLISHED, non-deleted
 * records are surfaced. Server-side pagination throughout.
 */
final class CatalogController extends SiteController
{
    private const PER_PAGE = 12;

    private function products(): ProductRepository            { return $this->container->get(ProductRepository::class); }
    private function categories(): ProductCategoryRepository  { return $this->container->get(ProductCategoryRepository::class); }
    private function areas(): TherapeuticAreaRepository       { return $this->container->get(TherapeuticAreaRepository::class); }
    private function dosageForms(): DosageFormRepository      { return $this->container->get(DosageFormRepository::class); }

    /**
     * When a catalog slug isn't found, honour a CMS redirect (e.g. after a slug
     * change) before returning 404 — catalog routes are matched before the
     * catch-all, so the redirect table must be consulted here too.
     */
    private function redirectOr404(Request $request): Response
    {
        $redirect = $this->container->get(\App\Services\RedirectService::class)->resolve($request->path());
        if ($redirect !== null) {
            return Response::redirect($redirect['to'], $redirect['code']);
        }
        throw new HttpException(404);
    }

    /** GET /products */
    public function index(Request $request): Response
    {
        $q      = trim((string) $request->query('q', ''));
        $catId  = (int) $request->query('category', 0);
        $taId   = (int) $request->query('ta', 0);
        $dfId   = (int) $request->query('dosage', 0);
        $page   = max(1, (int) $request->query('page', 1));

        $filter = ['q' => $q, 'ta' => $taId ?: null, 'dosage' => $dfId ?: null];
        if ($catId > 0) {
            $filter['category_ids'] = $this->categories()->idWithDescendants($catId);
        }

        $result = $this->products()->paginatePublic($filter, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $totalPages = (int) max(1, ceil($result['total'] / self::PER_PAGE));

        $seo = $this->seo()->forPage([
            'title'           => 'Products',
            'meta_description'=> 'Browse the SSJ Pharmaceuticals product catalogue.',
        ], '/products');

        $breadcrumbs = [
            ['name' => 'Home', 'url' => $this->settings()->websiteUrl() . '/'],
            ['name' => 'Products', 'url' => $this->settings()->websiteUrl() . '/products'],
        ];

        return $this->renderSite('site.catalog.index', $seo, [
            'products'   => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'q'          => $q,
            'catId'      => $catId,
            'taId'       => $taId,
            'dfId'       => $dfId,
            'categories' => $this->categories()->allPublished(),
            'areas'      => $this->areas()->allPublished(),
            'dosages'    => $this->dosageForms()->active(),
        ], $breadcrumbs);
    }

    /** GET /products/{slug} */
    public function product(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $product = $this->products()->findPublishedBySlug($slug);

        // Admin preview: a signed-in user with products.view may preview an
        // unpublished (draft / in-review / archived) or demo product. The public
        // still gets a 404, and the preview is rendered noindex (see $seoPage).
        $isPreview = false;
        if ($product === null) {
            $auth = $this->container->get(\App\Auth\Auth::class);
            $rbac = $this->container->get(\App\Auth\Rbac::class);
            if ($auth->check() && $rbac->can('products.view')) {
                $product = $this->products()->findAnyBySlug($slug);
                $isPreview = $product !== null;
            }
            if ($product === null) {
                return $this->redirectOr404($request);
            }
        }
        $id = (int) $product['id'];

        $images = $this->products()->images($id);
        $documents = $this->products()->documents($id);
        $specs = $this->products()->specifications($id);
        $tas = $this->products()->therapeuticAreas($id, true);
        $category = $product['category_id'] ? $this->categories()->findById((int) $product['category_id']) : null;
        $dosage = $product['dosage_form_id'] ? $this->dosageForms()->find((int) $product['dosage_form_id']) : null;
        $related = $this->products()->related($id, $product['category_id'] ? (int) $product['category_id'] : null, $this->products()->therapeuticAreaIds($id), 4);

        // SEO — product values with settings fallback (SeoService handles fallback).
        $seoPage = [
            'title'            => $product['name'],
            'meta_title'       => $product['meta_title'] ?? null,
            'meta_description' => $product['meta_description'] ?: $product['short_description'],
            'canonical_url'    => $product['canonical_url'] ?? null,
            'og_image_id'      => $product['og_image_id'] ?: $product['hero_image_id'],
            'robots'           => $isPreview ? 'noindex,nofollow' : ($product['robots'] ?? null),
        ];
        $path = '/products/' . $product['slug'];
        $seo = $this->seo()->forPage($seoPage, $path);
        $seo['og_type'] = 'product'; // product detail pages are og:type=product, not website

        // Breadcrumbs: Home → Products → [Category] → Product
        $base = $this->settings()->websiteUrl();
        $breadcrumbs = [
            ['name' => 'Home', 'url' => $base . '/'],
            ['name' => 'Products', 'url' => $base . '/products'],
        ];
        if ($category !== null && ($category['status'] ?? '') === 'published') {
            $breadcrumbs[] = ['name' => $category['name'], 'url' => $base . '/product-category/' . $category['slug']];
        }
        $breadcrumbs[] = ['name' => $product['name'], 'url' => $seo['canonical']];

        // Product JSON-LD — only real fields (no price/availability/rating).
        $heroUrl = '';
        if (!empty($product['hero_image_id'])) {
            $heroUrl = media_url((int) $product['hero_image_id']);
        }
        $productSchema = $this->schema()->product([
            'name'        => (string) $product['name'],
            'url'         => $path,
            'description' => (string) ($product['short_description'] ?? ''),
            'image'       => $heroUrl,
            'sku'         => (string) ($product['code'] ?? ''),
        ]);

        // WhatsApp per-product enquiry link (§19).
        $waMessage = "Hello SSJ Pharmaceuticals,\n\nI am interested in:\n\nProduct:\n{$product['name']}\n\nPlease share more information.\n\nThank you.";
        $waLink = $this->whatsapp()->link($waMessage);

        // The enquiry form lives on the Contact page (/contact-us?product={id}) to keep
        // the product page short; the CTAs link there with the product context.
        return $this->renderSite('site.catalog.product', $seo, [
            'product'    => $product,
            'isPreview'  => $isPreview,
            'images'     => $images,
            'documents'  => $documents,
            'specs'      => $specs,
            'tas'        => $tas,
            'category'   => $category,
            'dosage'     => $dosage,
            'related'    => $related,
            'waLink'     => $waLink,
        ], $breadcrumbs, 200, [$productSchema]);
    }

    /** GET /product-category/{slug} */
    public function category(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $category = $this->categories()->findPublishedBySlug($slug);
        if ($category === null) {
            return $this->redirectOr404($request);
        }
        $id = (int) $category['id'];
        $page = max(1, (int) $request->query('page', 1));

        $ids = $this->categories()->idWithDescendants($id);
        $result = $this->products()->paginatePublic(['category_ids' => $ids], self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $totalPages = (int) max(1, ceil($result['total'] / self::PER_PAGE));

        $seo = $this->seo()->forPage([
            'title'            => $category['name'],
            'meta_title'       => $category['meta_title'] ?? null,
            'meta_description' => $category['meta_description'] ?? null,
            'og_image_id'      => $category['image_id'] ?? null,
        ], '/product-category/' . $category['slug']);

        $base = $this->settings()->websiteUrl();
        $breadcrumbs = [
            ['name' => 'Home', 'url' => $base . '/'],
            ['name' => 'Products', 'url' => $base . '/products'],
            ['name' => $category['name'], 'url' => $seo['canonical']],
        ];

        return $this->renderSite('site.catalog.category', $seo, [
            'category'      => $category,
            'subcategories' => $this->categories()->publishedChildren($id),
            'products'      => $result['rows'],
            'total'         => $result['total'],
            'page'          => $page,
            'totalPages'    => $totalPages,
        ], $breadcrumbs);
    }

    /** GET /therapeutic-area/{slug} */
    public function therapeuticArea(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $area = $this->areas()->findPublishedBySlug($slug);
        if ($area === null) {
            return $this->redirectOr404($request);
        }
        $id = (int) $area['id'];
        $page = max(1, (int) $request->query('page', 1));

        $result = $this->products()->paginatePublic(['ta' => $id], self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $totalPages = (int) max(1, ceil($result['total'] / self::PER_PAGE));

        $seo = $this->seo()->forPage([
            'title'            => $area['name'],
            'meta_title'       => $area['meta_title'] ?? null,
            'meta_description' => $area['meta_description'] ?? null,
            'og_image_id'      => $area['image_id'] ?? null,
        ], '/therapeutic-area/' . $area['slug']);

        $base = $this->settings()->websiteUrl();
        $breadcrumbs = [
            ['name' => 'Home', 'url' => $base . '/'],
            ['name' => 'Products', 'url' => $base . '/products'],
            ['name' => $area['name'], 'url' => $seo['canonical']],
        ];

        return $this->renderSite('site.catalog.therapeutic_area', $seo, [
            'area'       => $area,
            'products'   => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => $totalPages,
        ], $breadcrumbs);
    }
}
