<?php
// includes/helpers.php

/**
 * Global settings accessor function
 * Retrieves a setting value by key, or returns a default if not found.
 */
function get_setting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Retrieves all categories with in-memory caching to prevent duplicate queries on a single page request.
 * Supports optional $force_reload for cache invalidation after write operations.
 */
function get_all_categories($pdo, $force_reload = false) {
    static $cache = null;
    if ($force_reload) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
        $cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $cache;
    } catch (PDOException $e) {
        error_log("Error retrieving categories: " . $e->getMessage());
        return [];
    }
}

/**
 * CSRF Protection Helpers
 * Generates a CSRF token for forms and state-changing requests.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies a CSRF token.
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Handles image file uploads safely.
 * @param array $file The $_FILES['input_name'] array.
 * @param string $prefix Prefix for the generated file name.
 * @return string|false Returns the relative path to the image on success, false on failure.
 */
function handle_image_upload($file, $prefix = 'img') {
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $file['tmp_name'];
        $file_name = basename($file['name']);
        $file_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $file_name);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'];
        if (in_array($ext, $allowed_exts)) {
            $dest_dir = dirname(__DIR__) . '/assets/images/';
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0755, true);
            }
            $new_filename = $prefix . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest_path = $dest_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $dest_path)) {
                return 'assets/images/' . $new_filename;
            }
        }
    }
    return false;
}

function get_site_url() {
    // 1. Resolve APP_URL from configuration sources (authoritative source)
    $app_url = defined('APP_URL') ? APP_URL : '';
    
    // Normalize trailing slash
    $app_url = $app_url ? rtrim($app_url, '/') : '';
    
    if (!empty($app_url)) {
        return $app_url;
    }
    
    // 2. Fetch database configured URL (fallback when APP_URL is unavailable)
    $db_url = get_setting('site_url');
    $db_url = $db_url ? rtrim($db_url, '/') : '';
    
    if (!empty($db_url)) {
        $parsed = parse_url($db_url);
        $db_host = isset($parsed['host']) ? $parsed['host'] : '';
        $db_scheme = isset($parsed['scheme']) ? $parsed['scheme'] : '';
        
        // Basic check for well-formed URL
        if (filter_var($db_url, FILTER_VALIDATE_URL) !== false && !empty($db_host) && !empty($db_scheme)) {
            $is_production = true;
            if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
                $is_production = false;
            }
            if ($is_production) {
                // In production, database site_url must:
                // - not contain localhost or 127.0.0.1
                // - use HTTPS
                if ($db_host !== 'localhost' && $db_host !== '127.0.0.1' && $db_scheme === 'https') {
                    return $db_url;
                }
            } else {
                // In local environment, any well-formed URL is fine
                return $db_url;
            }
        }
    }
    
    // 3. Request-derived fallback only if both are unavailable
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $dir    = dirname($script);
    
    // Fallback for CLI local testing if host is localhost
    if (php_sapi_name() === 'cli' && $host === 'localhost' && ($dir === '' || $dir === '.' || $dir === DIRECTORY_SEPARATOR)) {
        return 'http://localhost/World_of_Shelves_websit';
    }
    
    return $scheme . '://' . $host . ($dir === DIRECTORY_SEPARATOR ? '' : rtrim(str_replace('\\', '/', $dir), '/'));
}

// Load CMS settings registry and utility helpers
require_once __DIR__ . '/settings-helper.php';
require_once __DIR__ . '/media-helper.php';
require_once __DIR__ . '/services-helper.php';

/**
 * Generic helper to validate if a given path is a valid public image file.
 * 
 * @param string $path Relative path to validate.
 * @return bool Returns true if valid, false otherwise.
 */
function is_valid_public_image_path($path) {
    if (empty($path) || !is_string($path)) {
        return false;
    }
    
    // 1. Path traversal protection: block '..' or '../'
    if (strpos($path, '..') !== false) {
        return false;
    }
    
    // 2. Validate directory prefix to ensure it stays in public paths
    // e.g. starts with assets/ or uploads/ or media/
    if (!preg_match('~^(assets/|media/|uploads/)~i', $path)) {
        return false;
    }
    
    // 3. Extension check: must match common image files
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    if (!in_array($ext, $allowed, true)) {
        return false;
    }
    
    // 4. Physical existence check
    $full_path = dirname(__DIR__) . '/' . $path;
    if (!file_exists($full_path) || !is_file($full_path)) {
        return false;
    }
    
    return true;
}


