<?php
// config/setup.php

/**
 * Initializes the database schema based on the active driver.
 * Only handles CREATE TABLE and necessary ALTER TABLE commands.
 * 
 * @param PDO $pdo The active database connection
 * @param string $db_driver 'sqlite' or 'mysql'
 */
function initialize_database_schema(PDO $pdo, $db_driver) {
    if ($db_driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            role TEXT DEFAULT 'admin',
            full_name TEXT,
            image TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN full_name TEXT");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN image TEXT");
        } catch (PDOException $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name_ar TEXT NOT NULL,
            name_en TEXT NOT NULL,
            description_ar TEXT NOT NULL,
            description_en TEXT NOT NULL,
            price REAL NOT NULL,
            stock INTEGER NOT NULL,
            category TEXT NOT NULL,
            image_url TEXT NOT NULL,
            dimensions TEXT,
            materials_ar TEXT,
            materials_en TEXT,
            is_featured INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL
        )");

        // Add is_featured and other flags if they don't exist for existing databases
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_featured INTEGER DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_most_requested INTEGER DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_new INTEGER DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_active INTEGER DEFAULT 1");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN sort_order INTEGER DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN updated_at DATETIME DEFAULT NULL");
        } catch (PDOException $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            customer_address TEXT NOT NULL,
            total_price REAL NOT NULL,
            status TEXT DEFAULT 'pending',
            require_installation INTEGER DEFAULT 0,
            installation_date TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            price REAL NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key_name TEXT PRIMARY KEY,
            val TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS features (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title_ar TEXT NOT NULL,
            title_en TEXT NOT NULL,
            description_ar TEXT,
            description_en TEXT,
            icon TEXT DEFAULT 'shield-check',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            name_ar TEXT NOT NULL,
            name_en TEXT NOT NULL,
            icon TEXT DEFAULT 'layers',
            image_path TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL
        )");

        try {
            $pdo->exec("ALTER TABLE categories ADD COLUMN updated_at DATETIME DEFAULT NULL");
        } catch (PDOException $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS portfolio (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title_ar TEXT NOT NULL,
            title_en TEXT NOT NULL,
            description_ar TEXT,
            description_en TEXT,
            image_url TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            message TEXT NOT NULL,
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            title_ar TEXT NOT NULL,
            title_en TEXT NOT NULL,
            category_id INTEGER,
            category TEXT NOT NULL,
            location_ar TEXT,
            location_en TEXT,
            project_date TEXT,
            client_ar TEXT,
            client_en TEXT,
            service_ar TEXT,
            service_en TEXT,
            duration_ar TEXT,
            duration_en TEXT,
            desc_ar TEXT,
            desc_en TEXT,
            long_desc_ar TEXT,
            long_desc_en TEXT,
            main_image TEXT,
            before_image TEXT,
            after_image TEXT,
            seo_title_ar TEXT,
            seo_title_en TEXT,
            seo_description_ar TEXT,
            seo_description_en TEXT,
            is_featured INTEGER DEFAULT 0,
            is_latest INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

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

        $pdo->exec("CREATE TABLE IF NOT EXISTS project_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            image_path TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS testimonials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name_ar TEXT NOT NULL,
            name_en TEXT NOT NULL,
            company_ar TEXT,
            company_en TEXT,
            review_ar TEXT NOT NULL,
            review_en TEXT NOT NULL,
            stars INTEGER DEFAULT 5,
            is_featured INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_project_images_project_id ON project_images(project_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_projects_active_sort ON projects(is_active, sort_order, id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_testimonials_active_sort ON testimonials(is_active, sort_order, id)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS media_library (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            stored_name TEXT NOT NULL,
            file_path TEXT UNIQUE NOT NULL,
            mime_type TEXT NOT NULL,
            extension TEXT NOT NULL,
            file_size INTEGER NOT NULL,
            width INTEGER DEFAULT NULL,
            height INTEGER DEFAULT NULL,
            alt_text_ar TEXT DEFAULT NULL,
            alt_text_en TEXT DEFAULT NULL,
            title_ar TEXT DEFAULT NULL,
            title_en TEXT DEFAULT NULL,
            folder TEXT NOT NULL,
            uploaded_by_username TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_media_library_folder ON media_library(folder)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_media_library_created_at ON media_library(created_at)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            title_ar TEXT NOT NULL,
            title_en TEXT NOT NULL,
            short_desc_ar TEXT,
            short_desc_en TEXT,
            icon TEXT DEFAULT 'wrench',
            image_path TEXT DEFAULT NULL,
            benefit_1_ar TEXT,
            benefit_1_en TEXT,
            benefit_2_ar TEXT,
            benefit_2_en TEXT,
            benefit_3_ar TEXT,
            benefit_3_en TEXT,
            custom_action TEXT DEFAULT 'none',
            custom_action_label_ar TEXT DEFAULT NULL,
            custom_action_label_en TEXT DEFAULT NULL,
            homepage_action TEXT DEFAULT 'services',
            homepage_action_label_ar TEXT DEFAULT NULL,
            homepage_action_label_en TEXT DEFAULT NULL,
            is_featured INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            seo_title_ar TEXT,
            seo_title_en TEXT,
            seo_description_ar TEXT,
            seo_description_en TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_services_active_sort ON services(is_active, sort_order, id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_services_featured_sort ON services(is_active, is_featured, sort_order, id)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS hero_slides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            image_path TEXT NOT NULL,
            alt_ar TEXT,
            alt_en TEXT,
            sort_order INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hero_slides_active_sort ON hero_slides (is_active, sort_order)");

        // Product Gallery Tables (V3)
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
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
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pi_primary ON product_images (product_id, is_primary)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS product_colors (
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS product_models (
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

        // Product Detail Cards Table (V4: Suitable For & Why This Product)
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_detail_cards (
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

    } elseif ($db_driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_ar VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NOT NULL,
            description_ar TEXT NOT NULL,
            description_en TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            stock INT NOT NULL DEFAULT 0,
            category VARCHAR(100) NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            dimensions VARCHAR(100) DEFAULT NULL,
            materials_ar VARCHAR(255) DEFAULT NULL,
            materials_en VARCHAR(255) DEFAULT NULL,
            is_featured TINYINT(1) DEFAULT 0,
            is_most_requested TINYINT(1) DEFAULT 0,
            is_new TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            seo_title_ar VARCHAR(255) DEFAULT NULL,
            seo_title_en VARCHAR(255) DEFAULT NULL,
            meta_description_ar VARCHAR(255) DEFAULT NULL,
            meta_description_en VARCHAR(255) DEFAULT NULL,
            alt_text_ar VARCHAR(255) DEFAULT NULL,
            alt_text_en VARCHAR(255) DEFAULT NULL,
            og_media_id INT DEFAULT NULL,
            brand VARCHAR(100) DEFAULT NULL,
            sku VARCHAR(100) DEFAULT NULL,
            mpn VARCHAR(100) DEFAULT NULL,
            gtin VARCHAR(100) DEFAULT NULL,
            availability VARCHAR(50) DEFAULT 'InStock',
            condition_type VARCHAR(50) DEFAULT 'NewCondition',
            canonical_url VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_most_requested TINYINT(1) DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_new TINYINT(1) DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN sort_order INT DEFAULT 0");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN updated_at DATETIME DEFAULT NULL");
        } catch (PDOException $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key_name VARCHAR(100) PRIMARY KEY,
            val TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS features (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title_ar VARCHAR(255) NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            description_ar TEXT,
            description_en TEXT,
            icon VARCHAR(100) DEFAULT 'shield-check',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(100) UNIQUE NOT NULL,
            name_ar VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NOT NULL,
            icon VARCHAR(100) DEFAULT 'layers',
            image_path VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $pdo->exec("ALTER TABLE categories ADD COLUMN updated_at DATETIME DEFAULT NULL");
        } catch (PDOException $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS portfolio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title_ar VARCHAR(255) NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            description_ar TEXT,
            description_en TEXT,
            image_url VARCHAR(500) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) UNIQUE NOT NULL,
            title_ar VARCHAR(255) NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            category_id INT NULL,
            category VARCHAR(100) NOT NULL,
            location_ar VARCHAR(255),
            location_en VARCHAR(255),
            project_date VARCHAR(7),
            client_ar VARCHAR(255),
            client_en VARCHAR(255),
            service_ar VARCHAR(255) NULL,
            service_en VARCHAR(255) NULL,
            duration_ar VARCHAR(100) NULL,
            duration_en VARCHAR(100) NULL,
            desc_ar TEXT,
            desc_en TEXT,
            long_desc_ar TEXT,
            long_desc_en TEXT,
            main_image VARCHAR(500),
            before_image VARCHAR(500),
            after_image VARCHAR(500),
            seo_title_ar VARCHAR(255),
            seo_title_en VARCHAR(255),
            seo_description_ar TEXT,
            seo_description_en TEXT,
            is_featured TINYINT(1) DEFAULT 0,
            is_latest TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

        $pdo->exec("CREATE TABLE IF NOT EXISTS project_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_project_images_project_id FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS testimonials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_ar VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NOT NULL,
            company_ar VARCHAR(255),
            company_en VARCHAR(255),
            review_ar TEXT NOT NULL,
            review_en TEXT NOT NULL,
            stars INT DEFAULT 5,
            is_featured TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Idempotent indexes
        try {
            $pdo->exec("ALTER TABLE project_images ADD INDEX idx_project_images_project_id (project_id)");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE projects ADD INDEX idx_projects_active_sort (is_active, sort_order, id)");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE testimonials ADD INDEX idx_testimonials_active_sort (is_active, sort_order, id)");
        } catch (PDOException $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS media_library (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) UNIQUE NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            extension VARCHAR(10) NOT NULL,
            file_size INT NOT NULL,
            width INT DEFAULT NULL,
            height INT DEFAULT NULL,
            alt_text_ar VARCHAR(255) DEFAULT NULL,
            alt_text_en VARCHAR(255) DEFAULT NULL,
            title_ar VARCHAR(255) DEFAULT NULL,
            title_en VARCHAR(255) DEFAULT NULL,
            folder VARCHAR(100) NOT NULL,
            uploaded_by_username VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_media_library_folder (folder),
            INDEX idx_media_library_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(150) UNIQUE NOT NULL,
            title_ar VARCHAR(255) NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            short_desc_ar TEXT,
            short_desc_en TEXT,
            icon VARCHAR(100) DEFAULT 'wrench',
            image_path VARCHAR(500) DEFAULT NULL,
            benefit_1_ar VARCHAR(255),
            benefit_1_en VARCHAR(255),
            benefit_2_ar VARCHAR(255),
            benefit_2_en VARCHAR(255),
            benefit_3_ar VARCHAR(255),
            benefit_3_en VARCHAR(255),
            custom_action VARCHAR(50) DEFAULT 'none',
            custom_action_label_ar VARCHAR(255) DEFAULT NULL,
            custom_action_label_en VARCHAR(255) DEFAULT NULL,
            homepage_action VARCHAR(50) DEFAULT 'services',
            homepage_action_label_ar VARCHAR(255) DEFAULT NULL,
            homepage_action_label_en VARCHAR(255) DEFAULT NULL,
            is_featured TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            seo_title_ar VARCHAR(255),
            seo_title_en VARCHAR(255),
            seo_description_ar TEXT,
            seo_description_en TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $pdo->exec("ALTER TABLE services ADD INDEX idx_services_active_sort (is_active, sort_order, id)");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE services ADD INDEX idx_services_featured_sort (is_active, is_featured, sort_order, id)");
        } catch (PDOException $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS hero_slides (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(500) NOT NULL,
            alt_ar VARCHAR(255),
            alt_en VARCHAR(255),
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try {
            $pdo->exec("ALTER TABLE hero_slides ADD INDEX idx_hero_slides_active_sort (is_active, sort_order)");
        } catch (PDOException $e) {}

        // Product Gallery Tables (V3)
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS product_colors (
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS product_models (
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

        // Product Detail Cards Table (V4: Suitable For & Why This Product)
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_detail_cards (
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

    // --- Phase 6B Migration: Admin User Roles & Status ---
    $existing_cols_users = get_table_existing_columns($pdo, 'users', $db_driver);
    if (!in_array('status', $existing_cols_users, true)) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
        } catch (PDOException $e) {}
    }
    if (!in_array('role', $existing_cols_users, true)) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'admin'");
        } catch (PDOException $e) {}
    }
    if (!in_array('full_name', $existing_cols_users, true)) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(255) DEFAULT NULL");
        } catch (PDOException $e) {}
    }
    if (!in_array('image', $existing_cols_users, true)) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        } catch (PDOException $e) {}
    }

    // --- Phase 5A Migration: Product & Category Slugs and SEO Columns ---
    $existing_cols_products = get_table_existing_columns($pdo, 'products', $db_driver);
    $existing_cols_categories = get_table_existing_columns($pdo, 'categories', $db_driver);
    $existing_cols_proj_categories = get_table_existing_columns($pdo, 'project_categories', $db_driver);

    // 1. Alter products table
    if (!in_array('slug', $existing_cols_products, true)) {
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN slug VARCHAR(255) DEFAULT NULL");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("CREATE UNIQUE INDEX idx_products_slug ON products(slug)");
        } catch (PDOException $e) {}
    }

    // 2. Alter categories table
    $columns_cat = [
        'slug' => 'VARCHAR(100)',
        'seo_title_ar' => 'VARCHAR(255)',
        'seo_title_en' => 'VARCHAR(255)',
        'meta_desc_ar' => 'TEXT',
        'meta_desc_en' => 'TEXT',
        'description_ar' => 'TEXT',
        'description_en' => 'TEXT'
    ];
    foreach ($columns_cat as $col => $type) {
        if (!in_array($col, $existing_cols_categories, true)) {
            try {
                $pdo->exec("ALTER TABLE categories ADD COLUMN {$col} {$type} DEFAULT NULL");
            } catch (PDOException $e) {}
        }
    }
    try {
        $pdo->exec("CREATE UNIQUE INDEX idx_categories_slug ON categories(slug)");
    } catch (PDOException $e) {}

    // 3. Alter project_categories table
    $columns_pcat = [
        'slug' => 'VARCHAR(100)',
        'seo_title_ar' => 'VARCHAR(255)',
        'seo_title_en' => 'VARCHAR(255)',
        'meta_desc_ar' => 'TEXT',
        'meta_desc_en' => 'TEXT',
        'description_ar' => 'TEXT',
        'description_en' => 'TEXT'
    ];
    foreach ($columns_pcat as $col => $type) {
        if (!in_array($col, $existing_cols_proj_categories, true)) {
            try {
                $pdo->exec("ALTER TABLE project_categories ADD COLUMN {$col} {$type} DEFAULT NULL");
            } catch (PDOException $e) {}
        }
    }
    try {
        $pdo->exec("CREATE UNIQUE INDEX idx_project_categories_slug ON project_categories(slug)");
    } catch (PDOException $e) {}

    // 4. Safely migrate existing slug_en data to slug if present
    if (in_array('slug_en', $existing_cols_products, true)) {
        try {
            $stmt = $pdo->query("SELECT id, slug_en FROM products WHERE (slug IS NULL OR slug = '') AND (slug_en IS NOT NULL AND slug_en != '')");
            $to_migrate = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($to_migrate as $row) {
                $candidate = trim($row['slug_en']);
                $final_slug = $candidate;
                $counter = 1;
                while (true) {
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = :slug AND id != :id");
                    $stmt_check->execute([':slug' => $final_slug, ':id' => $row['id']]);
                    if ((int)$stmt_check->fetchColumn() === 0) {
                        break;
                    }
                    $final_slug = $candidate . '-' . $counter;
                    $counter++;
                }
                $up = $pdo->prepare("UPDATE products SET slug = :slug WHERE id = :id");
                $up->execute([':slug' => $final_slug, ':id' => $row['id']]);
            }
        } catch (Exception $e) {
            error_log("Error migrating slug_en to slug: " . $e->getMessage());
        }
    }

    // 5. Backfill unique slugs for products where slug is empty
    try {
        $stmt = $pdo->query("SELECT id, name_ar, name_en FROM products WHERE slug IS NULL OR slug = ''");
        $missing_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($missing_products as $p) {
            $slug = generate_product_slug_setup($p['name_en'], $p['name_ar'], $p['id'], $pdo);
            $up = $pdo->prepare("UPDATE products SET slug = :slug WHERE id = :id");
            $up->execute([':slug' => $slug, ':id' => $p['id']]);
        }
    } catch (Exception $e) {
        error_log("Error backfilling product slugs: " . $e->getMessage());
    }

    // 6. Backfill unique slugs for product categories where slug is empty
    try {
        $stmt = $pdo->query("SELECT id, code FROM categories WHERE slug IS NULL OR slug = ''");
        $missing_cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($missing_cats as $c) {
            $slug = strtolower(trim($c['code']));
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
            $slug = preg_replace('/[\s-]+/', '-', $slug);
            $slug = trim($slug, '-');
            if (empty($slug)) {
                $slug = 'category';
            }
            $final_slug = $slug;
            $counter = 1;
            while (true) {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = :slug AND id != :id");
                $stmt_check->execute([':slug' => $final_slug, ':id' => $c['id']]);
                if ((int)$stmt_check->fetchColumn() === 0) {
                    break;
                }
                $final_slug = $slug . '-' . $counter;
                $counter++;
            }
            $up = $pdo->prepare("UPDATE categories SET slug = :slug WHERE id = :id");
            $up->execute([':slug' => $final_slug, ':id' => $c['id']]);
        }
    } catch (Exception $e) {
        error_log("Error backfilling category slugs: " . $e->getMessage());
    }

    // 7. Backfill unique slugs for project categories where slug is empty
    try {
        $stmt = $pdo->query("SELECT id, category_key FROM project_categories WHERE slug IS NULL OR slug = ''");
        $missing_pcats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($missing_pcats as $c) {
            $slug = strtolower(trim($c['category_key']));
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
            $slug = preg_replace('/[\s-]+/', '-', $slug);
            $slug = trim($slug, '-');
            if (empty($slug)) {
                $slug = 'project-category';
            }
            $final_slug = $slug;
            $counter = 1;
            while (true) {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM project_categories WHERE slug = :slug AND id != :id");
                $stmt_check->execute([':slug' => $final_slug, ':id' => $c['id']]);
                if ((int)$stmt_check->fetchColumn() === 0) {
                    break;
                }
                $final_slug = $slug . '-' . $counter;
                $counter++;
            }
            $up = $pdo->prepare("UPDATE project_categories SET slug = :slug WHERE id = :id");
            $up->execute([':slug' => $final_slug, ':id' => $c['id']]);
        }
    } catch (Exception $e) {
        error_log("Error backfilling project category slugs: " . $e->getMessage());
    }
}

/**
 * Local helper function for getting table columns dynamically.
 */
function get_table_existing_columns(PDO $pdo, $table_name, $db_driver) {
    $existing = [];
    try {
        if ($db_driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$table_name})");
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $existing[] = strtolower($row['name']);
                }
            }
        } else {
            $stmt = $pdo->query("SHOW COLUMNS FROM {$table_name}");
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $existing[] = strtolower($row['Field']);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting columns for table {$table_name}: " . $e->getMessage());
    }
    return $existing;
}

/**
 * Local helper function for generating product slugs.
 */
function generate_product_slug_setup($name_en, $name_ar, $id, $pdo) {
    $base = '';
    if (!empty($name_en)) {
        $base = trim($name_en);
    } elseif (!empty($name_ar)) {
        $base = 'product';
    } else {
        $base = 'product';
    }

    $slug = strtolower($base);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');

    if (empty($slug)) {
        $slug = 'product';
    }

    $final_slug = $slug;
    if ($final_slug === 'product') {
        $final_slug = 'product-' . $id;
    }
    
    $counter = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = :slug AND id != :id");
        $stmt->execute([':slug' => $final_slug, ':id' => $id]);
        if ((int)$stmt->fetchColumn() === 0) {
            break;
        }
        $final_slug = $slug . '-' . $counter;
        $counter++;
    }

    return $final_slug;
}

/**
 * Seeds the default settings into the database if they do not exist.
 * 
 * @param PDO $pdo The active database connection
 */
function seed_default_settings(PDO $pdo) {
    $default_settings = [
        'site_logo' => '',
        'site_logo_text_ar' => 'عالم الرفوف',
        'site_logo_text_en' => 'World of Shelves',
        'site_name_ar' => 'عالم الرفوف',
        'site_name_en' => 'World of Shelves',
        'seo_home_title_ar' => 'عالم الرفوف | تصميم وتوصيل وتركيب رفوف التخزين والمستودعات',
        'seo_home_title_en' => 'World of Shelves | Storage & Warehouse Racking Solutions',
        'seo_home_desc_ar' => 'مؤسسة عالم الرفوف التجارية لبيع وتركيب أرفف المستودعات، الأرفف الحديدية، أرفف المحلات ورفوف التخزين المنزلي في السعودية.',
        'seo_home_desc_en' => 'World of Shelves Trading Est. offers high-quality warehouse racking, industrial shelving, shop shelving, and home storage racks in Saudi Arabia.',
        'seo_products_title_ar' => 'منتجاتنا من أنظمة الرفوف وحلول التخزين | عالم الرفوف',
        'seo_products_title_en' => 'Our Shelving Products & Storage Systems | World of Shelves',
        'seo_products_desc_ar' => 'تصفح تشكيلة واسعة من أرفف المستودعات، أرفف التخزين الحديدية، أرفف السوبرماركت والمحلات والرفوف المنزلية بأفضل الأسعار.',
        'seo_products_desc_en' => 'Browse a wide range of warehouse racking, industrial storage shelving, supermarket display racks, and home shelving at best prices.',
        'seo_services_title_ar' => 'خدمات تصميم وتوصيل وتركيب الرفوف | عالم الرفوف',
        'seo_services_title_en' => 'Shelving Design, Delivery & Installation Services | World of Shelves',
        'seo_services_desc_ar' => 'نقدم خدمات متكاملة تشمل التصميم الثلاثي الأبعاد، توصيل، تركيب وفك أنظمة الرفوف والمستودعات بأيدي مهندسين وفنيين مختصين.',
        'seo_services_desc_en' => 'We provide professional services including 3D design layout, delivery, installation, and relocation of warehouse shelving systems.',
        'seo_projects_title_ar' => 'معرض المشاريع والأعمال المنفذة | عالم الرفوف',
        'seo_projects_title_en' => 'Completed Storage Projects Portfolio | World of Shelves',
        'seo_projects_desc_ar' => 'استعرض أبرز مشاريع تركيب أرفف المستودعات والتخزين التي قمنا بتنفيذها لكبرى الشركات في السعودية.',
        'seo_projects_desc_en' => 'Explore our portfolio of completed warehouse racking and storage installation projects across Saudi Arabia.',
        'tagline_ar' => 'منصتك المتكاملة لتصميم وتوصيل وتركيب أرقى أنواع الرفوف',
        'tagline_en' => 'Your all-in-one platform to design, deliver, and install premium shelves',
        'hero_title_ar' => 'اجعل مساحتك أكثر تنظيماً وجمالاً',
        'hero_title_en' => 'Make Your Space Organized & Beautiful',
        'hero_subtitle_ar' => 'نوفر لك تشكيلة واسعة من الرفوف الجدارية، القائمة، والديكورية بأعلى جودة مع خدمة التوصيل والتركيب الاحترافية.',
        'hero_subtitle_en' => 'We offer a wide collection of wall, standing, and decorative shelves crafted with premium quality, complete with professional delivery and installation.',
        'hero_title_subtitle_placeholder' => '', // just keeping index intact
        'hero_image' => 'assets/images/shelf1.png',
        'about_image' => 'assets/images/shelf4.png',
        'whatsapp_number' => '966500000000',
        'contact_phone' => '+966 500 000 000',
        'contact_address_ar' => 'المملكة العربية السعودية، الرياض',
        'contact_address_en' => 'Riyadh, Saudi Arabia',
        'contact_map_iframe' => '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d115857.08726588145!2d46.75893264673629!3d24.846549216091217!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3e2efd978a9c3ce1%3A0x6b44edef890f6b4!2sRiyadh%20Saudi%20Arabia!5e0!3m2!1sen!2s!4v1700000000000!5m2!1sen!2s" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>',
        
        // Navbar
        'nav_home_ar' => 'الرئيسية',
        'nav_home_en' => 'Home',
        'nav_products_ar' => 'المنتجات',
        'nav_products_en' => 'Products',
        'nav_portfolio_ar' => 'أعمالنا',
        'nav_portfolio_en' => 'Our Work',
        'nav_about_ar' => 'من نحن',
        'nav_about_en' => 'About Us',
        'nav_admin_ar' => 'لوحة الإدارة',
        'nav_admin_en' => 'Admin Panel',
        
        // About Us
        'about_us_title_ar' => 'من نحن',
        'about_us_title_en' => 'About Us',
        'about_us_desc_ar' => 'تأسست شركة عالم الرفوف لتلبية الاحتياجات المتزايدة لحلول التخزين الراقية والمبتكرة. نوفر أرففاً مصنوعة بأعلى درجات الدقة والمهارة لتناسب المنازل والمكاتب والمستودعات مع توفير خدمة متكاملة تشمل التوصيل السريع والتركيب الفوري على أيدي أمهر الفنيين.',
        'about_us_desc_en' => 'World of Shelves was founded to meet the growing demand for premium and innovative storage solutions. We offer shelves crafted with precision to fit homes, offices, and warehouses, complete with delivery and professional installation.',
        'about_mission_title_ar' => 'رسالتنا',
        'about_mission_title_en' => 'Our Mission',
        'about_mission_desc_ar' => 'تقديم حلول تخزين وعرض مبتكرة تجمع بين القوة والجمال البصري.',
        'about_mission_desc_en' => 'To deliver innovative storage and display solutions that combine durability and visual appeal.',
        'about_vision_title_ar' => 'رؤيتنا',
        'about_vision_title_en' => 'Our Vision',
        'about_vision_desc_ar' => 'أن نكون الوجهة الأولى في المملكة لتوريد وتركيب الرفوف المنزلية والصناعية.',
        'about_vision_desc_en' => 'To be the premier destination in the Kingdom for supplying and installing residential and industrial shelving.',
        
        // Why Us
        'why_us_title_ar' => 'لماذا تختارنا؟',
        'why_us_title_en' => 'Why Choose Us?',
        
        // Features (Fallback settings - kept for backward compatibility if needed)
        'feature1_title_ar' => 'جودة ممتازة',
        'feature1_title_en' => 'Premium Quality',
        'feature1_desc_ar' => 'نختار أجود أنواع الخشب والمعادن لضمان قوة تحمل الرفوف وجمال مظهرها.',
        'feature1_desc_en' => 'We select the finest wood and metal materials to ensure maximum durability and aesthetics.',
        
        'feature2_title_ar' => 'تركيب احترافي',
        'feature2_title_en' => 'Professional Installation',
        'feature2_desc_ar' => 'فريق عمل متخصص جاهز لتركيب الرفوف بدقة وأمان تام في مكانك.',
        'feature2_desc_en' => 'Our specialized team is ready to install shelves with ultimate precision and safety at your place.',
        
        'feature3_title_ar' => 'شحن سريع',
        'feature3_title_en' => 'Fast Shipping',
        'feature3_desc_ar' => 'نقوم بتوصيل طلباتك في أسرع وقت وبكل سلاسة لجميع مناطق الخدمة.',
        'feature3_desc_en' => 'We deliver your orders as fast as possible to all available service locations.',
        
        // New CMS Settings from Sprint 1
        'default_language' => 'ar',
        'business_name_ar' => 'مؤسسة عالم الرفوف التجارية',
        'business_name_en' => 'World of Shelves Trading Est.',
        'copyright_text_ar' => 'جميع الحقوق محفوظة.',
        'copyright_text_en' => 'All Rights Reserved.',
        'site_logo_dark' => '',
        'site_favicon' => '',
        'default_og_image' => 'assets/images/shelf1.png',
        'logo_alt_ar' => 'شعار عالم الرفوف',
        'logo_alt_en' => 'World of Shelves Logo',
        'contact_phone_sec' => '',
        'contact_email' => 'info@worldofshelves.com',
        'working_hours_ar' => 'السبت - الخميس: 9:00 ص - 10:00 م',
        'working_hours_en' => 'Saturday - Thursday: 9:00 AM - 10:00 PM',
        'social_facebook' => '',
        'social_instagram' => '',
        'social_twitter' => '',
        'social_linkedin' => '',
        'social_youtube' => '',
        'social_tiktok' => '',
        'social_snapchat' => '',
        'hero_cta_primary_label_ar' => 'استعرض المنتجات',
        'hero_cta_primary_label_en' => 'Browse Products',
        'hero_cta_secondary_label_ar' => 'تواصل عبر واتساب',
        'hero_cta_secondary_label_en' => 'Contact via WhatsApp',
        'seo_home_title_ar' => 'عالم الرفوف - الرئيسية',
        'seo_home_title_en' => 'World of Shelves - Home',
        'seo_home_desc_ar' => 'نوفر لك تشكيلة واسعة من الرفوف بأعلى جودة.',
        'seo_home_desc_en' => 'We offer a wide collection of shelves crafted with premium quality.',
        'seo_title_ar' => 'عالم الرفوف',
        'seo_title_en' => 'World of Shelves',
        'seo_description_ar' => 'منصتك المتكاملة لتصميم وتوصيل وتركيب أرقى أنواع الرفوف',
        'seo_description_en' => 'Your all-in-one platform to design, deliver, and install premium shelves',
        'seo_keywords' => 'رفوف, رف جداري, خزانة كتب, رفوف مستودعات, shelves, bookshelves, warehouse racks',
        'site_url' => defined('APP_URL') ? APP_URL : 'http://localhost/World_of_Shelves_websit',
        'seo_google_console' => '',
        'analytics_google_ga4' => '',
        'analytics_google_gtm' => '',
        'analytics_meta_pixel' => '',
        'maintenance_mode' => '0',
        'maintenance_message_ar' => 'الموقع تحت الصيانة حالياً. سنعود قريباً!',
        'maintenance_message_en' => 'Our site is currently undergoing maintenance. We will be back soon!',
        'enable_dark_mode' => '1',
        // Sprint 6: Global UI / Navigation Default settings
        'nav_show_lang_switcher' => '1',
        'nav_show_dark_mode' => '1',
        'nav_show_home' => '1',
        'nav_show_products' => '1',
        'nav_show_services' => '1',
        'nav_show_projects' => '1',
        'nav_show_about' => '1',
        'nav_show_contact' => '1',
        'nav_order_home' => '10',
        'nav_order_products' => '20',
        'nav_order_services' => '30',
        'nav_order_projects' => '40',
        'nav_order_about' => '50',
        'nav_order_contact' => '60',
        'nav_services_ar' => 'الخدمات',
        'nav_services_en' => 'Services',
        'nav_projects_ar' => 'المشاريع',
        'nav_projects_en' => 'Portfolio',
        'nav_contact_ar' => 'اتصل بنا',
        'nav_contact_en' => 'Contact',
        // Footer Defaults
        'footer_show_identity' => '1',
        'footer_logo' => '',
        'footer_description_ar' => '',
        'footer_description_en' => '',
        'footer_show_services' => '1',
        'footer_services_title_ar' => 'الخدمات',
        'footer_services_title_en' => 'Services',
        'footer_services_limit' => '6',
        'footer_show_categories' => '1',
        'footer_categories_title_ar' => 'المنتجات',
        'footer_categories_title_en' => 'Products',
        // Floating Controls Defaults
        'show_floating_whatsapp' => '1',
        'show_floating_phone' => '1',
        'show_floating_visit' => '1',
        'floating_whatsapp_msg_ar' => 'السلام عليكم، أود طلب استشارة أو عرض سعر للأرفف الحديدية.',
        'floating_whatsapp_msg_en' => 'Hello, I would like to request a quotation or advice for steel shelves.',
        'floating_visit_msg_ar' => 'السلام عليكم، أود طلب حجز موعد معاينة مجانية للموقع لتخطيط وتركيب الأرفف.',
        'floating_visit_msg_en' => 'Hello, I want to book a free site visit/inspection for steel racking setup planning.',
        // Sprint 7: Contact Page Defaults
        'seo_contact_title_ar' => '',
        'seo_contact_title_en' => '',
        'seo_contact_desc_ar' => '',
        'seo_contact_desc_en' => '',
        'seo_contact_og_image' => '',
        'contact_show_hero' => '1',
        'contact_show_form' => '1',
        'contact_show_info' => '1',
        'contact_show_map' => '1',
        'contact_show_whatsapp_button' => '1',
        'contact_hero_title_ar' => 'اتصل بنا',
        'contact_hero_title_en' => 'Contact Us',
        'contact_hero_desc_ar' => 'يسعدنا تواصلك معنا للإجابة على استفساراتك وتقديم عروض الأسعار والخدمات الاستشارية مجاناً.',
        'contact_hero_desc_en' => 'We are glad to hear from you. Reach out to get free consultation, answers, and quotation offers.',
        'contact_form_title_ar' => 'أرسل لنا رسالة',
        'contact_form_title_en' => 'Send us a message',
        'contact_form_submit_ar' => 'إرسال الرسالة',
        'contact_form_submit_en' => 'Send Message',
        'contact_info_title_ar' => 'معلومات الاتصال',
        'contact_info_title_en' => 'Contact Info',
        'contact_location_title_ar' => 'موقعنا',
        'contact_location_title_en' => 'Our Location',
        'contact_whatsapp_template_ar' => 'السلام عليكم، أود الاستفسار عن الرفوف.',
        'contact_whatsapp_template_en' => 'Hello, I have an inquiry about the shelves.',

        // --- Used Shelves Page ---
        'used_show_hero' => '1',
        'used_order_hero' => '10',
        'used_show_buy' => '1',
        'used_order_buy' => '20',
        'used_show_process' => '1',
        'used_order_process' => '30',
        'used_show_factors' => '1',
        'used_order_factors' => '40',
        'used_show_faq' => '1',
        'used_order_faq' => '50',
        'used_show_cta' => '1',
        'used_order_cta' => '60',

        // Hero Content
        'used_hero_title_ar' => 'نشتري الأرفف الحديدية المستعملة بأفضل الأسعار',
        'used_hero_title_en' => 'We Buy Used Iron Shelves at Top Rates',
        'used_hero_subtitle_ar' => 'نشتري أرفف المستودعات، المحلات، والشركات مع تكفلنا الكامل والتام بكافة أعمال الفك والتحميل والنقل الفوري السريع.',
        'used_hero_subtitle_en' => 'We buy warehouse and retail shelving from businesses and warehouses, covering all dismantling, loading, and instant transport costs.',
        'used_hero_cta_label_ar' => 'أرسل صور الأرفف عبر واتساب',
        'used_hero_cta_label_en' => 'Send Shelf Photos via WhatsApp',
        'used_hero_secondary_label_ar' => 'الخدمات',
        'used_hero_secondary_label_en' => 'Services',
        'used_hero_wa_msg_ar' => 'السلام عليكم، أرغب في بيع أرفف حديدية مستعملة لدينا وأود الحصول على تسعير مبدئي، وسأقوم أرسل صور الأرفف والكميات الآن.',
        'used_hero_wa_msg_en' => 'Hello, I want to sell used iron shelves and would like a preliminary offer. I will send photos and quantities now.',

        // What We Buy Content
        'used_buy_title_ar' => 'ما هي الأرفف التي نشتريها؟',
        'used_buy_title_en' => 'What Used Shelves Do We Buy?',
        'used_buy_desc_ar' => 'نشتري جميع أنواع الأرفف الحديدية المستعملة بشرط سلامتها وقابليتها للاستخدام الآمن.',
        'used_buy_desc_en' => 'We purchase all types of used steel racking as long as it is structurally safe and reusable.',
        'used_buy_1_title_ar' => 'أرفف مستودعات ومخازن',
        'used_buy_1_title_en' => 'Warehouse & Industrial Racks',
        'used_buy_1_desc_ar' => 'نشتري الأرفف الثقيلة والمتوسطة المخصصة لتخزين البضائع في المستودعات اللوجستية الكبرى.',
        'used_buy_1_desc_en' => 'We buy heavy and medium-duty pallet racking systems designed for major warehouses.',
        'used_buy_2_title_ar' => 'أرفف محلات وسوبرماركت',
        'used_buy_2_title_en' => 'Retail & Grocery Gondola Shelves',
        'used_buy_2_desc_ar' => 'نشتري أرفف العرض المخصصة للمتاجر الكبرى والبقالات ومحلات التجزئة بمختلف أحجامها.',
        'used_buy_2_desc_en' => 'We buy display shelving from grocery stores, supermarkets, and boutique shops of all sizes.',
        'used_buy_3_title_ar' => 'أرفف حديدية ثقيلة (Pallet Racks)',
        'used_buy_3_title_en' => 'Heavy-Duty Cantilevers & Pallets',
        'used_buy_3_desc_ar' => 'نشتري الأرفف المخصصة للأوزان العالية والمنصات وحوامل المصانع الثقيلة.',
        'used_buy_3_desc_en' => 'We buy racking designed for heavy weights, raw materials, steel coils, and factory shelves.',
        'used_buy_4_title_ar' => 'أنظمة تخزين متكاملة وسقائف',
        'used_buy_4_title_en' => 'Complete Racking Structures & Mezzanines',
        'used_buy_4_desc_ar' => 'نشتري صفوف المستودعات الكاملة، السقائف الحديدية، وتوابعها التخزينية المتنوعة.',
        'used_buy_4_desc_en' => 'We buy full warehouse layout structures, platforms, and their storage accessories.',

        // Process Content
        'used_process_title_ar' => 'كيف تتم عملية الشراء والتقييم؟',
        'used_process_title_en' => 'How Purchase & Valuation Work',
        'used_process_desc_ar' => 'نعتمد آلية سريعة ومبسطة لتقييم أرففك ودفع قيمتها نقداً أو بتحويل فوري بدون تعقيدات.',
        'used_process_desc_en' => 'We follow a simple, rapid procedure to evaluate your racking and pay cash or instant transfer.',
        'used_step_1_title_ar' => 'إرسال صور ومقاسات',
        'used_step_1_title_en' => 'Send Photos & Sizes',
        'used_step_1_desc_ar' => 'أرسل صوراً واضحة للأرفف مع تفاصيل مقاساتها والكمية التقريبية المتاحة لديك.',
        'used_step_1_desc_en' => 'Send us clear photos of the shelving along with dimensions and quantity details.',
        'used_step_2_title_ar' => 'تحديد الموقع والفك',
        'used_step_2_title_en' => 'Location & Dismantling Details',
        'used_step_2_desc_ar' => 'حدد لنا مدينتك وهل تحتاج فريقنا للقيام بالفك والتحميل أم أنها جاهزة للنقل.',
        'used_step_2_desc_en' => 'Let us know your city and if shelves need dismantling or are ready to load.',
        'used_step_3_title_ar' => 'تسعير عادل وسريع',
        'used_step_3_title_en' => 'Fair Market Pricing',
        'used_step_3_desc_ar' => 'نقوم بدراسة طلبك وتقديم سعر شراء عادل ومناسب مبني على أسعار السوق وحالة الحديد.',
        'used_step_3_desc_en' => 'We study your details and send a fair offer based on steel condition and market value.',
        'used_step_4_title_ar' => 'الفك والتحميل الفوري',
        'used_step_4_title_en' => 'Dismantling & Quick Load',
        'used_step_4_desc_ar' => 'بعد الاتفاق، نرسل شاحناتنا وفريقنا الفني للقيام بكل أعمال الفك والنقل والدفع الفوري.',
        'used_step_4_desc_en' => 'Upon agreement, we dispatch our crews to handle all dismantling, loading, transport, and payment.',

        // Factors Content
        'used_factors_title_ar' => 'العوامل التي تحدد قيمة الأرفف',
        'used_factors_title_en' => 'Factors Determining Used Racking Value',
        'used_factor_1_title_ar' => 'الحالة الفنية للحديد',
        'used_factor_1_title_en' => 'Steel & Structural Integrity',
        'used_factor_1_desc_ar' => 'سلامة الأرفف من الانحناءات أو الصدأ الشديد أو الكسور والعيوب الجوهرية.',
        'used_factor_1_desc_en' => 'Shelving condition (absence of severe rust, major dents, cracks, or modifications).',
        'used_factor_2_title_ar' => 'سماكة ونوع الأرفف',
        'used_factor_2_title_en' => 'Iron Gauge & System Class',
        'used_factor_2_desc_ar' => 'الأرفف الثقيلة المخصصة للأوزان العالية وذات السماكات المرتفعة تحظى بتقييم أعلى.',
        'used_factor_2_desc_en' => 'Heavy industrial racks with higher load capacities carry higher trade-in value.',
        'used_factor_3_title_ar' => 'الكمية الكلية للرفوف',
        'used_factor_3_title_en' => 'Racking Lot Volume',
        'used_factor_3_desc_ar' => 'الكميات الكبيرة وتجهيزات المستودعات الكاملة تتيح لنا تقديم أسعار تنافسية وممتازة.',
        'used_factor_3_desc_en' => 'Bulk racking lots and entire warehouse liquidations allow us to offer premium rates.',
        'used_factor_4_title_ar' => 'موقع الأرفف وتسهيلات النقل',
        'used_factor_4_title_en' => 'Location & Dismantling Work',
        'used_factor_4_desc_ar' => 'هل هي مفككة وجاهزة للنقل في شاحناتنا أم تتطلب عملاً شاقاً لفريقنا للفك والتحميل.',
        'used_factor_4_desc_en' => 'Whether shelves are already uninstalled and palletized or require full dismantling work.',

        // FAQs
        'used_faq_title_ar' => 'أسئلة شائعة حول شراء الأرفف المستعملة',
        'used_faq_title_en' => 'Used Shelving Purchase FAQs',
        'used_faq_1_q_ar' => 'هل تشترون كميات قليلة من الأرفف المستعملة؟',
        'used_faq_1_q_en' => 'Do you buy small lots of used shelves?',
        'used_faq_1_a_ar' => 'نعم، نشتري جميع الكميات سواء كانت صغيرة للمحلات الفردية أو كميات كبرى للمستودعات اللوجستية والشركات.',
        'used_faq_1_a_en' => 'Yes, we buy all volumes, from single shop shelving rows to massive logistics warehouse liquidations.',
        'used_faq_2_q_ar' => 'هل تتحملون تكاليف الفك والنقل؟',
        'used_faq_2_q_en' => 'Do you cover dismantling and shipping expenses?',
        'used_faq_2_a_ar' => 'نعم، نتكفل بإرسال فنيين متخصصين للفك الفوري وشاحنات لنقل الأرفف دون تحميل العميل أي تكاليف إضافية.',
        'used_faq_2_a_en' => 'Yes, we dispatch our own professional technicians to dismantle and trucks to transport at no cost to you.',
        'used_faq_3_q_ar' => 'كيف يتم تقييم السعر المالي للأرفف؟',
        'used_faq_3_q_en' => 'How is the purchase offer calculated?',
        'used_faq_3_a_ar' => 'يعتمد التقييم على: حالة الحديد وسلامته من الصدأ، مقاسات الرف وسماكته، والكمية الإجمالية، وموقع العميل الجغرافي.',
        'used_faq_3_a_en' => 'We evaluate used racks based on condition, thickness/load class, lot size, and client location.',
        'used_faq_4_q_ar' => 'هل يمكنني إرسال الصور مباشرة عبر واتساب؟',
        'used_faq_4_q_en' => 'Can I send photos directly on WhatsApp?',
        'used_faq_4_a_ar' => 'نعم، تواصل معنا عبر واتساب وأرسل الصور والمقاسات للحصول على تسعير مبدئي وسريع جداً.',
        'used_faq_4_a_en' => 'Yes, message us on WhatsApp with photos and sizing for an immediate preliminary valuation.',
        'used_faq_5_q_ar' => 'هل تشترون الأرفف التالفة أو المكسورة؟',
        'used_faq_5_q_en' => 'Do you buy damaged or heavily rusted racks?',
        'used_faq_5_a_ar' => 'نشتري الأرفف الصالحة للاستخدام الآمن فقط. الأرفف التي بها صدأ شديد أو التواءات حرجة قد لا نقبل شراءها حرصاً على السلامة والأمان.',
        'used_faq_5_a_en' => 'We only buy racks fit for safe re-use. Highly rusted, bent, or broken racks are rejected for safety reasons.',

        // CTA Section
        'cta_used_title_ar' => 'لديك أرفف مستعملة وتريد التخلص منها بأفضل سعر؟',
        'cta_used_title_en' => 'Have old racking lots you want to liquidate for top dollar?',
        'cta_used_desc_ar' => 'نشتري كافة كميات الأرفف المستعملة وندفع لك فوراً، مع تحملنا لكامل تكاليف الفك والتحميل والنقل.',
        'cta_used_desc_en' => 'We purchase all used metal shelving and pay you instantly, covering all dismantling and logistics costs ourselves.',
        'cta_used_whatsapp_msg_ar' => 'السلام عليكم، أرغب في بيع أرفف مستعملة وأود التنسيق معكم.',
        'cta_used_whatsapp_msg_en' => 'Hello, I have used shelves to liquidate and would like to coordinate.',

        // SEO Overrides
        'seo_used_title_ar' => '',
        'seo_used_title_en' => '',
        'seo_used_desc_ar' => '',
        'seo_used_desc_en' => '',
        'seo_used_og_image' => '',

        // Sprint 9: Installation CMS Defaults
        'install_show_hero' => '1',
        'install_order_hero' => '10',
        'install_show_where' => '1',
        'install_order_where' => '20',
        'install_show_process' => '1',
        'install_order_process' => '30',
        'install_show_why' => '1',
        'install_order_why' => '40',
        'install_show_cta' => '1',
        'install_order_cta' => '50',

        // Hero Content
        'install_hero_title_ar' => 'تركيب الأرفف الحديدية باحترافية وأمان مطلق',
        'install_hero_title_en' => 'Professional & Anchor-Safe Racking Installation',
        'install_hero_subtitle_ar' => 'فريق متخصص في تركيب وتثبيت الأرفف الحديدية للمستودعات الكبرى، المحلات، غرف التخزين، والمنازل بأعلى معايير السلامة وثبات التحمل.',
        'install_hero_subtitle_en' => 'Specialized team installing and anchoring iron shelves for industrial warehouses, cold stores, retail shops, and archives under strict safety codes.',
        'install_hero_cta_label_ar' => 'احجز موعد معاينة تركيب عبر واتساب',
        'install_hero_cta_label_en' => 'Book Installation Inspection on WhatsApp',
        'install_hero_secondary_label_ar' => 'الخدمات',
        'install_hero_secondary_label_en' => 'Services',
        'install_hero_wa_msg_ar' => 'السلام عليكم، أود حجز موعد معاينة تركيب أرفف حديدية، وسأقوم أرسل المقاسات وصور الموقع الآن.',
        'install_hero_wa_msg_en' => 'Hello, I would like to book a site inspection for racking installation. I will send space dimensions and photos now.',

        // Where We Install Content
        'install_where_title_ar' => 'أين نقوم بتركيب وتثبيت الأرفف؟',
        'install_where_title_en' => 'Where Do We Install Shelves?',
        'install_where_desc_ar' => 'نوفر خدمات التركيب لمختلف المساحات والأنشطة التجارية والسكنية بأعلى دقة.',
        'install_where_desc_en' => 'We provide accurate racking assembly and anchoring for various storage environments.',

        'install_loc_1_title_ar' => 'المستودعات والمراكز اللوجستية الكبرى',
        'install_loc_1_title_en' => 'Logistics Warehouses & Depots',
        'install_loc_1_desc_ar' => 'تركيب وتأمين الأرفف الصناعية الضخمة وتثبيتها بالأرديات لضمان عدم السقوط أثناء التحميل.',
        'install_loc_1_desc_en' => 'Assembling and floor-anchoring large industrial racking to prevent collapses during forklift loading.',

        'install_loc_2_title_ar' => 'غرف التبريد ومخازن الأغذية',
        'install_loc_2_title_en' => 'Cold Storages & Food Facilities',
        'install_loc_2_desc_ar' => 'تركيب أرفف مقاومة للرطوبة والصدأ في مستودعات الأغذية والأدوية والمطاعم للامتثال للشروط الصحية.',
        'install_loc_2_desc_en' => 'Installing rust-proof and hygienic racking systems for restaurants, pharma, and food cold rooms.',

        'install_loc_3_title_ar' => 'المحلات والمعارض التجارية',
        'install_loc_3_title_en' => 'Retail Stores & Commercial Showrooms',
        'install_loc_3_desc_ar' => 'تركيب أرفف العرض الجدارية والاستعراضية لضمان ترتيب وتنسيق رائع يسهل وصول العملاء للمنتجات.',
        'install_loc_3_desc_en' => 'Setting up display racks, wall shelving, and checkout counters for clear product showcasing.',

        'install_loc_4_title_ar' => 'غرف التخزين المنزلية والمكاتب',
        'install_loc_4_title_en' => 'Office Archives & Home Storages',
        'install_loc_4_desc_ar' => 'أرفف خفيفة ومتوسطة لتنظيم الأغراض المنزلية والملفات المكتبية واستغلال المساحة المتاحة بشكل أمثل.',
        'install_loc_4_desc_en' => 'Medium and light-duty shelving layouts to neatly organize archive files and home goods.',

        // Installation Steps Content
        'install_phases_title_ar' => 'مراحل وخطوات التركيب الاحترافي للأرفف',
        'install_phases_title_en' => 'Steps of Professional Racking Installation',
        'install_phases_desc_ar' => 'نلتزم بخطة عمل دقيقة تضمن السرعة والالتزام بأعلى معايير الجودة والأمان.',
        'install_phases_desc_en' => 'We commit to a rigid workflow that guarantees speed, accuracy, and absolute safety.',

        'install_step_1_title_ar' => 'معاينة وتخطيط المساحة',
        'install_step_1_title_en' => 'Inspection & Space Layout Design',
        'install_step_1_desc_ar' => 'يقوم خبراؤنا بزيارة الموقع مجاناً لمعاينة المكان وتحديد المقاسات وتخطيط توزيع الأوزان.',
        'install_step_1_desc_en' => 'Our site inspectors visit your facility for free to measure space, plan aisles, and verify floor level.',

        'install_step_2_title_ar' => 'اختيار نوع وسماكة الرف',
        'install_step_2_title_en' => 'Selecting Racking Specs',
        'install_step_2_desc_ar' => 'نساعدك في اختيار الأرفف المناسبة لنوع البضائع وحجم الحمولة المتوقعة (خفيف، متوسط، ثقيل).',
        'install_step_2_desc_en' => 'We assist in selecting appropriate beam and upright load classes (light, medium, or heavy-duty).',

        'install_step_3_title_ar' => 'التركيب الفعلي والتثبيت',
        'install_step_3_title_en' => 'Assembly & Anchor Bolt Securing',
        'install_step_3_desc_ar' => 'يقوم فنيونا بتركيب وتجميع أجزاء الأرفف وتثبيتها بشكل آمن ومحكم في الأرضيات والجدران.',
        'install_step_3_desc_en' => 'Our crew carries out assembly, securing frame bracing and anchoring posts firmly to concrete.',

        'install_step_4_title_ar' => 'اختبار الثبات والتحمل',
        'install_step_4_title_en' => 'Balance & Capacity Verification',
        'install_step_4_desc_ar' => 'نتأكد من استقامة الأرفف وتوازنها ونقوم باختبار قدرتها على تحمل البضائع بأمان تام.',
        'install_step_4_desc_en' => 'We inspect alignment, leveling, locking pins, and verify safe loading capacity before handover.',

        // Why Professional Installation Section
        'install_why_title_ar' => 'لماذا يجب الاستعانة بفريق تركيب متخصص؟',
        'install_why_title_en' => 'Why Hire Professional Installers?',

        'install_why_1_title_ar' => 'أمان مطلق وحماية من الانهيار',
        'install_why_1_title_en' => 'Absolute Safety & Prevent Collapses',
        'install_why_1_desc_ar' => 'التركيب الخاطئ للأرفف الثقيلة يشكل خطراً كبيراً على الأرواح والمنتجات، فريقنا يضمن تثبيتها وتأمينها.',
        'install_why_1_desc_en' => 'Improper rack assembly poses severe risks to staff and goods. Professional anchoring prevents falls.',

        'install_why_2_title_ar' => 'توزيع مثالي ومدروس للأوزان',
        'install_why_2_title_en' => 'Optimized Weight & Load Distribution',
        'install_why_2_desc_ar' => 'نضمن توزيع الأحمال بالتساوي على القوائم والعوارض لتفادي انحناء أو التواء الحديد على المدى الطويل.',
        'install_why_2_desc_en' => 'We ensure loads are evenly distributed across beams and uprights to avoid steel fatigue or bending.',

        'install_why_3_title_ar' => 'استغلال ذكي لكامل المساحة المتاحة',
        'install_why_3_title_en' => 'Smart Storage Space Optimization',
        'install_why_3_desc_ar' => 'خبرتنا تتيح لنا ترتيب الممرات وارتفاعات الأرفف لاستغلال كل متر مربع بشكل يزيد الكفاءة.',
        'install_why_3_desc_en' => 'We layout storage systems to minimize aisle waste, maximizing pallet capacity per square meter.',

        // Call to Action
        'cta_install_title_ar' => 'أرسل مقاسات مستودعك أو صور المكان الآن، واحجز موعد معاينة تركيب مجاني!',
        'cta_install_title_en' => 'Send your warehouse dimensions or site photos now, and book a free inspection!',
        'cta_install_desc_ar' => 'يتكفل فريقنا الفني بفحص الموقع وتخطيط المساحة وتثبيت الأرفف بالخرسانة لضمان استقرارها الكامل.',
        'cta_install_desc_en' => 'Our crew surveys your space, plans optimal aisle widths, and anchors upright frames to concrete.',
        'cta_install_whatsapp_msg_ar' => 'السلام عليكم، أرغب في حجز موعد معاينة لتركيب أرفف حديدية.',
        'cta_install_whatsapp_msg_en' => 'Hello, I want to book a site inspection for shelving installation.',

        // SEO Overrides
        'seo_install_title_ar' => '',
        'seo_install_title_en' => '',
        'seo_install_desc_ar' => '',
        'seo_install_desc_en' => '',
        'seo_install_og_image' => '',
        
        // Sprint: Why Choose Services Cards
        'services_why_1_title_ar' => 'معاينة مجانية ودقيقة',
        'services_why_1_title_en' => 'Free & Accurate Site Inspection',
        'services_why_1_desc_ar' => 'نقدم خدمة فحص الموقع وأخذ المقاسات بدقة متناهية مجاناً لضمان التصميم والتركيب الأمثل.',
        'services_why_1_desc_en' => 'We provide space survey and dimensions check with extreme precision for free to guarantee the best fit.',
        'services_why_1_icon' => 'scan',
        'services_why_1_image' => '',

        'services_why_2_title_ar' => 'فريق فني محترف',
        'services_why_2_title_en' => 'Professional Technical Crew',
        'services_why_2_desc_ar' => 'فريقنا مجهز ومدرب على أعلى المستويات لضمان تثبيت الأرفف بأمان تام ومطابقة المعايير الهندسية.',
        'services_why_2_desc_en' => 'Our crew is highly trained and equipped to ensure racking anchoring with maximum safety and standards compliance.',
        'services_why_2_icon' => 'users',
        'services_why_2_image' => '',

        'services_why_3_title_ar' => 'أسعار منافسة وعادلة',
        'services_why_3_title_en' => 'Competitive & Fair Pricing',
        'services_why_3_desc_ar' => 'نقدم تسعيراً شفافاً وحلولاً تناسب ميزانيتك دون أي تكاليف خفية، مع الحفاظ على أعلى مستويات الجودة.',
        'services_why_3_desc_en' => 'We offer transparent pricing and custom solutions that fit your budget with zero hidden fees while retaining peak quality.',
        'services_why_3_icon' => 'badge-dollar-sign',
        'services_why_3_image' => '',

        'services_why_4_title_ar' => 'التزام تام بالمواعيد',
        'services_why_4_title_en' => 'Total Commitment to Timelines',
        'services_why_4_desc_ar' => 'نحترم وقت عملائنا ونلتزم بجدول زمني محدد لعمليات التوريد والتركيب والفك والنقل دون أي تأخير.',
        'services_why_4_desc_en' => 'We respect our client\'s time and stick to a strict timeline for delivery, setup, and teardown with no delays.',
        'services_why_4_icon' => 'timer',
        'services_why_4_image' => '',

        'services_why_5_title_ar' => 'خيارات وحلول متنوعة',
        'services_why_5_title_en' => 'Diverse Options & Solutions',
        'services_why_5_desc_ar' => 'سواء كنت بحاجة لرفوف جدارية أنيقة للمنزل أو أنظمة تخزين ثقيلة لمستودعك، فلدينا الحل المناسب.',
        'services_why_5_desc_en' => 'Whether you need elegant floating shelves for home or heavy pallet racking for your warehouse, we have the answer.',
        'services_why_5_icon' => 'boxes',
        'services_why_5_image' => '',

        'services_why_6_title_ar' => 'ضمان ودعم مستمر',
        'services_why_6_title_en' => 'Warranty & Continuous Support',
        'services_why_6_desc_ar' => 'نوفر ضماناً حقيقياً على جودة المواد والتركيب، مع دعم فني متواصل للإجابة على كافة استفساراتكم.',
        'services_why_6_desc_en' => 'We provide structural warranty on materials and assembly, coupled with dedicated post-service customer support.',
        'services_why_6_icon' => 'handshake',
        'services_why_6_image' => ''
    ];
    
    foreach ($default_settings as $k => $v) {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = :key_name");
        $check_stmt->execute([':key_name' => $k]);
        if ($check_stmt->fetchColumn() == 0) {
            $insert_stmt = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:key_name, :val)");
            $insert_stmt->execute([':key_name' => $k, ':val' => $v]);
        }
    }
}

