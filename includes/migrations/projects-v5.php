<?php
// includes/migrations/projects-v5.php
// Database Migration Script Version 5 - Projects Module Enterprise Upgrade

if (!defined('ALLOW_SITEMAP_GEN') && !defined('ADMIN_PANEL') && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('403 Forbidden');
}

function run_projects_v5_migration(PDO $pdo, string $db_driver): array {
    $steps = [];
    
    try {
        // 1. Add missing metadata columns to 'projects' table
        $columns_to_add = [
            'category_id' => 'INT NULL',
            'service_ar' => 'VARCHAR(255) NULL',
            'service_en' => 'VARCHAR(255) NULL',
            'duration_ar' => 'VARCHAR(100) NULL',
            'duration_en' => 'VARCHAR(100) NULL'
        ];

        foreach ($columns_to_add as $col_name => $col_def) {
            $col_check = false;
            if ($db_driver === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info(projects)");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as $c) {
                    if ($c['name'] === $col_name) {
                        $col_check = true;
                        break;
                    }
                }
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = :col");
                $stmt->execute([':col' => $col_name]);
                $col_check = ((int)$stmt->fetchColumn() > 0);
            }

            if (!$col_check) {
                $pdo->exec("ALTER TABLE projects ADD COLUMN {$col_name} {$col_def}");
                $steps[] = "Added column '{$col_name}' to 'projects' table.";
            } else {
                $steps[] = "Column '{$col_name}' already exists in 'projects' table.";
            }
        }

        // 2. Create 'project_categories' table if it does not exist
        if ($db_driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS project_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_key VARCHAR(64) NOT NULL UNIQUE,
                name_ar VARCHAR(255) NOT NULL,
                name_en VARCHAR(255) NOT NULL,
                sort_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS project_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_key VARCHAR(64) NOT NULL UNIQUE,
                name_ar VARCHAR(255) NOT NULL,
                name_en VARCHAR(255) NOT NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pc_sort (sort_order, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $steps[] = "Ensured table 'project_categories' exists.";

        // 3. Seed default starter categories if table is empty
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM project_categories");
        if ((int)$count_stmt->fetchColumn() === 0) {
            $starter_categories = [
                [
                    'key' => 'industrial',
                    'ar' => 'المستودعات والمصانع',
                    'en' => 'Industrial & Warehouses',
                    'order' => 1
                ],
                [
                    'key' => 'retail',
                    'ar' => 'المتاجر والسوبرماركت',
                    'en' => 'Retail & Supermarkets',
                    'order' => 2
                ],
                [
                    'key' => 'commercial',
                    'ar' => 'المكاتب والأرشيف',
                    'en' => 'Offices & Archives',
                    'order' => 3
                ],
                [
                    'key' => 'residential',
                    'ar' => 'المنازل والفلل',
                    'en' => 'Residential & Private',
                    'order' => 4
                ],
                [
                    'key' => 'archive',
                    'ar' => 'الأرشيف والمستندات',
                    'en' => 'Document Archives',
                    'order' => 5
                ]
            ];

            $ins = $pdo->prepare("INSERT INTO project_categories (category_key, name_ar, name_en, sort_order, is_active) VALUES (:key, :ar, :en, :order, 1)");
            foreach ($starter_categories as $sc) {
                $ins->execute([
                    ':key' => $sc['key'],
                    ':ar' => $sc['ar'],
                    ':en' => $sc['en'],
                    ':order' => $sc['order']
                ]);
            }
            $steps[] = "Seeded 5 initial starter categories into 'project_categories'.";
        } else {
            $steps[] = "Table 'project_categories' already has category data.";
        }

        // 3.5. Safely backfill category_id for projects matching string category_key
        $pdo->exec("UPDATE projects SET category_id = (
            SELECT pc.id FROM project_categories pc WHERE pc.category_key = projects.category LIMIT 1
        ) WHERE (category_id IS NULL OR category_id = 0) AND category IS NOT NULL AND category != ''");
        $steps[] = "Safely backfilled 'category_id' references in 'projects' table.";
        $default_settings = [
            'projects_page_title_ar' => 'معرض مشاريعنا المنفذة',
            'projects_page_title_en' => 'Our Executed Projects Portfolio',
            'projects_page_subtitle_ar' => 'استعرض مجموعة من أبرز مشاريع تركيب وتجهيز المستودعات والمتاجر والمكاتب التي قمنا بتنفيذها بأعلى معايير الجودة',
            'projects_page_subtitle_en' => 'Explore a selection of our top warehouse, retail, and office shelving projects executed to the highest quality standards.'
        ];

        $sett_ins = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:k, :v) ON DUPLICATE KEY UPDATE val = IF(val IS NULL OR val = '', :v_dup, val)");
        if ($db_driver === 'sqlite') {
            $sett_ins = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:k, :v) ON CONFLICT(key_name) DO UPDATE SET val = excluded.val WHERE val IS NULL OR val = ''");
        }

        foreach ($default_settings as $sk => $sv) {
            if ($db_driver === 'sqlite') {
                $sett_ins->execute([':k' => $sk, ':v' => $sv]);
            } else {
                $sett_ins->execute([':k' => $sk, ':v' => $sv, ':v_dup' => $sv]);
            }
        }
        $steps[] = "Ensured Projects Header settings exist in 'settings' table.";

        // 5. Update schema version to 5
        $ver_stmt = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES ('schema_version', '5') ON DUPLICATE KEY UPDATE val = '5'");
        if ($db_driver === 'sqlite') {
            $ver_stmt = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES ('schema_version', '5') ON CONFLICT(key_name) DO UPDATE SET val = '5'");
        }
        $ver_stmt->execute();
        $steps[] = "Updated database schema version to 5.";

        return [
            'success' => true,
            'version' => 5,
            'steps' => $steps
        ];
    } catch (Exception $e) {
        error_log("Migration V5 Failure: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'steps' => $steps
        ];
    }
}
