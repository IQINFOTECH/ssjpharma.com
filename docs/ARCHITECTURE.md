# SSJ Pharmaceuticals LLP — Technical Architecture

**Project:** SSJ Pharmaceuticals LLP corporate website + product catalog + CMS + built-in Lead CRM
**Domain:** https://ssjpharma.com
**Status:** Phase 0 (foundation) + Phase 1 (public site + CMS) + Phase 2 (Auth/RBAC/User-Management/Audit) + Phase 3 (Product CMS + public catalog) IMPLEMENTED & verified — see PHASE_1.md, AUTHENTICATION_RBAC.md, PHASE_3.md, DATABASE.md. CRM pipeline not yet built.
**Author:** Lead Software Architect
**Last updated:** 2026-08-29

---

## 1. Purpose & scope

A single PHP application that serves four concerns from one codebase:

1. **Public website** — premium pharmaceutical corporate presence (home, about, quality/certifications, contact).
2. **Product catalog** — categorised product listing with detail pages, editable from the admin.
3. **CMS** — non-developers edit pages, sections, menus, media, and SEO metadata.
4. **Lead CRM** — capture leads from site forms and manage them through a sales pipeline, with WhatsApp + email follow-up.

Everything runs as one deployable unit on **standard Apache + PHP 8.3 + MySQL** (GoDaddy Web Hosting Starter, cPanel). No background services, no separate API host.

---

## 2. Environment & hard constraints

| Layer | Decision | Reason |
|---|---|---|
| Runtime | PHP **8.3** (target 8.2+ syntax) | Host-provided; local dev is 8.2.12 |
| Web server | Apache + `.htaccess` (mod_rewrite) | cPanel default; no vhost access |
| Database | MySQL / MariaDB (single schema) | Host-provided |
| Package mgr | Composer (build-time) | Available; vendors committed or installed via cPanel Terminal/SSH |
| Sessions | Native PHP file sessions | No Redis available |
| Cache | OPcache + filesystem cache | No Redis/Memcached |
| Queue/cron | cPanel **Cron Jobs** hitting a CLI script | No daemon processes |

**Explicitly excluded** (per project constraints): WordPress, Node.js production runtime, Docker, Redis, MongoDB, PostgreSQL, n8n, Zapier, Make, any external CRM, any external backend service.

> **Node.js is used only as a local build tool** to compile Tailwind CSS. The compiled `app.css` is committed and shipped. **No Node process runs in production.** This fully respects the "No Node.js production runtime" constraint.

---

## 3. Framework decision — the one foundational choice

**Recommendation: a lightweight custom MVC on PHP 8.3**, wired together from small, well-audited Composer libraries — **not** a full framework.

### Why not Laravel / Symfony full-stack
- GoDaddy **Starter** shared hosting imposes entry-process and memory limits; a full framework's per-request bootstrap is heavier and harder to tune without server config access.
- Document-root remapping to `/public`, writable `storage/`, and `artisan`/cron plumbing are all doable on cPanel but fragile on the Starter tier.
- We control every dependency, keeping the attack surface and upgrade burden small.

### Why not raw PHP spaghetti
- Auth, RBAC, CMS, and CRM need real structure, testability, and security discipline.

### The chosen middle path
A thin, explicit MVC skeleton (PSR-4, PSR-12) that **borrows the hard/security-critical parts from proven libraries** and keeps the rest small and readable:

| Concern | Library | Rationale |
|---|---|---|
| Autoloading | `composer` PSR-4 | Standard |
| Routing | `nikic/fast-route` | Tiny, fast, no magic |
| HTTP messages | Custom Request/Response (thin) | Avoid heavy PSR-7 stack on shared host |
| Templating | **Native PHP templates** + a `View` helper | Zero compile step, fastest on shared hosting |
| Env config | `vlucas/phpdotenv` | `.env` outside webroot |
| DB access | **PDO** + a thin query builder / repository layer | Prepared statements everywhere |
| Migrations | Plain SQL files + a tiny runner script | No framework dependency; reviewable |
| Mail | `phpmailer/phpmailer` (SMTP) | Works with cPanel/host SMTP or transactional SMTP |
| Validation | Custom rules layer (small) | Predictable, no bloat |
| Logging | `monolog/monolog` | Standard, filesystem handler |
| CSRF / password / session hardening | Custom middleware + PHP native (`password_hash`, `random_bytes`) | See SECURITY_PLAN.md |

