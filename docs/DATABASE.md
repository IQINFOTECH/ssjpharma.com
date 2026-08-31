# Database — Implemented Schema Reference

Canonical list of **implemented** tables (source of truth: `database/migrations/*.sql`).
Design rationale lives in `DATABASE_PLAN.md`. Engine: MySQL/MariaDB, InnoDB, `utf8mb4_unicode_ci`.
Conventions: `BIGINT UNSIGNED` PKs, FKs + indexes on all relations/filter columns, `created_at`/`updated_at`, soft-delete (`deleted_at`) on content/CRM tables.

## Migrations
| # | File | Adds |
|---|------|------|
| 000 | create_migrations_table | migration bookkeeping |
| 001 | create_users_and_rbac | users, roles, permissions, role_permissions, user_roles |
| 002 | create_media_table | media |
| 003 | create_settings_table | settings |
| 004 | create_pages_table | pages |
| 005 | create_page_sections_table | page_sections |
| 006 | create_menus_table | menus |
| 007 | create_menu_items_table | menu_items |
| 008 | create_redirects_table | redirects |
| 009 | create_leads_foundation | lead_statuses, lead_sources, leads, contact_submissions |
| 010 | auth_rbac_extend | users +username/+phone/+locked_until, roles +is_active; password_resets, login_attempts, audit_log, user_sessions |
| 011 | create_product_catalog | dosage_forms, product_categories, therapeutic_areas, products, product_therapeutic_areas, product_images, product_documents, product_specifications; leads +product_id |

## Domains

### Auth & RBAC (Phase 1–2)
- **users** (id, name, email·uniq, username·uniq, phone, password_hash, is_active, must_change_password, locked_until, last_login_at/ip, timestamps, deleted_at)
- **roles** (key·uniq, name, description, is_system, is_active) · **permissions** (key·uniq, name, group)
- **role_permissions** (role_id, permission_id) · **user_roles** (user_id, role_id)
- **password_resets** (user_id→users, token_hash·sha256·uniq, expires_at, used_at, ip) — hashed, single-use
- **login_attempts** (email, ip, success, created_at) — throttling
- **audit_log** (user_id→users, event, entity_type, entity_id, ip, user_agent, meta·JSON, created_at) — append-only
- **user_sessions** (session_id·sha256·uniq, user_id→users, ip, user_agent, last_activity_at, revoked_at)

### CMS (Phase 1)
- **settings** (key·uniq, value, type, group, label, sort_order, updated_by)
- **media** (disk_path, url_path, original_name, mime, extension, size_bytes, width, height, alt_text, title, is_private, uploaded_by, timestamps, deleted_at)
- **pages** (title, slug·uniq, status, template, content, is_home, SEO fields, og/featured_image_id→media, published_at, created_by/updated_by, deleted_at)
- **page_sections** (page_id→pages·cascade, type, data·JSON, sort_order, is_visible)
- **menus** (key·uniq, name) · **menu_items** (menu_id→menus·cascade, parent_id·self, label, page_id→pages, url, is_external, open_new_tab, sort_order, is_active)
- **redirects** (from_path·uniq, to_url, code, is_active, hits, created_by)

