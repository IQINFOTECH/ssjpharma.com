# Production Deployment Checklist

Condensed from `GO_LIVE_PLAN.md` + `PHASE_7.md`. Status key:
**READY** = done in the artifact, nothing for the owner to do · **OWNER ACTION** = owner performs on the GoDaddy host (no assistant SSH) · **BLOCKED** = cannot complete until a dependency is met.

Run `php bin/preflight.php` on the host after creating the production `.env` — it should report **GO — no blockers**.

## Host & platform
| # | Item | Status | Note |
|---|---|---|---|
| 1 | GoDaddy PHP 8.3 | OWNER ACTION | cPanel → MultiPHP Manager: set the domain to PHP 8.3 (app targets 8.3; dev ran 8.2). |
| 2 | PHP CLI | OWNER ACTION | Confirm the CLI binary path for cron (e.g. `/usr/local/bin/php` or `ea-php83`). |
| 3 | MySQL/MariaDB | OWNER ACTION | Confirm DB service available on the plan. |
| 4 | Database + user creation | OWNER ACTION | cPanel → MySQL Databases; grant least privilege; put creds in `.env`. |
| 5 | Document root → /public | OWNER ACTION | Point docroot at `/public`; if the plan blocks it, the secure root `.htaccess` fallback is already in place (READY). |
| 6 | SSL / HTTPS | OWNER ACTION | AutoSSL or Cloudflare; enable HTTP→HTTPS. HSTS emits on HTTPS automatically. |

## Environment (.env) — never commit; never print secrets
| # | Item | Status | Note |
|---|---|---|---|
| 7 | Production `.env` created | OWNER ACTION | Copy `.env.example`; file perms `600`; verify `/.env` → 403 on the host. |
| 8 | `APP_ENV=production` | OWNER ACTION | Enables prod-only behavior (demo hidden, etc.). Enforced by code where set. |
| 9 | `APP_DEBUG=false` | READY + OWNER ACTION | Code force-disables debug when `APP_ENV=production`; still set it false explicitly. |
| 10 | `APP_URL=https://ssjpharma.com` | OWNER ACTION | Must equal the live HTTPS origin (asset URLs + CSP `'self'` depend on it). |
| 11 | `SESSION_SECURE=true` | READY + OWNER ACTION | Code forces Secure cookie in production; set it true anyway. |
| 12 | Strong unique `APP_KEY` | OWNER ACTION | Generate with `php bin/keygen.php`; do not reuse the dev key. |

## Email & cron
| # | Item | Status | Note |
|---|---|---|---|
| 13 | SMTP (`SMTP_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION`, `MAIL_FROM_*`) | OWNER ACTION | Real domain SMTP account. Leads still capture if SMTP fails (capture-first). |
| 14 | `MAIL_DELIVERY_MODE=smtp` | OWNER ACTION | Currently `log` in dev; set `smtp` in production for real delivery. |
| 15 | Lead notification email | OWNER ACTION | CMS `lead_notification_email` (or `MAIL_SALES_INBOX`). |
| 16 | Cron — queue worker (every 5 min) | OWNER ACTION | `*/5 * * * * /usr/local/bin/php /home/USER/ssjpharma/bin/process-email-queue.php >> …/storage/logs/cron.log 2>&1` (worker READY). |
| 17 | Cron — follow-up digest (daily 08:00) | OWNER ACTION | `0 8 * * * /usr/local/bin/php /home/USER/ssjpharma/bin/send-followup-digests.php >> …/storage/logs/cron.log 2>&1` (worker READY). |

## Accounts & content
| # | Item | Status | Note |
|---|---|---|---|
| 18 | Admin account review | OWNER ACTION | `php bin/create-admin.php "Name" email pass` (forces password change); verify RBAC, no shared creds. |
| 19 | Real product content | OWNER ACTION → gates indexing | Owner supplies real data (name/composition/strength/form/pack/category/TA/images/docs/SEO) via draft→in_review→approved→published. Nothing invented; missing fields blank. |
| 20 | Demo-data backup + purge | READY (tooling) / OWNER ACTION | `php bin/purge-demo-data.php` (dry-run inventory: 1 product, 2 categories, 1 TA, 1 image, 3 leads retained). **Back up first**, then run `--confirm` **only after explicit owner confirmation**. In production demo is already hidden from public + sitemap. |

## SEO & analytics
| # | Item | Status | Note |
|---|---|---|---|
| 21 | Sitemap | READY | `/sitemap.xml` — published-only; demo excluded in production (runtime-proven). |
| 22 | robots.txt | READY | Disallows `/admin`; references the sitemap. |
| 23 | GA4 | OWNER ACTION | Set `analytics_ga_id` in CMS; loads only when set (CSP-clean). |
| 24 | Google Search Console | OWNER ACTION | Set `analytics_gsc_verification` in CMS; submit sitemap **after** real content is live. |
| 25 | Bing Webmaster | OWNER ACTION | Set `analytics_bing_verification` in CMS; submit sitemap after real content is live. |

## Post-deploy verification (on the host)
| # | Item | Status | Note |
|---|---|---|---|
| 26 | End-to-end lead test | OWNER ACTION | Submit a controlled test enquiry → appears in `/admin/leads` with correct source/attribution; no unexpected duplicate. (Chain verified in dev — READY.) |
| 27 | Email delivery test | OWNER ACTION | After SMTP set: queue → run worker → confirm sender/recipient/subject/HTML/text/links; failure must not lose the lead. Send only to the owner/admin address. |
| 28 | WhatsApp wa.me test | READY + OWNER ACTION | Set the WhatsApp number in CMS; confirm link opens with correct number/message/product context; click tracked ≠ delivery. No API. |
| 29 | Production security smoke test | READY (code) + OWNER ACTION | On Apache confirm: `APP_DEBUG=false`, Secure cookies, HTTPS, CSRF, RBAC/IDOR, security headers/CSP/HSTS, and that `/.env` `/.git` `/storage` `/database/*.sql` are **not** web-accessible (403). |
| 30 | Backup & rollback | READY (documented) / OWNER ACTION | Take + verify a full backup (DB + media + `.env`) before migrating/purging; rollback = restore backup + redeploy prior release (`BACKUP_STRATEGY.md`, `GO_LIVE_PLAN.md`). |

## Sequence
Follow the 19-step order in **`GO_LIVE_PLAN.md`**. Do **not** enable search-engine indexing until real approved content is live, and do **not** run the demo purge until the owner explicitly confirms.
