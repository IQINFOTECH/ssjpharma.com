# Production Data Checklist — Purge Demo/Test Data Before Go-Live

**Status:** MANDATORY before the site handles real traffic.
**Rule:** No development/demo record may reach production. This project **never** contains real
pharmaceutical claims, compositions, certifications, approvals, or medical information — the owner
supplies all real product content. Everything currently in the database is placeholder/test data.

> Run this checklist on the PRODUCTION database only, after taking a backup. Do **not** run it now.
> Snapshot as of the Phase-3 final-hardening verification (2026-08-29).

---

## 1. How demo/test records are identified

| Signal | Meaning |
|---|---|
| `is_demo = 1` | Programmatic flag on `products`, `product_categories`, `therapeutic_areas`. **Every** catalog record currently carries it. |
| Names beginning "Demo…" / "Child Cat" | Human-readable marker; public product cards/detail show a **"Demo — replace before production"** badge when `is_demo = 1`. |
| Emails ending `@ssjpharma.local` | Test user accounts (non-routable `.local` domain). |
| Settings holding obvious test values | e.g. `whatsapp_number = 919876543210`. |

**Verification query (expect 0 production-looking catalog rows):**
```sql
SELECT
 (SELECT COUNT(*) FROM products            WHERE is_demo=0 AND deleted_at IS NULL) +
 (SELECT COUNT(*) FROM product_categories  WHERE is_demo=0 AND deleted_at IS NULL) +
 (SELECT COUNT(*) FROM therapeutic_areas   WHERE is_demo=0 AND deleted_at IS NULL) AS non_demo_catalog_rows;
```

---

## 2. Demo/test records that exist now (inventory)

- **Products (1):** `#1 "Demo Product Renamed"` (code `DEMO-001`, `is_demo=1`).
- **Product categories (2):** `"Demo Category"`, `"Child Cat"` (`is_demo=1`).
- **Therapeutic areas (1):** `"Demo Area"` (`is_demo=1`).
- **Product images (1):** the uploaded test image (`media #1 pixel.png`, `/uploads/2026/08/…png`) linked via `product_images`.
- **Product documents / specifications:** none currently.
- **Leads (5) + contact_submissions (5):** created by enquiry/contact tests.
- **Redirects (1):** `/products/demo-product-replace-before-production → /products/demo-product-renamed` (from a slug-rename test).
- **Users (2):** `admin@ssjpharma.local` (super_admin, DEV), `sales@ssjpharma.local` (sales_executive, RBAC test).
- **CMS pages (6):** `home, about-us, quality, contact-us, become-a-distributor, thank-you` — real pages, **placeholder copy** to be replaced (do NOT delete the pages; edit their content).
- **Dosage forms (10):** structural picklist (Tablet, Capsule, …) — **keep**; they imply no manufacturing. Deactivate any the owner doesn't use.

---

## 3. Safe removal procedure (run on production, after backup)

**Step 0 — Back up first.**
```bash
mysqldump -u <user> -p ssjpharma > backup_before_purge_$(date +%F).sql
```

**Step 1 — Remove demo catalog content** (FKs cascade to images/documents/specs/TA-links; `product_id` on leads is `SET NULL`):
```sql
DELETE FROM products           WHERE is_demo = 1;
DELETE FROM product_categories WHERE is_demo = 1;
DELETE FROM therapeutic_areas  WHERE is_demo = 1;
```

**Step 2 — Remove orphaned test media files** (delete the DB rows AND the files on disk):
```sql
-- Identify test media no longer referenced by any product/category/area/setting/page:
SELECT id, url_path FROM media
WHERE id NOT IN (SELECT media_id FROM product_images)
  AND id NOT IN (SELECT media_id FROM product_documents)
  AND id NOT IN (SELECT hero_image_id FROM products WHERE hero_image_id IS NOT NULL)
  AND id NOT IN (SELECT og_image_id  FROM products WHERE og_image_id  IS NOT NULL)
  AND id NOT IN (SELECT image_id FROM product_categories WHERE image_id IS NOT NULL)
  AND id NOT IN (SELECT image_id FROM therapeutic_areas  WHERE image_id IS NOT NULL);
-- Then delete each returned row and remove its file under public/uploads/…
```
Then delete the physical files listed by `url_path` from `public/uploads/`.

**Step 3 — Remove test redirects created during development:**
```sql
DELETE FROM redirects WHERE from_path LIKE '/products/demo-%'
   OR to_url LIKE '%demo-%';
```

