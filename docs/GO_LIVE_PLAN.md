# Go-Live Plan

Exact production cutover sequence for SSJ Pharmaceuticals. **No destructive step is automated.** Placeholders `USER`, paths, and credentials are set on the host — never commit real secrets.

Assumes GoDaddy Web Hosting Starter: Apache, PHP 8.3, MySQL/MariaDB, cPanel, cron, SMTP. Docroot should be `/public` (or the secure root-`.htaccess` fallback).

## Sequence

1. **Back up current production** (if anything exists) — full cPanel backup (files + DB) before any change. See `BACKUP_STRATEGY.md`.
2. **Upload the application** — deploy the repo (with committed `vendor/`) via cPanel File Manager / SFTP. Point the domain docroot at `public/`.
3. **Create the production `.env`** (copy `.env.example`, fill real values):
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://ssjpharma.com`
   - `APP_KEY` — generate: `php bin/keygen.php`
   - DB_* (from step 4), SMTP_* + `MAIL_FROM_*` + `MAIL_DELIVERY_MODE=smtp` + `MAIL_SALES_INBOX`
   - `SESSION_SECURE=true`
   - File permission `600`. Confirm it is not web-accessible (visit `/.env` → 403).
4. **Configure the database** — create DB + user in cPanel; grant privileges; put creds in `.env`.
5. **Run migrations** — `php bin/migrate.php` (applies all, tracked/idempotent).
6. **Run seeds** — `php bin/seed.php` (safe base data only: RBAC, settings placeholders, lookups, templates). **No business/pharma data is seeded.**
7. **Create the first admin** — `php bin/create-admin.php "Name" email@domain password` (forces password change on first login).
8. **Configure SMTP** — verify the `.env` SMTP block; from the admin, send a template test (queues to your own email); run the worker; confirm receipt.
9. **Configure cron** (cPanel → Cron Jobs; use the real PHP CLI path, e.g. `/usr/local/bin/php`):
   ```
   */5 * * * * /usr/local/bin/php /home/USER/ssjpharma/bin/process-email-queue.php >> /home/USER/ssjpharma/storage/logs/cron.log 2>&1
   0 8 * * *   /usr/local/bin/php /home/USER/ssjpharma/bin/send-followup-digests.php >> /home/USER/ssjpharma/storage/logs/cron.log 2>&1
   ```
10. **Configure analytics** — Admin → Settings → Analytics: GA4 ID, Google Search Console token, Bing token. WhatsApp number in Settings → WhatsApp.
11. **Upload real content** — owner enters real pages, company info, and products (name, composition, strength, form, pack size, category, therapeutic area, images, documents, SEO). **Nothing is invented; missing fields are left blank.**
12. **Verify real product data** — spot-check published products render correctly; drafts/in-review are not public.
13. **Purge demo data — ONLY after owner confirmation:**
    ```
    php bin/purge-demo-data.php            # review the inventory (dry run)
    php bin/purge-demo-data.php --confirm  # purge, once the owner approves
    ```
14. **Run smoke tests** — home, pages, /products, product detail, /contact-us + each enquiry form (creates a lead), /thank-you, WhatsApp CTA, admin login, /admin/leads, /admin/products, /admin/email-queue.
15. **Enable indexing** — confirm `APP_ENV=production` (demo hidden), sitemap at `/sitemap.xml`, `robots.txt` disallows /admin; submit sitemap in GSC + Bing.
16. **Monitor logs** — `storage/logs/` app log + cron log for the first hours.
17. **Verify enquiries** — submit a test enquiry; confirm it appears in `/admin/leads` with correct source/attribution.
18. **Verify email** — confirm the internal notification is queued and delivered (queue worker), and that a failure would not lose the lead.
19. **Verify WhatsApp + sitemap + analytics** — wa.me link opens with the correct number/prefill; sitemap lists only published items; GA4 realtime shows the visit and a conversion event on a test submit.

## Rollback
If a step fails: restore the step-1 backup (files + DB), set `APP_ENV` back, and investigate from `storage/logs/`. Migrations are additive; a bad deploy is recovered by redeploying the previous release + restoring the DB snapshot.
