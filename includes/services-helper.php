<?php
// includes/services-helper.php
// Centralized helper functions for the Services Management Module.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

/**
 * Returns allowed icons for services.
 */
function get_allowed_services_icons() {
    return [
        'package-search',
        'wrench',
        'recycle',
        'truck',
        'layout-grid',
        'shield-check',
        'warehouse',
        'package',
        'boxes',
        'settings',
        'ruler',
        'store',
        'building',
        'factory',
        'archive',
        'layers',
        'move',
        'shopping-cart'
    ];
}

/**
 * Returns allowed custom actions for the services.php page listing.
 */
function get_allowed_custom_actions() {
    return ['products', 'installation', 'used_shelves', 'contact', 'none'];
}

/**
 * Returns allowed homepage actions for the index.php homepage preview.
 */
function get_allowed_homepage_actions() {
    return ['products', 'installation', 'used_shelves', 'services', 'whatsapp', 'contact', 'none'];
}

/**
 * Maps an action string to its relative target URL.
 * Safely handles none/empty.
 */
function map_action_to_url($action, $whatsapp_number = '') {
    switch ($action) {
        case 'products':
            return 'products';
        case 'installation':
            return 'installation';
        case 'used_shelves':
            return 'used-shelves';
        case 'services':
            return 'services';
        case 'contact':
            return 'contact';
        case 'whatsapp':
            $num = !empty($whatsapp_number) ? preg_replace('/[^0-9]/', '', $whatsapp_number) : '966500000000';
            return "https://wa.me/{$num}";
        default:
            return '';
    }
}

/**
 * Escapes characters in user input for LIKE search statements in both MySQL and SQLite.
 */
function escape_like_wildcards($term) {
    // Escape backslashes, percent signs, and underscores
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
}

/**
 * Retrieves all active services with in-memory caching and force-reload support.
 */
function get_active_services($pdo, $force_reload = false) {
    static $cache = null;
    if ($force_reload) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $cache;
    } catch (PDOException $e) {
        error_log("Error retrieving active services: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves active featured services for homepage preview with in-memory caching and force-reload support.
 */
function get_active_featured_services($pdo, $force_reload = false) {
    static $cache = null;
    if ($force_reload) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 AND is_featured = 1 ORDER BY sort_order ASC, id ASC");
        $cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $cache;
    } catch (PDOException $e) {
        error_log("Error retrieving active featured services: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves a single service by ID.
 */
function get_service_by_id($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error retrieving service by ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves a single service by slug.
 */
function get_service_by_slug($pdo, $slug) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error retrieving service by slug: " . $e->getMessage());
        return false;
    }
}

/**
 * Generates a unique, alphanumeric slug capped at 150 chars from an English title.
 * Deduplicates with sequential suffix (e.g. -2, -3, etc.).
 */
function generate_service_slug($pdo, $title_en, $current_id = null) {
    // Convert to lowercase, remove non-alphanumeric, replace spaces/underscores with hyphens
    $slug = strtolower(trim($title_en));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s_]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    if (empty($slug)) {
        $slug = 'service';
    }

    // Cap at 150 characters
    if (strlen($slug) > 140) {
        $slug = substr($slug, 0, 140);
        $slug = trim($slug, '-');
    }

    $base_slug = $slug;
    $count = 1;
    
    while (true) {
        if ($count > 1) {
            $slug = $base_slug . '-' . $count;
        }
        
        // Check uniqueness
        if ($current_id !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE slug = :slug AND id != :id");
            $stmt->execute([':slug' => $slug, ':id' => (int)$current_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE slug = :slug");
            $stmt->execute([':slug' => $slug]);
        }
        
        if ((int)$stmt->fetchColumn() === 0) {
            break;
        }
        $count++;
    }

    return $slug;
}