/**
 * Seeds the default categories into the database if they do not exist.
 * 
 * @param PDO $pdo The active database connection
 */
function seed_default_categories(PDO $pdo) {
    $check_cat = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($check_cat->fetchColumn() == 0) {
        $default_categories = [
            ['code' => 'wall', 'name_ar' => 'رفوف جدارية', 'name_en' => 'Wall Shelves', 'icon' => 'layers'],
            ['code' => 'standing', 'name_ar' => 'رفوف قائمة', 'name_en' => 'Standing Bookcases', 'icon' => 'columns'],
            ['code' => 'industrial', 'name_ar' => 'رفوف مستودعات/صناعية', 'name_en' => 'Industrial/Warehouse Shelves', 'icon' => 'package'],
            ['code' => 'decorative', 'name_ar' => 'رفوف ديكورية', 'name_en' => 'Decorative Shelves', 'icon' => 'sparkles']
        ];
        $insert_cat = $pdo->prepare("INSERT INTO categories (code, name_ar, name_en, icon) VALUES (:code, :name_ar, :name_en, :icon)");
        foreach ($default_categories as $c) {
            $insert_cat->execute($c);
        }
    }
}

/**
 * Seeds the default features into the database if they do not exist.
 * 
 * @param PDO $pdo The active database connection
 */
