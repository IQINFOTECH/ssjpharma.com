# Lead Management

Authoritative reference for the SSJ Pharmaceuticals lead system (Phase 4 foundation + Phase 4.1 access control). Built on the existing custom PHP MVC — no external CRM, automation, or WhatsApp API. See also `PHASE_4.md`, `SECURITY_PLAN.md §16c/§16d`, `AUTHENTICATION_RBAC.md`.

## 1. Lifecycle

```
Visitor → Website / Product page → Enquiry form
  → validate (CSRF + honeypot + rate limit + server rules)
  → SAVE lead (transaction: leads + contact_submissions + "created" activity) → COMMIT
  → attempt SMTP notification (after commit; failure never loses the lead) → log outcome
  → visitor redirected to /thank-you  |  WhatsApp wa.me CTA available
  → Admin lead inbox (/admin/leads) → assign → status → priority → follow-up → notes
```

Capture-first is the core guarantee: a lead is durably committed **before** any email is attempted, so notification/SMTP problems can never discard a genuine enquiry.

## 2. Schema (see `DATABASE.md` for the full column list)

- **`leads`** — identity (name/company/email/phone/whatsapp/country/state/city), business (business_type, enquiry_type, product_id → products, product_name_snapshot, message, requirement), attribution (source_id → lead_sources, landing_page, source_url, referrer, utm_source/medium/campaign/term/content), management (status_id → lead_statuses, priority, assigned_user_id → users, **follow_up_date**, last_contacted_at), consent (consent, consent_at, privacy_version), system (ip, user_agent, is_spam, notification_status, notified_at, created_at, updated_at, deleted_at). Soft-deleted via `deleted_at`.
- **`lead_statuses`** / **`lead_sources`** — configurable lookups (key, name, flags).
- **`lead_activities`** — append-only per-lead timeline (lead_id, user_id, type, description, meta JSON, created_at).
- **`contact_submissions`** — one row per raw submission (repeat enquiries link here to an existing open lead).
- **`whatsapp_clicks`** — CTA click analytics (a click is **not** a lead).

Indexes for scale (designed for 100k+ leads): status, source, created, email, phone, enquiry_type, assigned_user_id, priority, follow_up_date, product_id, deleted_at. All listing/search/export is server-side paginated; per-status metrics use a single grouped query (no N+1).

## 3. Sources

Configurable in `lead_sources`. Technical sources set server-side: `contact_form`, `product_enquiry`, `distributor_enquiry`, `partnership_enquiry`, `website`, `landing_page`, plus attribution rows (`whatsapp`, `website_cta`, `organic_search`, `paid_search`, `social`, `direct`, `referral`, `phone`, `other`). Future channels (linkedin/google/facebook/email) can be added as rows without code changes. **Attribution is only recorded when it actually exists** — UTMs are captured as-is and never fabricated.

## 4. Statuses

`New → Contacted → Qualified → Proposal → Converted → Lost`, plus `Spam`. Configurable and extensible for the future CRM phase; no pipeline automation. Semantic flags `is_won` (Converted) / `is_lost` (Lost) drive metrics and duplicate logic — code keys off the flags, not the label, so statuses can be renamed safely.

## 5. Priority

`Low / Medium / High / Urgent`. Default `Medium` (CMS setting `lead_default_priority`). Never auto-assigned by fabricated business logic; changed manually by users holding `leads.priority`.

## 6. Permissions (RBAC)

`leads.view` (module access only — **grants no visibility on its own**), `leads.view_all`, `leads.view_assigned`, `leads.create`, `leads.edit`, `leads.assign`, `leads.delete`, `leads.export`, `leads.notes`, `leads.status`, `leads.priority`. Enforced by route `can:` middleware **and** re-checked in every controller action.

Default grants (least privilege): super_admin (all, wildcard), admin (all except role editing), sales_manager (view_all + create/edit/assign/delete/export/notes/status/priority), sales_executive (view_assigned + create/edit/notes/status/priority — no assign/delete/export), product_manager / content_manager (no lead permissions).

## 7. Visibility & assignment rules (Phase 4.1)

`LeadVisibility::scope()` resolves a per-user scope from permissions (no role names hardcoded): `view_all` → all leads; `view_assigned` → only `assigned_user_id = <session user id>`; neither → none. Enforced **in SQL** across list, search, filter, pagination, detail, every mutation, export, and dashboard metrics — never PHP/frontend filtering, never trusting a client-supplied user/assignee id. An out-of-scope lead id returns **404** (indistinguishable from a missing id — no existence leak / IDOR).

**Assignment edge case (addressed):** assignment requires `leads.assign`, and the target lead must be within the actor's visibility scope (`findVisibleOr404`). A `view_assigned` user without `leads.assign` cannot reassign at all (the seeded sales_executive). Reassignment cannot be used to view a lead the actor could not already see.

