# SSJ Pharmaceuticals LLP — Development Plan

**Companion to:** ARCHITECTURE.md · DATABASE_PLAN.md · SECURITY_PLAN.md
**Status:** Proposed. No application code written yet.
**Last updated:** 2026-08-29

---

## 1. Guiding principles

- **Ship in vertical slices.** Each phase produces something demonstrable, not just plumbing.
- **Security is not a phase** — CSRF, escaping, prepared statements, and RBAC land with the first feature, not bolted on later.
- **Nothing overwrites existing work.** (Currently greenfield — no existing code to preserve.)
- **Every phase ends with:** working feature + migrations + seed data + a short manual test checklist.
- **Deploy early, deploy often** to the GoDaddy host once Phase 1 is stable, so hosting quirks surface fast.

---

## 2. Toolchain baseline (already verified)

| Tool | Local | Production | Notes |
|---|---|---|---|
| PHP | 8.2.12 | 8.3 | Target 8.2+ syntax for compatibility |
| Composer | 2.10 | confirm on host | Needed for vendors |
| MySQL | XAMPP (local) | cPanel MySQL/MariaDB | — |
| Node/npm | 24 / 11 | **not used in prod** | Tailwind build only |
| Git | 2.55 | — | Version control |

⚠️ Confirm on GoDaddy before Phase 1 sign-off: **`gd`/`imagick`** (image resizing), **`intl`**, Composer/SSH availability, ability to set document root, cron access, SMTP details.

---

## 2a. Locked decisions (ADR-001, 2026-08-29)

1. **Framework:** custom lightweight MVC, PHP 8.3, cPanel-compatible. No Laravel/Slim.
2. **WhatsApp:** `wa.me` click-to-chat only; number + default messages CMS-configurable; service layer future-proofed for Cloud API.
3. **SMTP:** production SMTP, credentials supplied at deploy via `.env` (placeholders in `.env.example`). No hardcoding.
4. **Composer/Deploy:** Composer is dev-only; commit `vendor/`; server never runs Composer; deploy via cPanel File Manager/SFTP, no SSH; no secrets committed.
5. **Document root:** prefer `/public`; secure `.htaccess` fallback protects app/config/database/storage/.env/logs/backups.
6. **Language:** English only; schema/code kept i18n-friendly without translation tables now.

**Refinement:** Phase 0 core is **dependency-free pure PHP**; Composer reserved for PHPMailer (Phase 5) + PHPUnit (dev). App boots with or without `vendor/`.

## 3. Phase 0 — Project foundation (skeleton only)

**Goal:** a booting app that serves a health-check route through the full MVC pipeline, with security, sessions, CSRF, auth/RBAC scaffolding, and a working DB connection — no site content.

