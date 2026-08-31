# Phase 6 — Compliance, SEO/AEO/GEO, Analytics, Performance & Launch Readiness

**Status:** Audit + targeted hardening complete & verified (2026-08-29). No production deploy, no demo purge, no CRM, no automation, no fabricated pharmaceutical data.
**Builds on:** Phases 0–5. Architecture unchanged (ADR-001). GoDaddy-compatible (Apache/PHP 8.3/MySQL/cron/SMTP).

This phase was an audit-and-harden pass, not a rebuild. Three parallel audits (SEO/structured-data, security, performance) informed targeted fixes; the rest is documentation for launch.

## 1. Content governance (§2, §5)
- The CMS never generates pharmaceutical content; only owner-supplied fields render (Phase 3 rule upheld).
- **Fix — demo data is not public in production.** `is_demo=1` products/categories/therapeutic-areas are now excluded from all public queries **and** the sitemap when `APP_ENV=production` (`ProductRepository`/`ProductCategoryRepository`/`TherapeuticAreaRepository::demoCond()`). In staging/local they remain visible with the "Demo — replace before production" badge for review. The definitive control remains the pre-launch purge.

## 2. Product publishing workflow (§3, §4)
- Statuses extended to **draft → in_review → approved → published → archived**. Content is never auto-approved/published.
- New permissions `products.review`, `products.archive` (granted to product_manager + admin + super_admin). Each transition is gated by the permission its **target** status requires (`ProductsController::STATUS_PERMISSION`): approve→`products.review`, publish→`products.publish`, archive→`products.archive`, draft/submit→`products.edit`. Every transition audits (`PRODUCT_SUBMITTED_REVIEW`/`PRODUCT_APPROVED`/`PRODUCT_PUBLISHED`/`PRODUCT_ARCHIVED`). Public catalog still shows only `published`, so in_review/approved never leak.

## 3. SEO (§6) — audited, correct
Titles, meta description, canonical, robots, OG, Twitter card, breadcrumbs (visible + JSON-LD), single-h1 hierarchy, image alt, and internal linking are all in place and driven from one source (`SeoService` + `layout.php`). Sitemap and robots.txt exclude admin/draft/archived (and demo in prod). No hardcoded/duplicated SEO values.

## 4. Structured data (§7, §8) — audited + hardened
JSON-LD: Organization, WebSite, BreadcrumbList, Product. **Product schema emits only name/url/description/image/sku** — no price/offers/availability/aggregateRating/review/gtin or any fabricated pharmaceutical claim. **Fix:** JSON-LD encoding now uses `JSON_HEX_TAG` so a `</script>` in an admin-entered value can't break out of the inline block.

## 5. AEO / GEO (§9, §10, §11)
Company facts are centrally CMS-managed (`company_*`, `website_*`, social) — no duplicated hardcoded company data. Consistent name/legal-name/URL/contact/description support answer- and generative-engine readability. No invented addresses, certifications, registrations, FAQs, or locations — those remain owner-supplied/approved.

## 6. Analytics (§12, §13, §14)
- GA4 loads only when an ID is configured. **Fix:** GA bootstrap + all JS moved into external `app.js`, so the strict CSP (`script-src 'self'`) needs no inline-script exception (the previous inline GA block was CSP-blocked). GA ID passed via `<body data-ga-id>`.
- New CMS settings: `analytics_gsc_verification` (Google Search Console) + `analytics_bing_verification` (Bing) → emitted as verification `<meta>` tags.
- **Conversion events** (no PII): `contact_form_submit`, `product_enquiry_submit`, `distributor_enquiry_submit`, `partnership_enquiry_submit`, `whatsapp_click` fire via `window.ssjTrack` from a whitelisted `?c=` marker on `/thank-you` and the WhatsApp click. Names/emails/phones/messages are never sent to analytics.
- UTM attribution is captured on submit and preserved on the lead (verified in Phase 4).

## 7. Performance (§15, §16, §17) — audited + tuned
No N+1 anywhere; server-side pagination throughout; strong indexing; memoized per-request settings; session-backed (zero-query) RBAC; single compiled CSS/JS (deferred) with lazy-loaded images; no caching of admin/lead data. **Fixes:** leads-list metrics consolidated from ~11 COUNT round-trips to **2 grouped conditional-aggregate queries**; added `products.updated_at` index (admin-list sort filesort); asset URLs now carry `?v=<mtime>` cache-busting + far-future immutable cache headers for static assets; admin responses send `Cache-Control: no-store`.

## 8. Security hardening (§18–21) — audited + net added
Strong existing controls confirmed: HttpOnly/SameSite/Secure cookies, strict CSP, HSTS-on-HTTPS, CSRF global, parameterised SQL, RBAC + IDOR + visibility scoping, upload validation + no-PHP-exec uploads, no user enumeration, bcrypt + rehash, throttling, sanitised errors, `.htaccess` protection of `.env`/logs/config/dumps. **Fixes (defence in depth against a mis-set `.env`):** `APP_DEBUG` is force-disabled and the session cookie `Secure` flag is forced true whenever `APP_ENV=production`, regardless of `.env`.

## 9. Accessibility (§34)
Confirmed: skip-to-content link, semantic landmarks, labelled form fields with inline errors, `aria-label` breadcrumbs, single-h1 hierarchy, image alt text, keyboard-operable nav/drawer, focus-visible states. Practical WCAG 2.2 AA posture; brand-orange small-text contrast is an owner-accepted exception (documented previously).

## 10. Demo-data inventory & purge (§23)
`bin/purge-demo-data.php` — **dry-run by default** (inventory only), purges only with `--confirm`. Current inventory: 1 product, 2 categories, 1 therapeutic area (+1 image; 3 leads would keep the lead and null `product_id`). **Not purged** — run `--confirm` only after owner confirmation.

## 11. Files created
`bin/purge-demo-data.php`; migration `016_product_updated_index.sql`; `tests/Unit/Phase6HardeningTest.php`; docs `PHASE_6.md`, `PRODUCTION_READINESS.md`, `GO_LIVE_PLAN.md`, `BACKUP_STRATEGY.md`.

## 12. Files modified
`ProductRepository`, `ProductCategoryRepository`, `TherapeuticAreaRepository` (demo governance); `StructuredDataService` (JSON_HEX_TAG); `LeadRepository` (metrics consolidation); `ProductService` + `ProductsController` (review workflow); `config/app.php`, `config/security.php`, `bootstrap/app.php` (production security net); `AdminController` (no-store); `Support/helpers.php` (asset versioning); `site/layout.php` + `public/assets/js/app.js` (CSP-clean GA + conversion events + verification metas); `ContactController` (conversion marker); `public/.htaccess` (static cache); seeds `001_rbac.sql` (product perms) + `002_settings.sql` (analytics verification); `.env.example`.

## 13. Verification
- **80 tests green** (+`Phase6HardeningTest`).
- Demo inventory dry-run confirmed; leads-metrics consolidation re-verified against the Phase 4.1 visibility matrix (no regression).
- Browser: public site renders with **zero console/CSP errors** on the matching origin; `window.ssjTrack` defined; GA absent when unconfigured (correct).
- Full app lint clean.

## 14. Not done (by instruction)
No production deploy, no demo purge, no real pharma content upload, no CRM, no WhatsApp Cloud API, no marketing automation.
