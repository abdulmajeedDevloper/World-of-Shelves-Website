<?php
// includes/migrations/product-cards-v4.php
// Migration Version 4: Product Detail Cards ("Suitable For" & "Why This Product?")
// Idempotent migration that creates product_detail_cards and seeds pre-existing products.

function run_product_cards_migration(PDO $pdo, $db_driver) {
    $steps = [];

    // 1. Check current schema version
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

    if ($schema_version >= 4) {
        $steps[] = "Schema version is already >= 4. Skipping migration.";
        return ['success' => true, 'version' => $schema_version, 'steps' => $steps];
    }

    try {
        $pdo->beginTransaction();

        // ─── TABLE: product_detail_cards ───
        $table_exists = false;
        if ($db_driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='product_detail_cards'");
            $stmt->execute();
            $table_exists = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'product_detail_cards'");
            $stmt->execute();
            $table_exists = ((int)$stmt->fetchColumn() > 0);
        }

        if (!$table_exists) {
            $steps[] = "Creating product_detail_cards table...";
            if ($db_driver === 'sqlite') {
                $pdo->exec("CREATE TABLE product_detail_cards (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    section_type TEXT NOT NULL,
                    title_ar TEXT NOT NULL,
                    title_en TEXT NOT NULL,
                    description_ar TEXT DEFAULT NULL,
                    description_en TEXT DEFAULT NULL,
                    icon_name TEXT NOT NULL DEFAULT 'shield',
                    sort_order INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pdc_product ON product_detail_cards (product_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pdc_composite ON product_detail_cards (product_id, section_type, is_active, sort_order)");
            } else {
                $pdo->exec("CREATE TABLE product_detail_cards (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    section_type VARCHAR(50) NOT NULL,
                    title_ar VARCHAR(255) NOT NULL,
                    title_en VARCHAR(255) NOT NULL,
                    description_ar TEXT DEFAULT NULL,
                    description_en TEXT DEFAULT NULL,
                    icon_name VARCHAR(100) NOT NULL DEFAULT 'shield',
                    sort_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pdc_product (product_id),
                    INDEX idx_pdc_composite (product_id, section_type, is_active, sort_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            $steps[] = "product_detail_cards table created successfully.";
        } else {
            $steps[] = "product_detail_cards table already exists. Skipped.";
        }

        // ─── SEEDING: Seed default cards for existing products ───
        $steps[] = "Seeding default 'Suitable For' and 'Why This Product?' cards for existing products...";
        $products_stmt = $pdo->query("SELECT id FROM products");
        $all_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
        $seeded_cards_count = 0;

        $default_suitable_cards = [
            [
                'title_ar' => 'مناسب للمستودعات',
                'title_en' => 'Suitable for Warehouses',
                'desc_ar'  => 'مثالي لتنظيم وتخزين البضائع والمعدات في المستودعات والمخازن.',
                'desc_en'  => 'Ideal for organizing and storing goods and equipment in warehouses.',
                'icon'     => 'warehouse',
                'sort'     => 10
            ],
            [
                'title_ar' => 'مناسب للمحلات والمتاجر',
                'title_en' => 'Suitable for Shops & Stores',
                'desc_ar'  => 'تصميم أنيق وجذاب لعرض المنتجات في المحلات التجارية والمتاجر.',
                'desc_en'  => 'Elegant design for displaying products in commercial shops and retail stores.',
                'icon'     => 'store',
                'sort'     => 20
            ],
            [
                'title_ar' => 'مناسب لغرف التخزين',
                'title_en' => 'Suitable for Storages',
                'desc_ar'  => 'استغلال أمثل للمساحات الضيقة وغرف الأرشيف والتخزين.',
                'desc_en'  => 'Optimal space utilization for storage rooms and archives.',
                'icon'     => 'archive',
                'sort'     => 30
            ],
            [
                'title_ar' => 'مناسب للاستخدام المنزلي',
                'title_en' => 'Suitable for Homes',
                'desc_ar'  => 'مناسب لترتيب المستلزمات والأغراض المنزلية في الكراج والحديقة.',
                'desc_en'  => 'Perfect for arranging household items in garages and utility rooms.',
                'icon'     => 'home',
                'sort'     => 40
            ]
        ];

        $default_why_cards = [
            [
                'title_ar' => 'حماية وأمان عالي',
                'title_en' => 'High Safety & Protection',
                'desc_ar'  => 'مصممة بهيكل متين يضمن أقصى درجات الثبات والسلامة أثناء التخزين والاستخدام اليومي المكثف.',
                'desc_en'  => 'Built with a durable structure guaranteeing maximum stability and safety during heavy daily use.',
                'icon'     => 'shield',
                'sort'     => 10
            ],
            [
                'title_ar' => 'تحمل أوزان ثقيلة',
                'title_en' => 'Heavy Duty Weight Capacity',
                'desc_ar'  => 'تتحمل الأوزان الثقيلة بكفاءة عالية بفضل الألواح الفولاذية والدعامات القوية.',
                'desc_en'  => 'Handles heavy weight capacities efficiently thanks to reinforced steel beams and panels.',
                'icon'     => 'weight',
                'sort'     => 20
            ],
            [
                'title_ar' => 'مرونة وسهولة التركيب',
                'title_en' => 'Easy & Flexible Assembly',
                'desc_ar'  => 'سهلة التركيب والتفكيك مع إمكانية تعديل ارتفاع الأرفف بسهولة لتناسب مختلف احتياجاتك.',
                'desc_en'  => 'Easy assembly and disassembly with flexible shelf height adjustments to match your needs.',
                'icon'     => 'layers',
                'sort'     => 30
            ]
        ];

        $ins_card_stmt = $pdo->prepare("INSERT INTO product_detail_cards 
            (product_id, section_type, title_ar, title_en, description_ar, description_en, icon_name, sort_order, is_active) 
            VALUES (:pid, :section, :tar, :ten, :dar, :den, :icon, :sort, 1)");

        foreach ($all_products as $prod) {
            $pid = (int)$prod['id'];

            // Seed suitable_for if none exist
            $chk_s = $pdo->prepare("SELECT COUNT(*) FROM product_detail_cards WHERE product_id = :pid AND section_type = 'suitable_for'");
            $chk_s->execute([':pid' => $pid]);
            if ((int)$chk_s->fetchColumn() === 0) {
                foreach ($default_suitable_cards as $sc) {
                    $ins_card_stmt->execute([
                        ':pid'     => $pid,
                        ':section' => 'suitable_for',
                        ':tar'     => $sc['title_ar'],
                        ':ten'     => $sc['title_en'],
                        ':dar'     => $sc['desc_ar'],
                        ':den'     => $sc['desc_en'],
                        ':icon'    => $sc['icon'],
                        ':sort'    => $sc['sort']
                    ]);
                    $seeded_cards_count++;
                }
            }

            // Seed why_product if none exist
            $chk_w = $pdo->prepare("SELECT COUNT(*) FROM product_detail_cards WHERE product_id = :pid AND section_type = 'why_product'");
            $chk_w->execute([':pid' => $pid]);
            if ((int)$chk_w->fetchColumn() === 0) {
                foreach ($default_why_cards as $wc) {
                    $ins_card_stmt->execute([
                        ':pid'     => $pid,
                        ':section' => 'why_product',
                        ':tar'     => $wc['title_ar'],
                        ':ten'     => $wc['title_en'],
                        ':dar'     => $wc['desc_ar'],
                        ':den'     => $wc['desc_en'],
                        ':icon'    => $wc['icon'],
                        ':sort'    => $wc['sort']
                    ]);
                    $seeded_cards_count++;
                }
            }
        }

        $steps[] = "Seeding complete: $seeded_cards_count cards inserted.";

        // ─── Update schema version to 4 ───
        if ($db_driver === 'sqlite') {
            $pdo->exec("INSERT OR REPLACE INTO settings (key_name, val) VALUES ('schema_version', '4')");
        } else {
            $pdo->exec("INSERT INTO settings (key_name, val) VALUES ('schema_version', '4') ON DUPLICATE KEY UPDATE val = '4'");
        }
        $steps[] = "Schema version updated to 4.";

        $pdo->commit();

        return ['success' => true, 'version' => 4, 'steps' => $steps];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage(), 'version' => $schema_version, 'steps' => $steps];
    }
}
