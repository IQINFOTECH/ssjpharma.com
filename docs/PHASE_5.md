# Phase 5 — Communications & Follow-up Operations

**Status:** Implemented & verified (2026-08-29).
**Builds on:** Phases 0–4 + 4.1 (lead system, RBAC, visibility scoping, SMTP/PHPMailer, settings, audit). Architecture unchanged (ADR-001). No CRM pipeline, scoring, forecasting, automation, or WhatsApp API.
**GoDaddy-compatible:** PHP 8.3 CLI + MySQL/MariaDB + Apache + cPanel cron + direct SMTP. No Node.js/Redis/supervisor/Docker/daemon.

---

## 1. Outbound email queue

`email_queue` (migration 014) decouples lead capture from delivery. Columns: lead_id (→leads SET NULL), template_key, recipient_email/name, reply_to_email/name, subject, body_html, body_text, status (`pending|processing|sent|failed|cancelled`), attempts, max_attempts, available_at, locked_by, locked_at, sent_at, last_attempt_at, last_error (sanitised), created/updated_at. Indexes: (status, available_at), created_at, lead_id, locked_by. **No secrets are ever stored** in the queue.

## 2. Capture-first flow (unchanged guarantee)

`LeadService::create()` still saves the lead in a transaction and commits **before** any email work. After commit, `notify()` now **enqueues** (renders a CMS template into a queue row) instead of sending inline. A missing template / bad recipient / mail fault can never lose the lead — `notify()` is wrapped and best-effort. The lead's `notification_status` becomes `queued`; the timeline records `email_queued`, and later `email_sent`/`email_failed` from the worker.

```
Lead (committed) → email_queue (render template) → cron worker → SMTP → sent / retry / failed
```

## 3. Queue worker + retry (`bin/process-email-queue.php`)

CLI-only. Reclaims rows stranded in `processing` >15 min, then **atomically claims** due rows with a unique worker token (`UPDATE … SET status='processing', locked_by=token … LIMIT n`, then reads back its own rows). This is safe on MariaDB/MySQL without `SKIP LOCKED`, so **two overlapping cron runs never send a message twice**. Each claim increments `attempts`. Outcomes:
- **ok** → `sent` (+ `email_sent` activity on the lead).
- **temporary failure** → back to `pending` with exponential backoff `available_at` (60s, 5m, 15m, 1h, 3h) until `max_attempts` (default 5) → then `failed`.
- **permanent failure** (invalid recipient address) → `failed` immediately (no infinite retry).
Errors are sanitised and length-capped; credentials/bodies are never printed.

## 4. Delivery mode (safe dev)

`MAIL_DELIVERY_MODE` (`.env`): `smtp` | `log` | `disabled`. Blank = auto (`smtp` when SMTP configured, else `log`). `log`/`disabled` never send real email (dev/staging safe). SMTP credentials stay in `.env` only. The current mode is shown on the Email Queue admin page.

## 5. CMS templates + safe rendering

`email_templates` and `whatsapp_templates` (migration 014) are CMS-managed (key, name, subject/body_html/body_text or message, is_active, updated_by). `TemplateRenderer` performs **pure `{{placeholder}}` substitution** — never `eval`/`include`, so templates can't run PHP/JS/SQL/shell. In HTML bodies every value is HTML-escaped; subjects have CR/LF stripped (header-injection defence); unknown placeholders are dropped. Placeholders: `{{lead.*}}`, `{{site.*}}`, and for the digest `{{assignee.name}}`, `{{followups.count}}`, `{{followups.rows}}` (a pre-escaped raw block), `{{followups.rows_text}}`. Templates render **at enqueue time** into the queue row, so a later template edit/deletion can't break in-flight mail.

Default templates: `lead_internal_notification`, `lead_customer_acknowledgement`, `followup_due_digest` (email); `general_enquiry`, `product_enquiry`, `distributor_enquiry` (WhatsApp).

## 6. Follow-up reminders (`bin/send-followup-digests.php`)

