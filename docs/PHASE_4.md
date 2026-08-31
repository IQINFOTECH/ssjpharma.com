# Phase 4 — Lead Generation + Enquiry System

**Status:** Implemented & verified (2026-08-29)
**Builds on:** Phase 0 core, Phase 1 CMS/media/SEO/contact-capture, Phase 2 Auth/RBAC/Audit, Phase 3 Product catalog. Architecture unchanged (ADR-001 — custom dependency-free PHP MVC, no external CRM/automation).
**Content rule:** No fabricated pharmaceutical data, response-time promises, or business claims. Auto-reply/acknowledgement copy is generic and OFF by default; the owner controls all real content.
**Scope guard:** This phase delivers reliable first-party lead *capture + basic management*. It deliberately STOPS short of an advanced CRM pipeline, lead scoring, sales forecasting, automated follow-up sequences, marketing automation, the WhatsApp Business API, n8n/Zapier, and advanced analytics.

---

## 1. One lead table, every source

All enquiry sources write to the **same** `leads` table so nothing is lost and everything is searchable in one place:

- **General contact** (`/contact-us`) → `general` / `contact_form`
- **Product enquiry** (product page) → `product` / `product_enquiry`
- **Distributor** (`/become-a-distributor`) → `distributor` / `distributor_enquiry`
- **Partnership** (`/partnership`, new Phase 4 page) → `partnership` / `partnership_enquiry`
- **Website CTA / WhatsApp** → attribution sources for reporting

The enquiry **type** and **source** are resolved **server-side** from the submitting form (`App\Support\EnquiryType`), never trusted from a client field. `whatsapp_clicks` records CTA clicks for analytics — a click is explicitly **not** a lead.

Schema: migration `012_lead_generation_extend.sql` (non-destructive — extends the Phase 1 foundation), seeds `007_lead_extras.sql` (spam status, sources, auto-reply/privacy settings) and `008_partnership_page.sql`.

## 2. Capture-first reliability (a lead is never lost)

`App\Services\LeadService::create()` follows a strict order (spec §11):

1. **Validate** server-side (name/email/phone required; sizes capped; consent required).
2. **Save the lead** inside a DB transaction (`leads` + `contact_submissions` + a `created` activity), then **commit**.
3. **Only then** attempt email notification.
4. **Log** the outcome to the lead's timeline and `notification_status` (`pending`/`sent`/`failed`/`skipped`).

A mail/SMTP failure cannot roll back or lose the lead — notification happens **after** commit and never throws to the caller. SMTP credentials come from `.env` only (`config/mail.php`); if SMTP is unconfigured (typical in dev) sending is skipped gracefully.

## 3. Duplicate handling (link, never destroy)

A submission that matches an **open, non-spam** lead by exact email or phone within a 24h window is **linked** to that lead (a new `contact_submissions` row + a `repeat_enquiry` activity + `updated_at` touch) instead of creating a duplicate. Won/lost/spam leads are excluded, so legitimate later enquiries still create fresh leads. Detection is portable (no `REGEXP_REPLACE`); it never blocks a genuine enquiry.

## 4. Product enquiries are trustworthy

The hidden `product_id` is **validated against a published product** and the product **name is snapshotted from the database** (`product_name_snapshot`) — a spoofed client-supplied product name is ignored. An invalid/unpublished `product_id` is dropped (the lead is still captured as a product enquiry); it never causes an error or a bogus FK.

## 5. Admin lead management (RBAC-gated)

- **`/admin/leads`** — list with metric cards (New / Open / Today / Unassigned), full-text search (name/company/email/phone/reference via a single-placeholder `CONCAT_WS … LIKE`), filters (status, priority, enquiry type, assignee, source, date range), sort, and pagination (25/page, indexed).
- **`/admin/leads/{id}`** — full detail: contact/business/enquiry/product, source & attribution + UTMs, consent record, status/priority/assignment controls, internal notes, and the append-only **activity timeline**.
- **Actions:** change status, change priority, assign/unassign (assignee validated against users holding `leads.view`/`leads.edit`), add internal note, mark contacted, soft-delete.
- **CSV export** — filtered rows, UTF-8 BOM, with **formula-injection protection** (`App\Support\Csv` prefixes any cell starting with `= + - @`/tab/CR with a quote). Capped at 5,000 rows.
- **Dashboard** surfaces the same lead metrics + recent enquiries to users with `leads.view`.

Permissions: `leads.view`, `leads.create`, `leads.edit`, `leads.assign`, `leads.delete`, `leads.export`. Every controller action re-checks its permission (defence in depth) in addition to the route `can:` middleware. Admin lead data is never exposed on the public site.

## 6. WhatsApp (wa.me only)

Click-to-chat links stay `wa.me`. A best-effort JS beacon posts to **`POST /whatsapp/track`** (CSRF-protected, rate-limited) recording context/page/UTMs. For product pages the product is resolved from the **page path** (`/products/{slug}`), never from a DOM id, so no internal ids leak. Tracking failures never block the user's WhatsApp hand-off.

## 7. Layered spam & abuse protection

CSRF (global middleware) · honeypot field · per-IP rate limiting (filesystem window + DB backstop) · server-side validation + size limits · optional Cloudflare Turnstile (already wired) · spam is **stored-and-flagged** (`is_spam`, `spam` status, `notification_status=skipped`) rather than silently dropped. Email header injection is prevented (CR/LF stripped from subjects; PHPMailer validates all addresses; enquirer set only as validated `Reply-To`).

## 8. Reusable email templates

