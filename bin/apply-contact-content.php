<?php

declare(strict_types=1);

/**
 * Apply the premium Contact page hero (reference design). SAFE BY DEFAULT —
 * it only touches the contact-us page's HERO section, nothing else.
 *
 *   php bin/apply-contact-content.php            # DRY RUN — shows current vs new
 *   php bin/apply-contact-content.php --confirm  # replace the contact hero data
 *
 * The trust-feature labels are design placeholders the owner may edit in the
 * CMS afterwards (Pages -> Contact Us -> Hero). No pharmaceutical claims.
 */

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("apply-contact-content.php is a CLI script.\n");
}

$confirm = in_array('--confirm', $argv, true);

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Database $db */
$db = $app->container()->get(Database::class);

$page = $db->selectOne("SELECT id FROM pages WHERE slug = 'contact-us' AND deleted_at IS NULL LIMIT 1");
if ($page === null) {
    fwrite(STDERR, "No 'contact-us' page found.\n");
    exit(1);
}
$hero = $db->selectOne(
    "SELECT id, data FROM page_sections WHERE page_id = :p AND type = 'hero' ORDER BY sort_order ASC LIMIT 1",
    ['p' => (int) $page['id']]
);
if ($hero === null) {
    fwrite(STDERR, "The contact-us page has no hero section to update.\n");
    exit(1);
}

$new = [
    'style' => 'premium', 'size' => 'small',
    'heading' => 'Contact', 'heading_highlight' => 'Us',
    'subheading' => 'We are here to help and answer any question you may have. We look forward to hearing from you.',
    // Contact-themed illustration (headset/chat/mail/pin); the owner's uploaded
    // Media image replaces it via the hero Image field.
    'image_id' => '/assets/hero-contact.svg', 'image_alt' => 'Customer support and communication',
    'features' => [
        ['label' => 'Reliable Support'],
        ['label' => 'Expert Guidance'],
        ['label' => 'Long-Term Partnerships'],
        ['label' => 'Pan India Presence'],
    ],
    'align' => 'left',
];

echo "=== Contact page hero ===\n";
echo "  Current: " . mb_substr((string) $hero['data'], 0, 110) . "…\n";
echo "  New:     style=premium size=small heading='Contact Us' + 4 trust features\n";

if (!$confirm) {
    echo "\nDRY RUN — nothing changed.\nTo apply:\n  php bin/apply-contact-content.php --confirm\n";
    exit(0);
}

$db->statement(
    "UPDATE page_sections SET data = :d WHERE id = :id",
    ['d' => json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'id' => (int) $hero['id']]
);
echo "\nContact hero updated. Review at /contact-us.\n";
exit(0);
