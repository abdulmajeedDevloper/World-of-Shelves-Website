<?php
// scripts/run_migrations.php
// CLI-only migration runner.

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('403 Forbidden: CLI execution only.');
}

// Load application bootstrap safely
define('ALLOW_SITEMAP_GEN', true);
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/migrations/hero-carousel-categories-v2.php';
require_once dirname(__DIR__) . '/includes/migrations/product-gallery-v3.php';
require_once dirname(__DIR__) . '/includes/migrations/product-cards-v4.php';
require_once dirname(__DIR__) . '/includes/migrations/projects-v5.php';

echo "===========================================\n";
echo "Starting Database Migrations...\n";
echo "===========================================\n\n";

$all_success = true;

// --- Migration V2: Hero Carousel & Categories ---
echo "--- Migration V2: Hero Carousel & Categories ---\n";
try {
    $result = run_hero_carousel_and_categories_migration($pdo, $db_driver);
    if ($result['success']) {
        echo "Status: SUCCESS (Schema Version: " . $result['version'] . ")\n";
        echo implode("\n", $result['steps']) . "\n";
    } else {
        echo "Status: FAILED\n";
        echo "Error: " . $result['error'] . "\n";
        $all_success = false;
    }
} catch (Exception $e) {
    echo "Status: CRITICAL EXCEPTION\n";
    echo "Error: " . $e->getMessage() . "\n";
    $all_success = false;
}

echo "\n";

// --- Migration V3: Product Gallery, Colors & Models ---
echo "--- Migration V3: Product Gallery, Colors & Models ---\n";
try {
    $result = run_product_gallery_migration($pdo, $db_driver);
    if ($result['success']) {
        echo "Status: SUCCESS (Schema Version: " . $result['version'] . ")\n";
        echo implode("\n", $result['steps']) . "\n";
    } else {
        echo "Status: FAILED\n";
        echo "Error: " . $result['error'] . "\n";
        $all_success = false;
    }
} catch (Exception $e) {
    echo "Status: CRITICAL EXCEPTION\n";
    echo "Error: " . $e->getMessage() . "\n";
    $all_success = false;
}

echo "\n";

// --- Migration V4: Product Detail Cards (Suitable For & Why This Product) ---
echo "--- Migration V4: Product Detail Cards ---\n";
try {
    $result = run_product_cards_migration($pdo, $db_driver);
    if ($result['success']) {
        echo "Status: SUCCESS (Schema Version: " . $result['version'] . ")\n";
        echo implode("\n", $result['steps']) . "\n";
    } else {
        echo "Status: FAILED\n";
        echo "Error: " . $result['error'] . "\n";
        $all_success = false;
    }
} catch (Exception $e) {
    echo "Status: CRITICAL EXCEPTION\n";
    echo "Error: " . $e->getMessage() . "\n";
    $all_success = false;
}

echo "\n";

// --- Migration V5: Projects Module Enterprise Upgrade ---
echo "--- Migration V5: Projects Module Enterprise Upgrade ---\n";
try {
    $result = run_projects_v5_migration($pdo, $db_driver);
    if ($result['success']) {
        echo "Status: SUCCESS (Schema Version: " . $result['version'] . ")\n";
        echo implode("\n", $result['steps']) . "\n";
    } else {
        echo "Status: FAILED\n";
        echo "Error: " . $result['error'] . "\n";
        $all_success = false;
    }
} catch (Exception $e) {
    echo "Status: CRITICAL EXCEPTION\n";
    echo "Error: " . $e->getMessage() . "\n";
    $all_success = false;
}

echo "\n===========================================\n";
if ($all_success) {
    echo "All migrations completed successfully.\n";
    exit(0);
} else {
    echo "One or more migrations failed. Review output above.\n";
    exit(1);
}
