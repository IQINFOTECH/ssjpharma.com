# Production Readiness Checklist

Status legend: **READY** (done in code/config) · **OWNER ACTION** (owner must do on the host) · **BLOCKED** (waiting on a dependency).

Target: GoDaddy Web Hosting **Starter** — Apache, PHP 8.3, MySQL/MariaDB, cPanel, cron, SMTP. No Node/Redis/Docker/daemon.

## Application
| Item | Status | Notes |
|---|---|---|
| Custom PHP MVC boots with/without vendor/ | READY | ADR-001 fallback autoloader |
| `APP_DEBUG` forced off in production | READY | `config/app.php`, `bootstrap/app.php` — even if `.env` sets true |
| 404 / 403 / 500 error pages, no technical detail in prod | READY | `app/Views/errors/*`, gated by debug |
| Real production `.env` created (not the dev one) | OWNER ACTION | `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE=true`, strong `APP_KEY` (`php bin/keygen.php`) |

## Database
| Item | Status | Notes |
|---|---|---|
| Migrations run cleanly (016 total) | READY | `php bin/migrate.php` |
| Idempotent seeds | READY | `php bin/seed.php` (safe base data only) |
| Indexes for catalog/leads/email_queue/digests | READY | incl. Phase 6 `products.updated_at` |
| No N+1; server-side pagination everywhere | READY | audited |
| Production DB + user created | OWNER ACTION | cPanel → MySQL Databases |

## Security
| Item | Status | Notes |
|---|---|---|
| CSRF global, XSS-escaped output, parameterised SQL | READY | audited |
| Strict CSP, X-Frame-Options, nosniff, Referrer-Policy | READY | `SecurityHeaders` |
| HSTS on HTTPS | READY | gated to secure requests |
| Secure session cookie forced in production | READY | `config/security.php` |
| RBAC + IDOR + visibility scoping | READY | leads/products/communications |
| Upload validation, no-PHP-exec in /uploads, SVG sanitised | READY | `MediaService`, `.htaccess` |
| `.env` / logs / config / db dumps not web-servable | READY | `.htaccess` (root + per-dir) |
| Admin responses `Cache-Control: no-store` | READY | `AdminController` |
| HTTPS + valid SSL on the domain | OWNER ACTION | cPanel AutoSSL / Cloudflare |

## SEO / AEO / GEO
| Item | Status | Notes |
|---|---|---|
| Titles/desc/canonical/robots/OG/Twitter | READY | one source (`SeoService` + layout) |
| Sitemap = published only; excludes admin/draft/demo(prod) | READY | `SeoController`, demo excluded in prod |
| robots.txt disallows /admin | READY | |
| JSON-LD Organization/WebSite/Breadcrumb/Product, no fabricated fields | READY | `JSON_HEX_TAG` hardened |
| GSC + Bing verification tokens | OWNER ACTION | Admin → Settings → Analytics |
| Owner-approved FAQ/company copy | OWNER ACTION | no invented pharma content |

## Analytics
| Item | Status | Notes |
|---|---|---|
| GA4 loads only when ID set; CSP-clean (external JS) | READY | `app.js` |
| Conversion events (form/whatsapp), no PII | READY | whitelisted markers only |
| GA4 ID + GSC + Bing configured | OWNER ACTION | CMS settings |

## Email / Cron
| Item | Status | Notes |
|---|---|---|
| Capture-first queue + worker + retry | READY | Phase 5 |
| `MAIL_DELIVERY_MODE` guards dev sends | READY | set `smtp` in prod |
| SMTP block filled | OWNER ACTION | `.env` |
| Cron: `process-email-queue.php` (5 min), `send-followup-digests.php` (daily) | OWNER ACTION | cPanel cron, real paths (see GO_LIVE_PLAN) |

## WhatsApp
| Item | Status | Notes |
|---|---|---|
| wa.me only; click tracked ≠ delivery | READY | ADR-005 |
| WhatsApp number set + validated | OWNER ACTION | CMS settings |

## Media / Content
| Item | Status | Notes |
|---|---|---|
| Real product data (owner-supplied) loaded | OWNER ACTION | no invented compositions/claims |
| Demo data inventory + purge tool | READY | `php bin/purge-demo-data.php` (dry-run) |
| Demo data purged | BLOCKED | run `--confirm` only after owner confirms |
| Server-side thumbnails (needs host GD) | OWNER ACTION | confirm `gd` on host; optional |

## Backups / Monitoring
| Item | Status | Notes |
|---|---|---|
| Backup strategy documented | READY | `BACKUP_STRATEGY.md` |
| Backups scheduled (DB + media + config) | OWNER ACTION | cPanel backups / cron |
| Log location + review | OWNER ACTION | `storage/logs/`, cron log |

## DNS / SSL
| Item | Status | Notes |
|---|---|---|
| Domain → host, SSL active | OWNER ACTION | |
| `APP_URL` matches live https origin | OWNER ACTION | required for asset/CSP `'self'` |
