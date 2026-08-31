<?php

declare(strict_types=1);

/**
 * Idempotent seed runner. Executes database/seeds/*.sql in filename order.
 * Seeds use INSERT IGNORE / ON DUPLICATE KEY so re-running is safe.
 *
 * Usage:  php bin/seed.php
 */

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("seed.php is a CLI script.\n");
}

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Database $db */
$db = $app->container()->get(Database::class);

try {
    $db->pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "✗ Cannot connect to the database. Check .env DB_* settings.\n  {$e->getMessage()}\n");
    exit(1);
}

$dir = dirname(__DIR__) . '/database/seeds';
$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_STRING);

if ($files === []) {
    echo "No seed files found.\n";
    exit(0);
}

foreach ($files as $file) {
    try {
        $db->pdo()->exec((string) file_get_contents($file));
        echo "  ✓ Seeded " . basename($file) . "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "  ✗ Failed " . basename($file) . ": {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Seeding complete.\n";
exit(0);
