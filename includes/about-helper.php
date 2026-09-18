<?php
// includes/about-helper.php
// Centralized helper functions for managing the About Us page CMS.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

/**
 * Returns milestones from About CMS settings (Sprint 5.1).
 * Reads 4 fixed milestones from the settings registry.
 * Falls back to translation keys for backward compatibility.
 */
function get_about_milestones() {
    $lang = function_exists('get_current_lang') ? get_current_lang() : 'ar';
    $milestones = [];

    for ($i = 1; $i <= 4; $i++) {
        $title = get_setting("about_milestone_{$i}_title_{$lang}", '');
        $desc = get_setting("about_milestone_{$i}_desc_{$lang}", '');

        // Fallback to translation keys for backward compatibility
        if (empty($title)) {
            $title = __("timeline_{$i}_title");
        }
        if (empty($desc)) {
            $desc = __("timeline_{$i}_desc");
        }

        // Skip rendering if title is completely empty
        if (empty($title)) {
            continue;
        }

        $milestones[] = [
            'title' => $title,
            'desc'  => $desc
        ];
    }

    return $milestones;
}
