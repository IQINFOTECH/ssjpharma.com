# SSJ Pharmaceuticals LLP — Security Plan

**Companion to:** ARCHITECTURE.md · DEVELOPMENT_PLAN.md · DATABASE_PLAN.md
**Scope:** custom PHP 8.3 MVC on Apache + MySQL (GoDaddy cPanel Starter)
**Principle:** defence in depth — every control assumes the layer in front of it may fail.
**Last updated:** 2026-08-29

---

## 1. Threat model (what we defend against)

Public web app handling business enquiries (PII: names, emails, phones, messages) with a privileged admin/CRM. Primary threats:

- SQL injection, XSS (stored + reflected), CSRF.
- Broken authentication / brute force / session hijacking.
- Broken access control (horizontal + vertical privilege escalation in CRM).
- Malicious file upload (webshell via media library).
- Secrets exposure (config in webroot, committed credentials).
- Spam/abuse of public lead forms.
- Sensitive-data exposure over transport or in logs.
- Supply-chain risk from Composer dependencies.

No cardholder data, no clinical/patient data handled by this app (out of scope by design).

---

## 2. Secrets & configuration

- All secrets (DB, SMTP, WhatsApp token, app key, GA IDs) in **`.env` outside the webroot**; never committed. `.env.example` documents keys only.
- `.env` file permissions `600`; `config/` returns arrays, no secrets inline.
- Application `APP_KEY` (random 32 bytes) for signing tokens/cookies; generated per environment.
- `.gitignore` excludes `.env`, `/storage/*`, `/public/uploads/*`.
- No secrets in logs, error output, or client responses.

---

## 3. Transport & headers