> **DECIDED (ADR-001):** custom lightweight MVC, no Laravel/Slim. Per ADR-001 the core is written dependency-free (first-party router/env/logger etc.); the library table above is the original intent and is superseded by §11's refinement — Composer is used only for PHPMailer (Phase 5) and PHPUnit (dev).

---

## 4. High-level architecture

```
                         Internet (HTTPS via GoDaddy / cPanel AutoSSL)
                                        │
                                   Apache + mod_rewrite
                                        │
                      /public/index.php   ← single front controller
                                        │
        ┌───────────────────────────────┼───────────────────────────────┐
        │                               │                               │
   Public site           Admin panel (/admin)              Cron endpoint (CLI only)
   controllers            controllers (auth-gated)          bin/cron.php
        │                               │                               │
        └──────────────► Application core (Router → Middleware → Controller → Service → Repository) ◄──────────────┘
                                        │
                                     PDO (MySQL)
                                        │
                                 ssjpharma schema
```

- **One front controller** (`/public/index.php`) boots the app, resolves the route, runs middleware, dispatches a controller.
- **Two route groups:** public site and `/admin` (session + RBAC gated).
- **Cron** is a separate CLI entrypoint (`bin/cron.php`) invoked by cPanel Cron for email queue flush, lead reminders, sitemap regeneration.

---

## 5. Directory layout

```
ssjpharma.com/
├── public/                     ← Apache document root (set in cPanel)
│   ├── index.php               ← front controller
│   ├── .htaccess               ← rewrite + security headers
│   ├── assets/
│   │   ├── css/app.css         ← compiled Tailwind (committed)
│   │   ├── js/app.js
│   │   └── img/
│   └── uploads/                ← user/CMS media (writable, hardened)
├── app/
│   ├── Core/                   ← Router, Request, Response, View, Container, Middleware base
│   ├── Middleware/             ← Auth, Rbac, Csrf, RateLimit, SecurityHeaders
│   ├── Controllers/
│   │   ├── Site/               ← public pages, catalog, contact/lead capture
│   │   └── Admin/              ← dashboard, CMS, catalog mgmt, CRM, users, settings
│   ├── Services/               ← LeadService, MailService, WhatsAppService, CmsService, SeoService, AuthService
│   ├── Repositories/           ← data access (PDO) per aggregate
│   ├── Models/                 ← lightweight entities/DTOs
│   ├── Support/                ← helpers (Str, Arr, Url, Sanitizer, Uploader)
│   └── Views/
│       ├── layouts/            ← site + admin master layouts
│       ├── site/               ← public templates
│       ├── admin/              ← admin templates
│       └── partials/
├── config/                     ← app.php, database.php, mail.php, security.php
├── database/
│   ├── migrations/             ← NNN_description.sql
│   └── seeds/                  ← roles, permissions, admin user, sample catalog
├── storage/                    ← OUTSIDE public: logs/, cache/, sessions/, mail-queue/
├── bin/
│   ├── cron.php                ← scheduled tasks entry
│   └── migrate.php             ← run migrations/seeds
├── resources/
│   └── css/tailwind.css        ← Tailwind source (build-time only)
├── docs/                       ← this documentation
├── tests/                      ← unit/integration (PHPUnit)
├── .env.example                ← template (real .env is gitignored, outside webroot)
├── composer.json
├── package.json                ← Tailwind build only (dev dependency)
└── tailwind.config.js
```

**Critical:** the Apache document root points at `/public` only. `app/`, `config/`, `storage/`, `database/`, `.env`, and `vendor/` live **above** the webroot and are never directly reachable. If the Starter plan forbids moving the document root, a fallback `.htaccess` deny-rule scheme is documented in DEPLOYMENT below.

---

## 6. Request lifecycle (MVC)

