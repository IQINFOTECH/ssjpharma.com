# SSJ Pharmaceuticals LLP — Database Plan

**Companion to:** ARCHITECTURE.md · DEVELOPMENT_PLAN.md · SECURITY_PLAN.md
**Engine:** MySQL / MariaDB · `utf8mb4` / `utf8mb4_unicode_ci` · InnoDB
**Migrations:** plain SQL files in `database/migrations/NNN_name.sql`, applied by `bin/migrate.php`
**Last updated:** 2026-08-29

---

## 1. Conventions

- All tables `InnoDB`, `utf8mb4`, `utf8mb4_unicode_ci`.
- PK `id BIGINT UNSIGNED AUTO_INCREMENT`.
- Timestamps `created_at`, `updated_at` (`TIMESTAMP`, default/`ON UPDATE` where useful); soft delete via `deleted_at NULL` on content/CRM tables.
- FKs with explicit `ON DELETE` behaviour; index every FK and every column used in filters/sorts.
- Booleans as `TINYINT(1)`. Enumerable states as small lookup tables (statuses, sources, roles) rather than hardcoded ENUMs, so they're CMS-editable.
- No secrets stored in plaintext (password hashes, hashed tokens only).
- Slugs unique per scope; SEO fields live on the entity that owns the URL.

---

## 2. Schema domains

1. **Auth & RBAC** — users, roles, permissions, joins, resets, attempts, audit.
2. **CMS** — settings, pages, sections, menus, menu_items, media, redirects.
3. **Catalog** — categories, products, product_media.
4. **CRM** — leads, statuses, sources, activities, notes, status_history, assignments, contact_submissions.
5. **Comms** — email_queue, email_log, whatsapp_log.
6. **System** — migrations meta, rate_limits.

---

## 3. Auth & RBAC

```sql
-- users
id, name, email (UNIQUE), password_hash, is_active TINYINT DEFAULT 1,
must_change_password TINYINT DEFAULT 0, last_login_at NULL, last_login_ip,
created_at, updated_at, deleted_at NULL

-- roles            (seed: super_admin, content_editor, sales_agent, sales_manager)
id, key (UNIQUE), name, description, is_system TINYINT DEFAULT 0, created_at, updated_at

-- permissions      (e.g. cms.manage, catalog.manage, leads.view.own, leads.view.all,
--                   leads.assign, users.manage, settings.manage, reports.view)
id, key (UNIQUE), name, group, description

-- user_roles       (M:N)  user_id FK→users, role_id FK→roles, PK(user_id, role_id)
-- role_permissions (M:N)  role_id FK→roles, permission_id FK→permissions, PK(role_id, permission_id)

-- password_resets  id, user_id FK, token_hash, expires_at, used_at NULL, created_at
-- login_attempts   id, identifier, ip, success TINYINT, created_at   (index identifier, ip, created_at)
-- audit_log        id, user_id NULL, action, entity_type, entity_id NULL,
--                  meta JSON NULL, ip, created_at   (index user_id, entity_type+entity_id, created_at)
```

Ownership/scope checks (SECURITY_PLAN §7) rely on `leads.owner_id` + `lead_assignments`.

---

## 4. CMS

```sql
-- settings  (key/value global config: site_name, logo_media_id, contact_email, contact_phone,
--            whatsapp_number, address, social_*, ga4_id, gtm_id, smtp_* NON-secret flags, etc.)
id, `key` (UNIQUE), `value` TEXT NULL, type ENUM('string','text','json','bool','media'),
updated_by NULL FK→users, updated_at
-- NOTE: real secrets (SMTP pass, WhatsApp token) live in .env, NOT here.

-- pages
id, slug (UNIQUE), title, template, status ENUM('draft','published') DEFAULT 'draft',
meta_title, meta_description, canonical_url NULL, og_image_media_id NULL FK→media,
json_ld JSON NULL, published_at NULL, created_by FK→users, updated_by NULL FK→users,
created_at, updated_at, deleted_at NULL

-- page_sections   (ordered typed blocks composing a page)
id, page_id FK→pages ON DELETE CASCADE, type ENUM('hero','richtext','image','feature_grid',
'gallery','cta','stats','contact'), sort_order INT, data JSON, is_visible TINYINT DEFAULT 1,
created_at, updated_at   (index page_id, sort_order)

-- menus       id, `key` (UNIQUE: 'header','footer'), name
-- menu_items  id, menu_id FK ON DELETE CASCADE, parent_id NULL (self FK), label,
--             url NULL, page_id NULL FK→pages, target, sort_order, is_visible TINYINT
--             (index menu_id, parent_id, sort_order)

-- media
id, disk_path, url_path, original_name, mime, size_bytes, width NULL, height NULL,
alt_text NULL, uploaded_by FK→users, created_at   (index mime, created_at)

-- redirects   id, from_path (UNIQUE), to_path, code SMALLINT DEFAULT 301, is_active TINYINT, created_at
```