- **Force HTTPS** (301) + **HSTS** (`max-age=63072000; includeSubDomains; preload`) once SSL confirmed.
- Security headers on every response (via middleware + `.htaccess`):
  - `Content-Security-Policy` — default-src 'self'; script/style from self (+ nonce for inline where unavoidable); img/media self + data; connect-src self (+ WhatsApp/GA endpoints only if enabled); frame-ancestors 'none'.
  - `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (disable geolocation/camera/mic), `Cross-Origin-Opener-Policy: same-origin`.
- No mixed content; all assets self-hosted (Tailwind compiled locally, fonts self-hosted).

---

## 4. Input handling & injection

- **SQL:** PDO **prepared statements only**; no string-concatenated queries anywhere. Repositories are the sole DB access path. Identifiers (table/column) never taken from user input.
- **Output:** context-aware escaping in views — `e()` (htmlspecialchars, UTF-8) for HTML, dedicated encoders for attributes/JS/URL contexts. Escape on output, not input.
- **Validation:** server-side validation on every request (type, length, format, allowlist). Client validation is UX only, never trusted.
- **Rich text (CMS):** sanitised server-side with an allowlist HTML sanitizer before storage and re-checked on render; no raw HTML from non-admins.
- **Mass assignment:** explicit field allowlists per form → repository; never bind whole request arrays.

---

## 5. Authentication

- `password_hash()` with `PASSWORD_BCRYPT`/`argon2id`; **rehash-on-login** when cost/algorithm changes.
- Passwords: min length policy, breach-list discouraged reuse (optional), never logged.
- **Login throttling:** `login_attempts` tracked by identifier + IP; progressive delay/lockout after N failures; generic error messages (no user enumeration).
- **Password reset:** single-use, time-limited, hashed token emailed; token invalidated on use; no reset-state leaked in responses.
- First Super Admin seeded with forced password change on first login.
- Constant-time verification (`password_verify`, `hash_equals` for tokens).

---

## 6. Session management

- Native PHP sessions stored **outside webroot** (`storage/sessions/`).
- Cookies: `HttpOnly`, `Secure`, `SameSite=Lax` (Strict for admin), no session id in URLs.
- **Session id regenerated** on login and on privilege change; absolute + idle timeout; server-side invalidation on logout.
- Session fixation prevented; bind session to a coarse fingerprint (user-agent) without breaking legitimate use.

---

## 7. Access control (RBAC)

- Deny-by-default: no route/action is public unless explicitly allowed.
- **Two-layer enforcement:** `Rbac` middleware gates routes; **services re-check** the permission before acting (defence in depth) — never trust the UI having hidden a button.
- **Horizontal checks (CRM):** Sales Agent may only read/modify leads they own or are assigned; every lead-scoped query is filtered by ownership, not just the list view.
- Object references validated against the acting user's scope (prevents IDOR on `/admin/leads/{id}`).
- Sensitive actions (user mgmt, settings, deletes) restricted to Super Admin / Manager and audit-logged.

---

## 8. CSRF

- Synchronizer token in session; `csrf_field()` injected into **every** state-changing form; `Csrf` middleware verifies on all POST/PUT/PATCH/DELETE with `hash_equals`.
- Same-site cookies as a second barrier. JSON/admin AJAX sends token via header.

---

## 9. File uploads (media library)

- Allowlist of MIME types + extensions (images, PDF spec sheets); verify real content type (`finfo`), not just extension.
- Enforce max size; reject on mismatch.
- Store with **generated random filenames**, strip original name; never execute uploaded files.
- Upload dir `public/uploads/` served static only — `.htaccess` there **disables PHP execution** (`RemoveHandler`/`php_flag engine off` / deny `.php`).
- Images re-encoded via GD/Imagich (strips embedded payloads + EXIF); dimension/pixel limits to prevent decompression bombs.
- No user-controlled paths (path traversal prevented); uploads referenced by DB id.

---

## 10. Public form abuse (leads)

- **Honeypot** field + minimum-time-to-submit check.
- Per-IP + per-form **rate limiting** (filesystem/DB counter).
- CSRF + server validation on all public forms.
- Optional lightweight challenge if spam observed (privacy-respecting, self-hosted; no external CAPTCHA required by constraints — reCAPTCHA only if you approve it as an exception).
- **Capture-first**: store the lead before any notify step so abuse of the notifier can't drop legitimate leads; notifications are queued, not inline-blocking.

---

## 11. Email & WhatsApp security

- SMTP over TLS, credentials in `.env`. SPF/DKIM/DMARC configured on the domain for deliverability + anti-spoofing.
- All recipient addresses validated; no header injection (PHPMailer handles, plus validation).
- WhatsApp Cloud API (if enabled): token in `.env`, HTTPS only, server-to-server; never expose token to the client; log sends without logging secrets. Disabled by default.
- Auto-acknowledgement + notification templates avoid reflecting unsanitised user input into HTML mail.

---

## 12. Error handling & logging

- Production: generic error pages; **no stack traces, SQL, or paths** to the client. `display_errors=Off`.
- Detailed errors + security events (logins, resets, permission denials, uploads, admin changes) to **Monolog** in `storage/logs` (outside webroot), with rotation.
- **Audit log** table for admin/CRM actions (who, what, when, from where).
- No PII/secrets written to logs beyond what's necessary; access-restricted log files.

---

## 13. Database security

- Dedicated MySQL user with least privilege on the single app schema (no `DROP`/`GRANT` in normal operation; migrations run separately).
- Prepared statements only (see §4).
- Passwords hashed, reset tokens hashed at rest; consider column-level care for any sensitive notes.
- Regular `mysqldump` backups stored **off the webroot**, access-restricted; restore tested.

---

## 14. Deployment & host hardening (ADR-001)

- **Preferred:** document root = `/public`; all app/config/secrets above it — inherently unreachable.
- **Fallback (no root move):** a **root `.htaccess`** internally rewrites public traffic into `/public/` so the front controller still runs, while **hard-deny `.htaccess` files** in each sensitive dir (`app/`, `config/`, `database/`, `storage/`, `bin/`, `bootstrap/`, `tests/`, `vendor/`) plus a dotfile rule make the following **publicly inaccessible**: application code, config, database migrations/seeds, storage (sessions/cache), **`.env`**, **logs**, and **backups**. Verified in the Phase 0 checklist.
- **No SSH required:** `vendor/` is committed and uploaded via cPanel File Manager/SFTP; the server never executes Composer. Secrets are never committed — supplied via `.env` (perms `600`) at deploy.
- Directory listing off (`Options -Indexes`).
- Disable dangerous PHP where possible; OPcache on; `expose_php=Off`.
- Keep PHP at 8.3 with host security updates; monitor.
- cPanel: strong account password, 2FA if available, limit FTP, use AutoSSL.

---

## 15. Dependency & supply chain

- Minimal, well-known Composer deps (fast-route, phpdotenv, phpmailer, monolog).
- `composer audit` in CI/local before releases; pin versions; review updates.
- No untrusted third-party scripts; nothing loaded from CDNs at runtime.

---

## 16. Privacy & compliance

- Privacy-first cookie consent: no non-essential cookies/analytics until accepted.
- Collect only necessary lead data; document retention; provide a deletion path for CRM records.
- Privacy policy + terms pages (CMS-managed).
- PII access limited by RBAC; audit trail on CRM record access where feasible.

---

## 16a. Phase 1 controls (implemented & verified 2026-08-29)

- CSRF on every public/admin write; verified 419 on a tokenless POST.
- RBAC gate in middleware **and** re-checked in each admin controller; verified `content_editor` → 403 on `/admin/settings`, 200 on permitted modules.
- Upload hardening (MIME/extension/size, random names, SVG scrub, no-exec `.htaccess`); path traversal prevented (files referenced by id).
- Rich text sanitised on save (`HtmlSanitizer`) + escaped on render; verified a `<script>` in section data renders escaped, not executed.
- Redirect loop + open-redirect protection (`RedirectService`), unit-tested.
- Contact form: honeypot + filesystem rate limiter + optional Turnstile + server validation; capture-first (spam stored-and-flagged, never silently dropped).
- Forced password change on first admin login; session id regenerated on login/privilege change.
- Secrets only in `.env`; dynamic CSP widens for GA/Turnstile hosts only when those are enabled.

## 16b. Phase 2 controls (implemented & verified 2026-08-29)

Full detail: **AUTHENTICATION_RBAC.md**. Summary of what closed:

- **AuthN:** session regenerated on login; hardened cookies; constant-time credential check; rehash-on-login; forced first-login password change.
- **Throttling:** DB `login_attempts` (5/(email+ip)/15 min, temporary) + per-IP filesystem limit (20/10 min). No permanent lock-out. Verified.
- **AuthZ (defence in depth):** `auth → track_session → must_change → can:<perm>` middleware **and** `requirePermission()` in controllers **and** integrity rules in services. Deny-by-default; `super_admin` wildcard. Verified 403 (GET+POST) for a limited role on users/roles/settings/audit.
- **Super-Admin protection:** last active Super Admin cannot be deactivated/deleted or lose the role; only a Super Admin may grant/remove `super_admin` (anti-escalation — verified an `admin` cannot mint one).
- **Password reset:** hashed single-use expiring tokens; no user enumeration (identical responses); all sessions revoked on completion; audited. Dev-only link logging, never in production.
- **Audit:** append-only `audit_log`, read-only viewer, sensitive-key redaction; no passwords/tokens/secrets stored.
- **Sessions:** registry with remote revoke; revoked sessions logged out on next request; ids stored hashed. Verified revoke + IDOR block.
- **CSRF:** verified 419 on tokenless admin writes. **IDOR/mass-assignment:** object scope re-checks + explicit field allowlists.

## 16c. Phase 4 controls (implemented & verified 2026-08-29)

Full detail: **PHASE_4.md**. Lead generation / enquiry system:

- **Capture-first integrity:** validate → save lead (transaction) → commit → notify → log. A mail/SMTP failure can never roll back or lose a lead (notification runs post-commit and never throws).
- **Server-side classification:** enquiry type + source derived from the form (`EnquiryType`), never from a client field; a spoofed/unknown `form_key` falls back to `general` (verified). Product `product_id` validated against a **published** product; product name **snapshotted from the DB** (client-supplied name ignored — verified); invalid ids dropped, not fatal.
- **Public form abuse:** CSRF (419 verified) + honeypot + per-IP rate limit (filesystem + DB backstop) + server validation + size caps + optional Turnstile. Spam is **stored-and-flagged** (`is_spam`, `spam` status, notification skipped), never silently dropped.
- **Email:** SMTP secrets from `.env` only; CR/LF stripped from subjects; PHPMailer validates all addresses; enquirer added only as a validated `Reply-To`; templates escape every value and carry no secrets.
- **CSV export:** spreadsheet formula-injection neutralised (`Csv` quote-prefixes cells starting `= + - @`/tab/CR) — unit-tested; row cap; audited (`LEADS_EXPORTED`).
- **AuthZ:** `leads.view/create/edit/assign/delete/export` enforced by route `can:` middleware **and** re-checked in every controller action; assignee validated against lead-capable users; lead data never exposed publicly. All mutations audited + written to an append-only per-lead activity timeline.
- **WhatsApp tracking:** `POST /whatsapp/track` is CSRF-protected + rate-limited; product resolved from the **page path**, so no internal ids appear in the DOM; a click is not a lead; tracking failures are swallowed.
- **SQL:** all lead queries parameterised; search uses a single-placeholder `CONCAT_WS … LIKE` (EMULATE_PREPARES=false safe); LIMIT/OFFSET/INTERVAL int-cast + inlined.

## 16d. Phase 4.1 controls — lead visibility (implemented & verified 2026-08-29)

Full detail: **PHASE_4.md §10a**. Per-user lead visibility, enforced server-side:

- **Permission-driven scope** (`LeadVisibility::scope()`, no role names hardcoded): `leads.view_all` → all; `leads.view_assigned` → only `assigned_user_id = <session user id>`; neither → none. `leads.view` is module access only and grants **no** visibility. Deny-by-default / fail-closed.
- **Data-access enforcement:** a single SQL scope fragment gates list, search, filter, pagination, detail, every mutation, export, and dashboard metrics/recent — never PHP/frontend filtering. The assigned id comes from the session, never from client input (no trusted `user_id`/`assigned_user_id`).
- **IDOR:** detail + all mutations load via `findByIdInScope()`; an out-of-scope id returns **404**, indistinguishable from a missing id (no existence leak). Verified: an assigned-only user cannot open, edit, or discover-by-search another user's lead.
- **Export:** same scope; `leads.export` without visibility → 403; no over-scope data leaves via CSV/filters/search/pagination/counts.
- **Dashboard:** scoped metrics/recent; the unscoped company-wide lead total was removed so restricted users never learn it.
- **Granular action permissions:** status/priority/notes are separately gated (`leads.status`/`leads.priority`/`leads.notes`) at both the route and controller layers, in addition to visibility scope — a user can hold `leads.edit` yet be denied a specific action. Follow-up scheduling is gated by `leads.edit`. All mutations still load through `findVisibleOr404` (scope-checked).
- **Verified:** unit resolution tests + data-layer matrices (view_all / assigned-only / no-access / admin) across list/search/filter/pagination/detail/metrics/export/recent/IDOR + follow-up/converted/medium alignment; role resolution (super_admin/admin/sales_manager=all, sales_executive=assigned, product/content/view-only=none). 66 tests green.

## 16e. Phase 5 controls — communications (implemented & verified 2026-08-29)

Full detail: **PHASE_5.md §12**, **ADR-005**.

- **Template rendering** is pure `{{placeholder}}` substitution (`TemplateRenderer`) — never eval/include, so no PHP/JS/SQL/shell execution; HTML bodies escape every value; subjects strip CR/LF (header injection); unknown placeholders dropped; admin previews render in a **sandboxed iframe**. Unit-tested.
- **Email queue** stores no secrets; `last_error` sanitised + length-capped; recipients are **immutable** in the admin (no recipient manipulation); retry/cancel gated by `communications.retry`.
- **Worker concurrency**: atomic UPDATE-claim with a unique token → a message is never sent twice under overlapping cron; stale `processing` rows self-heal; invalid recipients fail permanently (no infinite retry).
- **Delivery mode** (`MAIL_DELIVERY_MODE`) prevents accidental production sends in dev; SMTP secrets stay in `.env`.
- **Follow-up digests** contain only the recipient's own leads (query-enforced `assigned_user_id = recipient`; excludes converted/lost/spam and disabled users) → no visibility leak; DB-unique idempotency (no duplicate digests); no CC/BCC.
- **Test send** goes only to the acting admin's own email — never an arbitrary recipient.
- **CLI jobs** are CLI-only (reject web), return exit codes, and never print credentials.
- **RBAC**: `communications.*` granted to admin/super_admin only. Audit: COMM_EMAIL_RETRIED/CANCELLED, COMM_TEMPLATE_UPDATED, COMM_TEST_SENT.
- **Verified:** 74 tests + 27-check live integration + real CLI run (log mode, no real mail); routes 302→login unauth, POST 419 without CSRF.

## 16f. Phase 6 controls — launch hardening (2026-08-29)

Full detail: **PHASE_6.md §1, §4, §8**.

- **Production safety net:** `APP_DEBUG` is force-disabled and the session cookie `Secure` flag is forced true whenever `APP_ENV=production` (`config/app.php`, `config/security.php`, `bootstrap/app.php`) — a mis-set `.env` can no longer leak traces or send the admin cookie over plaintext.
- **Content governance:** `is_demo=1` records are excluded from all public queries + the sitemap in production (demo pharma data is never indexable).
- **Product workflow authz:** draft→in_review→approved→published→archived, each transition gated by its own permission (`products.review`/`products.publish`/`products.archive`); content is never auto-published.
- **JSON-LD hardening:** `JSON_HEX_TAG` prevents `</script>` breakout from admin-entered values.
- **Analytics/CSP:** all JS is external (`app.js`) so the strict `script-src 'self'` needs no inline exception; conversion events carry no PII (whitelisted markers only).
- **Caching:** admin responses `Cache-Control: no-store`; static assets versioned (`?v=mtime`) with far-future immutable cache; no caching of lead/admin data.
- Full external audit (SEO/security/performance) recorded in PHASE_6.md; no High findings.

## 17. Security acceptance checklist (per release)

- [ ] All state-changing forms carry CSRF tokens; middleware verifies.
- [ ] No raw SQL string interpolation anywhere (grep clean).
- [ ] All output escaped in the correct context.
- [ ] RBAC negative tests pass (agent can't see others' leads; editor can't reach user mgmt).
- [ ] Uploads: type/size enforced, PHP execution disabled in uploads dir, files re-encoded.
- [ ] Security headers + HSTS present; HTTPS forced.
- [ ] `.env` not in webroot, not in git; permissions correct.
- [ ] Login throttling + reset flow verified; no user enumeration.
- [ ] Error pages leak nothing; logs capture security events.
- [ ] `composer audit` clean; dependencies pinned.
- [ ] Backups run and a test restore succeeded.
