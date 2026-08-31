# Phase 3 — Product CMS + Public Product Catalog

**Status:** Implemented & verified (2026-08-29)
**Builds on:** Phase 0 core, Phase 1 CMS/media/SEO, Phase 2 Auth/RBAC/Audit. Architecture unchanged.
**Content rule:** NO fabricated pharmaceutical data. All product content is owner-supplied; only clearly-marked demo records (`is_demo`, "Demo — replace before production") may exist for development.

---

## 1. Product architecture

Relationship-driven (not repeated text). New tables (migration `011_create_product_catalog.sql`):

- **`dosage_forms`** — managed picklist (name, slug, is_active, sort_order).
- **`product_categories`** — nestable (self-FK `parent_id`), slug, description, image, SEO, status, sort_order, `is_demo`, soft-delete.
- **`therapeutic_areas`** — name, slug, description, image, SEO, status, sort_order, `is_demo`, soft-delete.
- **`products`** — name, code, slug, short/full description, status, is_featured, `is_demo`, sort_order; OPTIONAL scientific fields (generic_name, composition, strength, dosage_form_id, pack_size); category_id, hero_image_id; SEO (meta_title/description, canonical, og_image_id, robots); created_by/updated_by, published_at, soft-delete.
- **`product_therapeutic_areas`** — M:N product↔therapeutic area.
- **`product_images`** — gallery rows linking `products`↔`media` (alt, is_primary, sort_order).
- **`product_documents`** — PDF rows linking `products`↔`media` (display_name, doc_type, uploaded_by).
- **`product_specifications`** — structured title/value/unit rows.
- **`leads.product_id`** — added (FK→products, ON DELETE SET NULL) for product enquiries.

Physical files reuse the Phase 1 `media` table + its hardened uploader. Indexes on slug (unique), code, status, category_id, dosage_form_id, is_featured, name, and all FKs.

## 2. Admin features

