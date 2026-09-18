<?php
// includes/admin-components.php
// Enterprise Admin Framework Component Library for World of Shelves CMS.

if (!defined('ADMIN_COMPONENTS_LOADED')) {
    define('ADMIN_COMPONENTS_LOADED', true);

    /**
     * Renders a compact enterprise page header with breadcrumbs and action buttons.
     */
    if (!function_exists('render_admin_page_header')) {
        function render_admin_page_header($title, $breadcrumbs = [], $actions = []) {
            $lang = get_current_lang();
            echo '<header class="admin-header" style="padding-bottom: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--admin-border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">';
            echo '  <div>';
            
            if (!empty($breadcrumbs)) {
                echo '    <div style="font-size: 12px; color: var(--admin-text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">';
                echo '      <a href="index.php" style="color: var(--admin-text-muted); text-decoration: none;">' . htmlspecialchars(__('brand_name')) . '</a>';
                foreach ($breadcrumbs as $item) {
                    echo '      <span>/</span>';
                    if (!empty($item['url'])) {
                        echo '      <a href="' . htmlspecialchars($item['url']) . '" style="color: var(--admin-text-muted); text-decoration: none;">' . htmlspecialchars($item['label']) . '</a>';
                    } else {
                        echo '      <span>' . htmlspecialchars($item['label']) . '</span>';
                    }
                }
                echo '    </div>';
            }

            echo '    <h1 style="font-size: 22px; font-weight: 800; margin: 0; color: var(--admin-text-main);">' . htmlspecialchars($title) . '</h1>';
            echo '  </div>';

            if (!empty($actions)) {
                echo '  <div style="display: flex; gap: 10px; flex-wrap: wrap;">';
                foreach ($actions as $act) {
                    $class = $act['class'] ?? 'btn-secondary';
                    $icon  = $act['icon'] ?? '';
                    $target = !empty($act['target']) ? ' target="' . htmlspecialchars($act['target']) . '"' : '';
                    echo '    <a href="' . htmlspecialchars($act['url']) . '" class="btn btn-sm ' . htmlspecialchars($class) . '"' . $target . ' style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">';
                    if ($icon) echo '      <i data-lucide="' . htmlspecialchars($icon) . '" style="width:15px; height:15px;"></i>';
                    echo '      <span>' . htmlspecialchars($act['label']) . '</span>';
                    echo '    </a>';
                }
                echo '  </div>';
            }

            echo '</header>';
        }
    }

    /**
     * Renders a standardized enterprise KPI summary card.
     */
    if (!function_exists('render_admin_kpi_card')) {
        function render_admin_kpi_card($label, $value, $icon = 'activity', $colorScheme = 'blue') {
            $schemes = [
                'blue'   => ['bg' => 'rgba(59, 130, 246, 0.1)', 'color' => '#2563eb'],
                'green'  => ['bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#059669'],
                'amber'  => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#d97706'],
                'red'    => ['bg' => 'rgba(239, 68, 68, 0.1)',  'color' => '#dc2626'],
                'gray'   => ['bg' => 'rgba(107, 114, 128, 0.1)','color' => '#6b7280'],
            ];
            $style = $schemes[$colorScheme] ?? $schemes['blue'];

            echo '<div style="background: var(--admin-bg-card); border: 1px solid var(--admin-border-color); border-radius: var(--radius-lg); padding: 16px; display: flex; align-items: center; justify-content: space-between;">';
            echo '  <div>';
            echo '    <span style="font-size: 12px; color: var(--admin-text-muted); font-weight: 600; display: block; margin-bottom: 4px;">' . htmlspecialchars($label) . '</span>';
            echo '    <strong style="font-size: 22px; color: ' . ($colorScheme === 'green' ? 'var(--admin-success)' : 'var(--admin-text-main)') . '; font-weight: 800;">' . htmlspecialchars((string)$value) . '</strong>';
            echo '  </div>';
            echo '  <div style="width: 40px; height: 40px; border-radius: 10px; background: ' . $style['bg'] . '; color: ' . $style['color'] . '; display: flex; align-items: center; justify-content: center;">';
            echo '    <i data-lucide="' . htmlspecialchars($icon) . '" style="width: 20px; height: 20px;"></i>';
            echo '  </div>';
            echo '</div>';
        }
    }

    /**
     * Renders a standardized status badge.
     */
    if (!function_exists('render_admin_badge')) {
        function render_admin_badge($label, $type = 'active') {
            $typeClassMap = [
                'active'   => 'badge-success',
                'success'  => 'badge-success',
                'inactive' => 'badge-danger',
                'danger'   => 'badge-danger',
                'error'    => 'badge-danger',
                'featured' => 'badge-warning',
                'warning'  => 'badge-warning',
                'info'     => 'badge-info',
                'neutral'  => 'badge-neutral',
            ];
            $class = $typeClassMap[$type] ?? 'badge-neutral';
            return '<span class="nav-badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
        }
    }

    /**
     * Renders a standardized alert message.
     */
    if (!function_exists('render_admin_alert')) {
        function render_admin_alert($type = 'success', $message = '') {
            if (empty($message)) return;
            $class = ($type === 'danger' || $type === 'error') ? 'alert-danger' : 'alert-success';
            $bg = ($type === 'danger' || $type === 'error') ? 'var(--danger-light)' : 'var(--success-light)';
            $color = ($type === 'danger' || $type === 'error') ? 'var(--danger)' : 'var(--success)';
            $icon = ($type === 'danger' || $type === 'error') ? 'alert-triangle' : 'check-circle';

            echo '<div class="' . $class . '" style="background-color: ' . $bg . '; color: ' . $color . '; padding: 14px; border-radius: var(--radius-md); margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px;">';
            echo '  <i data-lucide="' . $icon . '" style="width: 18px; height: 18px;"></i>';
            echo '  <span>' . htmlspecialchars($message) . '</span>';
            echo '</div>';
        }
    }

    /**
     * Renders a standardized empty state card.
     */
    if (!function_exists('render_admin_empty_state')) {
        function render_admin_empty_state($icon, $title, $subtitle, $ctaText = '', $ctaUrl = '#', $isReset = false) {
            echo '<div style="background: var(--admin-bg-card); border: 1px solid var(--admin-border-color); border-radius: var(--radius-lg); padding: 48px 24px; text-align: center;">';
            echo '  <i data-lucide="' . htmlspecialchars($icon) . '" style="width: 48px; height: 48px; color: var(--admin-text-subtle); margin-bottom: 12px; display: inline-block;"></i>';
            echo '  <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--admin-text-main); font-weight: 700;">' . htmlspecialchars($title) . '</h3>';
            echo '  <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0 0 16px 0;">' . htmlspecialchars($subtitle) . '</p>';
            
            if (!empty($ctaText)) {
                $btnClass = $isReset ? 'btn-secondary' : 'btn-primary';
                $btnIcon  = $isReset ? 'rotate-ccw' : 'plus';
                echo '  <a href="' . htmlspecialchars($ctaUrl) . '" class="btn ' . $btnClass . ' btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">';
                echo '    <i data-lucide="' . $btnIcon . '"></i>';
                echo '    <span>' . htmlspecialchars($ctaText) . '</span>';
                echo '  </a>';
            }
            echo '</div>';
        }
    }

    /**
     * Renders a standardized pagination strip.
     */
    if (!function_exists('render_admin_pagination')) {
        function render_admin_pagination($currentPage, $totalPages, $totalRecords, $currentCount, $itemLabel = 'records', $baseUrl = '', $queryParams = []) {
            if ($totalPages <= 1) return;

            $lang = get_current_lang();
            $counterText = ($lang === 'ar') 
                ? "عرض {$currentCount} من إجمالي {$totalRecords} {$itemLabel}" 
                : "Showing {$currentCount} of {$totalRecords} {$itemLabel}";

            echo '<div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: var(--admin-bg-card); border: 1px solid var(--admin-border-color); border-radius: var(--radius-lg); margin-top: 20px; flex-wrap: wrap; gap: 12px;">';
            echo '  <span style="font-size: 13px; color: var(--admin-text-muted);">' . htmlspecialchars($counterText) . '</span>';
            echo '  <div style="display: flex; gap: 6px;">';
            
            for ($i = 1; $i <= $totalPages; $i++) {
                $params = array_merge($queryParams, ['page' => $i]);
                $url = $baseUrl . '?' . http_build_query($params);
                $btnClass = ($currentPage === $i) ? 'btn-primary' : 'btn-secondary';
                echo '    <a href="' . htmlspecialchars($url) . '" class="btn btn-sm ' . $btnClass . '" style="padding: 5px 11px; font-size: 13px; font-weight: 600;">' . $i . '</a>';
            }

            echo '  </div>';
            echo '</div>';
        }
    }

    /**
     * Renders a standardized floating sticky save bar for forms.
     */
    if (!function_exists('render_admin_sticky_save_bar')) {
        function render_admin_sticky_save_bar($infoText = '', $cancelUrl = 'index.php', $submitText = '', $isEdit = false) {
            $lang = get_current_lang();
            if (empty($infoText)) {
                $infoText = $isEdit ? ($lang === 'ar' ? 'جاهز لتحديث السجل' : 'Ready to update record') : ($lang === 'ar' ? 'جاهز لحفظ السجل الجديد' : 'Ready to save new record');
            }
            if (empty($submitText)) {
                $submitText = $isEdit ? ($lang === 'ar' ? 'تحديث التغييرات' : 'Update Changes') : ($lang === 'ar' ? 'حفظ البيانات' : 'Save Record');
            }

            echo '<div class="sticky-save-bar">';
            echo '  <div class="sticky-save-bar-info">';
            echo '    <i data-lucide="check-circle" style="color: var(--admin-success); width: 18px;"></i>';
            echo '    <span>' . htmlspecialchars($infoText) . '</span>';
            echo '  </div>';
            echo '  <div style="display: flex; gap: 10px;">';
            echo '    <a href="' . htmlspecialchars($cancelUrl) . '" class="btn btn-secondary btn-sm" style="color: #ffffff; border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); font-weight: 600;">';
            echo '      <span>' . htmlspecialchars(__('cancel')) . '</span>';
            echo '    </a>';
            echo '    <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 700; padding: 8px 18px;">';
            echo '      <i data-lucide="save" style="width: 16px;"></i>';
            echo '      <span>' . htmlspecialchars($submitText) . '</span>';
            echo '    </button>';
            echo '  </div>';
            echo '</div>';
        }
    }
}
