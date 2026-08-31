<?php

declare(strict_types=1);

/**
 * Simple SQL migration runner (ADR-001 — no framework dependency).
 *
 * Applies every database/migrations/*.sql file not yet recorded in the
 * `migrations` table, in filename order, each in its own batch entry.
 * Idempotent: already-applied files are skipped.
 *
 * Usage (locally or via cPanel "Cron"/Terminal if available):
 *     php bin/migrate.php
 *     php bin/migrate.php --status     # list applied / pending, apply nothing
 */

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("migrate.php is a CLI script.\n");
}

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Database $db */
$db = $app->container()->get(Database::class);

$migrationsDir = dirname(__DIR__) . '/database/migrations';
$statusOnly = in_array('--status', $argv, true);

try {
    $db->pdo(); // force connection early with a clear error
} catch (Throwable $e) {
    fwrite(STDERR, "✗ Cannot connect to the database. Check your .env DB_* settings.\n");
    fwrite(STDERR, '  ' . $e->getMessage() . "\n");
    exit(1);
}

// Ensure the bookkeeping table exists (000 migration is safe to re-run).
$bootstrapSql = $migrationsDir . '/000_create_migrations_table.sql';
if (is_file($bootstrapSql)) {
    $db->pdo()->exec((string) file_get_contents($bootstrapSql));
}

$applied = [];
foreach ($db->select('SELECT migration FROM migrations') as $row) {
    $applied[$row['migration']] = true;
}

$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_STRING);

$batch = (int) ($db->selectOne('SELECT COALESCE(MAX(batch),0) AS b FROM migrations')['b'] ?? 0) + 1;
$pending = [];

foreach ($files as $file) {
    $name = basename($file);
    if ($name === '000_create_migrations_table.sql') {
        continue;
    }
    if (!isset($applied[$name])) {
        $pending[] = $file;
    }
}

if ($statusOnly) {
    echo "Applied migrations:\n";
    foreach (array_keys($applied) as $name) {
        echo "  ✓ {$name}\n";
    }
    echo "\nPending migrations:\n";
    if ($pending === []) {
        echo "  (none)\n";
    }
    foreach ($pending as $file) {
        echo "  • " . basename($file) . "\n";
    }
    exit(0);
}

if ($pending === []) {
    echo "Nothing to migrate. Database is up to date.\n";
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    $sql  = (string) file_get_contents($file);

    try {
        $db->pdo()->exec($sql);
        $db->statement(
            'INSERT INTO migrations (migration, batch) VALUES (:m, :b)',
            ['m' => $name, 'b' => $batch],
        );
        echo "  ✓ Migrated {$name}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "  ✗ Failed {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Done. Batch {$batch} applied " . count($pending) . " migration(s).\n";
exit(0);