- **Products** (`/admin/products`): list + search (name/generic/code) + filters (category, therapeutic area, status, featured, dosage form) + pagination; tabbed create/edit (Basic, Product Info, Categories & Areas, Images, Documents, SEO, Publishing); Save Draft / Publish / Unpublish / Archive; **Duplicate as draft**; per-product image gallery (upload, set primary, delete) and PDF documents (upload, delete); structured specifications repeater.
- **Product Categories** (`/admin/product-categories`): nested CRUD with parent selection, publish/unpublish, archive; **circular-hierarchy prevention** (a category cannot become its own parent or a descendant's parent).
- **Therapeutic Areas** (`/admin/therapeutic-areas`): CRUD + publish + archive.
- **Dosage Forms** (`/admin/dosage-forms`): add/edit/activate/deactivate/sort/delete (delete blocked while in use).

## 3. Public catalog features

- **`/products`** — listing with search, category/therapeutic-area/dosage filters, server-side pagination (12/page), honest empty state.
- **`/products/{slug}`** — detail: breadcrumbs, hero (gallery + name/generic/code + Enquire + WhatsApp), Product Information (only non-empty rows), Description (sanitised), Specification table, Documents, Related Products, product-bound enquiry form. Empty sections are hidden (no "Composition: —").
- **`/product-category/{slug}`** — category description, subcategories, products, pagination.
- **`/therapeutic-area/{slug}`** — area description, related products, pagination.
- Only **published, non-deleted** records are surfaced. Product cards show name, generic name, dosage form, short description, View/Enquire — no clutter. Mobile-first; verified no horizontal overflow 320–1920.

## 4. Product routes

Public: `GET /products`, `GET /products/{slug}`, `GET /product-category/{slug}`, `GET /therapeutic-area/{slug}` (registered before the catch-all; consult the redirect table before 404).
Admin (all under `auth → track_session → must_change → can:products.*`): products index/create/store/edit/update/status/duplicate/delete + images(upload/primary/delete) + documents(upload/delete); product-categories & therapeutic-areas index/create/store/edit/update/status/delete; dosage-forms index/store/update/delete.

## 5. Permissions

Reuses the Phase 2 `products.*` set: **view** (read all catalog admin), **create**, **edit** (incl. images/docs/specs, categories/areas/dosage forms), **publish** (publish/unpublish products, categories, areas), **delete** (archive). Enforced by route `can:` middleware **and** `requirePermission()` in each controller. Verified: a `sales_executive` gets 403 (GET+POST) on all catalog admin routes.

## 6. Image & document handling

- Images: JPG/JPEG/PNG/WEBP only; documents: PDF only. Enforced by narrowing `MediaService::handleUpload($file, $userId, $alt, $restrictExtensions)` — real MIME check (`finfo`), extension allowlist, 8 MB cap, random filenames, no execution (uploads dir `.htaccess`). Verified: a text file renamed `.jpg` is rejected ("does not match its extension"); a PNG offered as a document is rejected ("only accepts: pdf").
- Primary image syncs `products.hero_image_id` (used on cards + OG). Removing a product image deletes the underlying media file. Documents are labelled by type; the UI warns against labelling as a regulatory certificate.

## 7. SEO

Per-page dynamic `<title>`, meta description, canonical, OG title/description/image, robots — from product/category/area values with settings fallback (`SeoService`). Product OG falls back to the hero image. **Sitemap** (`/sitemap.xml`) now includes published CMS pages + `/products` + published products, categories and therapeutic areas; excludes drafts/archived/admin/noindex. **Slug changes** create a 301 redirect via the CMS redirect table (verified old→new 301; catalog routes consult redirects before 404).

## 8. JSON-LD

- **Product** — only real, supplied fields: `@context`, `@type`, `name`, `url`, and `description`/`image`/`sku` when present. **Never** price, availability, brand claims, ratings or reviews (unit-tested). Valid JSON escaping via `json_encode`.
- **BreadcrumbList** — Home → Products → [Category] → Product, with real URLs.
- Organization + WebSite continue site-wide.

## 9. Security controls

- SQL: prepared statements throughout; the only interpolated SQL fragments are internally-built JOIN/WHERE strings whose values are bound; `IN (...)` lists are `array_map('intval')`-cast.
- Stored XSS: product/category/area descriptions sanitised on save AND re-sanitised on render (`HtmlSanitizer`). Verified a `<script>` in a description renders escaped, `<strong>` preserved.
- CSRF on every admin write (global middleware). Authorization server-side only (no UI-hiding reliance).
- IDOR: images/documents scoped to their product (`WHERE product_id = :p`); product/category ids validated to exist; the enquiry **product_id is validated server-side** against a published product — a tampered/nonexistent id is discarded (lead stored as a general enquiry with `product_id = NULL`).
- Uploads: MIME/extension/size validation, secure random names, path-traversal-proof, no executable content.
- Mass assignment: explicit field allowlists in controllers; category/dosage/therapeutic-area ids filtered to existing rows.

## 10. Audit logging

Reuses the Phase 2 append-only audit log: `PRODUCT_CREATED/UPDATED/PUBLISHED/UNPUBLISHED/ARCHIVED`, `CATEGORY_CREATED/UPDATED/ARCHIVED`, `THERAPEUTIC_AREA_CREATED/CHANGED`, `IMAGE_UPLOADED/REMOVED`, `DOCUMENT_UPLOADED/REMOVED`.

## 11. Tests performed

- **Automated:** PHPUnit 45 tests / 113 assertions green (incl. new Product JSON-LD guarantees).
- **Live (MariaDB):** migrations + FKs + `leads.product_id`; category/area/product create + publish; specs + TA links persisted; public catalog list + detail + category + TA pages (200, only published); Product + Breadcrumb JSON-LD present, sku present, no price/rating; WhatsApp wa.me link; enquiry creates a lead with `product_id` + `product_enquiry` source; **tampered product_id → NULL**; slug rename → 301 redirect (old→new), missing slug → 404; **upload security** (image MIME mismatch rejected, PDF-only enforced); **circular category** blocked; primary-image → hero sync; **RBAC** 403 for sales_executive; sitemap includes catalog URLs; responsive (no h-scroll at 320 & 375).

## 12–13. Bugs found & fixed

1. **`ProductRepository::update()` HY093** — callers passed a superset of params (`created_by`, `is_demo`) not in the SQL, which fails under `PDO::ATTR_EMULATE_PREPARES=false`. Fixed by binding an exact column set inside `update()` and adding `is_demo` to the statement (+ hero-sync payload).
2. **Product slug redirect not firing** — catalog routes resolve products directly (before the catch-all) and never consulted the redirect table, so an old slug 404'd. Fixed with a `redirectOr404()` fallback in the catalog controller for products/categories/areas.
- (Non-bug) A `1366 Incorrect string value` during testing was a shell mis-encoding of an em-dash in the test payload, not an application issue — the DB/connection is `utf8mb4`.

## 14. Known limitations

- Media pickers (hero/OG/category image) are by Media ID (no modal picker yet).
- Rich text uses a sanitised textarea (no WYSIWYG).
- Product search uses `LOWER LIKE` (indexed, GoDaddy-friendly); no external search engine. A `FULLTEXT` upgrade is possible later.
- Image thumbnails are rendered with CSS `object-contain` + `loading="lazy"`; server-side thumbnail generation is deferred pending host `gd`/`imagick` confirmation.
- No bulk actions yet (kept out for safety); duplicate + per-row publish are provided.

## 15. Production deployment considerations

- Run `php bin/migrate.php` (applies `011`) then `php bin/seed.php` (adds dosage forms; idempotent). Rebuild Tailwind locally (`npm run build`) and ship the committed `public/assets/css/app.css` — no Node on the server.
- Confirm host PHP extensions: `gd`/`imagick` (future thumbnails), `fileinfo` (upload MIME — required, already used).
- Ensure `public/uploads` is writable and its `.htaccess` (no-exec) is deployed.
- **Remove demo records before go-live:** anything with `is_demo = 1` (products/categories/areas) is clearly badged; delete/replace with real owner-supplied content.
- No pharmaceutical information in this build is verified or supplied — the catalog ships empty of real products by design.

## 16. Recommended Phase 4

**Lead CRM pipeline** — manage the leads already captured (contact + product enquiries, incl. `product_id`): assignment, statuses/stages, activity timeline, notes, follow-ups, and a sales dashboard, gated by the existing `leads.*` permissions. (Advanced analytics / lead scoring / automation are later still.)
