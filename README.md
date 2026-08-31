# SSJ Pharmaceuticals LLP

Corporate website + product catalog + CMS + built-in Lead CRM.
Custom lightweight **PHP 8.3 MVC** for standard **Apache + MySQL** hosting (GoDaddy cPanel). No frameworks, no Node.js runtime, no Docker.

> See [`docs/`](docs) for ARCHITECTURE, DEVELOPMENT_PLAN, SECURITY_PLAN and DATABASE_PLAN.
> Current stage: **Phase 0 — Foundation** (no site content, catalog or CRM yet).

---

## Requirements

- PHP **8.2+** (production: 8.3) with `pdo_mysql`, `mbstring`, `openssl`, `json`
- MySQL / MariaDB
- Apache with `mod_rewrite` (and `mod_headers` recommended)
- Composer — **development only** (see deployment note)

## Local setup

```bash
cp .env.example .env
php bin/keygen.php          # paste the output into APP_KEY=
# edit .env: APP_ENV=local, APP_DEBUG=true, DB_* and (later) SMTP_*
composer install            # optional locally; app also boots without vendor/
php bin/migrate.php         # create all tables
php bin/seed.php            # roles, settings, lead lookups, starter pages + menus
php bin/create-admin.php "Site Admin" you@example.com "AStrongPassphrase"
php -S localhost:8000 -t public   # visit http://localhost:8000/  and  /admin
```

### Frontend build (Tailwind — local only, never on the server)

```bash
npm install
npm run build      # → public/assets/css/app.css (committed; production needs no Node)
# npm run dev      # watch mode while developing
```

The app **boots with or without `vendor/`** — a first-party PSR-4 autoloader is the fallback (ADR-001).

## Running checks

```bash
find app bin bootstrap public config routes -name '*.php' -print0 | xargs -0 -n1 php -l   # syntax
composer test        # PHPUnit (Unit + Smoke)
php bin/migrate.php --status                                                              # migration state
```

## Deployment to GoDaddy cPanel (no SSH required — ADR-001)

Composer is **not** run on the server. Prepare the release locally, then upload.

1. **Build the release locally**
   ```bash
   composer install --no-dev --optimize-autoloader   # production vendor/ (from Phase 5, when PHPMailer is added)
   ```
   Commit/keep the resulting `vendor/` so it ships with the code.
2. **Upload** the whole project (except `.env`, `/storage/*`, `/node_modules`) via **cPanel File Manager or SFTP**.
3. **Document root**
   - *Preferred:* cPanel → point the domain's document root at **`/public`**.
   - *Fallback:* if the root can't be moved, upload so the project root is the web root — the bundled root `.htaccess` routes traffic into `/public` and denies `app/`, `config/`, `database/`, `storage/`, `.env`, logs and backups.
4. **Create the `.env`** on the server (never committed). `chmod 600 .env`. Set `APP_ENV=production`, `APP_DEBUG=false`, real `DB_*` and `SMTP_*`.
5. **Permissions:** make `storage/logs`, `storage/cache`, `storage/sessions`, `public/uploads` writable (typically `755`/`775`).
6. **Database:** create the schema + user in cPanel → MySQL. Run migrations via cPanel Terminal if available (`php bin/migrate.php`), otherwise through a temporary protected route (documented in the Phase 6 runbook).
7. **Cron:** cPanel → Cron Jobs → `php /home/USER/ssjpharma/bin/cron.php` every 5 minutes.
8. **TLS:** enable AutoSSL; the app forces HTTPS + HSTS when served over TLS.

## Directory layout

```
public/       web root (front controller, assets, uploads)
app/          Core (framework), Controllers, Auth, Models, Repositories, Views, Support
config/       app, database, mail, security, logging
database/     migrations, seeds
storage/      logs, cache, sessions        (outside web root; .htaccess-denied)
bin/          migrate.php, cron.php, keygen.php
routes/       web.php
bootstrap/    app.php (wires the container)
tests/        Unit + Smoke (PHPUnit)
docs/         architecture & plans
```

## Security

Secrets live only in `.env`. Prepared statements, output escaping, CSRF, RBAC, hardened sessions and security headers are built into the foundation. See [`docs/SECURITY_PLAN.md`](docs/SECURITY_PLAN.md).
