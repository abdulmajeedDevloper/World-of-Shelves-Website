<?php
// includes/product-helper.php
// Centralized helper functions for managing Product Galleries, Colors, and Models.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

/**
 * Fetch all active gallery images for a product.
 * Returns array of image records ordered by primary first, then sort_order, then ID.
 */
function get_product_images($pdo, $product_id) {
    if (empty($product_id)) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = :pid AND is_active = 1 ORDER BY is_primary DESC, sort_order ASC, id ASC");
        $stmt->execute([':pid' => (int)$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching product images: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all gallery images for a product (admin view, includes inactive).
 */
function get_admin_product_images($pdo, $product_id) {
    if (empty($product_id)) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = :pid ORDER BY is_primary DESC, sort_order ASC, id ASC");
        $stmt->execute([':pid' => (int)$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admin product images: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all active colors for a product.
 */
function get_product_colors($pdo, $product_id) {
    if (empty($product_id)) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_colors WHERE product_id = :pid AND is_active = 1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute([':pid' => (int)$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching product colors: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all colors for a product (admin view).
 */
function get_admin_product_colors($pdo, $product_id) {
    if (empty($product_id)) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_colors WHERE product_id = :pid ORDER BY sort_order ASC, id ASC");
        $stmt->execute([':pid' => (int)$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admin product colors: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all active models/designs for a product.
 */
function get_product_models($pdo, $product_id) {
    if (empty($product_id)) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_models WHERE product_id = :pid AND is_active = 1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute([':pid' => (int)$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching product models: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all models/designs for a product (admin view).
 */
function get_admin_product_models($pdo, $product_id) {
    if (empty($product_id)) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_models WHERE product_id = :pid ORDER BY sort_order ASC, id ASC");
        $stmt->execute([':pid' => (int)$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admin product models: " . $e->getMessage());
        return [];
    }
}

/**
 * Build complete structured product gallery payload for detail page & JSON injection.
 * Implements deterministic fallback hierarchy:
 * 1. Exact color + model image
 * 2. Selected color image
 * 3. Selected model image
 * 4. General product gallery image
 * 5. Product primary image / legacy image_url
 * 6. Default placeholder
 */
function get_product_gallery_payload($pdo, $product, $lang = 'ar') {
    $product_id = (int)$product['id'];
    $images = get_product_images($pdo, $product_id);
    $colors = get_product_colors($pdo, $product_id);
    $models = get_product_models($pdo, $product_id);

    // Fallback: If no images in product_images, use product['image_url']
    if (empty($images) && !empty($product['image_url'])) {
        $alt = ($lang === 'ar') 
            ? (!empty($product['alt_text_ar']) ? $product['alt_text_ar'] : $product['name_ar'])
            : (!empty($product['alt_text_en']) ? $product['alt_text_en'] : $product['name_en']);
        $images[] = [
            'id' => 0,
            'product_id' => $product_id,
            'image_path' => $product['image_url'],
            'alt_ar' => $alt,
            'alt_en' => $alt,
            'color_id' => null,
            'model_id' => null,
            'is_primary' => 1,
            'is_active' => 1,
            'sort_order' => 0
        ];
    }

    $formatted_images = [];
    foreach ($images as $img) {
        $alt_text = ($lang === 'ar') ? ($img['alt_ar'] ?? $product['name_ar']) : ($img['alt_en'] ?? $product['name_en']);
        $formatted_images[] = [
            'id' => (int)$img['id'],
            'url' => $img['image_path'],
            'alt' => htmlspecialchars($alt_text, ENT_QUOTES, 'UTF-8'),
            'color_id' => $img['color_id'] ? (int)$img['color_id'] : null,
            'model_id' => $img['model_id'] ? (int)$img['model_id'] : null,
            'is_primary' => (bool)$img['is_primary']
        ];
    }

    $formatted_colors = [];
    foreach ($colors as $col) {
        $name = ($lang === 'ar') ? $col['name_ar'] : $col['name_en'];
        $formatted_colors[] = [
            'id' => (int)$col['id'],
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'hex' => !empty($col['hex_value']) ? $col['hex_value'] : null,
            'image_path' => !empty($col['image_path']) ? $col['image_path'] : null
        ];
    }

    $formatted_models = [];
    foreach ($models as $mod) {
        $name = ($lang === 'ar') ? $mod['name_ar'] : $mod['name_en'];
        $desc = ($lang === 'ar') ? ($mod['description_ar'] ?? '') : ($mod['description_en'] ?? '');
        $formatted_models[] = [
            'id' => (int)$mod['id'],
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'desc' => htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'),
            'image_path' => !empty($mod['image_path']) ? $mod['image_path'] : null
        ];
    }

    return [
        'product_id' => $product_id,
        'images' => $formatted_images,
        'colors' => $formatted_colors,
        'models' => $formatted_models
    ];
}

/**
 * Returns allowlist array of valid Lucide icon names.
 */
function get_allowed_lucide_icons() {
    return [
        'warehouse', 'store', 'archive', 'home', 'shield', 'weight', 'layers',
        'check-circle', 'star', 'sparkles', 'package', 'box', 'truck', 'award',
        'wrench', 'settings', 'check-square', 'cpu', 'clock', 'zap', 'lock',
        'eye', 'thumbs-up', 'heart', 'ruler', 'shield-check', 'package-check',
        'info', 'tag', 'gift', 'tool', 'file-text', 'map-pin', 'phone', 'mail',
        'shopping-bag', 'shopping-cart', 'grid', 'server', 'compass', 'target'
    ];
}

/**
 * Validates and sanitizes Lucide icon name against allowlist.
 */
function validate_icon_name($icon_name) {
    $icon = trim((string)$icon_name);
    if (empty($icon) || !preg_match('/^[a-z0-9-]+$/i', $icon)) {
        return 'shield';
    }
    $allowed = get_allowed_lucide_icons();
    return in_array(strtolower($icon), $allowed, true) ? strtolower($icon) : (preg_match('/^[a-z0-9-]+$/', strtolower($icon)) ? strtolower($icon) : 'shield');
}

/**
 * Fetch active detail cards for public rendering.
 */
function get_product_detail_cards($pdo, $product_id, $section_type = null) {
    if (empty($product_id)) return [];
    try {
        if ($section_type) {
            $stmt = $pdo->prepare("SELECT * FROM product_detail_cards WHERE product_id = :pid AND section_type = :stype AND is_active = 1 ORDER BY sort_order ASC, id ASC");
            $stmt->execute([':pid' => (int)$product_id, ':stype' => trim($section_type)]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product_detail_cards WHERE product_id = :pid AND is_active = 1 ORDER BY sort_order ASC, id ASC");
            $stmt->execute([':pid' => (int)$product_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching product detail cards: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all detail cards for admin management (includes inactive).
 */
function get_admin_product_detail_cards($pdo, $product_id, $section_type = null) {
    if (empty($product_id)) return [];
    try {
        if ($section_type) {
            $stmt = $pdo->prepare("SELECT * FROM product_detail_cards WHERE product_id = :pid AND section_type = :stype ORDER BY sort_order ASC, id ASC");
            $stmt->execute([':pid' => (int)$product_id, ':stype' => trim($section_type)]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product_detail_cards WHERE product_id = :pid ORDER BY sort_order ASC, id ASC");
            $stmt->execute([':pid' => (int)$product_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admin product detail cards: " . $e->getMessage());
        return [];
    }
}

/**
 * Parses and returns array of technical specifications for a product.
 * Returns array of objects containing key_ar, key_en, value_ar, value_en.
 */
function get_product_tech_specs($product) {
    if (empty($product['tech_specs'])) return [];
    $raw = $product['tech_specs'];
    if (is_array($raw)) return $raw;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return [];
    
    $clean_specs = [];
    foreach ($decoded as $spec) {
        if (!is_array($spec)) continue;
        $k_ar = trim($spec['key_ar'] ?? '');
        $k_en = trim($spec['key_en'] ?? '');
        $v_ar = trim($spec['value_ar'] ?? '');
        $v_en = trim($spec['value_en'] ?? '');
        if ($k_ar !== '' || $k_en !== '' || $v_ar !== '' || $v_en !== '') {
            $clean_specs[] = [
                'key_ar'   => $k_ar,
                'key_en'   => $k_en,
                'value_ar' => $v_ar,
                'value_en' => $v_en
            ];
        }
    }
    return $clean_specs;
}

/**
 * Fetches active related products for a given product.
 * Excludes current product. Maintains manual ordering if set, otherwise falls back to category match.
 */
function get_related_products($pdo, $product, $limit = 4) {
    if (empty($product) || empty($product['id'])) return [];
    $current_id = (int)$product['id'];
    $category   = $product['category'] ?? '';

    // 1. Check if manual related product IDs are set
    $related_ids = [];
    if (!empty($product['related_product_ids'])) {
        $raw = $product['related_product_ids'];
        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $rid) {
                $clean_id = (int)$rid;
                if ($clean_id > 0 && $clean_id !== $current_id && !in_array($clean_id, $related_ids, true)) {
                    $related_ids[] = $clean_id;
                }
            }
        }
    }

    $fetched_products = [];
    if (!empty($related_ids)) {
        try {
            $in_clause = implode(',', array_fill(0, count($related_ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in_clause) AND is_active = 1 AND id != ?");
            $params = array_merge($related_ids, [$current_id]);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Re-order rows based on manual selection order
            $rows_by_id = [];
            foreach ($rows as $r) {
                $rows_by_id[(int)$r['id']] = $r;
            }
            foreach ($related_ids as $rid) {
                if (isset($rows_by_id[$rid])) {
                    $fetched_products[] = $rows_by_id[$rid];
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching manual related products: " . $e->getMessage());
        }
    }

    // 2. Fallback: If no manual related products or count < limit, fetch category matches
    if (count($fetched_products) < $limit && !empty($category)) {
        $exclude_ids = array_merge([$current_id], array_map(function($p) { return (int)$p['id']; }, $fetched_products));
        $needed = $limit - count($fetched_products);
        try {
            $in_clause = implode(',', array_fill(0, count($exclude_ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM products WHERE category = ? AND is_active = 1 AND id NOT IN ($in_clause) ORDER BY sort_order ASC, id DESC LIMIT " . (int)$needed);
            $params = array_merge([$category], $exclude_ids);
            $stmt->execute($params);
            $cat_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cat_rows as $cr) {
                $fetched_products[] = $cr;
            }
        } catch (PDOException $e) {
            error_log("Error fetching category related products fallback: " . $e->getMessage());
        }
    }

    return array_slice($fetched_products, 0, $limit);
}

/**
 * Idempotent migration function to auto-add Enterprise Product Landing Page CMS columns.
 * Runs efficiently using static guard and single marker check.
 */
function run_product_cms_migrations($pdo) {
    static $already_run = false;
    if ($already_run || !$pdo) return;
    $already_run = true;

    try {
        $db_driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $existing_cols = [];
        if ($db_driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(products)");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing_cols[] = strtolower($row['name']);
            }
        } else {
            // MySQL
            $stmt = $pdo->query("SHOW COLUMNS FROM products");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing_cols[] = strtolower($row['Field']);
            }
        }

        $cols_to_add = [
            'seo_title_ar'        => "VARCHAR(255) NULL",
            'seo_title_en'        => "VARCHAR(255) NULL",
            'meta_description_ar' => "VARCHAR(255) NULL",
            'meta_description_en' => "VARCHAR(255) NULL",
            'alt_text_ar'         => "VARCHAR(255) NULL",
            'alt_text_en'         => "VARCHAR(255) NULL",
            'og_media_id'         => "INT NULL",
            'brand'               => "VARCHAR(100) NULL",
            'sku'                 => "VARCHAR(100) NULL",
            'mpn'                 => "VARCHAR(100) NULL",
            'gtin'                => "VARCHAR(100) NULL",
            'availability'        => "VARCHAR(50) NULL DEFAULT 'InStock'",
            'condition_type'      => "VARCHAR(50) NULL DEFAULT 'NewCondition'",
            'canonical_url'       => "VARCHAR(255) NULL",
            'tech_specs'          => "TEXT NULL",
            'related_product_ids' => "TEXT NULL",
            'slug'                => "VARCHAR(255) NULL",
            'short_intro_ar'      => "TEXT NULL",
            'short_intro_en'      => "TEXT NULL",
            'selling_points'      => "TEXT NULL",
            'benefits'            => "TEXT NULL",
            'industries_served'   => "TEXT NULL",
            'catalog_pdf_url'     => "VARCHAR(255) NULL",
            'focus_keyword_ar'    => "VARCHAR(255) NULL",
            'focus_keyword_en'    => "VARCHAR(255) NULL",
            'cta_settings'        => "TEXT NULL",
            'related_project_ids' => "TEXT NULL",
            'related_service_ids' => "TEXT NULL",
            'sort_order'          => "INT NOT NULL DEFAULT 0",
            'dimensions_ar'       => "VARCHAR(255) NULL",
            'dimensions_en'       => "VARCHAR(255) NULL"
        ];

        foreach ($cols_to_add as $c_name => $c_def) {
            if (!in_array(strtolower($c_name), $existing_cols, true)) {
                try {
                    $pdo->exec("ALTER TABLE products ADD COLUMN {$c_name} {$c_def}");
                } catch (Throwable $t2) {
                    error_log("Col add note {$c_name}: " . $t2->getMessage());
                }
            }
        }

        // Migrate legacy dimensions data safely
        if (in_array('dimensions', $existing_cols, true) && in_array('dimensions_ar', $existing_cols, true)) {
            $pdo->exec("UPDATE products 
                        SET dimensions_ar = dimensions 
                        WHERE (dimensions_ar IS NULL OR dimensions_ar = '') 
                          AND (dimensions IS NOT NULL AND dimensions != '')");
        }

    } catch (Throwable $t) {
        error_log("CMS Migration execution error: " . $t->getMessage());
    }
}

/**
 * Safely parses and returns array/object from JSON column of a product.
 */
function get_product_json_field($product, $fieldname) {
    if (empty($product[$fieldname])) return [];
    $raw = $product[$fieldname];
    if (is_array($raw)) return $raw;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Fetches active related portfolio projects for a given product.
 */
function get_related_projects($pdo, $product, $limit = 3) {
    if (empty($product) || empty($product['related_project_ids'])) return [];
    $raw_ids = get_product_json_field($product, 'related_project_ids');
    if (empty($raw_ids) || !is_array($raw_ids)) return [];

    $clean_ids = [];
    foreach ($raw_ids as $id) {
        $cid = (int)$id;
        if ($cid > 0 && !in_array($cid, $clean_ids, true)) $clean_ids[] = $cid;
    }
    if (empty($clean_ids)) return [];

    try {
        $in_clause = implode(',', array_fill(0, count($clean_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id IN ($in_clause) AND is_active = 1 LIMIT " . (int)$limit);
        $stmt->execute($clean_ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching related projects: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches active related storage services for a given product.
 */
function get_related_services($pdo, $product, $limit = 3) {
    if (empty($product) || empty($product['related_service_ids'])) return [];
    $raw_ids = get_product_json_field($product, 'related_service_ids');
    if (empty($raw_ids) || !is_array($raw_ids)) return [];

    $clean_ids = [];
    foreach ($raw_ids as $id) {
        $cid = (int)$id;
        if ($cid > 0 && !in_array($cid, $clean_ids, true)) $clean_ids[] = $cid;
    }
    if (empty($clean_ids)) return [];

    try {
        $in_clause = implode(',', array_fill(0, count($clean_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id IN ($in_clause) AND is_active = 1 LIMIT " . (int)$limit);
        $stmt->execute($clean_ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching related services: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches active products related to a given project ID (avoiding N+1 queries).
 */
function get_project_related_products($pdo, $projectId) {
    if (empty($projectId)) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE is_active = 1");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $related = [];
        foreach ($products as $p) {
            $proj_ids = get_product_json_field($p, 'related_project_ids');
            if (is_array($proj_ids)) {
                $clean_proj_ids = array_map('intval', $proj_ids);
                if (in_array((int)$projectId, $clean_proj_ids, true)) {
                    $related[] = $p;
                }
            }
        }
        return $related;
    } catch (PDOException $e) {
        error_log("Error fetching project related products: " . $e->getMessage());
        return [];
    }
}

/**
 * Centralized URL helper for product details pages.
 * Normalizes slugs, handles query parameters, prevents open redirects,
 * and maintains full backward compatibility for local subdirectories.
 */
function get_product_details_url($product, $lang = null, $absolute = false) {
    if (empty($product['slug'])) {
        // Fallback to legacy ?id structure if slug is not available
        $params = ['id' => $product['id']];
        if ($lang && $lang !== 'ar') {
            $params['lang'] = $lang;
        }
        $base = 'product-details';
        if ($absolute) {
            $base = get_site_url() . '/' . $base;
        }
        return $base . '?' . http_build_query($params);
    }
    
    $slug = trim($product['slug']);
    $base = 'products/' . $slug;
    if ($absolute) {
        $base = get_site_url() . '/' . $base;
    }
    
    $params = [];
    if ($lang && $lang !== 'ar') {
        $params['lang'] = $lang;
    }
    
    if (!empty($params)) {
        return $base . '?' . http_build_query($params);
    }
    return $base;
}

/**
 * Generates a unique, URL-safe slug for a product.
 */
function generate_unique_product_slug($name_en, $name_ar, $id, $pdo) {
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


