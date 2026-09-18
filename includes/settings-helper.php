<?php
// includes/settings-helper.php
// Centralized settings utility functions for World of Shelves CMS.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/settings-registry.php';

/**
 * Retrieves a localized setting value based on active language.
 * E.g., get_localized_setting('site_name') returns site_name_ar or site_name_en.
 * Falls back to $base_key if the localized version is empty, then to default.
 */
function get_localized_setting($base_key, $default = '') {
    $lang = get_current_lang();
    $loc_key = $base_key . '_' . $lang;
    
    $val = get_setting($loc_key);
    if ($val !== '') {
        return $val;
    }
    
    return get_setting($base_key, $default);
}

/**
 * Validates a single setting value against its registry rules.
 * Returns true if valid, or a translation key error message string if invalid.
 */
function validate_setting_value($key, $value, $def) {
    // 1. Required Check
    if (!empty($def['required']) && trim($value) === '') {
        return 'validation_required';
    }

    if (trim($value) === '') {
        return true; // Optional field is empty
    }

    // 2. Length Check
    if (isset($def['max_length']) && mb_strlen($value) > $def['max_length']) {
        return 'validation_too_long';
    }

    // 3. Specific Types
    if ($def['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return 'validation_invalid_email';
    }

    if ($def['type'] === 'url') {
        if (!filter_src_or_url($value, false)) {
            return 'validation_invalid_url';
        }
        // Social media settings require HTTPS specifically
        if ($def['group'] === 'social_media' && strpos(strtolower($value), 'https://') !== 0) {
            return 'validation_invalid_url';
        }
    }


    if ($def['type'] === 'select' && isset($def['options']) && !array_key_exists($value, $def['options'])) {
        return 'validation_invalid_option';
    }

    if ($def['type'] === 'toggle' && $value !== '0' && $value !== '1') {
        return 'validation_invalid_toggle';
    }

    // 4. Custom Regex Check
    if (isset($def['regex']) && !preg_match($def['regex'], $value)) {
        return 'validation_invalid_format';
    }

    // 5. Special Iframe Sanitizer for Maps
    if ($def['type'] === 'map_iframe') {
        $clean_iframe = sanitize_google_map_iframe($value);
        if ($clean_iframe === false) {
            return 'validation_invalid_map_iframe';
        }
    }

    return true;
}

/**
 * Parses and sanitizes Google Maps embed code to extract only the safe maps URL
 * and reconstructs a completely clean, XSS-proof iframe tag.
 */
function sanitize_google_map_iframe($input) {
    $input = trim($input);
    if (empty($input)) {
        return '';
    }

    // Attempt to extract the URL from src="..."
    $url = '';
    if (preg_match('/src="([^"]+)"/i', $input, $matches)) {
        $url = $matches[1];
    } else {
        // If not full iframe, check if it's just a raw URL
        $url = $input;
    }

    // Strictly validate that the URL is a Google Maps embed URL
    // Format: https://www.google.com/maps/embed?... or https://google.com/maps/...
    if (!preg_match('~^https://(www\.)?google\.com/maps/(embed|view|place|dir)~i', $url)) {
        return false;
    }

    // Reconstruct clean HTML to prevent injection
    return '<iframe src="' . htmlspecialchars($url) . '" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
}

/**
 * Safely validates a URL
 */
function filter_src_or_url($url, $allow_relative = true) {
    if (empty($url)) {
        return true;
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return true;
    }
    if ($allow_relative && preg_match('~^[a-zA-Z0-9\._\/-]+$~', $url)) {
        return true;
    }
    return false;
}