**Step 4 — Remove test leads (only if the owner has no real leads yet):**
```sql
-- CAUTION: verify there are no genuine enquiries first.
DELETE FROM contact_submissions;   -- FK: lead_id SET NULL; delete submissions first or after as preferred
DELETE FROM leads;
```

**Step 5 — Reset AUTO_INCREMENTs (optional, cosmetic):**
```sql
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE leads    AUTO_INCREMENT = 1;
```

---

## 4. CMS settings to replace (Admin → Settings, or SQL)

Replace every placeholder/test value with the owner's real details:

| Setting | Current (placeholder/test) | Action |
|---|---|---|
| `company_name` | "SSJ Pharmaceuticals LLP" | Confirm exact legal name |
| `website_url` | `https://ssjpharma.com` | Confirm production URL |
| `whatsapp_number` | `919876543210` (**TEST**) | Replace with the real WhatsApp number, or clear to hide the CTA |
| `whatsapp_default_message` | default text | Confirm/adjust |
| `company_email`, `company_phone`, `company_address` | empty | Fill in real details |
| `lead_notification_email`, `lead_sales_email` | empty | Set the real sales inbox (enquiries are logged-only until set) |
| `social_*` | empty | Add real social URLs (optional) |
| `analytics_ga_id` | empty (a `G-TEST12345` may exist from testing) | Set the real GA4 id or clear |
| `turnstile_enabled` / `turnstile_site_key` | off / empty | Enable + configure if using Cloudflare Turnstile (secret in `.env`) |
| `seo_default_title` / `seo_default_description` / `seo_default_og_image` | placeholders | Set real defaults |

Also review the **6 CMS pages** — replace all placeholder section copy with real, owner-approved content. Do **not** publish invented pharmaceutical claims.

---

## 5. Demo users to disable/remove

- **`admin@ssjpharma.local`** (super_admin): a DEV account with a known password. **Before production:** create the owner's real Super Admin via `php bin/create-admin.php "Real Name" real@email "StrongPassphrase"`, log in and verify, then **delete or deactivate** this dev account. Never ship the `.local` admin.
- **`sales@ssjpharma.local`** (sales_executive): test account — **delete or deactivate**.
```sql
-- After a real Super Admin exists and is verified:
UPDATE users SET is_active = 0, deleted_at = NOW() WHERE email LIKE '%@ssjpharma.local';
```
(The last-active-Super-Admin protection prevents locking yourself out — ensure the real admin is active first.)

---

## 6. Demo media to remove

- `media #1` (`pixel.png`, `/uploads/2026/08/…png`) and any other files created during testing. Handled by **Step 2** above (DB row + physical file). Verify `public/uploads/` contains no leftover test files after the purge.

---

## 7. Post-purge verification

Re-run the identification query (§1) — expect `non_demo_catalog_rows = 0` **before** adding real content, and only real records afterwards. Confirm:
- `SELECT COUNT(*) FROM products WHERE is_demo=1;` → `0`
- `/products` shows only real, published products (or an honest empty state).
- `/sitemap.xml` lists only real published URLs.
- No `@ssjpharma.local` users remain active.
- `.env` holds production `DB_*`, real `SMTP_*`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE=true`, and (if used) `TURNSTILE_SECRET`.

---

## 8. Application deployment reminders (not data, but pre-go-live)

- Set document root to `/public` (or use the fallback `.htaccess`); ensure `public/uploads` writable + no-exec `.htaccess` deployed.
- Ship the committed `public/assets/css/app.css` (no Node on the server).
- Run `php bin/migrate.php` (schema) — do **not** run `bin/seed.php` on production unless you want the placeholder settings/pages/dosage-forms as a starting point (it is idempotent and adds no product data).

## 9. Demo-data purge tool (Phase 6)

A safe, self-documenting purge mechanism replaces manual SQL:

```
php bin/purge-demo-data.php            # DRY RUN — prints the is_demo inventory, deletes nothing
php bin/purge-demo-data.php --confirm  # purges is_demo=1 catalog records (ONLY after owner confirmation)
```

Scope: `is_demo=1` in `products`, `product_categories`, `therapeutic_areas`. Product child rows (images/documents/specs/TA-links) cascade; leads referencing a demo product are **kept** with `product_id` set to NULL. Also in production, `APP_ENV=production` already hides `is_demo` records from all public pages + the sitemap, so demo pharma data is never indexable even before the purge. **Do not purge until the owner confirms.**
