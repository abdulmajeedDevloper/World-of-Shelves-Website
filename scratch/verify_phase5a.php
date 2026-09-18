<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/product-helper.php';
require_once __DIR__ . '/../includes/portfolio-helper.php';

echo "=== PHASE 5A VERIFICATION STATUS ===\n\n";

// 1. Database Columns check
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'slug'");
    $col = $stmt->fetch();
    echo $col ? "✓ Column 'slug' exists in products table.\n" : "✗ Column 'slug' missing in products table!\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'slug'");
    $col = $stmt->fetch();
    echo $col ? "✓ Column 'slug' exists in categories table.\n" : "✗ Column 'slug' missing in categories table!\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM project_categories LIKE 'slug'");
    $col = $stmt->fetch();
    echo $col ? "✓ Column 'slug' exists in project_categories table.\n" : "✗ Column 'slug' missing in project_categories table!\n";
} catch (PDOException $e) {
    echo "Error querying columns: " . $e->getMessage() . "\n";
}

// 2. Slug backfill verification
try {
    $p_count = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE slug IS NULL OR slug = ''")->fetchColumn();
    echo ($p_count === 0) ? "✓ All products have a non-empty slug.\n" : "✗ There are {$p_count} products without a slug!\n";
    
    $c_count = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE slug IS NULL OR slug = ''")->fetchColumn();
    echo ($c_count === 0) ? "✓ All categories have a non-empty slug.\n" : "✗ There are {$c_count} categories without a slug!\n";
    
    $pc_count = (int)$pdo->query("SELECT COUNT(*) FROM project_categories WHERE slug IS NULL OR slug = ''")->fetchColumn();
    echo ($pc_count === 0) ? "✓ All project categories have a non-empty slug.\n" : "✗ There are {$pc_count} project categories without a slug!\n";
} catch (PDOException $e) {
    echo "Error checking slug empty counts: " . $e->getMessage() . "\n";
}

// 3. Test generate_unique_product_slug helper
try {
    $test_slug1 = generate_unique_product_slug("Heavy Duty Shelf", "", 0, $pdo);
    $test_slug2 = generate_unique_product_slug("Heavy Duty Shelf", "", 99999, $pdo);
    echo "✓ Test slug generation (new product): {$test_slug1}\n";
    echo "✓ Test slug generation (different ID): {$test_slug2}\n";
} catch (Exception $e) {
    echo "✗ Slug generation helper failed: " . $e->getMessage() . "\n";
}

// 4. Test URL generation helper
try {
    $product_dummy = ['id' => 12, 'slug' => 'medium-duty-shelving'];
    $url_ar = get_product_details_url($product_dummy, 'ar');
    $url_en = get_product_details_url($product_dummy, 'en');
    $url_abs = get_product_details_url($product_dummy, 'en', true);
    
    echo "✓ URL (AR): {$url_ar}\n";
    echo "✓ URL (EN): {$url_en}\n";
    echo "✓ URL (ABSOLUTE): {$url_abs}\n";
} catch (Exception $e) {
    echo "✗ URL helper failed: " . $e->getMessage() . "\n";
}

echo "\nVerification script completed successfully.\n";
