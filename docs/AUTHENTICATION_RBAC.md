# Authentication, RBAC, User Management & Audit — Reference

**Phase:** 2 (implemented & verified 2026-08-29)
**Builds on:** Phase 0 core (Auth/Rbac/Session/middleware) + Phase 1 minimal login. Architecture unchanged.

---

## 1. Authentication flow

```
POST /admin/login
  → per-IP rate limit (RateLimiter, 20/10min)
  → AuthService::attempt(email, password)
       → ThrottleService: block if ≥5 failed (email+ip) in 15 min
       → UserRepository::findActiveByEmail  (constant-time hash check even if absent)
       → password_verify; rehash-on-login if algo/cost changed
       → explicit lockout check (users.locked_until)
       → Auth::login(): session_regenerate_id (fixation defence) + store identity
       → UserSessionRepository::touch(new session id)
       → audit LOGIN_SUCCESS ; login_attempts success + clear failures
  → forced-password gate (must_change) → /admin/password if required
  → /admin
```

Failed attempts record `login_attempts` + audit `LOGIN_FAILED` (email masked). Logout revokes the session in the registry and audits `LOGOUT`.

## 2. Session model

- Native PHP file sessions, stored outside the webroot (`storage/sessions`), `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS, idle timeout (`SESSION_LIFETIME`).
- Session id **regenerated on login and on any privilege/identity change** (`Auth::login`).
- **Registry** (`user_sessions`): each active session is tracked by the **SHA-256 of its id** (never the raw id). `TrackSession` middleware, on every `/admin` request, updates `last_activity_at` and logs the user out if the session was revoked.
- Revocation: `/admin/sessions` (own sessions for anyone; all sessions with `users.view`; revoke others requires `users.edit`). Password reset/change and deactivation revoke a user's sessions.

## 3. Roles (database-driven)

| key | name | scope |
|---|---|---|
| `super_admin` | Super Admin | wildcard — all permissions (code-level) |
| `admin` | Admin | everything except `roles.create/edit/delete` |
| `sales_manager` | Sales Manager | dashboard + all leads + reports |
| `sales_executive` | Sales Executive | dashboard + leads view/create/edit |
| `product_manager` | Product Manager | dashboard + products + media view/upload + reports.view |
| `content_manager` | Content Manager | dashboard + pages + menus + media + redirects.view |

Roles/permissions/matrix live in the DB and are editable at `/admin/roles`. The matrix seed (`database/seeds/001_rbac.sql`) is the default, **not** hardcoded authorization.

## 4. Permissions (42, grouped)

`dashboard.view` · `users.{view,create,edit,delete,activate}` · `roles.{view,create,edit,delete}` · `pages.{view,create,edit,publish,delete}` · `menus.{view,create,edit,delete}` · `media.{view,upload,delete}` · `redirects.{view,create,edit,delete}` · `settings.{view,edit}` · `leads.{view,create,edit,assign,delete,export}` · `products.{view,create,edit,publish,delete}` · `reports.{view,export}` · `audit.view`.

(`leads.*`/`products.*`/`reports.*` are defined now for the coming CRM/Product phases; no such modules are built yet.)

## 5. Authorization architecture (defence in depth)

```
Route middleware:  auth → track_session → must_change → can:<permission>
Controller:        requirePermission('<permission>')   ← re-check
Service:           integrity rules (super-admin protection, anti-escalation)
```

`Rbac::can()` is deny-by-default; `super_admin` short-circuits to allow. Authorization is **only** server-side — hidden nav links are cosmetic. Verified: a `content_manager` receives 403 (GET and POST) on users/roles/settings/audit routes.

### Protections enforced
- **Super Admin:** cannot deactivate/delete the last active Super Admin, nor remove its role. Only a Super Admin can grant or remove the `super_admin` role (anti-escalation — an `admin` minting a super_admin has the role stripped).
- **Self:** cannot deactivate/delete your own account; cannot change your own roles via the users screen.
- **IDOR:** user/role/section/session objects are re-checked for scope/ownership; revoking another user's session requires `users.edit`.
- **Mass assignment:** explicit field allowlists; `is_active`/roles/permissions set only via dedicated, guarded methods.

## 6. Password policy & reset

- Policy (`PasswordPolicy`): ≥10 chars, not a single repeated char, not all-numeric, not equal to the email local part. No arbitrary complexity theatre. Passwords are `password_hash()` only; never logged.
- **Reset:** `/admin/forgot-password` → identical response regardless of account existence (no enumeration). A cryptographically-random 32-byte token is generated; **only its SHA-256 hash is stored** (`password_resets`), 60-minute expiry, single-use, prior tokens invalidated. On completion the password is updated, the token marked used, and **all of the user's sessions are revoked**. Audited: `PASSWORD_RESET_REQUESTED`, `PASSWORD_RESET_COMPLETED`.
- **Email:** via the existing SMTP `MailService` (creds from `.env`, never hardcoded). If SMTP is unconfigured on a **local dev** box only, the reset link is logged to the app log for convenience — never in production, and the raw token is never logged in production.

## 7. Login throttling

DB-backed (`login_attempts`), no Redis. After **5** failed attempts for an `(email, ip)` pair within **15 minutes**, further attempts are blocked with a friendly "try again in ~15 minutes" message until the window rolls off. A success clears the counter. A separate per-IP filesystem limit (20/10 min) guards against email-spraying. Users are never permanently locked. Thresholds: `config/security.php` → `throttle`.

## 8. Audit logging

`audit_log` is **append-only** (no update/delete endpoints; viewer at `/admin/audit-logs` is read-only, gated by `audit.view`). Each record: actor `user_id`, `event`, `entity_type`/`entity_id`, `ip`, `user_agent`, `meta` (JSON), `created_at`. `AuditService` **redacts** any meta key matching `pass|secret|token|api_key|authorization|hash|otp` and never stores passwords, reset tokens, SMTP passwords, API secrets or auth tokens.

Events wired: `LOGIN_SUCCESS`, `LOGIN_FAILED`, `LOGOUT`, `PASSWORD_CHANGED`, `PASSWORD_RESET_REQUESTED`, `PASSWORD_RESET_COMPLETED`, `USER_CREATED/UPDATED/ACTIVATED/DEACTIVATED/DELETED`, `ROLE_CHANGED`, `ROLE_CREATED/UPDATED/DELETED`, `PERMISSION_CHANGED`, `SETTINGS_UPDATED`, `PAGE_PUBLISHED/UNPUBLISHED`, `MEDIA_UPLOADED/DELETED`, `SESSION_REVOKED`.

## 9. Key security decisions

- Authorization is data-driven (DB roles/permissions) and enforced in middleware **and** services.
- Tokens/secrets are hashed at rest or kept out of the DB entirely.
- No user enumeration on login or reset; generic error messages.
- Temporary, self-clearing throttling — availability over lock-out.
- Everything GoDaddy-compatible: no Redis/daemons; DB + filesystem only.

## 10. Admin bootstrap (demo/dev)

Create the first Super Admin (dev only): `php bin/create-admin.php "Name" email password` (forces password change at first login). Additional users are created in `/admin/users`. **No business data, product data, or claims are seeded — technical accounts only.**