- `composer.json` (PSR-4 `App\` → `app/`) declaring PHPMailer (require) + PHPUnit (require-dev); first-party fallback autoloader so the app boots without `vendor/`.
- Core (all first-party): `App` kernel, `Container`, `Env`, `Config`, `Request`, `Response`, `Router`, base `Controller`, `View`, `Session`, `Csrf`, `Logger`, `Database` (PDO), base `Model`, base `Repository`.
- Middleware: `SecurityHeaders`, `StartSession`, `VerifyCsrf`, `Authenticate`, `Authorize` (RBAC).
- Auth foundation: `Auth` (session identity) + `Rbac` (permission checks) — scaffolding only, no login UI yet.
- Front controller `public/index.php`; `public/.htaccess` (rewrite + headers); **root `.htaccess` fallback** + per-dir deny `.htaccess` in `app/`, `config/`, `database/`, `storage/`.
- Config loader (`config/*.php`) + `.env` loader; `.env.example` with SMTP + DB + app placeholders.
- Error/exception/shutdown handler + first-party file `Logger` to `storage/logs` (no secrets, no traces to client).
- `routes/web.php` with a `/health` (JSON) and `/` placeholder route; `bin/migrate.php` runner + `000_create_migrations_table.sql`.
- `storage/` subtree (logs, cache, sessions) with deny `.htaccess`; `.gitignore`; `README.md`; `phpunit.xml` + smoke tests.

> Tailwind wiring moves to **Phase 1** (first real UI). Phase 0 ships no site styling beyond a minimal health view.

**Exit test:** `/health` returns `{status: ok}` with DB connectivity flag; 404/500 handled cleanly; security headers present; protected dirs return 403; migrate runner applies the meta table; app boots with `vendor/` absent; `php -l` clean across all files; unit tests green.

---

## 4. Phase 1 — Public website + CMS foundation  ✅ DONE (2026-08-29)

> **Implemented & verified.** See **PHASE_1.md** for the full report (files, routes, DB, tests, bugs fixed, limitations). Exit tests passed: CMS edit reflects on the public site; contact form stores leads (capture-first); sitemap/robots/SEO/JSON-LD present; RBAC denial verified; no horizontal overflow 320–1920. A minimal admin-auth foundation was included (login + forced password change + RBAC gating) to protect the CMS; full user-management/reset/throttling remains Phase 2.

**Goal:** the corporate site is live and editable, even before catalog/CRM.

- DB: `settings`, `pages`, `page_sections`, `menus`, `menu_items`, `media`, `redirects` (see DATABASE_PLAN).
- Site controllers: home, about, quality/certifications, contact (static-ish, CMS-driven).
- CMS rendering: pages composed from typed sections; header/footer menus from DB; global settings (logo, contact, socials).
- SEO baseline: per-page meta/OG/canonical, JSON-LD Organization, `robots.txt`, sitemap stub.
- Contact form → stored submission + email notification (capture-first; CRM lead model comes in Phase 4 but the contact table is forward-compatible).
- Cookie-consent banner + analytics injection (only when ID set + consented).

**Exit test:** edit a page/menu/setting in DB (admin UI arrives Phase 3) and see it reflected; contact form stores + emails; Lighthouse SEO/a11y pass on public pages.

---

## 5. Phase 2 — Authentication + RBAC  ✅ DONE (2026-08-29)

> **Implemented & verified.** Full detail in **AUTHENTICATION_RBAC.md**. Delivered: 6 DB-driven roles + 42 granular permissions + configurable matrix (`/admin/roles`); user management (`/admin/users`) with super-admin protection + anti-escalation; login throttling (DB) + per-IP limit; secure password reset (hashed single-use tokens, no enumeration); append-only audit log + read-only viewer (`/admin/audit-logs`); profile (`/admin/profile`) + active-session registry with remote revoke (`/admin/sessions`). Phase 1 controllers migrated from coarse `*.manage` to granular permissions. Verified: RBAC 403s (GET+POST) for limited roles, CSRF, super-admin protection, throttle, reset single-use, session revocation, IDOR/escalation blocked.

**Goal:** secure admin foundation every later admin feature builds on.

- DB: `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `password_resets`, `login_attempts`, `audit_log`.
- Auth: login/logout, `password_hash`/verify + rehash-on-login, secure session cookies, login throttling, forgot/reset password via emailed single-use token.
- RBAC: `Auth` + `Rbac` middleware; permission checks also re-asserted in services.
- Seeds: roles (Super Admin, Content Editor, Sales Agent, Sales Manager), permission catalogue, first Super Admin (credentials via `.env`/prompt, forced change on first login).
- Admin shell: layout, nav gated by permissions, "force password change" gate.
- Audit logging of security-relevant actions.

**Exit test:** each role sees only permitted nav/actions; throttling blocks brute force; reset flow works; unauthorized direct URL access is denied by middleware **and** service.

---

> **Note (2026-08-29):** the Product Catalog was delivered as its own **Phase 3 — Product CMS + Public Catalog** (see PHASE_3.md): product/category/therapeutic-area/dosage-form CRUD, public `/products`, `/products/{slug}`, `/product-category/{slug}`, `/therapeutic-area/{slug}`, product search/filters, Product+Breadcrumb JSON-LD, slug redirects, product enquiry + WhatsApp CTAs, sitemap, gated by the existing `products.*` permissions. No fabricated pharmaceutical data. The original "Phase 3" section below (admin CMS/media, delivered earlier in Phase 1) is retained for history.

## 6. Phase 3 (historical label) — Admin CMS + Media + Catalog management (UI)

**Goal:** non-developers manage content and products.

- CMS admin: pages CRUD, drag-order sections, menu builder, settings editor, redirects, draft/publish.
- Media library: upload (hardened validation), GD/Imagick resize/thumbnail, alt-text, reuse picker.
- Catalog admin: `categories` (nestable) + `products` CRUD (composition, strength, form, pack size, therapeutic category, images, spec-sheet upload, active flag, SEO fields).
- Public catalog: category listing + filter + search, product detail `/products/{category}/{slug}` with product enquiry form.
- Product & Breadcrumb JSON-LD; sitemap includes products.

**Exit test:** Content Editor (no CRM/user rights) can build a page, upload media, and publish a product end-to-end; public catalog reflects changes; enquiry form on a product submits.

---

## 7. Phase 4 — Lead generation + Enquiry system  ✅ DONE (2026-08-29)

> **Delivered (see PHASE_4.md):** one central `leads` table for every source (general/product/distributor/partnership/CTA), enquiry type + source resolved **server-side**; capture-first with post-commit notification (a mail failure never loses a lead); duplicate **linking** within 24h; product name **snapshotted from the DB**; `/admin/leads` list (metrics, search, filters, pagination) + `/admin/leads/{id}` detail with append-only activity timeline, notes, status/priority/assignment; CSV export with formula-injection protection; dashboard metrics; `whatsapp_clicks` analytics via `POST /whatsapp/track` (wa.me only); layered spam protection; reusable escaped email templates. Gated by `leads.*`. **56 tests green.** Deferred to later phases (unchanged): advanced pipeline analytics, lead scoring, forecasting, automated follow-up/reminders, WhatsApp Business API. The original plan for this phase is retained below for history.

> **Phase 4.1 — Lead access control hardening  ✅ DONE (2026-08-29, see PHASE_4.md §10a):** replaced "any `leads.view` holder sees all leads" with a permission-driven visibility scope — `leads.view_all` (all) vs `leads.view_assigned` (own assigned only) vs none; `leads.view` is module access only. Enforced in SQL at the data-access layer across list/search/filter/pagination/detail/mutations/export/dashboard metrics (never PHP/frontend, never trusting client ids); out-of-scope detail → 404 (IDOR-safe); export/metrics same scope. No role names hardcoded (`LeadVisibility`). **62 tests green** incl. a 24-check data-layer authorization matrix.

> **Phase 4 foundation completion  ✅ DONE (2026-08-29, see LEAD_MANAGEMENT.md):** added `follow_up_date` (+ inbox column, set/clear action, `LEAD_FOLLOWUP_UPDATED`); granular `leads.notes`/`leads.status`/`leads.priority` permissions (route + controller gated; granted to existing edit-holders to preserve behaviour); aligned vocabulary (status **Converted**, priority **Medium**); added `website`/`landing_page` sources; expanded scoped lead dashboard (contacted/qualified/converted, this-week/this-month, product enquiries — one grouped query, no N+1). Migration 013 + seed 009. New `docs/LEAD_MANAGEMENT.md`. **66 tests green.**

**Goal:** every enquiry becomes a managed lead.

- DB: `leads`, `lead_activities`, `lead_notes`, `lead_status_history`, `lead_sources`, `lead_statuses`, `lead_assignments`.
- Capture: contact + product enquiry + "request a quote" all create `leads` (capture-first, honeypot, CSRF, rate limit, server validation). Lead tagged with source + product.
- CRM UI: lead list (filter by status/source/agent/date, search), lead detail with activity timeline + notes + next-follow-up, status transitions with history, assignment.
- Dashboard: counts by status, agent workload, conversion, recent activity, due follow-ups.
- Notifications: email to sales on new lead + auto-acknowledgement to enquirer.
- RBAC: Sales Agent sees own/assigned; Sales Manager sees all + reports.

**Exit test:** submitting any public form creates a lead + activity + emails; agent can progress a lead through the pipeline; manager sees dashboard; agent cannot see others' leads.

---

## 8. Phase 5 — Communications & Follow-up Operations  ✅ DONE (2026-08-29)

> **Delivered (see PHASE_5.md + ADR-005):** outbound `email_queue` + cron worker (`bin/process-email-queue.php`) with atomic claim (concurrency-safe, no double-send), exponential-backoff retry, permanent-fail on invalid recipient; capture-first preserved (lead commits, then enqueue); `MAIL_DELIVERY_MODE` (smtp/log/disabled) dev guard; CMS email + WhatsApp templates with safe `{{placeholder}}` rendering (`TemplateRenderer` — no code execution, HTML-escaped, header-safe, sandboxed preview); daily follow-up digests (`bin/send-followup-digests.php`) — one per assignee, visibility-scoped, DB-idempotent; `/admin/email-queue` monitor (retry/cancel) + `/admin/communications/templates` (edit/preview/test-to-self); follow-up quick filters + counts on `/admin/leads`; `communications.*` permissions (admin/super_admin only); ADR-005 (wa.me only, Cloud API NOT IMPLEMENTED). Migrations 014/015, seed 010. **74 tests green + 27-check live integration + real CLI run.** Original outline retained below.

## 8b. Phase 5 (original outline) — Email queue + WhatsApp + reminders

**Goal:** reliable outbound comms without external automation platforms.

- Email: outbound `email_queue` + `email_log`; PHPMailer SMTP; templated mails; **cron flushes the queue** (`bin/cron.php`).
- Reminders: cron scans due follow-ups → digest email to agents; overdue flags on dashboard.
- WhatsApp Tier 1: `wa.me` click-to-chat links (staff quick-contact + site CTAs) — zero dependency.
- WhatsApp Tier 2 (optional, if approved): `WhatsAppService` calling **Meta WhatsApp Cloud API directly over cURL**, credentials in `.env`, settings toggle, `whatsapp_log`; disabled by default.

**Exit test:** queued mail sends via cron and is logged; reminder digest fires; wa.me links prefill correctly; (if enabled) a WhatsApp template send logs success/failure.

---

## 9. Phase 6 — Compliance, SEO/AEO/GEO, Analytics, Performance & Launch Readiness  ✅ AUDIT+HARDENING DONE (2026-08-29)

> **Delivered (see PHASE_6.md):** content governance (demo hidden from public+sitemap in production; product review workflow draft→in_review→approved→published→archived with per-transition permissions `products.review`/`products.archive`); SEO/JSON-LD audited (correct, no fabricated fields) + `JSON_HEX_TAG` hardening; analytics moved to CSP-clean external JS + GSC/Bing verification settings + no-PII conversion events; performance (leads metrics 11→2 queries, `products.updated_at` index, asset cache-busting + far-future cache, admin `no-store`); security net (force `APP_DEBUG=false` + Secure cookie in production); demo-purge CLI (dry-run default); launch docs (`PRODUCTION_READINESS.md`, `GO_LIVE_PLAN.md`, `BACKUP_STRATEGY.md`). **80 tests green.** NOT done by instruction: production deploy, demo purge, real pharma content, CRM, WhatsApp API, marketing automation. Original outline below.

### 9-orig. SEO/Analytics polish + hardening + launch (outline)

**Goal:** production-ready.

- SEO: finalise sitemap generation (cron), canonical/redirect audit, 404 UX, structured data validation.
- Analytics: GA4/GTM wiring behind consent; internal first-party lead/traffic counters.
- Performance: OPcache config notes, query/index review, asset caching headers, image sizes.
- Security pass against SECURITY_PLAN checklist; dependency audit; upload/CSP review; secrets rotation.
- Backups: `mysqldump` cron + retention; documented restore.
- Content load: real pages, products, certifications, imagery.
- **Deployment runbook** executed on GoDaddy (document root, migrations, cron, SSL/HSTS, SMTP).

**Exit test:** full smoke test on production domain; security checklist green; backups verified by a test restore.

---

## 10. Deployment procedure (reused each release)

1. Tag release; run `composer install --no-dev -o` (locally or on host).
2. Compile Tailwind locally; commit `public/assets/css/app.css`.
3. Upload code above webroot; `public/` → document root (or fallback layout).
4. Set `.env` on host (never committed); verify perms (`storage/`, `public/uploads/` writable; `.env` 600).
5. Run `php bin/migrate.php` (new migrations only).
6. Confirm cron job present; hit health route; smoke-test key flows.
7. Clear OPcache (touch/restart or cPanel) so new code loads.

Full first-time GoDaddy steps (document root, DB creation, cron, SSL) are expanded in the Phase 6 runbook.

---

## 11. Testing strategy

- **Unit:** services (LeadService, AuthService, validation, SeoService).
- **Integration:** repositories against a test MySQL schema; auth/RBAC middleware.
- **Manual checklists:** per-phase exit tests above.
- **Security:** CSRF present on all state-changing forms; RBAC negative tests; upload abuse tests; SQLi/XSS spot checks.

---

## 12. Risks & mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Starter plan blocks document-root move | Exposed app files | Fallback layout + `.htaccess` deny rules (Phase 6 runbook) |
| No SSH → can't run Composer on host | Deploy friction | Commit `vendor/` as documented fallback |
| `gd`/`imagick` missing on host | No image resize | Confirm early; degrade to store-original + client-side constraints |
| Shared-host resource limits | Slow/timeouts | Lightweight MVC, OPcache, queue heavy work to cron |
| SMTP deliverability | Leads/mails lost | Dedicated transactional SMTP + SPF/DKIM/DMARC |
| Email/WhatsApp send failures | Missed leads | Capture-first + queue + logs; never block capture on notify |

---

## 13. What I need from you to start Phase 0

Answers to the six **Open decisions** in ARCHITECTURE.md §11 (framework, WhatsApp tier, SMTP, vendor deploy, document root, multilingual). With those confirmed, I will scaffold Phase 0.