1. Apache rewrites all non-file requests to `/public/index.php`.
2. Front controller loads Composer autoload, `.env`, config, error handler.
3. **Router** (FastRoute) matches method + path → controller action.
4. **Middleware pipeline** runs in order:
   `SecurityHeaders → Session → Csrf → (Auth → Rbac for /admin) → RateLimit`.
5. **Controller** validates input, calls a **Service**.
6. **Service** holds business logic, calls **Repositories** (PDO).
7. Controller returns a **View** (native PHP template rendered into a layout) or JSON.
8. Response emitted with correct headers.

No global state; dependencies passed explicitly via a minimal container.

---

## 7. Frontend

- **Tailwind CSS 3** compiled locally → single minified `public/assets/css/app.css` (committed). No CDN, no runtime build.
- **Server-rendered native PHP templates** with a shared layout, partials, and a `View` helper providing `e()` (escape), `csrf_field()`, `asset()`, `route()`.
- **Progressive enhancement JS** (vanilla, small `app.js`): mobile nav, catalog filters, form UX, admin table interactions. Optional **Alpine.js** (single file, self-hosted) for admin widgets if needed — no build step.
- **Design language:** premium pharma — clean, clinical, trustworthy; strong typography, whitespace, accessible colour contrast, certification/quality badges. Full component/design pass happens in the build phase.
- **Accessibility:** semantic HTML, WCAG AA contrast, keyboard nav, alt text enforced in CMS media.

---

## 8. Backend & core subsystems

### 8.1 Authentication
- Session-based login for admin/CRM users (see SECURITY_PLAN).
- `password_hash()` (bcrypt/argon2id), rehash-on-login, secure session cookies, login throttling, password reset via emailed single-use token.

### 8.2 RBAC
- Roles → Permissions (many-to-many), Users → Roles.
- Seed roles: **Super Admin**, **Content Editor** (CMS + catalog), **Sales Agent** (CRM own/assigned leads), **Sales Manager** (all leads + reports).
- Permission checks enforced in the `Rbac` middleware **and** re-checked in services (defence in depth).

### 8.3 CMS
- Editable **Pages** (slug, title, SEO fields, published state) composed of ordered **Sections** (typed blocks: hero, rich text, image, feature grid, CTA, gallery).
- **Menus** (header/footer) built from menu-item rows, not hardcoded.
- **Media library** with server-side validation, image resizing (GD/Imagick), and alt-text.
- **Global settings** (site name, logo, contact info, social links, analytics IDs, WhatsApp number) in a `settings` key/value table.
- Draft vs published, with an audit trail of who changed what.

### 8.4 Product catalog
- **Categories** (nestable) and **Products** (name, slug, composition, strength, form, pack size, therapeutic category, description, images, downloadable spec sheet, active flag, SEO fields).
- Public listing with category filter + search; SEO-friendly detail URLs `/products/{category}/{slug}`.
- Each product detail page carries an **enquiry form** → creates a CRM lead tagged with the product.

### 8.5 Lead generation  ✅ implemented (Phase 4, 2026-08-29 — see PHASE_4.md)
- Capture points: contact page, product enquiry, distributor, partnership, website CTA — all write to **one** `leads` table. Enquiry type + source are resolved **server-side** (`EnquiryType`), never from client input.
- **Capture-first** order: validate → save lead (transaction) → commit → notify → log. A mail failure never loses a lead. Honeypot + CSRF + rate limit + server-side validation + optional Turnstile on every form; spam is stored-and-flagged, not dropped.
- Each submission → `leads` row + `contact_submissions` payload + `lead_activities` "created" entry + post-commit email. Repeat enquiries within 24h **link** to the open lead instead of duplicating. Product enquiries snapshot the product name **from the DB**.

