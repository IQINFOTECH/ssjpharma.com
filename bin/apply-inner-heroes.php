<?php

declare(strict_types=1);

/**
 * Apply the premium banner hero to the inner CMS pages (About, Quality,
 * Distributor, Partnership). SAFE BY DEFAULT — it only touches each page's
 * FIRST hero section, nothing else on the page.
 *
 *   php bin/apply-inner-heroes.php            # DRY RUN — shows what would change
 *   php bin/apply-inner-heroes.php --confirm  # apply
 *
 * Copy below is neutral / owner-approved phrasing, all CMS-editable afterwards.
 * Illustrations are bundled placeholders; a Media upload replaces any of them.
 */

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("apply-inner-heroes.php is a CLI script.\n");
}

$confirm = in_array('--confirm', $argv, true);

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Database $db */
$db = $app->container()->get(Database::class);

$heroes = [
    'about-us' => [
        'style' => 'premium', 'size' => 'small',
        'heading' => 'About', 'heading_highlight' => 'Us',
        'subheading' => 'SSJ Pharmaceuticals LLP is a pharmaceutical manufacturing company focused on delivering high-quality, safe and reliable healthcare products.',
        'image_id' => '/assets/hero-about.svg', 'image_alt' => 'Company and facility',
        'align' => 'left',
    ],
    'quality' => [
        'style' => 'premium', 'size' => 'small',
        'heading' => 'Our', 'heading_highlight' => 'Quality',
        'subheading' => 'How we approach pharmaceutical quality for our business and healthcare partners.',
        'image_id' => '/assets/hero-quality.svg', 'image_alt' => 'Quality assurance',
        'align' => 'left',
    ],
    'become-a-distributor' => [
        'style' => 'premium', 'size' => 'small',
        'heading' => 'Become a', 'heading_highlight' => 'Distributor',
        'subheading' => 'Tell us about your business and market, and our team will be in touch.',
        'image_id' => '/assets/hero-partner.svg', 'image_alt' => 'Partnership and distribution',
        'align' => 'left',
    ],
    'partnership' => [
        'style' => 'premium', 'size' => 'small',
        'heading' => 'Partner', 'heading_highlight' => 'With Us',
        'subheading' => 'Tell us about your organisation and we will be in touch.',
        'image_id' => '/assets/hero-partner.svg', 'image_alt' => 'Partnership and distribution',
        'align' => 'left',
    ],
];

echo "=== Inner-page premium heroes ===\n";
$plan = [];
foreach ($heroes as $slug => $data) {
    $row = $db->selectOne(
        "SELECT s.id FROM page_sections s JOIN pages p ON p.id = s.page_id
          WHERE p.slug = :slug AND p.deleted_at IS NULL AND s.type = 'hero'
          ORDER BY s.sort_order ASC LIMIT 1",
        ['slug' => $slug]
    );
    if ($row === null) {
        echo "  [skip] {$slug}: no hero section found\n";
        continue;
    }
    $plan[] = [(int) $row['id'], $slug, $data];
    echo "  [set ] {$slug}: '{$data['heading']} {$data['heading_highlight']}' + {$data['image_id']}\n";
}

if ($plan === []) {
    echo "\nNothing to change.\n";
    exit(0);
}
if (!$confirm) {
    echo "\nDRY RUN — nothing changed.\nTo apply:\n  php bin/apply-inner-heroes.php --confirm\n";
    exit(0);
}

foreach ($plan as [$id, $slug, $data]) {
    $db->statement(
        "UPDATE page_sections SET data = :d WHERE id = :id",
        ['d' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'id' => $id]
    );
}
echo "\nUpdated " . count($plan) . " hero(s). Review the pages.\n";
exit(0);