`app/Views/emails/lead_internal.php` (internal new-lead notification, Reply-To the enquirer) and `lead_ack.php` (optional customer acknowledgement). Branding pulls from CMS settings; all values are escaped; no secrets or credentials are ever included. Recipient precedence: CMS `lead_notification_email` → CMS `lead_sales_email` → `MAIL_SALES_INBOX` (`.env`).

## 9. Verification

- **56 automated tests** green (`vendor/bin/phpunit`) — includes new `EnquiryTypeTest` (server-side classification, spoofed-key fallback) and `CsvTest` (formula-injection neutralisation, quoting, BOM).
- **Live integration smoke** (30 checks against the dev DB): capture-first, server-side classification, product snapshot-from-DB (client name ignored), duplicate linking, invalid-product resilience, filters/search/metrics, status/priority/assign, export, WhatsApp click.
- **HTTP wiring:** `/admin/leads` → 302 to login when unauthenticated; `POST /whatsapp/track` without CSRF → 419; `GET` on a POST-only action → 404; `/contact-us` and `/partnership` → 200.

## 10. New / changed files

**Schema:** `database/migrations/012_lead_generation_extend.sql`; `database/seeds/007_lead_extras.sql`, `008_partnership_page.sql`.
**Domain:** `app/Support/EnquiryType.php`, `app/Support/Csv.php`; `app/Repositories/{LeadRepository,LeadActivityRepository,WhatsappClickRepository}.php`; `app/Services/LeadService.php`, `MailService.php` (sendView + header hardening).
**Controllers:** `app/Controllers/Admin/LeadsController.php`, `DashboardController.php` (metrics); `app/Controllers/Site/WhatsAppController.php`, `ContactController.php` (state/requirement, server-derived source).
**Views:** `app/Views/admin/leads/{index,show}.php`, `admin/dashboard.php`, `admin/layout.php` (CRM nav); `emails/{lead_internal,lead_ack}.php`; `site/sections/contact_form.php` (state/requirement).
**Wiring:** `routes/web.php` (admin `leads.*` group + public `/whatsapp/track`), `bootstrap/app.php` (bindings).
**Tests:** `tests/Unit/{EnquiryTypeTest,CsvTest}.php`.

## 10a. Phase 4.1 — Lead access control hardening (2026-08-29)

Phase 4 granted every `leads.view` holder sight of **all** leads. Phase 4.1 replaces that with a **permission-driven visibility scope enforced in SQL**.

**Permissions (added):** `leads.view_all`, `leads.view_assigned` (alongside existing `leads.view/create/edit/assign/delete/export`). Grants: `sales_manager`, `admin`, `super_admin` → `view_all`; `sales_executive` → `view_assigned`. `leads.view` is now **module access only** — it no longer implies visibility.

**Scope model** (`App\Support\LeadVisibility::scope()`, permission-driven, no role names hardcoded):
- `view_all` → **all** leads.
- `view_assigned` (and neither `view_all`) → only leads where `assigned_user_id = <session user id>`.
- neither → **none** (module opens, but zero leads).
- Resolution verified per role: super_admin/admin/sales_manager → all; sales_executive → assigned; product_manager/content_manager/view-only → none.

**Enforcement is at the data-access layer** (`LeadRepository`), never by filtering in PHP and never trusting a client-supplied user/assignee id:
- A single `scopeSql()` fragment (`1=1` / `assigned_user_id = <int-from-session>` / `1=0`) is injected into `buildFilter()` (so **list, search, filter, pagination, export** all inherit it), into `metrics()` and `recent()` (dashboard), and into `findByIdInScope()` (detail + every mutation). Fails **closed**: no scope → no rows.
- **Detail/IDOR:** `show` and every mutation (`status`/`priority`/`assign`/`note`/`contacted`/`delete`) load via `findByIdInScope()`; an out-of-scope id returns **404**, identical to a non-existent id — no existence leak.
- **Export:** uses the same scope; a user with `leads.export` but no visibility gets **403** and can never export beyond their scope.
- **Dashboard:** lead metrics + recent list are scoped; the unscoped company-wide "leads captured" count was removed (it leaked a total to restricted users) — the only lead figure shown is the scoped `total`.
- **UI reflects permissions but the backend is authoritative:** the Export button and lead figures are hidden outside scope, yet every server action re-checks scope regardless of what the UI shows.

**Testing:** unit `LeadVisibilityTest` (6 cases: view_all/assigned/none resolution, view-all-wins, module-access-≠-visibility, missing-user-id→none) + a 24-check live data-layer matrix (User A view_all, User B assigned-only, no-access, admin) covering list/search/filter/pagination/detail/metrics/export/recent/IDOR. **62 tests green.** No SQL/PHP-filter bypass; no cross-user discovery via search; assigned-only `unassigned` metric correctly 0.

**Files:** new `app/Support/LeadVisibility.php`, `tests/Unit/LeadVisibilityTest.php`; changed `LeadRepository` (scopeSql/findByIdInScope/findRow, scoped buildFilter/metrics/recent), `LeadsController` (scope resolver + findVisibleOr404 on detail & all mutations, scoped export), `DashboardController` + `admin/dashboard.php` (scoped metrics/recent, removed unscoped count), `admin/leads/index.php` (no-visibility notice, scoped export button), `database/seeds/001_rbac.sql` (2 permissions + grants).

## 11. Owner setup before production

1. In `/admin/settings` set the **Notification Email** (or `lead_sales_email`, or `MAIL_SALES_INBOX` in `.env`) and complete the **SMTP** block — otherwise notifications are skipped (leads are still captured).
2. Decide whether to enable the **auto-reply** acknowledgement and edit its (generic) subject/message.
3. Replace the **partnership** page placeholder copy with real content.
4. Optionally enable **Turnstile** for the public forms.