### 8.6 CRM (lead management)  ✅ basic management + visibility control (Phase 4 + 4.1)
- **Done:** configurable **statuses** (New → Contacted → Qualified → Proposal → Won/Lost/Spam), **sources**, **priority**, **assignment** (validated against lead-capable users); `/admin/leads` list (metrics, search, filters, pagination) + `/admin/leads/{id}` detail with append-only activity timeline, internal notes, mark-contacted, soft-delete; CSV export (formula-injection safe); dashboard lead metrics. Gated by `leads.*`; all mutations audited.
- **Visibility control (Phase 4.1):** permission-driven per-user scope — `leads.view_all` (all leads) vs `leads.view_assigned` (`assigned_user_id = self`) vs none; `leads.view` is module access only. Enforced in SQL at the data-access layer across list/search/filter/pagination/detail/mutations/export/metrics (never PHP/frontend filtering, never trusting client ids); out-of-scope detail returns 404 (IDOR-safe). `App\Support\LeadVisibility`, no role names hardcoded.
- **Foundation completion:** manual `follow_up_date` (schedule, not automation), granular `leads.notes`/`leads.status`/`leads.priority` permissions, status vocabulary New→Contacted→Qualified→Proposal→Converted→Lost(+Spam), priority Low/Medium/High/Urgent, and a scoped lead dashboard (per-status + today/week/month + product enquiries). Full reference: **LEAD_MANAGEMENT.md**.
- **Deferred (later phases, intentionally not built):** automated follow-up *reminders*, lead scoring, conversion/forecast analytics, automated follow-up sequences.

### 8.7 Email  ✅ implemented (Phase 5 — see PHASE_5.md)
- **PHPMailer over SMTP** (host SMTP or a transactional SMTP account via `.env`); `MAIL_DELIVERY_MODE` (smtp/log/disabled) guards dev.
- CMS-managed templates rendered with safe `{{placeholders}}` (`TemplateRenderer` — no code execution, HTML-escaped, header-safe).
- **Outbound `email_queue`** flushed by cron (`bin/process-email-queue.php`): atomic claim (no double-send under concurrent cron), exponential-backoff retry, permanent-fail on invalid recipient. Keeps web requests fast and survives SMTP hiccups; capture-first preserved (enqueue after commit).
- **Follow-up digests** (`bin/send-followup-digests.php`): one visibility-scoped, idempotent daily digest per assignee.

### 8.8 WhatsApp
- **Implemented (Phase 4): Tier 1 only** — `wa.me` click-to-chat links plus a CSRF-protected, rate-limited `POST /whatsapp/track` click beacon (`whatsapp_clicks`); the product is resolved from the page path so no internal ids leak. Tier 2 (Cloud API) remains **not built** and, per ADR-001, wa.me-only stands until the owner decides otherwise.
- Constraint-compliant approach — **no n8n/Zapier/Make, no external CRM.**
- **Tier 1 (always available, zero dependency):** generate `https://wa.me/<number>?text=<prefilled>` **click-to-chat** links for staff and for site CTAs. Fully static, no API.
- **Tier 2 (optional, direct API only):** a `WhatsAppService` that calls the **WhatsApp Cloud API (Meta) directly over HTTPS/cURL** for template notifications, credentials in `.env`. This is a direct first-party integration, not a third-party automation platform, so it stays within constraints. Disabled by default via a settings toggle; every send logged in `whatsapp_log`.

### 8.9 SEO
- Per-page/per-product editable `<title>`, meta description, canonical, Open Graph, Twitter card.
- JSON-LD structured data (Organization, Product, BreadcrumbList).
- Auto-generated `sitemap.xml` (cron-refreshed) and `robots.txt`.
- Clean rewrite URLs, 301 redirect table (CMS-managed), 404 handling.

### 8.10 Analytics
- Google Analytics 4 / Google Tag Manager ID stored in settings, injected only when set and consent given.
- Cookie-consent banner (privacy-first default: no non-essential cookies until accepted).
- Lightweight first-party server-side hit/lead counters for internal dashboards (no external dependency).

### 8.11 Security
- Full detail in **SECURITY_PLAN.md**. Summary: prepared statements everywhere, output escaping, CSRF tokens, security headers/CSP, session hardening, RBAC defence-in-depth, upload hardening, rate limiting, secrets in `.env` outside webroot, audit logging.

---

## 9. Deployment (GoDaddy cPanel)

