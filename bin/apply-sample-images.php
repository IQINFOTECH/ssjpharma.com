<?php

declare(strict_types=1);

/**
 * Place the bundled sample illustrations on the About / Quality / Distributor
 * page heroes. SAFE BY DEFAULT and idempotent — it only fills a hero image that
 * is currently EMPTY, and never overwrites an image the owner has already set
 * (e.g. a real photo uploaded via Admin -> Media).
 *
 *   php bin/apply-sample-images.php            # DRY RUN — shows what would change
 *   php bin/apply-sample-images.php --confirm  # apply
 *
 * The illustrations ship in /public/assets and are referenced by path, so no
 * Media upload is required. Replace any of them later in the CMS.
 */

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("apply-sample-images.php is a CLI script.\n");
}

$confirm = in_array('--confirm', $argv, true);

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Database $db */
$db = $app->container()->get(Database::class);

// slug => [image path, force align to left?]
$targets = [
    'about-us'             => ['/assets/sample-lab.svg',       true],
    'quality'              => ['/assets/sample-quality.svg',   true],
    'become-a-distributor' => ['/assets/sample-warehouse.svg', true],
];

echo "=== Sample images on page heroes ===\n";

$plan = [];
foreach ($targets as $slug => [$path, $forceLeft]) {
    $row = $db->selectOne(
        "SELECT s.id, s.data
           FROM page_sections s
           JOIN pages p ON p.id = s.page_id
          WHERE p.slug = :slug AND p.deleted_at IS NULL AND s.type = 'hero'
          ORDER BY s.sort_order ASC LIMIT 1",
        ['slug' => $slug]
    );
    if ($row === null) {
        echo "  [skip] {$slug}: no hero section found\n";
        continue;
    }
    $data = json_decode((string) $row['data'], true) ?: [];
    $existing = trim((string) ($data['image_id'] ?? ''));
    if ($existing !== '') {
        echo "  [keep] {$slug}: hero already has an image ({$existing}) — left untouched\n";
        continue;
    }
    $data['image_id'] = $path;
    if ($forceLeft && ($data['align'] ?? '') === 'center') {
        $data['align'] = 'left';
    }
    unset($data['size']); // let the image get full-height room
    $plan[] = [(int) $row['id'], $slug, $path, $data];
    echo "  [set ] {$slug}: hero image -> {$path}\n";
}

if ($plan === []) {
    echo "\nNothing to change.\n";
    exit(0);
}

if (!$confirm) {
    echo "\nDRY RUN — nothing changed.\n";
    echo "To apply:\n  php bin/apply-sample-images.php --confirm\n";
    exit(0);
}

echo "\n--confirm supplied — applying...\n";
$db->beginTransaction();
try {
    foreach ($plan as [$id, $slug, $path, $data]) {
        $db->statement(
            "UPDATE page_sections SET data = :d WHERE id = :id",
            ['d' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'id' => $id]
        );
    }
    $db->commit();
    echo "  Updated " . count($plan) . " hero section(s).\n";
    echo "Done. Visit the pages to review.\n";
} catch (\Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "Failed and rolled back (see logs).\n");
    exit(1);
}
exit(0);