function seed_default_features(PDO $pdo) {
    $check_feat = $pdo->query("SELECT COUNT(*) FROM features");
    if ($check_feat->fetchColumn() == 0) {
        $default_features = [
            [
                'title_ar' => 'جودة ممتازة',
                'title_en' => 'Premium Quality',
                'description_ar' => 'نختار أجود أنواع الخشب والمعادن لضمان قوة تحمل الرفوف وجمال مظهرها.',
                'description_en' => 'We select the finest wood and metal materials to ensure maximum durability and aesthetics.',
                'icon' => 'shield-check'
            ],
            [
                'title_ar' => 'تركيب احترافي',
                'title_en' => 'Professional Installation',
                'description_ar' => 'فريق عمل متخصص جاهز لتركيب الرفوف بدقة وأمان تام في مكانك.',
                'description_en' => 'Our specialized team is ready to install shelves with ultimate precision and safety at your place.',
                'icon' => 'wrench'
            ],
            [
                'title_ar' => 'شحن سريع',
                'title_en' => 'Fast Shipping',
                'description_ar' => 'نقوم بتوصيل طلباتك في أسرع وقت وبكل سلاسة لجميع مناطق الخدمة.',
                'description_en' => 'We deliver your orders as fast as possible to all available service locations.',
                'icon' => 'truck'
            ]
        ];
        $insert_feat = $pdo->prepare("INSERT INTO features (title_ar, title_en, description_ar, description_en, icon) VALUES (:title_ar, :title_en, :description_ar, :description_en, :icon)");
        foreach ($default_features as $f) {
            $insert_feat->execute($f);
        }
    }
}

