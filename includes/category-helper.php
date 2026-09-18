<?php
// includes/category-helper.php
// Centralized category path and alt text helpers.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

/**
 * Returns Category Image URL with fallback to placeholder.
 */
function get_category_image_url($category) {
    $path = !empty($category['image_path']) ? trim($category['image_path']) : '';
    if ($path !== '' && is_valid_public_image_path($path)) {
        return $path;
    }
    return 'assets/images/category-placeholder.png';
}

/**
 * Returns Category Image Alt text localized.
 */
function get_category_image_alt($category, $lang = 'ar') {
    if ($lang === 'ar') {
        $name = !empty($category['name_ar']) ? $category['name_ar'] : '';
        return 'صورة تصنيف ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    } else {
        $name = !empty($category['name_en']) ? $category['name_en'] : '';
        return htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' category image';
    }
}
