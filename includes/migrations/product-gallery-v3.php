<?php
// includes/migrations/product-gallery-v3.php
// Idempotent migration for Version 3: Product Gallery, Colors, and Models.
// BACKWARD COMPATIBLE: Does not alter or remove any existing columns or tables.

function run_product_gallery_migration(PDO $pdo, $db_driver) {
    $steps = [];

    // 1. Get current schema version
    $schema_version = 1;
    try {
        $stmt = $pdo->prepare("SELECT val FROM settings WHERE key_name = 'schema_version'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            $schema_version = intval($val);
        }
    } catch (Exception $e) {
        $schema_version = 0;
    }

    $steps[] = "Current schema version detected: $schema_version";

    if ($schema_version >= 3) {
        $steps[] = "Schema version is already >= 3. Skipping migration.";
        return ['success' => true, 'version' => $schema_version, 'steps' => $steps];
    }

    // Ensure settings table exists
    if ($schema_version === 0) {
        if ($db_driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key_name TEXT PRIMARY KEY, val TEXT NOT NULL)");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key_name VARCHAR(100) PRIMARY KEY, val TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $steps[] = "Settings table ensured.";
    }

    try {
        $pdo->beginTransaction();

        // ─── TABLE: product_images ───
        $table_exists = false;
        if ($db_driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='product_images'");
            $stmt->execute();
            $table_exists = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'product_images'");
            $stmt->execute();
            $table_exists = ((int)$stmt->fetchColumn() > 0);
        }

        if (!$table_exists) {
            $steps[] = "Creating product_images table...";
            if ($db_driver === 'sqlite') {
                $pdo->exec("CREATE TABLE product_images (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    image_path TEXT NOT NULL,
                    alt_ar TEXT,
                    alt_en TEXT,
                    color_id INTEGER DEFAULT NULL,
                    model_id INTEGER DEFAULT NULL,
                    is_primary INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    sort_order INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pi_product ON product_images (product_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pi_color ON product_images (color_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pi_model ON product_images (model_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pi_primary ON product_images (product_id, is_primary)");
            } else {
                $pdo->exec("CREATE TABLE product_images (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    image_path VARCHAR(500) NOT NULL,
                    alt_ar VARCHAR(255) DEFAULT NULL,
                    alt_en VARCHAR(255) DEFAULT NULL,
                    color_id INT DEFAULT NULL,
                    model_id INT DEFAULT NULL,
                    is_primary TINYINT(1) DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    sort_order INT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pi_product (product_id),
                    INDEX idx_pi_color (color_id),
                    INDEX idx_pi_model (model_id),
                    INDEX idx_pi_primary (product_id, is_primary)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            $steps[] = "product_images table created.";
        } else {
            $steps[] = "product_images table already exists. Skipped.";
        }

        // ─── TABLE: product_colors ───
        $table_exists = false;
        if ($db_driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='product_colors'");
            $stmt->execute();
            $table_exists = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'product_colors'");
            $stmt->execute();
            $table_exists = ((int)$stmt->fetchColumn() > 0);
        }

        if (!$table_exists) {
            $steps[] = "Creating product_colors table...";
            if ($db_driver === 'sqlite') {
                $pdo->exec("CREATE TABLE product_colors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    name_ar TEXT NOT NULL,
                    name_en TEXT NOT NULL,
                    hex_value TEXT DEFAULT NULL,
                    image_path TEXT DEFAULT NULL,
                    is_active INTEGER DEFAULT 1,
                    sort_order INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pc_product ON product_colors (product_id)");
            } else {
                $pdo->exec("CREATE TABLE product_colors (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    name_ar VARCHAR(100) NOT NULL,
                    name_en VARCHAR(100) NOT NULL,
                    hex_value VARCHAR(7) DEFAULT NULL,
                    image_path VARCHAR(500) DEFAULT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    sort_order INT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pc_product (product_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            $steps[] = "product_colors table created.";
        } else {
            $steps[] = "product_colors table already exists. Skipped.";
        }

        // ─── TABLE: product_models ───
        $table_exists = false;
        if ($db_driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='product_models'");
            $stmt->execute();
            $table_exists = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'product_models'");
            $stmt->execute();
            $table_exists = ((int)$stmt->fetchColumn() > 0);
        }

        if (!$table_exists) {
            $steps[] = "Creating product_models table...";
            if ($db_driver === 'sqlite') {
                $pdo->exec("CREATE TABLE product_models (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    name_ar TEXT NOT NULL,
                    name_en TEXT NOT NULL,
                    description_ar TEXT DEFAULT NULL,
                    description_en TEXT DEFAULT NULL,
                    image_path TEXT DEFAULT NULL,
                    is_active INTEGER DEFAULT 1,
                    sort_order INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pm_product ON product_models (product_id)");
            } else {
                $pdo->exec("CREATE TABLE product_models (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    name_ar VARCHAR(100) NOT NULL,
                    name_en VARCHAR(100) NOT NULL,
                    description_ar TEXT DEFAULT NULL,
                    description_en TEXT DEFAULT NULL,
                    image_path VARCHAR(500) DEFAULT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    sort_order INT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pm_product (product_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            $steps[] = "product_models table created.";
        } else {
            $steps[] = "product_models table already exists. Skipped.";
        }

        // ─── BACKFILL: Migrate existing products.image_url into product_images ───
        $steps[] = "Checking existing products for image backfill...";
        $products_stmt = $pdo->query("SELECT * FROM products");
        $all_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
        $backfill_count = 0;

        foreach ($all_products as $prod) {
            if (empty($prod['image_url'])) {
                continue;
            }

            // Check if this product already has a primary image in product_images
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = :pid AND is_primary = 1");
            $check_stmt->execute([':pid' => $prod['id']]);
            $has_primary = (int)$check_stmt->fetchColumn();

            if ($has_primary === 0) {
                // Use existing alt text or product name as fallback safely
                $alt_ar = (!empty($prod['alt_text_ar'])) ? $prod['alt_text_ar'] : (isset($prod['name_ar']) ? $prod['name_ar'] : '');
                $alt_en = (!empty($prod['alt_text_en'])) ? $prod['alt_text_en'] : (isset($prod['name_en']) ? $prod['name_en'] : '');

                $insert_stmt = $pdo->prepare("INSERT INTO product_images 
                    (product_id, image_path, alt_ar, alt_en, is_primary, is_active, sort_order) 
                    VALUES (:pid, :path, :alt_ar, :alt_en, 1, 1, 0)");
                $insert_stmt->execute([
                    ':pid' => $prod['id'],
                    ':path' => $prod['image_url'],
                    ':alt_ar' => $alt_ar,
                    ':alt_en' => $alt_en
                ]);
                $backfill_count++;
            }
        }
        $steps[] = "Backfill complete: $backfill_count products migrated to product_images.";

        // ─── Update schema version to 3 ───
        if ($db_driver === 'sqlite') {
            $pdo->exec("INSERT OR REPLACE INTO settings (key_name, val) VALUES ('schema_version', '3')");
        } else {
            $pdo->exec("INSERT INTO settings (key_name, val) VALUES ('schema_version', '3') ON DUPLICATE KEY UPDATE val = '3'");
        }
        $steps[] = "Schema version updated to 3.";

        $pdo->commit();

        // ─── Verification queries ───
        $steps[] = "--- Verification ---";
        $v1 = $pdo->query("SELECT COUNT(*) FROM product_images")->fetchColumn();
        $steps[] = "product_images records: $v1";
        $v2 = $pdo->query("SELECT COUNT(*) FROM product_colors")->fetchColumn();
        $steps[] = "product_colors records: $v2";
        $v3 = $pdo->query("SELECT COUNT(*) FROM product_models")->fetchColumn();
        $steps[] = "product_models records: $v3";
        $v4 = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $steps[] = "products records (unchanged): $v4";

        // Orphan check
        if ($db_driver !== 'sqlite') {
            $orphan_check = $pdo->query("SELECT COUNT(*) FROM product_images pi LEFT JOIN products p ON pi.product_id = p.id WHERE p.id IS NULL")->fetchColumn();
            $steps[] = "Orphan product_images (no matching product): $orphan_check";
        }

        // Duplicate primary check
        $dup_primary = $pdo->query("SELECT product_id, COUNT(*) as cnt FROM product_images WHERE is_primary = 1 GROUP BY product_id HAVING cnt > 1")->fetchAll(PDO::FETCH_ASSOC);
        $steps[] = "Products with duplicate primaries: " . count($dup_primary);

        return ['success' => true, 'version' => 3, 'steps' => $steps];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage(), 'version' => $schema_version, 'steps' => $steps];
    }
}