- **Preferred:** point the domain's document root to `/public` (cPanel → Domains). App code sits above webroot.
- **Fallback** (if root can't be moved on Starter): deploy `public/` contents into `public_html/` and place `app/`, `config/`, `storage/`, `vendor/`, `.env` in a sibling private folder above `public_html`, with `.htaccess` hard-deny on any sensitive paths. Documented step-by-step in DEVELOPMENT_PLAN.
- **Composer:** run `composer install --no-dev -o` via cPanel Terminal/SSH if available; otherwise commit `vendor/` (documented tradeoff).
- **Assets:** Tailwind compiled locally, `app.css` committed — nothing to build on the server.
- **DB:** create schema + user in cPanel MySQL; run `bin/migrate.php` (via Terminal or a one-time protected web route) to apply migrations + seeds.
- **Cron:** cPanel Cron → `php /home/USER/ssjpharma/bin/cron.php` every 5 min (email queue, reminders, sitemap).
- **TLS:** cPanel AutoSSL / Let's Encrypt; force HTTPS + HSTS in `.htaccess`.
- **Backups:** cPanel backups + scheduled `mysqldump` retained off the webroot.

---

## 10. Non-functional targets

- **Performance:** OPcache on, compiled CSS, indexed queries, no N+1 in list views, HTTP caching for static assets. Target < 200 ms server render for cached pages.
- **Maintainability:** PSR-12, typed properties, small services, SQL migrations reviewed in PRs.
- **Portability:** nothing tied to a specific host beyond standard LAMP; can move to any Apache+PHP+MySQL host.
- **Testability:** PHPUnit for services/validation; smoke tests for critical routes.

---

## 11. Architecture Decision Record — ADR-001 (LOCKED 2026-08-29)

These six decisions are **final** and govern all implementation.

1. **Framework — Custom lightweight MVC on PHP 8.3.** No Laravel, no Slim. Must remain compatible with standard GoDaddy cPanel hosting.
2. **WhatsApp — `wa.me` Click-to-Chat only at launch.** No Cloud API now. The WhatsApp number **and** default prefilled messages are **configurable from the CMS** (`settings` table). The service layer is designed so the Cloud API can be added later **without rewriting the lead system** (see §8.8).
3. **SMTP — SMTP for production email; credentials supplied at deployment.** Never hardcoded. `.env.example` ships with placeholders (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`). Provider-agnostic; works with any standard SSJ-domain SMTP account.
4. **Deployment / Composer — SSH/Composer on GoDaddy is NOT assumed.** Composer is a **development-time** tool. The production-ready `vendor/` is committed/uploaded so the server never runs Composer. Deployment works through **cPanel File Manager / SFTP without SSH**. Secrets are never committed. Dependency install is documented in DEVELOPMENT_PLAN.
5. **Document root — prefer pointing the domain at `/public`; MUST support a secure fallback.** If the root cannot be moved, a fallback `.htaccess` layout keeps the public entry point reachable while making `app/`, `config/`, `database/`, `storage/`, `.env`, logs, and backups **publicly inaccessible**. See §5 and SECURITY_PLAN §14.
6. **Language — English only at launch.** No i18n framework, no translation tables now. Schema/code kept clean enough that Hindi/other languages can be added later without a major rewrite (nullable/extensible design, no hardcoded copy in logic). No unnecessary translation complexity added at this stage.

### Refinement to §3 (consequence of ADR-4/1): dependency-free core

To honour "the server never runs Composer" and "works via cPanel File Manager without SSH," the **Phase 0 foundation is written as pure PHP 8.3 with zero required runtime Composer dependencies.** The router, env loader, config, container, view, session, CSRF, PDO layer, logger, and error handler are all first-party. Composer is reserved for:

- **`phpmailer/phpmailer`** — SMTP mail (introduced in Phase 5), shipped inside the committed `vendor/`.
- **`phpunit/phpunit`** — dev-only test tooling, never deployed.

This supersedes the earlier §3 table's use of `nikic/fast-route`, `vlucas/phpdotenv`, and `monolog/monolog` — each is replaced by a small first-party equivalent to minimise the deployed surface and guarantee the app boots even if `vendor/` is absent. The Composer autoloader is used when present; a bundled PSR-4 autoloader is the fallback.

See DEVELOPMENT_PLAN.md for the phased build, DATABASE_PLAN.md for the schema, and SECURITY_PLAN.md for controls.