## 8. Notes (internal only)

Stored as `lead_activities` rows of type `note` (lead_id, user_id, description, created_at), gated by `leads.notes`, escaped on render (no stored XSS), never shown publicly. Do not store passwords, credentials, or secrets in notes.

## 9. Activity history

Per-lead timeline types: `created`, `status_changed`, `priority_changed`, `assigned`, `unassigned`, `note`, `contacted`, `followup_updated`, `email_sent`, `email_failed`, `repeat_enquiry`. Admin mutations additionally write the global `audit_log` (LEAD_STATUS_CHANGED, LEAD_UPDATED, LEAD_ASSIGNED, LEAD_NOTE_ADDED, LEAD_FOLLOWUP_UPDATED, LEAD_DELETED, LEADS_EXPORTED). **A wa.me click is recorded as a click only — the system never claims a WhatsApp message was delivered or answered.**

## 10. Email flow (queued since Phase 5 — see PHASE_5.md)

Direct SMTP via PHPMailer (`MailService`), credentials from `.env` only. On lead creation the internal notification (and optional acknowledgement) is **rendered from a CMS template and enqueued** (`email_queue`) after commit; the cron worker (`bin/process-email-queue.php`) performs delivery with exponential-backoff retry. Recipient precedence: setting `lead_notification_email` → `lead_sales_email` → `MAIL_SALES_INBOX`. Templates (`lead_internal_notification`, `lead_customer_acknowledgement`) use safe `{{placeholders}}`; subjects are CR/LF-stripped; HTML values escaped; the enquirer is added only as a validated `Reply-To`; visitors can never specify the recipient. `MAIL_DELIVERY_MODE` (smtp/log/disabled) prevents accidental dev sends.

**Failure handling:** enqueue runs after commit and never throws; the lead's `notification_status` becomes `queued` and the timeline records `email_queued` → later `email_sent`/`email_failed`. A missing template, bad recipient, or SMTP fault can never lose the lead.

**Follow-up digests:** `bin/send-followup-digests.php` queues one daily digest per assignee of their own due open leads (visibility-enforced, idempotent). See PHASE_5.md §6.

## 11. WhatsApp flow

`wa.me` only (no Cloud API). The number comes from CMS settings, is normalised/validated before building the link, and is never hardcoded. CTAs ("Enquire on WhatsApp" on product pages, "Chat on WhatsApp" on contact) generate a prefilled message with only safe contextual info (e.g. the product name) — never sensitive visitor data. Clicks are best-effort tracked via CSRF-protected, rate-limited `POST /whatsapp/track` (product resolved from the page path, so no internal ids in the DOM).

## 12. Duplicate handling

**Decision: link, never discard.** When a submission matches an **open, non-spam** lead by exact email or phone within a 24h window, it is attached to that lead (a new `contact_submissions` row + a `repeat_enquiry` activity + `updated_at` touch) rather than creating a duplicate. Converted/lost/spam leads are excluded, so legitimate later enquiries still create fresh leads. No genuine enquiry is ever silently dropped. (Chosen over destructive merge because the schema already separates `leads` from `contact_submissions`, giving a clean one-to-many history.)

## 13. Spam protection

Layered and non-blocking for legitimate users: CSRF (global), honeypot field, per-IP rate limiting (filesystem window + DB backstop), server-side validation + size limits, and optional Cloudflare Turnstile (only enforced when configured). Spam is **stored-and-flagged** (`is_spam`, `spam` status, notification skipped), never silently discarded.

## 14. Export security

`leads.export` + visibility scope both required (a user with export permission but no visibility gets 403 and can never export beyond their scope). CSV carries only presentation fields (no passwords/sessions/secrets), uses a UTF-8 BOM, and neutralises spreadsheet **formula injection** — any cell starting `= + - @`/tab/CR is quote-prefixed (`App\Support\Csv`, unit-tested). Row-capped.

## 15. Privacy

Only business-contact fields are collected; consent is captured with timestamp + policy version. Public forms carry consent language. No fabricated legal-compliance or privacy-policy claims are made. Before go-live, purge development/test lead records.

## 16. Routes

`GET /admin/leads` (inbox + dashboard), `GET /admin/leads/export`, `GET /admin/leads/{id}`, `POST /admin/leads/{id}/status|priority|assign|notes|contacted|followup`, `DELETE /admin/leads/{id}/delete`; public `POST /contact`, `POST /whatsapp/track`. Each gated by the matching `leads.*` permission.

## 17. Not built (future CRM phase)

No automated follow-up reminders, lead scoring, AI qualification, forecasting, sales sequences, pipeline automation, WhatsApp Cloud API, Telegram, n8n/Zapier/Make, or payments.