CMS rich text/`data JSON` is server-sanitised (SECURITY_PLAN §4).

---

## 5. Product catalog

```sql
-- categories  (nestable)
id, parent_id NULL (self FK ON DELETE SET NULL), name, slug (UNIQUE), description TEXT NULL,
meta_title, meta_description, image_media_id NULL FK→media, sort_order INT,
is_active TINYINT DEFAULT 1, created_at, updated_at, deleted_at NULL
(index parent_id, slug, is_active)

-- products
id, category_id FK→categories ON DELETE RESTRICT, name, slug (UNIQUE),
composition VARCHAR, strength VARCHAR NULL, dosage_form VARCHAR NULL, pack_size VARCHAR NULL,
therapeutic_category VARCHAR NULL, short_description TEXT NULL, description LONGTEXT NULL,
spec_sheet_media_id NULL FK→media, hero_media_id NULL FK→media,
meta_title, meta_description, canonical_url NULL, json_ld JSON NULL,
is_active TINYINT DEFAULT 1, is_featured TINYINT DEFAULT 0, sort_order INT,
created_by FK→users, updated_by NULL FK→users, created_at, updated_at, deleted_at NULL
(index category_id, slug, is_active, is_featured; FULLTEXT(name, composition, short_description))

-- product_media   (gallery, M:N-ish ordered)
id, product_id FK ON DELETE CASCADE, media_id FK→media, sort_order INT, is_primary TINYINT
(index product_id, sort_order)
```

Public detail URL: `/products/{category.slug}/{product.slug}`.

---

## 6. CRM

```sql
-- lead_statuses   (seed: new, contacted, qualified, proposal, won, lost)
id, `key` (UNIQUE), name, color, sort_order, is_won TINYINT, is_lost TINYINT, is_active TINYINT

-- lead_sources    (seed: contact_form, product_enquiry, quote_request, phone, referral, other)
id, `key` (UNIQUE), name, is_active TINYINT

-- leads
id, reference (UNIQUE, e.g. SSJ-000123), name, email NULL, phone NULL, company NULL,
message TEXT NULL, source_id FK→lead_sources, status_id FK→lead_statuses,
product_id NULL FK→products ON DELETE SET NULL, owner_id NULL FK→users ON DELETE SET NULL,
next_follow_up_at NULL, is_verified TINYINT DEFAULT 0,
ip NULL, user_agent NULL, utm JSON NULL,
created_at, updated_at, deleted_at NULL
(index status_id, source_id, owner_id, product_id, next_follow_up_at, created_at)

-- lead_activities  (timeline: created, status_change, note, email_sent, whatsapp_sent, call, assignment)
id, lead_id FK ON DELETE CASCADE, user_id NULL FK→users, type, summary, meta JSON NULL, created_at
(index lead_id, created_at)

-- lead_notes       id, lead_id FK ON DELETE CASCADE, user_id FK→users, body TEXT, created_at
-- lead_status_history id, lead_id FK, from_status_id NULL, to_status_id FK, user_id FK, created_at
-- lead_assignments id, lead_id FK, assigned_to FK→users, assigned_by FK→users, created_at
--                  (current owner mirrored on leads.owner_id for fast scope filtering)

-- contact_submissions  (raw record of every public form hit, even spam-suspect; forward-compatible with leads)
id, lead_id NULL FK→leads, form_key, payload JSON, ip, user_agent, is_spam TINYINT DEFAULT 0, created_at
```

**Scope filtering:** Sales Agent queries always add `WHERE owner_id = :me` (or assignment match); Manager/Super Admin unrestricted (SECURITY_PLAN §7).

---

## 7. Comms

```sql
-- email_queue
id, to_email, to_name NULL, subject, body_html LONGTEXT, body_text TEXT NULL,
template NULL, meta JSON NULL, status ENUM('pending','sending','sent','failed') DEFAULT 'pending',
attempts INT DEFAULT 0, last_error TEXT NULL, available_at, sent_at NULL, created_at
(index status, available_at)

-- email_log     id, email_queue_id NULL FK, to_email, subject, status, error NULL,
--               lead_id NULL FK→leads, created_at   (index lead_id, created_at)

-- whatsapp_log  id, to_number, template NULL, body TEXT NULL, direction ENUM('out','in') DEFAULT 'out',
--               status, provider_message_id NULL, error NULL, lead_id NULL FK→leads, created_at
--               (index lead_id, created_at)
```

