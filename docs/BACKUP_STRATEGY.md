# Backup Strategy

Production backups for SSJ Pharmaceuticals on GoDaddy shared hosting (cPanel). **Production secrets are never stored in Git.**

## What to back up

| Asset | What | How |
|---|---|---|
| **Database** | full `mysqldump` of the app schema (products, leads, email_queue, communication_digests, settings, users/RBAC, audit_log, etc.) | cPanel → Backup, or cron `mysqldump` |
| **Uploaded media** | `public/uploads/` (product images/documents) — not in Git | cPanel file backup / cron `tar` |
| **Application code** | the deployed release incl. committed `vendor/` | Git tag/release + cPanel file backup |
| **Environment/config** | `.env` (contains secrets) | store OUTSIDE Git, in the owner's password manager / secure vault; back up encrypted separately |

## Recommended schedule

- **Database:** daily (retain 7 daily + 4 weekly). Enquiries/leads change often.
- **Media:** weekly, or after any bulk product upload.
- **Code:** on every deploy (tag the release).
- **`.env`:** whenever a secret changes; keep the current copy in a secure vault.

## Example cron (host paths are placeholders)

```
# Nightly DB dump (02:15), keep 7 days
15 2 * * * mysqldump -u USER -p'PASS' DBNAME | gzip > /home/USER/backups/db-$(date +\%F).sql.gz && find /home/USER/backups -name 'db-*.sql.gz' -mtime +7 -delete
```
Store credentials in a protected `~/.my.cnf` (chmod 600) rather than inline where possible. Keep `/home/USER/backups` outside the webroot (it already is; the app's own `/backups` and `storage` are `.htaccess`-denied and git-ignored).

## Restore drill

1. Restore code (redeploy the release), restore `.env` from the vault.
2. `gunzip < db-YYYY-MM-DD.sql.gz | mysql -u USER -p DBNAME`.
3. Restore `public/uploads/` from the media backup.
4. Run `php bin/migrate.php` (no-op if already current), smoke-test.

## Notes

- Test a restore periodically — an untested backup is not a backup.
- Off-site copy: download the cPanel backup or push the encrypted dump to separate storage so a host failure doesn't lose everything.
- Do not include `.env` or DB dumps in the Git repository (`.gitignore` already excludes `.env`, `storage/*`, uploads, `backups/`, `*.sql.gz`).
