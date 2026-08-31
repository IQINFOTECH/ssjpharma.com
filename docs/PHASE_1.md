# Phase 1 — Public Website + CMS Foundation

**Status:** Implemented & verified (2026-08-29)
**Builds on:** Phase 0 foundation (custom PHP 8.3 MVC). Architecture unchanged.
**Scope guard:** No Product CMS, product listing, or CRM pipeline (later phases).

---

## 1. What was implemented

- **Database:** 9 migrations (RBAC, media, settings, pages, page_sections, menus, menu_items, redirects, leads foundation) + idempotent seeds.
- **Settings system:** CMS-managed global settings (company, website, social, lead, WhatsApp, analytics, security) via a typed key/value store; secrets stay in `.env`.
- **CMS pages:** list / create / edit / draft / publish / unpublish / archive / delete, search + pagination.
- **Modular sections:** 10 section types (hero, richtext, image_text, cards, cta, faq, stats, product_showcase, testimonials, contact_cta) driven by a `SectionRegistry`; open/closed for new types.
- **Public renderer:** URL → redirect check → published page → sections → SEO/OG/JSON-LD → breadcrumbs → render; 404 for missing/unpublished.
- **Dynamic menus:** header / mobile / footer, nested items, page-links or URLs — nothing hardcoded.
- **Header & footer:** premium, responsive; header nav + Enquire CTA + mobile drawer + WhatsApp; footer columns from CMS + contact/social from settings.
- **Design system:** Tailwind compiled locally to committed `public/assets/css/app.css` (no Node in production).
- **SEO:** per-page meta/canonical/OG/Twitter with settings fallback; `/sitemap.xml` (published, indexable only); `/robots.txt` (blocks /admin).
- **Structured data:** Organization, WebSite, BreadcrumbList JSON-LD — only real, admin-entered data.
- **Redirects:** CMS-managed 301/302 with loop + open-redirect protection.
- **Media library:** secure upload (MIME/extension/size, safe names, SVG sanitising, no execution), listing, alt-text, delete.
- **Contact / lead capture:** capture-first lead creation with CSRF, honeypot, rate limiting, optional Turnstile, server validation; `/thank-you`.
- **Email foundation:** SMTP via PHPMailer-optional `MailService` (graceful skip when unconfigured — lead still saved).
- **WhatsApp:** configurable wa.me click-to-chat button (no API).
- **Admin auth (minimal):** real login against the users table, forced password change, RBAC-gated modules. (Full user-management/reset/throttling = Phase 2.)

## 2. Database changes

New tables (see DATABASE_PLAN.md §15 for columns): `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `media`, `settings`, `pages`, `page_sections`, `menus`, `menu_items`, `redirects`, `lead_statuses`, `lead_sources`, `leads`, `contact_submissions`. All InnoDB / utf8mb4, FKs + indexes, `created_at/updated_at`, soft-delete where appropriate.

Runners: `php bin/migrate.php`, `php bin/seed.php`, `php bin/create-admin.php "Name" email password`.

## 3. Routes

**Public:** `GET /`, `GET /health`, `GET /sitemap.xml`, `GET /robots.txt`, `POST /contact`, `GET /{path}` (catch-all: redirect → page → 404).
**Admin (auth + forced-password gate):** `GET/POST /admin/login`, `POST /admin/logout`, `GET/POST /admin/password`, `GET /admin`; `pages` (index/create/store/edit/update/status/delete + sections add/update/delete); `menus` (index/add/update/delete item); `media` (index/upload/update/delete); `redirects` (index/store/update/delete); `settings` (index/update). Each admin write is CSRF-protected and permission-gated (`can:*`).

## 4. CMS features completed

Pages CRUD + status workflow; modular section editor; dynamic menu management; media library; redirect manager; global settings editor; dashboard with honest empty states.

## 5. Security implemented (Phase 1 additions)

- Prepared statements throughout (repositories); output escaped via `e()`.
- CSRF on every admin/public write (global middleware).
- RBAC gate in middleware **and** re-checked in every admin controller (defence in depth) — verified: a `content_editor` gets 403 on `/admin/settings`.
- Upload hardening: real-MIME check, extension allowlist, size cap, random names, SVG scrubbing, `.htaccess` no-exec in `public/uploads`.
- Redirect loop + open-redirect protection.
- Rich text sanitised (`HtmlSanitizer`) on save and re-escaped on render.
- Honeypot + filesystem rate limiter + optional Turnstile on the contact form; capture-first so notifications can never lose a lead.
- Secrets only in `.env`; dynamic CSP widened for GA/Turnstile only when enabled.
- Forced password change on first admin login; session regenerated on login/privilege change.

## 6. Tests performed

- **Automated:** PHPUnit 34 tests / 87 assertions green (router, env/config, CSRF, view, validator, HTML sanitizer, redirect-safety, section registry, slug, health smoke).
- **Live (against MariaDB):** migrations + seeds; all public routes (200/404/302 as expected); sitemap/robots content-types; admin auth (unauthorized→login, login→forced-password, gate); CMS round-trip (edit hero → homepage reflects); XSS attempt escaped; settings write reflected on site (WhatsApp button, GA + dynamic CSP); contact form valid (lead stored) / honeypot (stored + flagged) / invalid (rejected, no lead); RBAC denial for limited role; no horizontal overflow at 320–1920.

## 7. Bugs found & fixed during Phase 1

1. **Namespace alias collision** — `use App\Core\App;` in the bootstrap shadowed the `App\` namespace, so `App\Services\*` resolved to `App\Core\App\Services\*` (fatal). Fixed by aliasing the kernel import (`use App\Core\App as Kernel;`).
2. **`Repository::all()` LIMIT binding** — binding `LIMIT`/`OFFSET` fails under `PDO::ATTR_EMULATE_PREPARES=false`; switched to int-cast inlining (also applied to `INTERVAL` in `LeadRepository`).
3. **Seed `CAST(... AS JSON)`** — invalid on MariaDB 10.4; JSON columns take the string literal directly.
4. **Invalid form-in-`<tr>` / cross-boundary forms** — menus & redirects editors rewritten as grid rows so each `<form>` is well-formed.
5. **Section delete button inheriting `_method=PUT`** — split into a sibling POST form.

## 8. Known limitations (by design for Phase 1)

- Repeater section fields (cards/faq/stats/testimonials items) are edited as JSON in the admin; a visual repeater/WYSIWYG is a later enhancement.
- Rich text uses a textarea + server-side sanitiser (no WYSIWYG yet).
- Media picker is by Media ID (no modal picker yet); image resizing/thumbnails deferred (needs `gd`/`imagick` confirmation on host).
- Admin auth is the minimal foundation; full user management, password reset, and login throttling are Phase 2.
- Email sending requires PHPMailer (Phase 5) + SMTP creds; until then notifications are logged-and-skipped.

## 9. Recommended next phase

**Phase 2 — Authentication & RBAC (complete):** user management UI, roles & permissions matrix, password reset, login throttling, audit log — building on the tables already created here. Then Phase 3 (Product CMS).