/**
 * Copies generated artifact images into the assets/images directory if they don't exist.
 * This function is a development-only utility and will silently no-op if ARTIFACTS_PATH
 * is not defined (i.e. on production servers).
 */
function copy_artifact_images_if_needed() {
    // Bail out safely if ARTIFACTS_PATH is not defined (production server)
    if (!defined('ARTIFACTS_PATH') || !is_dir(ARTIFACTS_PATH)) {
        return;
    }

    $img_dir = UPLOADS_PATH;
    if (!file_exists($img_dir)) {
        mkdir($img_dir, 0755, true);
    }

    $artifact_dir = ARTIFACTS_PATH;
    $image_mappings = [
        'shelf_floating_oak'    => 'shelf1.png',
        'shelf_industrial_metal' => 'shelf2.png',
        'shelf_corner_tripod'   => 'shelf3.png',
        'shelf_standing_bookcase' => 'shelf4.png',
        'shelf_warehouse_iron'  => 'shelf5.png',
    ];

    foreach ($image_mappings as $prefix => $target_name) {
        $target_path = $img_dir . '/' . $target_name;
        if (!file_exists($target_path)) {
            $pattern = $artifact_dir . '/' . $prefix . '_*.png';
            $files = glob($pattern);
            if ($files && count($files) > 0) {
                copy($files[0], $target_path);
            }
        }
    }
}

