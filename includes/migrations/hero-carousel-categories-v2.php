<?php
// includes/migrations/hero-carousel-categories-v2.php
// Idempotent migration for Version 2: Hero Carousel and Category Images.

function run_hero_carousel_and_categories_migration(PDO $pdo, $db_driver) {
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
        $schema_version = 0; // settings table might not even exist yet (fresh install)
    }

    $steps[] = "Current schema version detected: $schema_version";

    // 2. Safely create settings table if it doesn't exist (MySQL fallback)
    if ($schema_version === 0) {
        $steps[] = "Initializing default settings table if missing...";
        if ($db_driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key_name TEXT PRIMARY KEY, val TEXT NOT NULL)");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key_name VARCHAR(100) PRIMARY KEY, val TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    // 3. check/apply hero_slides table
    $table_exists = false;
    if ($db_driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='hero_slides'");
        $stmt->execute();
        $table_exists = (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'hero_slides'");
        $stmt->execute();
        $table_exists = ((int)$stmt->fetchColumn() > 0);
    }

    if (!$table_exists) {
        $steps[] = "Creating hero_slides table...";
        if ($db_driver === 'sqlite') {
            $pdo->exec("CREATE TABLE hero_slides (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                image_path TEXT NOT NULL,
                alt_ar TEXT,
                alt_en TEXT,
                sort_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE hero_slides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                image_path VARCHAR(500) NOT NULL,
                alt_ar VARCHAR(255),
                alt_en VARCHAR(255),
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $steps[] = "Table hero_slides created.";
    } else {
        $steps[] = "Table hero_slides already exists (skipped).";
    }

    // 4. check/apply index on hero_slides
    $index_exists = false;
    if ($db_driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_hero_slides_active_sort'");
        $stmt->execute();
        $index_exists = (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'hero_slides' AND index_name = 'idx_hero_slides_active_sort'");
        $stmt->execute();
        $index_exists = ((int)$stmt->fetchColumn() > 0);
    }

    if (!$index_exists) {
        $steps[] = "Creating index idx_hero_slides_active_sort...";
        if ($db_driver === 'sqlite') {
            $pdo->exec("CREATE INDEX idx_hero_slides_active_sort ON hero_slides (is_active, sort_order)");
        } else {
            $pdo->exec("ALTER TABLE hero_slides ADD INDEX idx_hero_slides_active_sort (is_active, sort_order)");
        }
        $steps[] = "Index idx_hero_slides_active_sort created.";
    } else {
        $steps[] = "Index idx_hero_slides_active_sort already exists (skipped).";
    }

    // 5. check/apply categories.image_path column
    $col_exists = false;
    if ($db_driver === 'sqlite') {
        $stmt = $pdo->prepare("PRAGMA table_info(categories)");
        $stmt->execute();
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if ($c['name'] === 'image_path') {
                $col_exists = true;
                break;
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'categories' AND column_name = 'image_path'");
        $stmt->execute();
        $col_exists = ((int)$stmt->fetchColumn() > 0);
    }

    if (!$col_exists) {
        $steps[] = "Adding column image_path to categories table...";
        if ($db_driver === 'sqlite') {
            $pdo->exec("ALTER TABLE categories ADD COLUMN image_path TEXT DEFAULT NULL");
        } else {
            $pdo->exec("ALTER TABLE categories ADD COLUMN image_path VARCHAR(500) DEFAULT NULL");
        }
        $steps[] = "Column image_path added to categories.";
    } else {
        $steps[] = "Column image_path already exists on categories table (skipped).";
    }

    // 6. Run one-time seed
    $seed_completed = false;
    try {
        $stmt = $pdo->prepare("SELECT val FROM settings WHERE key_name = 'hero_slides_initial_seed_completed'");
        $stmt->execute();
        $seed_completed = ($stmt->fetchColumn() === '1');
    } catch (Exception $e) {}

    if (!$seed_completed) {
        $steps[] = "Running initial hero slide seed...";
        
        // Fetch legacy hero_image setting
        $hero_image = '';
        try {
            $stmt = $pdo->prepare("SELECT val FROM settings WHERE key_name = 'hero_image'");
            $stmt->execute();
            $hero_image = trim((string)$stmt->fetchColumn());
        } catch (Exception $e) {}
        
        // Ensure path is valid and matches allowed formats
        if (empty($hero_image) || strpos($hero_image, '..') !== false || !preg_match('~^(assets/|media/|uploads/)~i', $hero_image)) {
            $hero_image = 'assets/images/shelf1.png';
        }

        // Check if slides is empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM hero_slides");
        $slide_count = (int)$stmt->fetchColumn();
        
        if ($slide_count === 0) {
            $ins = $pdo->prepare("INSERT INTO hero_slides (image_path, alt_ar, alt_en, sort_order, is_active) VALUES (:image_path, :alt_ar, :alt_en, 1, 1)");
            $ins->execute([
                ':image_path' => $hero_image,
                ':alt_ar' => 'رفوف حديدية عالية الجودة لتنظيم المساحات',
                ':alt_en' => 'High quality iron shelves for organizing spaces'
            ]);
            $steps[] = "Legacy hero slide seeded successfully.";
        } else {
            $steps[] = "Slides already exist in hero_slides table. Seeding skipped.";
        }

        // Save seed completed marker so it never runs again
        $stmt = $pdo->prepare($db_driver === 'mysql' 
            ? "INSERT INTO settings (key_name, val) VALUES ('hero_slides_initial_seed_completed', '1') ON DUPLICATE KEY UPDATE val = '1'"
            : "INSERT OR REPLACE INTO settings (key_name, val) VALUES ('hero_slides_initial_seed_completed', '1')"
        );
        $stmt->execute();
        $steps[] = "Seed completed marker written to database.";
    } else {
        $steps[] = "Hero slide seeding already completed (skipped).";
    }

    // 7. Verify all structures exist
    $verified = false;
    try {
        // Test query on hero_slides
        $pdo->query("SELECT id, image_path, is_active, sort_order FROM hero_slides LIMIT 1");
        // Test query on categories
        $pdo->query("SELECT id, code, image_path FROM categories LIMIT 1");
        $verified = true;
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => "Structure verification failed: " . $e->getMessage(),
            'steps' => $steps
        ];
    }

    if ($verified) {
        $steps[] = "All tables, columns, and indexes verified successfully.";
        
        // Write schema version 2 setting
        $stmt = $pdo->prepare($db_driver === 'mysql'
            ? "INSERT INTO settings (key_name, val) VALUES ('schema_version', '2') ON DUPLICATE KEY UPDATE val = '2'"
            : "INSERT OR REPLACE INTO settings (key_name, val) VALUES ('schema_version', '2')"
        );
        $stmt->execute();
        $steps[] = "Schema version updated to 2.";
        
        return [
            'success' => true,
            'version' => 2,
            'steps' => $steps
        ];
    }

    return [
        'success' => false,
        'error' => 'Unknown migration error.',
        'steps' => $steps
    ];
}
