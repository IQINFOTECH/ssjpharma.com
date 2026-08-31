<?php

declare(strict_types=1);

/**
 * Demo-data inventory & purge (Phase 6, §23). SAFE BY DEFAULT.
 *
 *   php bin/purge-demo-data.php            # DRY RUN — inventory only, deletes nothing
 *   php bin/purge-demo-data.php --confirm  # actually purge is_demo=1 catalog records
 *
 * Only records explicitly flagged is_demo=1 in the catalog tables are affected
 * (products, product_categories, therapeutic_areas). Product child rows
 * (images/documents/specs/therapeutic-area links) cascade via FK; leads that
 * reference a demo product keep the lead but null its product_id (FK SET NULL).
 * Nothing else is touched. Run the DRY RUN, review, then purge ONLY after the
 * owner confirms. This script never runs without an explicit --confirm flag.
 */

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("purge-demo-data.php is a CLI script.\n");
}

$confirm = in_array('--confirm', $argv, true);

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Database $db */
$db = $app->container()->get(Database::class);

$count = static fn (string $sql): int => (int) ($db->selectOne($sql)['c'] ?? 0);

$demoProducts   = $count("SELECT COUNT(*) c FROM products WHERE is_demo=1");
$demoCategories = $count("SELECT COUNT(*) c FROM product_categories WHERE is_demo=1");
$demoAreas      = $count("SELECT COUNT(*) c FROM therapeutic_areas WHERE is_demo=1");

$depImages = $count("SELECT COUNT(*) c FROM product_images pi JOIN products p ON p.id=pi.product_id WHERE p.is_demo=1");
$depDocs   = $count("SELECT COUNT(*) c FROM product_documents pd JOIN products p ON p.id=pd.product_id WHERE p.is_demo=1");
$depSpecs  = $count("SELECT COUNT(*) c FROM product_specifications ps JOIN products p ON p.id=ps.product_id WHERE p.is_demo=1");
$depPta    = $count("SELECT COUNT(*) c FROM product_therapeutic_areas pta JOIN products p ON p.id=pta.product_id WHERE p.is_demo=1");
$affLeads  = $count("SELECT COUNT(*) c FROM leads l JOIN products p ON p.id=l.product_id WHERE p.is_demo=1");

echo "=== DEMO DATA INVENTORY (is_demo = 1) ===\n";
printf("  products                         : %d\n", $demoProducts);
printf("  product_categories               : %d\n", $demoCategories);
printf("  therapeutic_areas                : %d\n", $demoAreas);
echo "  --- dependent rows (cascade on product delete) ---\n";
printf("  product_images                   : %d\n", $depImages);
printf("  product_documents                : %d\n", $depDocs);
printf("  product_specifications           : %d\n", $depSpecs);
printf("  product_therapeutic_areas        : %d\n", $depPta);
echo "  --- leads referencing a demo product (kept; product_id → NULL) ---\n";
printf("  leads affected                   : %d\n", $affLeads);

$total = $demoProducts + $demoCategories + $demoAreas;
if ($total === 0) {
    echo "\nNo demo records found. Nothing to purge.\n";
    exit(0);
}

if (!$confirm) {
    echo "\nDRY RUN — nothing was deleted.\n";
    echo "Review the inventory above. To purge (ONLY after owner confirmation) re-run:\n";
    echo "  php bin/purge-demo-data.php --confirm\n";
    exit(0);
}

echo "\n--confirm supplied — purging demo records...\n";
$db->beginTransaction();
try {
    // Children cascade; leads.product_id is ON DELETE SET NULL.
    $delP = $db->affectingStatement("DELETE FROM products WHERE is_demo=1");
    $delC = $db->affectingStatement("DELETE FROM product_categories WHERE is_demo=1");
    $delA = $db->affectingStatement("DELETE FROM therapeutic_areas WHERE is_demo=1");
    $db->commit();
    printf("  deleted products=%d categories=%d therapeutic_areas=%d\n", $delP, $delC, $delA);
    echo "Demo data purged.\n";
} catch (\Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "Purge failed and was rolled back (see logs).\n");
    exit(1);
}
exit(0);