/**
 * Seeds the default services into the database if they do not exist.
 * Idempotent, transaction-safe, seed-once using persistent marker.
 *
 * @param PDO $pdo The active database connection
 */
function seed_default_services(PDO $pdo) {
    try {
        // Check for persistent initialization marker
        $marker_stmt = $pdo->prepare("SELECT val FROM settings WHERE key_name = 'services_initial_seed_completed' LIMIT 1");
        $marker_stmt->execute();
        $marker = $marker_stmt->fetchColumn();

        if ($marker === '1') {
            // Already initialized. Never seed again.
            return;
        }

        // Marker is missing. Inspect services table.
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM services");
        $services_count = intval($count_stmt->fetchColumn());

        if ($services_count > 0) {
            // Table already contains records. Do not seed defaults. Mark as completed.
            $check_s = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = 'services_initial_seed_completed'");
            $check_s->execute();
            if ($check_s->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET val = '1' WHERE key_name = 'services_initial_seed_completed'")->execute();
            } else {
                $pdo->prepare("INSERT INTO settings (key_name, val) VALUES ('services_initial_seed_completed', '1')")->execute();
            }
            return;
        }

        // Table is empty. Begin transaction and seed the six default services.
        $in_transaction = $pdo->inTransaction();
        if (!$in_transaction) {
            $pdo->beginTransaction();
        }

        $default_services = [
            [
                ':slug' => 'new-shelves-sale',
                ':title_ar' => 'بيع أرفف حديدية جديدة',
                ':title_en' => 'Sell New Iron Shelves',
                ':short_desc_ar' => 'نوفر أرففاً حديدية جديدة بمقاسات ومواصفات مختلفة لتناسب المحلات والمستودعات.',
                ':short_desc_en' => 'We supply new iron shelves in various sizes and specs to fit shops and warehouses.',
                ':icon' => 'package-search',
                ':benefit_1_ar' => 'حديد عالي الجودة وسماكات متعددة',
                ':benefit_1_en' => 'High quality steel & multiple thicknesses',
                ':benefit_2_ar' => 'تحمل الأوزان الثقيلة بأمان',
                ':benefit_2_en' => 'Safely carries heavy weights',
                ':benefit_3_ar' => 'ضمان طويل المدى ضد العيوب المصنعية',
                ':benefit_3_en' => 'Long-term warranty against manufacturer defects',
                ':custom_action' => 'products',
                ':custom_action_label_ar' => 'تصفح المنتجات الجديدة',
                ':custom_action_label_en' => 'Browse New Products',
                ':homepage_action' => 'products',
                ':homepage_action_label_ar' => 'تصفح المنتجات',
                ':homepage_action_label_en' => 'Browse Products',
                ':is_featured' => 1,
                ':is_active' => 1,
                ':sort_order' => 10
            ],
            [
                ':slug' => 'shelves-installation',
                ':title_ar' => 'تركيب أرفف للمخازن والمحلات',
                ':title_en' => 'Warehouse & Shop Shelves Installation',
                ':short_desc_ar' => 'فريق فني متخصص لتركيب وتثبيت الأرفف الحديدية بأعلى معايير الأمان والسلامة.',
                ':short_desc_en' => 'A specialized team of technicians to install and secure iron shelves to the highest safety standards.',
                ':icon' => 'wrench',
                ':benefit_1_ar' => 'تثبيت وتثقيب آمن في الخرسانة',
                ':benefit_1_en' => 'Safe anchoring & bolting in concrete',
                ':benefit_2_ar' => 'ضبط مستوى الارتفاعات بدقة تامة',
                ':benefit_2_en' => 'Precise alignment and leveling',
                ':benefit_3_ar' => 'تركيب متوافق مع معايير السلامة',
                ':benefit_3_en' => 'Compliance with storage safety regulations',
                ':custom_action' => 'installation',
                ':custom_action_label_ar' => 'تفاصيل خدمة التركيب',
                ':custom_action_label_en' => 'Installation Service Details',
                ':homepage_action' => 'installation',
                ':homepage_action_label_ar' => 'تفاصيل التركيب',
                ':homepage_action_label_en' => 'Installation Details',
                ':is_featured' => 1,
                ':is_active' => 1,
                ':sort_order' => 20
            ],
            [
                ':slug' => 'used-shelves-buying',
                ':title_ar' => 'شراء أرفف مستعملة',
                ':title_en' => 'Buy Used Shelves',
                ':short_desc_ar' => 'نشتري الأرفف الحديدية المستعملة بأسعار عادلة ومناسبة للجميع مع الفك والنقل.',
                ':short_desc_en' => 'We buy used iron shelves at fair prices, including dismantling and transportation.',
                ':icon' => 'recycle',
                ':benefit_1_ar' => 'شراء الكميات الفردية والمستودعات الكبيرة',
                ':benefit_1_en' => 'Buying small shops & large warehouse lots',
                ':benefit_2_ar' => 'دفع فوري نقداً أو بتحويل بنكي',
                ':benefit_2_en' => 'Instant cash payment or bank transfer',
                ':benefit_3_ar' => 'نتكفل بالكامل بأعمال الفك والنقل',
                ':benefit_3_en' => 'We fully handle dismantling & shipping',
                ':custom_action' => 'used_shelves',
                ':custom_action_label_ar' => 'تفاصيل بيع أرفف مستعملة',
                ':custom_action_label_en' => 'Used Shelves Sale Details',
                ':homepage_action' => 'used_shelves',
                ':homepage_action_label_ar' => 'بيع رفوف مستعملة',
                ':homepage_action_label_en' => 'Sell Used Shelves',
                ':is_featured' => 1,
                ':is_active' => 1,
                ':sort_order' => 30
            ],
            [
                ':slug' => 'dismantling-relocation',
                ':title_ar' => 'فك ونقل الأرفف',
                ':title_en' => 'Dismantling & Moving Shelves',
                ':short_desc_ar' => 'نقوم بفك الأرفف وتوضيبها بشكل آمن، مع نقلها وإعادة تركيبها في موقعكم الجديد بالدقة المطلوبة.',
                ':short_desc_en' => 'We dismantle and pack shelves securely, transporting and reassembling them at your new location with precision.',
                ':icon' => 'truck',
                ':benefit_1_ar' => 'سيارات نقل مجهزة لمختلف أطوال الأعمدة',
                ':benefit_1_en' => 'Equipped transport vehicles for long uprights',
                ':benefit_2_ar' => 'تفكيك احترافي يمنع التواء الحديد',
                ':benefit_2_en' => 'Professional dismantling preventing steel bending',
                ':benefit_3_ar' => 'إعادة تركيب وتثبيت فوري في الموقع الجديد',
                ':benefit_3_en' => 'Immediate reassembly & anchoring at new site',
                ':custom_action' => 'none',
                ':custom_action_label_ar' => '',
                ':custom_action_label_en' => '',
                ':homepage_action' => 'services',
                ':homepage_action_label_ar' => 'تفاصيل الفك والنقل',
                ':homepage_action_label_en' => 'Dismantling Details',
                ':is_featured' => 1,
                ':is_active' => 1,
                ':sort_order' => 40
            ],
            [
                ':slug' => 'storage-solutions',
                ':title_ar' => 'توريد حلول تخزين للمستودعات',
                ':title_en' => 'Warehouse Storage Solutions Supply',
                ':short_desc_ar' => 'نقدم استشارات هندسية وتخطيطية لمسارات وممرات مستودعك لرفع الكفاءة التشغيلية ومضاعفة السعة.',
                ':short_desc_en' => 'We offer technical layout and routing advice for your warehouse to boost operation efficiency & double capacity.',
                ':icon' => 'layout-grid',
                ':benefit_1_ar' => 'رسم كروكي وتصميم ثلاثي الأبعاد للمستودع',
                ':benefit_1_en' => 'Site sketching and 3D warehouse layout',
                ':benefit_2_ar' => 'تحديد ممرات حركة الرافعات الشوكية',
                ':benefit_2_en' => 'Planning optimal forklift transit paths',
                ':benefit_3_ar' => 'زيادة سعة البالتات بنسبة تصل إلى 40%',
                ':benefit_3_en' => 'Increasing pallet capacity by up to 40%',
                ':custom_action' => 'none',
                ':custom_action_label_ar' => '',
                ':custom_action_label_en' => '',
                ':homepage_action' => 'services',
                ':homepage_action_label_ar' => 'حلول التخزين',
                ':homepage_action_label_en' => 'Storage Solutions',
                ':is_featured' => 1,
                ':is_active' => 1,
                ':sort_order' => 50
            ],
            [
                ':slug' => 'shelves-maintenance',
                ':title_ar' => 'صيانة وتعديل الأرفف الحالية',
                ':title_en' => 'Maintenance & Modification',
                ':short_desc_ar' => 'نعيد فحص أرففك الحالية وصيانتها وتغيير القوائم أو العوارض الملتوية مع تعديل مستويات الارتفاع عند الطلب.',
                ':short_desc_en' => 'We inspect your existing racks, repair/replace bent columns/beams, and modify height tiers upon request.',
                ':icon' => 'shield-check',
                ':benefit_1_ar' => 'استبدال الأجزاء المتضررة بمواصفات مطابقة',
                ':benefit_1_en' => 'Replacing damaged components with match specs',
                ':benefit_2_ar' => 'فحص البراغي وأوتاد التثبيت الأرضية',
                ':benefit_2_en' => 'Inspecting bolts and concrete anchor anchors',
                ':benefit_3_ar' => 'أمان مستمر وحماية للبضائع والموظفين',
                ':benefit_3_en' => 'Ongoing safety & protection for goods/staff',
                ':custom_action' => 'none',
                ':custom_action_label_ar' => '',
                ':custom_action_label_en' => '',
                ':homepage_action' => 'services',
                ':homepage_action_label_ar' => 'تفاصيل الصيانة',
                ':homepage_action_label_en' => 'Maintenance Details',
                ':is_featured' => 1,
                ':is_active' => 1,
                ':sort_order' => 60
            ]
        ];

        $insert_stmt = $pdo->prepare("INSERT INTO services (
            slug, title_ar, title_en, short_desc_ar, short_desc_en, icon,
            benefit_1_ar, benefit_1_en, benefit_2_ar, benefit_2_en, benefit_3_ar, benefit_3_en,
            custom_action, custom_action_label_ar, custom_action_label_en,
            homepage_action, homepage_action_label_ar, homepage_action_label_en,
            is_featured, is_active, sort_order
        ) VALUES (
            :slug, :title_ar, :title_en, :short_desc_ar, :short_desc_en, :icon,
            :benefit_1_ar, :benefit_1_en, :benefit_2_ar, :benefit_2_en, :benefit_3_ar, :benefit_3_en,
            :custom_action, :custom_action_label_ar, :custom_action_label_en,
            :homepage_action, :homepage_action_label_ar, :homepage_action_label_en,
            :is_featured, :is_active, :sort_order
        )");

        foreach ($default_services as $service) {
            $insert_stmt->execute($service);
        }

        // Seed initialization marker key
        $marker_check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = 'services_initial_seed_completed'");
        $marker_check->execute();
        if ($marker_check->fetchColumn() > 0) {
            $pdo->prepare("UPDATE settings SET val = '1' WHERE key_name = 'services_initial_seed_completed'")->execute();
        } else {
            $pdo->prepare("INSERT INTO settings (key_name, val) VALUES ('services_initial_seed_completed', '1')")->execute();
        }

        if (!$in_transaction) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if (isset($in_transaction) && !$in_transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[WorldOfShelves] Services seeding failed: " . $e->getMessage());
    }
}
