<?php
// includes/favicon_helper.php
// Dedicated favicon asset loader and cache buster to avoid code duplication across headers.

if (!function_exists('get_favicon_asset_url')) {
    /**
     * Generates a root-relative, cache-busted URL for a given favicon asset.
     *
     * @param string $path The relative path to the asset (e.g. 'favicon.ico').
     * @return string The root-relative cache-busted URL.
     */
    function get_favicon_asset_url($path) {
        $site_url = get_site_url();
        $url_path = rtrim(parse_url($site_url, PHP_URL_PATH) ?: '', '/');
        
        // __DIR__ is includes/ directory, dirname(__DIR__) is workspace root
        $full_path = dirname(__DIR__) . '/' . ltrim($path, '/');
        $ver = file_exists($full_path) ? filemtime($full_path) : '1';
        
        return $url_path . '/' . ltrim($path, '/') . '?v=' . $ver;
    }
}

if (!function_exists('get_favicon_manifest_url')) {
    /**
     * Generates the root-relative URL for the site webmanifest.
     *
     * @return string
     */
    function get_favicon_manifest_url() {
        $site_url = get_site_url();
        $url_path = rtrim(parse_url($site_url, PHP_URL_PATH) ?: '', '/');
        return $url_path . '/site.webmanifest';
    }
}
