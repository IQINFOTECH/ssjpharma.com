# Phase 7 — Controlled Production Go-Live & Post-Launch Stabilization

**Status:** Artifact verified & GO for deployment; production execution is **owner-run on GoDaddy** (the assistant has no SSH/host access — ADR-001: commit-vendor, deploy via cPanel File Manager/SFTP). No destructive action taken. **Demo data NOT purged.** No pharmaceutical content invented.

This phase is deployment + verification + stabilization — **not** feature development. See `GO_LIVE_PLAN.md` (19-step sequence + rollback), `PRODUCTION_READINESS.md`, `BACKUP_STRATEGY.md`.

## 1. GO / NO-GO

**Deployable artifact: GO.** Verified locally:
- 16/16 migrations applied, none pending; idempotent seeds.
- 80 automated tests green; full `app/`+`bin/` lint clean.
- Both cron workers run cleanly in log mode (no real mail): `process-email-queue.php`, `send-followup-digests.php`.
- Public routes 200, `/admin` 302→login, `/sitemap.xml` + `/robots.txt` correct (robots disallows /admin).
- **Content governance proven at runtime:** with `APP_ENV=production` the demo product/category/therapeutic-area drop out of the sitemap and public lookups (0 records; demo slug → null), while remaining visible in non-production for review.
- Public site renders with **zero console/CSP errors** on the matching origin; `ssjTrack` present; GA absent when unconfigured.

**Overall go-live: BLOCKED on owner-only host actions** (host has no assistant SSH): create production `.env` + DB, run migrations on the host, configure SMTP/cron/DNS/SSL, upload real product content, and (only on explicit confirmation) purge demo. None of these can or should be done by the assistant.

## 2. Preflight self-check (`bin/preflight.php`)

A **read-only** production diagnostic the owner runs on the host after uploading + creating the production `.env`:

```
php bin/preflight.php
```

It prints READY/WARNING/BLOCKED for: APP_ENV/DEBUG/URL/KEY, Secure cookie, DB connectivity, migration state, required tables, admin account, demo inventory, published-real-product count, SMTP config, delivery mode, notification recipient, writable storage, compiled CSS. **It never prints secrets and never mutates data.** Exit 0 = no blockers. On the live host with a correct production `.env` it should report **GO — no blockers** (locally it reports the expected dev warnings + APP_DEBUG blocker, since the dev `.env` is not a production profile).

## 3. Demo data (safety)

Inventory (dry run — nothing deleted): **1 product, 2 categories, 1 therapeutic area, 1 image; 3 leads retained (product_id → NULL)**. Purge is available via `php bin/purge-demo-data.php --confirm` but **must not run until the owner explicitly confirms**. In production the demo is already hidden from all public surfaces + the sitemap, so it is never indexable even before purge.

## 4. DNS / SSL / canonical (owner action — report only)

Intended canonical is `https://ssjpharma.com` (from the `website_url` CMS setting). Required on the host: valid SSL (cPanel AutoSSL or Cloudflare), HTTP→HTTPS redirect, and `www.ssjpharma.com` → `ssjpharma.com` (or vice-versa, matching the canonical). `APP_URL` in `.env` must equal the live HTTPS origin (asset URLs + CSP `'self'` depend on it). **No DNS change is made by the assistant** — if records must change, the owner does so; report exact records first.

## 5. Post-launch monitoring plan (stabilization period — check daily initially)

| Signal | Where | Watch for |
|---|---|---|
| Uptime / 5xx | host + `storage/logs/` app log | 500s, white screens |
| Enquiries | `/admin/leads` | leads created, correct source/attribution, no unexpected duplicates |
| Email queue | `/admin/email-queue` + cron log | pending draining, failed count, sanitised errors |
| SMTP | cron log | auth/connection failures (queue retries; leads never lost) |
| Cron | `storage/logs/cron.log` | both jobs firing on schedule |
| DB | app log | connection/permission errors |
| Analytics | GA4 Realtime, GSC, Bing | pageviews + conversion events; indexing coverage |
| SEO | GSC coverage, `/sitemap.xml` | only published real content indexed |

## 6. Rollback (do not execute unless required)

Per `GO_LIVE_PLAN.md`: restore the pre-deploy cPanel backup (files + DB), redeploy the previous release, restore `public/uploads/`, revert DNS only if it was changed. Migrations are additive; recovery is redeploy + DB snapshot restore.

## 7. No feature creep

No CRM, sales pipeline, lead scoring, marketing automation, bulk email, drip campaigns, WhatsApp Cloud API, Telegram, or n8n. These remain future work.
