<?php

declare(strict_types=1);

/**
 * Production preflight self-check (Phase 7). READ-ONLY: it inspects configuration
 * and connectivity and prints a READY / WARNING / BLOCKED verdict per item. It
 * NEVER prints secret values, and it NEVER changes data. Run it on the host after
 * uploading the app and creating the production .env:
 *
 *   php bin/preflight.php
 *
 * Exit code: 0 when there are no BLOCKED items, 1 otherwise. WARNING items are
 * advisory (e.g. optional SMTP not yet configured) and do not fail the run.
 */

use App\Core\App;
use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("preflight.php is a CLI script.\n");
}

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$c   = $app->container();
/** @var Config $config */
$config = $c->get(Config::class);

$blocked = 0; $warn = 0;
$line = static function (string $status, string $label, string $detail = '') use (&$blocked, &$warn): void {
    $tag = match ($status) { 'READY' => '[READY]  ', 'WARNING' => '[WARN]   ', default => '[BLOCKED]' };
    if ($status === 'BLOCKED') { $blocked++; } elseif ($status === 'WARNING') { $warn++; }
    echo "  {$tag} {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};
$set = static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== false;

echo "=== SSJ Pharmaceuticals — production preflight ===\n\n";

// --- 1. Application env ------------------------------------------------------
echo "Application\n";
$env   = (string) $config->get('app.env', '');
$debug = (bool) $config->get('app.debug', false);
$url   = (string) $config->get('app.url', '');
$key   = (string) $config->get('app.key', '');
$line($env === 'production' ? 'READY' : 'WARNING', 'APP_ENV', $env === 'production' ? 'production' : "is '{$env}' (expected production on the live host)");
$line(!$debug ? 'READY' : 'BLOCKED', 'APP_DEBUG', $debug ? 'debug is ON — must be false in production' : 'off');
$line(str_starts_with($url, 'https://') ? 'READY' : 'WARNING', 'APP_URL', $url !== '' ? $url : 'not set');
$line(strlen($key) >= 24 ? 'READY' : 'BLOCKED', 'APP_KEY', strlen($key) >= 24 ? 'present' : 'missing/too short (run php bin/keygen.php)');

// --- 2. Session security -----------------------------------------------------
echo "\nSession & security\n";
$secure = (bool) $config->get('security.session.secure', false);
$line(($env === 'production' ? true : $secure) ? 'READY' : 'WARNING', 'Secure cookie', $env === 'production' ? 'forced true in production' : ((string) ($secure ? 'true' : 'false')));

// --- 3. Database -------------------------------------------------------------
echo "\nDatabase\n";
try {
    /** @var Database $db */
    $db = $c->get(Database::class);
    $db->selectOne('SELECT 1 AS ok');
    $line('READY', 'DB connectivity', 'connected'); // no host/user/password printed

    $applied = array_column($db->select('SELECT migration FROM migrations'), 'migration');
    $files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
    $pending = [];
    foreach ($files as $f) {
        $name = basename($f);
        // 000 creates the migrations table itself and is not self-recorded (matches migrate.php).
        if ($name === '000_create_migrations_table.sql') { continue; }
        if (!in_array($name, $applied, true)) { $pending[] = $name; }
    }
    $line($pending === [] ? 'READY' : 'BLOCKED', 'Migrations', $pending === [] ? count($applied) . ' applied, none pending' : count($pending) . ' PENDING (run php bin/migrate.php)');

    foreach (['users', 'leads', 'products', 'email_queue', 'email_templates', 'settings'] as $t) {
        $exists = (int) ($db->selectOne("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:t", ['t' => $t])['c'] ?? 0) > 0;
        if (!$exists) { $line('BLOCKED', "Table {$t}", 'missing'); }
    }

    $admins = (int) ($db->selectOne("SELECT COUNT(*) c FROM users WHERE deleted_at IS NULL AND is_active=1")['c'] ?? 0);
    $line($admins > 0 ? 'READY' : 'BLOCKED', 'Admin account', $admins > 0 ? "{$admins} active user(s)" : 'no active admin (run php bin/create-admin.php)');

    $demo = (int) ($db->selectOne("SELECT COUNT(*) c FROM products WHERE is_demo=1")['c'] ?? 0)
          + (int) ($db->selectOne("SELECT COUNT(*) c FROM product_categories WHERE is_demo=1")['c'] ?? 0)
          + (int) ($db->selectOne("SELECT COUNT(*) c FROM therapeutic_areas WHERE is_demo=1")['c'] ?? 0);
    $realProducts = (int) ($db->selectOne("SELECT COUNT(*) c FROM products WHERE is_demo=0 AND status='published' AND deleted_at IS NULL")['c'] ?? 0);
    $line($demo === 0 ? 'READY' : 'WARNING', 'Demo data', $demo === 0 ? 'none' : "{$demo} demo record(s) present (hidden from public in production; purge when owner confirms)");
    $line($realProducts > 0 ? 'READY' : 'WARNING', 'Published real products', $realProducts > 0 ? (string) $realProducts : 'none yet (owner uploads real content)');
} catch (\Throwable $e) {
    $line('BLOCKED', 'DB connectivity', 'cannot connect — check DB_* in .env');
}

// --- 4. Mail -----------------------------------------------------------------
echo "\nMail\n";
$smtp = (array) $config->get('mail.smtp', []);
$from = (array) $config->get('mail.from', []);
$smtpOk = $set($smtp['host'] ?? '') && $set($smtp['username'] ?? '') && $set($from['address'] ?? '');
$line($smtpOk ? 'READY' : 'WARNING', 'SMTP configured', $smtpOk ? 'host/user/from set' : 'incomplete (leads still capture; notifications skip until set)');
$mode = strtolower((string) $config->get('mail.delivery_mode', ''));
$effectiveMode = in_array($mode, ['smtp', 'log', 'disabled'], true) ? $mode : ($smtpOk ? 'smtp' : 'log');
$line(($env !== 'production' || $effectiveMode === 'smtp') ? 'READY' : 'WARNING', 'MAIL_DELIVERY_MODE', $effectiveMode . ($env === 'production' && $effectiveMode !== 'smtp' ? ' (set smtp for real delivery in production)' : ''));
$recipient = '';
try {
    $s = $c->get(App\Services\SettingsService::class);
    $recipient = $s->get('lead_notification_email') ?: ($s->get('lead_sales_email') ?: (string) $config->get('mail.sales_inbox', ''));
} catch (\Throwable) {}
$line($set($recipient) ? 'READY' : 'WARNING', 'Notification recipient', $set($recipient) ? 'configured' : 'not set (set lead_notification_email or MAIL_SALES_INBOX)');

// --- 5. Filesystem -----------------------------------------------------------
echo "\nFilesystem\n";
foreach (['storage/sessions', 'storage/logs'] as $rel) {
    $p = dirname(__DIR__) . '/' . $rel;
    $ok = is_dir($p) && is_writable($p);
    $line($ok ? 'READY' : 'BLOCKED', "Writable {$rel}", $ok ? 'ok' : 'missing or not writable');
}
$line(is_file(dirname(__DIR__) . '/public/assets/css/app.css') ? 'READY' : 'BLOCKED', 'Compiled CSS present', 'public/assets/css/app.css');

// --- Summary -----------------------------------------------------------------
echo "\n=== Summary: " . ($blocked === 0 ? "GO — no blockers" : "NO-GO — {$blocked} blocker(s)") . ", {$warn} warning(s) ===\n";
if ($blocked === 0 && $env !== 'production') {
    echo "(Note: APP_ENV is not 'production' here — run this again on the live host with the production .env.)\n";
}
exit($blocked === 0 ? 0 : 1);
