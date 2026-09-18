<?php
// includes/lang_helper.php
require_once dirname(__DIR__) . '/config/constants.php';

// Guard: start session only if not already started (idempotent — safe if init.php ran first)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define available languages
$available_langs = ['ar', 'en'];

// Check if user requested a language change
if (isset($_GET['lang']) && in_array($_GET['lang'], $available_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Determine default language (default: ar)
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'ar';

// Load language dictionary
$lang_file = LANG_PATH . "/{$lang}.php";
$translations = file_exists($lang_file) ? include($lang_file) : [];

/**
 * Localization helper function
 * @param string $key Translation key
 * @return string Translated string or the key itself if not found
 */
function __($key) {
    global $translations;
    
    // Map specific keys to database settings
    $lang = get_current_lang();
    $setting_maps = [
        // Navbar
        'nav_home' => 'nav_home_' . $lang,
        'nav_products' => 'nav_products_' . $lang,
        'nav_about' => 'nav_about_' . $lang,
        'nav_admin' => 'nav_admin_' . $lang,
        
        // Site Branding & Hero
        'brand_name' => 'site_name_' . $lang,
        'tagline' => 'tagline_' . $lang,
        'hero_title' => 'hero_title_' . $lang,
        'hero_subtitle' => 'hero_subtitle_' . $lang,
        
        // About Us
        'about_us_title' => 'about_us_title_' . $lang,
        'about_us_desc' => 'about_us_desc_' . $lang,
        'about_mission_title' => 'about_mission_title_' . $lang,
        'about_mission_desc' => 'about_mission_desc_' . $lang,
        'about_vision_title' => 'about_vision_title_' . $lang,
        'about_vision_desc' => 'about_vision_desc_' . $lang,
        
        // Why Choose Us Title & Features
        'why_us_title' => 'why_us_title_' . $lang,
        'feature1_title' => 'feature1_title_' . $lang,
        'feature1_desc' => 'feature1_desc_' . $lang,
        'feature2_title' => 'feature2_title_' . $lang,
        'feature2_desc' => 'feature2_desc_' . $lang,
        'feature3_title' => 'feature3_title_' . $lang,
        'feature3_desc' => 'feature3_desc_' . $lang,
    ];
    
    if (isset($setting_maps[$key])) {
        $setting_val = get_setting($setting_maps[$key]);
        if ($setting_val !== '') {
            return $setting_val;
        }
    }
    
    return isset($translations[$key]) ? $translations[$key] : $key;
}

/**
 * Helper to get the current language code ('ar' or 'en')
 */
function get_current_lang() {
    global $lang;
    return $lang;
}

/**
 * Helper to get writing direction ('rtl' or 'ltr')
 */
function get_lang_direction() {
    global $lang;
    return ($lang === 'ar') ? 'rtl' : 'ltr';
}

/**
 * Centralized language-aware internal URL helper.
 * Correctly preserves or removes ?lang=en according to target language,
 * handles fragments, existing parameters, and protects external/admin/asset links.
 */
function localize_url($url, $lang = null) {
    if ($lang === null) {
        $lang = function_exists('get_current_lang') ? get_current_lang() : 'ar';
    }
    
    $trimmed = trim($url);
    if ($trimmed === '' || $trimmed === '#') {
        return $url;
    }
    
    // Do NOT modify external, tel, mailto, whatsapp links
    if (preg_match('~^(https?:)?//~i', $trimmed)) {
        // Only modify if it is internal domain (i.e. starts with site_url)
        $site_url = function_exists('get_site_url') ? get_site_url() : '';
        if (!empty($site_url) && strpos($trimmed, $site_url) === 0) {
            $is_absolute = true;
            $relative_part = substr($trimmed, strlen($site_url));
        } else {
            return $url;
        }
    } else {
        $is_absolute = false;
        $relative_part = $trimmed;
    }
    
    // Ignore non-http protocols (mailto:, tel:, javascript:, etc.)
    if (preg_match('/^[a-z0-9]+:/i', $relative_part) && !preg_match('/^https?:/i', $relative_part)) {
        return $url;
    }
    
    // Ignore static assets
    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico|css|js|woff2?|ttf|pdf|webmanifest)$/i', $relative_part)) {
        return $url;
    }
    
    // Ignore admin URLs
    if (strpos($relative_part, 'admin/') === 0 || strpos($relative_part, '/admin/') !== false) {
        return $url;
    }
    
    // Separate fragment
    $parts = explode('#', $relative_part, 2);
    $path_query = $parts[0];
    $fragment = isset($parts[1]) ? '#' . $parts[1] : '';
    
    // Separate query string
    $query_parts = explode('?', $path_query, 2);
    $path = $query_parts[0];
    $query_str = isset($query_parts[1]) ? $query_parts[1] : '';
    
    // Parse query parameters
    $params = [];
    if ($query_str !== '') {
        parse_str($query_str, $params);
    }
    
    if ($lang === 'en') {
        $params['lang'] = 'en';
    } else {
        unset($params['lang']);
    }
    
    // Rebuild query
    $new_query_str = http_build_query($params);
    $rebuilt = $path;
    if ($new_query_str !== '') {
        $rebuilt .= '?' . $new_query_str;
    }
    
    $rebuilt .= $fragment;
    
    if ($is_absolute) {
        return $site_url . $rebuilt;
    }
    
    return $rebuilt;
}