Cron (`bin/cron.php`) flushes `email_queue`, sends reminder digests, regenerates sitemap.

---

## 8. System

```sql
-- migrations   id, migration (UNIQUE filename), batch INT, applied_at
-- rate_limits  id, `key` (ip+form or user+action), hits INT, window_start, expires_at
--              (index key, expires_at)
```

---

## 9. Entity relationships (summary)

```
users ─< user_roles >─ roles ─< role_permissions >─ permissions
users ─< audit_log
pages ─< page_sections
menus ─< menu_items >─ (pages)
media ─< (pages.og_image, products.*, product_media, categories.image, settings.media)
categories ─< products ─< product_media >─ media
categories ─< categories        (self, nestable)
lead_sources ─< leads >─ lead_statuses
products ─< leads                (product enquiry)
users ─< leads                   (owner)  + lead_assignments
leads ─< lead_activities / lead_notes / lead_status_history / lead_assignments
leads ─< email_log / whatsapp_log ; contact_submissions >─ leads
```

---

## 10. Indexing & performance

- FK columns + all list-filter columns indexed (status, source, owner, dates, slugs, is_active).
- `FULLTEXT` on products for catalog search; `LIKE 'term%'` fallback where FULLTEXT unavailable.
- Slugs unique-indexed; `reference` unique on leads.
- Avoid N+1: repositories batch-load related media/status/source for list views.
- Keep `JSON` columns for flexible/rarely-queried data (section data, utm, meta), not for filtered fields.

---

## 11. Seed data (Phase 2–4)

- **Roles:** super_admin, content_editor, sales_agent, sales_manager.
- **Permissions:** cms.manage, catalog.manage, media.manage, leads.view.own, leads.view.all, leads.manage, leads.assign, users.manage, roles.manage, settings.manage, reports.view.
- **Role→permission map** per ARCHITECTURE §8.2.
- **First Super Admin** (email/password from `.env`, `must_change_password=1`).
- **lead_statuses / lead_sources** default rows.
- **Menus:** header + footer with starter items.
- **Sample category + product** for catalog verification.

---

## 12. Backup & retention

- Scheduled `mysqldump` (cron) to an off-webroot, access-restricted path; rotated retention.
- Soft-deleted rows purged on a documented schedule; CRM PII deletion path for compliance (SECURITY_PLAN §16).
- Restore procedure tested before launch (Phase 6 exit test).

---

## 13. Schema decisions (resolved — ADR-001, 2026-08-29)

1. **Language — English only at launch.** **No translation tables** and no `locale` columns now (avoids unnecessary complexity per ADR-001). Future-proofing kept lightweight: all user-facing copy lives in data rows (pages/sections/products/settings), never hardcoded in logic, so a future `*_translations` table or `locale` column can be added additively without restructuring. Do **not** add i18n scaffolding in early phases.
2. **WhatsApp — `wa.me` click-to-chat only.** No Cloud API at launch, so `whatsapp_log` is **created but idle** initially; `settings` holds `whatsapp_number` and `whatsapp_default_message` (CMS-editable). Table + service exist so Cloud-API sends can begin later with zero schema change.
3. **Quote/enquiry extras** — deferred to Phase 4; captured flexibly via `leads.utm`/a `meta JSON` field rather than new columns, unless sales specifies fixed fields.

## 15. Phase 1 — implemented schema (2026-08-29)

The following tables are **built and migrated** (see `database/migrations/001–009`):
`users`, `roles`, `permissions`, `role_permissions`, `user_roles` (minimal RBAC — Phase 2 extends), `media`, `settings`, `pages`, `page_sections`, `menus`, `menu_items`, `redirects`, `lead_statuses`, `lead_sources`, `leads`, `contact_submissions`. Conventions match §1–§7 (InnoDB, utf8mb4, FKs, indexes, timestamps, soft-delete on content/CRM tables). Idempotent seeds populate roles/permissions, default settings (safe placeholders), lead lookups, starter pages/sections (placeholder copy), and header/mobile/footer menus. Full CRM columns beyond capture (assignments, activities, notes UI) remain deferred to the CRM phase. This section is the canonical DB reference (a.k.a. "DATABASE").

## 14. Deployment note (ADR-001)

`vendor/` (production libs, e.g. PHPMailer) is committed and uploaded via cPanel File Manager/SFTP — **the server never runs Composer**. No credentials live in the schema or repo; SMTP/app secrets are supplied through `.env` at deployment. Backups (`mysqldump`) are written outside the webroot (SECURITY_PLAN §13–14).