CLI-only daily digest. For each active assignee with due follow-ups it sends **one digest** containing only that assignee's **own** open leads with `follow_up_date <= today` (excludes Converted/Lost/Spam) — visibility enforced by the query (`assigned_user_id = recipient`), so no cross-user leakage; disabled/deleted users are excluded. Idempotent via `communication_digests` UNIQUE(user_id, digest_date) — a second run the same day claims nothing. Digests are **queued** (not sent inline); the worker delivers them. Gated by the `lead_followup_digest_enabled` setting.

## 7. Admin follow-up view

`/admin/leads` gains follow-up **quick filters** (Overdue / Due today / Next 7 days / No follow-up, with scoped counts) and a `followup` filter, all honouring the Phase 4.1 visibility scope. The metric panel adds `due_today`, `overdue`, `upcoming`.

## 8. Email-queue admin (`/admin/email-queue`)

Monitor by status (with counts + delivery-mode banner), view a message (subject + **sandboxed-iframe** HTML preview, attempts, errors), **retry** a failed/cancelled message (fresh attempts) and **cancel** a pending/failed one. Recipients are never editable. Gated by `communications.view` / `communications.retry`.

## 9. Template admin (`/admin/communications/templates`)

Edit email + WhatsApp templates, **preview** with clearly-labelled DEMO values in a sandboxed iframe (never sends), and **send a test** — which queues to the acting admin's **own** account email only (no arbitrary recipient). WhatsApp editing shows the generated `wa.me` preview link. Gated by `communications.manage_templates` / `communications.send_test`.

## 10. Permissions

New: `communications.view`, `communications.retry`, `communications.manage_templates`, `communications.send_test`. Granted to `admin` (via the "everything except role-editing" grant) and `super_admin` (wildcard) only — **not** to sales roles (least privilege). Follow-up features continue to respect `leads.view_all` / `leads.view_assigned`.

## 11. WhatsApp

`wa.me` only — see **ADR-005**. CMS templates build pre-filled links with safe context; a click is tracked (`whatsapp_clicks`) but never reported as a delivered/answered message. No Cloud API.

## 12. Security

RBAC on every admin action + CLI-only guards on both workers; recipients immutable in admin; template rendering can't execute code; HTML escaped; subject CR/LF stripped; sandboxed previews; queue/worker safe under concurrent cron; digests respect visibility (no IDOR/leak); parameterised SQL (single-placeholder search pattern preserved); errors sanitised (no credentials). Audit events: `COMM_EMAIL_RETRIED`, `COMM_EMAIL_CANCELLED`, `COMM_TEMPLATE_UPDATED`, `COMM_TEST_SENT`.

## 13. Cron (cPanel) — see also DEPLOYMENT

Use real paths on the host; do not assume this repo's path.

```
# Email queue — every 5 minutes
*/5 * * * * /usr/local/bin/php /home/USER/ssjpharma/bin/process-email-queue.php >> /home/USER/ssjpharma/storage/logs/cron.log 2>&1

# Follow-up digests — once daily at 08:00
0 8 * * * /usr/local/bin/php /home/USER/ssjpharma/bin/send-followup-digests.php >> /home/USER/ssjpharma/storage/logs/cron.log 2>&1
```

Both workers are safe if a run overlaps a previous one (atomic claim + DB idempotency).

## 14. Migrations & seeds

Migration `014_communications.sql` (email_queue, email_templates, whatsapp_templates, communication_digests). Migration `015_status_converted.sql` (one-time legacy `won`→`converted` reconciliation). Seed `010_communications.sql` (digest setting + default templates). Seed `001` adds the four `communications.*` permissions.

## 15. Verification

- **74 tests green** (+`TemplateRendererTest`: escaping, no code execution, header-injection, raw-key control, flatten).
- **Live integration (27 checks):** enqueue+render+HTML-escaping, delivery mode, worker claim/deliver (log), permanent-fail on invalid recipient, concurrency (disjoint claims), backoff (retried row not re-claimed), digest visibility (excludes converted + other users' leads) + idempotency, follow-up filters.
- **Real CLI run:** digest job queued a digest → worker delivered it in `log` mode (no real email); second digest run skipped (idempotent).
- **HTTP:** all communications routes 302→login unauth; POST without CSRF → 419.

## 16. Not built (future phases)

CRM pipeline/stages, lead scoring, forecasting, automated sequences/drip, marketing automation, WhatsApp Cloud API, Telegram, n8n/Zapier/Make, payments.