### Leads (Phase 1, extended Phase 3 & 4)
- **lead_statuses**, **lead_sources** (lookup: key·uniq, name, flags) — Phase 4 seeds add the `spam` status and attribution sources (distributor/partnership/whatsapp/website_cta/organic/paid/social/direct)
- **leads** (reference·uniq, name/company/email/phone/whatsapp/country/**state**/city/business_type, **enquiry_type** (general·product·distributor·partnership·other, default general), **product_id→products·SET NULL**, **product_name_snapshot** (fetched from DB, never client), message, **requirement**, preferred_contact, priority (low·**medium**·high·urgent, default medium), consent + **consent_at** + **privacy_version**, source_id→lead_sources, status_id→lead_statuses, **assigned_user_id→users·SET NULL**, **last_contacted_at**, **follow_up_date** (DATE), landing_page/**source_url**/referrer/UTMs, ip, user_agent, is_spam, notified_at, **notification_status** (pending·sent·failed·skipped), deleted_at) — indexes on phone/enquiry_type/assigned_user_id/priority/**follow_up_date**. Statuses: new·contacted·qualified·proposal·**converted**·lost·spam.
- **contact_submissions** (lead_id→leads, form_key, payload·JSON, ip, user_agent, is_spam) — one row per raw submission (repeat enquiries link to an existing open lead)
- **lead_activities** (Phase 4) — append-only per-lead timeline: lead_id·cascade, user_id→users·SET NULL (NULL = public visitor/system), type (created·status_changed·priority_changed·assigned·unassigned·note·email_sent·email_failed·repeat_enquiry), description, meta·JSON (never secrets), created_at; index (lead_id,id)
- **whatsapp_clicks** (Phase 4) — CTA click analytics (a click is NOT a lead): context, page, product_id→products·SET NULL, UTMs, ip, user_agent, created_at

### Communications (Phase 5)
- **email_queue** — outbound queue: lead_id→leads·SET NULL, template_key, recipient_email/name, reply_to_email/name, subject, body_html/body_text, status (pending·processing·sent·failed·cancelled), attempts, max_attempts, available_at, locked_by/locked_at (atomic claim), sent_at, last_attempt_at, last_error (sanitised, NEVER secrets), created/updated_at; indexes (status,available_at)/created/lead/locked
- **email_templates** — CMS templates: key·uniq, name, subject, body_html, body_text, is_active, updated_by→users·SET NULL (rendered with safe {{placeholders}} at enqueue)
- **whatsapp_templates** — CMS wa.me message templates: key·uniq, name, message, is_active, updated_by→users·SET NULL
- **communication_digests** — follow-up digest idempotency: user_id→users·CASCADE, digest_date, lead_count, status, **UNIQUE(user_id,digest_date)** (one digest per assignee per day)

### Product catalog (Phase 3)
- **dosage_forms** (name, slug·uniq, is_active, sort_order)
- **product_categories** (parent_id·self·SET NULL, name, slug·uniq, description, image_id→media, SEO, status, sort_order, is_demo, deleted_at)
- **therapeutic_areas** (name, slug·uniq, description, image_id→media, SEO, status, sort_order, is_demo, deleted_at)
- **products** (name, code, slug·uniq, short/full description, status, is_featured, is_demo, sort_order, generic_name, composition, strength, dosage_form_id→dosage_forms·SET NULL, pack_size, category_id→product_categories·SET NULL, hero_image_id/og_image_id→media, SEO, created_by/updated_by, published_at, deleted_at) — index on name/code/status/featured/category/dosage
- **product_therapeutic_areas** (product_id·cascade, therapeutic_area_id·cascade) — M:N
- **product_images** (product_id·cascade, media_id·cascade, alt_text, is_primary, sort_order)
- **product_documents** (product_id·cascade, media_id·cascade, display_name, doc_type, uploaded_by, sort_order)
- **product_specifications** (product_id·cascade, title, value, unit, sort_order)

## Seeds (idempotent, `database/seeds/*.sql`)
RBAC (6 roles + 53 permissions + matrix; lead perms: view/view_all/view_assigned/create/edit/assign/delete/export/notes/status/priority; product perms incl. Phase 6 review/archive; communications perms: view/retry/manage_templates/send_test → admin + super_admin only). Phase 6: `products.updated_at` index; `is_demo` records hidden from public queries + sitemap in production., settings (safe placeholders), lead lookups, starter CMS pages/sections (placeholder copy), menus, dosage-form options, Phase 4 lead extras (spam status, attribution sources, auto-reply settings OFF by default, privacy-policy version), partnership enquiry page. **No product/business data is seeded** — demo records are created on demand with `is_demo = 1`.

## Runners
`php bin/migrate.php` · `php bin/seed.php` · `php bin/create-admin.php "Name" email password`
