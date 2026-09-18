<?php
// includes/navigation-helper.php
// Centralized helper functions for managing and rendering public site navigation.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

/**
 * Returns sorted active navigation items for public header.
 * 
 * @return array List of navigation items with label, url, key, and active status
 */
function get_public_navigation_menu() {
    $lang = function_exists('get_current_lang') ? get_current_lang() : 'ar';
    
    // 1. Definition of the 6 fixed system routes
    $nav_definitions = [
        'home' => [
            'url'           => 'index',
            'label_fallback'=> 'nav_home',
            'default_order' => 10,
            'settings_label_key' => 'nav_home' // Reuses nav_home_ar/en
        ],
        'products' => [
            'url'           => 'products',
            'label_fallback'=> 'nav_products',
            'default_order' => 20,
            'settings_label_key' => 'nav_products' // Reuses nav_products_ar/en
        ],
        'services' => [
            'url'           => 'services',
            'label_fallback'=> 'services_title',
            'default_order' => 30,
            'settings_label_key' => 'nav_services' // New label settings
        ],
        'projects' => [
            'url'           => 'projects',
            'label_fallback'=> 'nav_portfolio',
            'default_order' => 40,
            'settings_label_key' => 'nav_projects' // New label settings
        ],
        'about' => [
            'url'           => 'about',
            'label_fallback'=> 'nav_about',
            'default_order' => 50,
            'settings_label_key' => 'nav_about' // Reuses nav_about_ar/en
        ],
        'contact' => [
            'url'           => 'contact',
            'label_fallback'=> 'nav_contact',
            'default_order' => 60,
            'settings_label_key' => 'nav_contact' // New label settings
        ]
    ];

    $items = [];

    // 2. Fetch and resolve items
    foreach ($nav_definitions as $key => $info) {
        $show = get_setting("nav_show_{$key}", '1') === '1';
        if (!$show) {
            continue;
        }

        // Get label from settings: nav_home_ar/en, nav_services_ar/en, etc.
        $label_key = $info['settings_label_key'] . '_' . $lang;
        $label = get_setting($label_key, '');
        if (empty($label)) {
            $label = __($info['label_fallback']);
        }

        // Resolve sort order
        $order_val = get_setting("nav_order_{$key}", (string)$info['default_order']);
        $order = filter_var($order_val, FILTER_VALIDATE_INT);
        if ($order === false || $order < 0 || $order > 999) {
            $order = $info['default_order'];
        }

        $current_file = basename($_SERVER['PHP_SELF']);
        $current_name = pathinfo($current_file, PATHINFO_FILENAME);
        
        $request_path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $request_file = basename($request_path);
        $request_name = pathinfo($request_file, PATHINFO_FILENAME);
        
        // Active page logic: check both internally executed script and client REQUEST_URI
        $is_active = false;
        if ($info['url'] === 'index') {
            $is_active = ($current_name === 'index' || empty($request_name) || $request_name === 'World_of_Shelves_websit');
        } else {
            $is_active = ($current_name === $info['url'] || $request_name === $info['url']);
        }

        $raw_url = ($key === 'home') ? get_site_url() . '/' : $info['url'];
        $items[] = [
            'key'        => $key,
            'url'        => localize_url($raw_url, $lang),
            'label'      => $label,
            'order'      => $order,
            'default_order' => $info['default_order'],
            'is_active'  => $is_active
        ];
    }

    // 3. Sort deterministically: 
    //    a) Order weight ascending
    //    b) If equal, default order weight ascending
    //    c) If still equal, alphabetically by key name
    usort($items, function($a, $b) {
        if ($a['order'] !== $b['order']) {
            return $a['order'] <=> $b['order'];
        }
        if ($a['default_order'] !== $b['default_order']) {
            return $a['default_order'] <=> $b['default_order'];
        }
        return strcmp($a['key'], $b['key']);
    });

    return $items;
}
