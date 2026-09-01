<?php

declare(strict_types=1);

/**
 * Apply the real Home page content (built from SSJ's own company profile). SAFE
 * BY DEFAULT — it only touches the Home page's sections, nothing else.
 *
 *   php bin/apply-home-content.php            # DRY RUN — shows current vs new, changes nothing
 *   php bin/apply-home-content.php --confirm  # replace the Home sections with the scaffold
 *
 * Use this once on the host after deploying: the DB seed never overwrites an
 * existing Home, so this is how the refreshed Home replaces the old placeholder.
 * Re-runnable (it always resets the Home to the scaffold below). No fabricated
 * facts — the copy is the owner's business description.
 */

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("apply-home-content.php is a CLI script.\n");
}

$confirm = in_array('--confirm', $argv, true);

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Database $db */
$db = $app->container()->get(Database::class);

$home = $db->selectOne("SELECT id, title FROM pages WHERE slug = 'home' AND deleted_at IS NULL LIMIT 1");
if ($home === null) {
    fwrite(STDERR, "No published 'home' page found.\n");
    exit(1);
}
$homeId = (int) $home['id'];

// The Home scaffold — hero, capabilities, FAQ, closing CTA.
$sections = [
    [10, 'hero', [
        'style' => 'collage',
        'eyebrow' => 'Pharmaceutical Manufacturing',
        'heading' => 'Quality medicine, manufactured to standard.',
        'subheading' => 'SSJ Pharmaceuticals LLP is a pharmaceutical manufacturing company focused on delivering high-quality, safe and reliable healthcare products for businesses and healthcare partners.',
        'primary_label' => 'Become a distributor', 'primary_url' => '/become-a-distributor',
        'secondary_label' => 'Explore products', 'secondary_url' => '/products',
        'image_id' => null, 'align' => 'left',
    ]],
    [20, 'cards', [
        'heading' => 'Our manufacturing solutions',
        'subheading' => 'Pharmaceutical manufacturing services for businesses and healthcare partners.',
        'cards' => [
            ['title' => 'Contract Manufacturing', 'text' => 'Third-party and contract manufacturing for businesses and healthcare partners.', 'icon' => '', 'url' => '/partnership'],
            ['title' => 'Bulk Drug Formulation', 'text' => 'Bulk drug formulation under defined processes and quality controls.', 'icon' => '', 'url' => '/partnership'],
            ['title' => 'Custom Production', 'text' => 'Customized pharmaceutical production tailored to partner requirements.', 'icon' => '', 'url' => '/partnership'],
        ],
    ]],
    [30, 'faq', [
        'eyebrow' => 'Common questions', 'heading' => 'Answers, up front',
        'items' => [
            ['question' => 'What does SSJ Pharmaceuticals do?', 'answer' => 'SSJ Pharmaceuticals LLP is a pharmaceutical manufacturing company focused on delivering high-quality, safe and reliable healthcare products for businesses and healthcare partners.'],
            ['question' => 'What manufacturing services do you offer?', 'answer' => 'We offer pharmaceutical manufacturing solutions including contract manufacturing, bulk drug formulation, and customized pharmaceutical production.'],
            ['question' => 'How can I partner with SSJ Pharmaceuticals?', 'answer' => 'Share your requirement through our distributor or partnership enquiry form and our team will get back to you.'],
            ['question' => 'How do I get in touch?', 'answer' => 'You can reach us by phone or email, or start a WhatsApp chat using the button on the site.'],
        ],
    ]],
    [40, 'contact_cta', [
        'heading' => 'Discuss your requirement',
        'text' => 'Tell us about your distribution, manufacturing or partnership needs and we will get back to you.',
        'button_label' => 'Send an enquiry', 'button_url' => '/contact-us',
    ]],
];

echo "=== Home page content ===\n";
$current = $db->select("SELECT type, sort_order FROM page_sections WHERE page_id = :p ORDER BY sort_order", ['p' => $homeId]);
echo "  Current sections: " . ($current === [] ? '(none)' : implode(', ', array_map(static fn ($r) => $r['type'], $current))) . "\n";
echo "  New sections:     " . implode(', ', array_map(static fn ($s) => $s[1], $sections)) . "\n";

if (!$confirm) {
    echo "\nDRY RUN — nothing changed.\n";
    echo "To apply (replaces the Home sections above):\n  php bin/apply-home-content.php --confirm\n";
    exit(0);
}

echo "\n--confirm supplied — replacing Home sections...\n";
$db->beginTransaction();
try {
    $db->statement("DELETE FROM page_sections WHERE page_id = :p", ['p' => $homeId]);
    foreach ($sections as [$order, $type, $data]) {
        $db->statement(
            "INSERT INTO page_sections (page_id, type, sort_order, data) VALUES (:p, :t, :o, :d)",
            ['p' => $homeId, 't' => $type, 'o' => $order, 'd' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );
    }
    // Home SEO title/description (only if still empty/placeholder).
    $db->statement(
        "UPDATE pages SET meta_title = :t, meta_description = :d WHERE id = :id AND (meta_description IS NULL OR meta_description = '')",
        [
            't' => 'SSJ Pharmaceuticals LLP — Pharmaceutical Manufacturing',
            'd' => 'SSJ Pharmaceuticals LLP is a pharmaceutical manufacturing company offering contract manufacturing, bulk drug formulation and customized production for businesses and healthcare partners.',
            'id' => $homeId,
        ]
    );
    $db->commit();
    echo "  Home updated with " . count($sections) . " sections.\n";
    echo "Done. Visit the site to review.\n";
} catch (\Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "Failed and rolled back (see logs).\n");
    exit(1);
}
exit(0);
