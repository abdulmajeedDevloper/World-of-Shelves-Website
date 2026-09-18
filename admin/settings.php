<?php
// admin/settings.php
// Enterprise CMS Settings & System Configuration Center

require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';
require_once dirname(__DIR__) . '/includes/settings-helper.php';

$success_message = '';
$error_message = '';
$field_errors = [];

// Get all registry settings
$registry = get_settings_registry();

// Define allowed groups managed by settings.php
$allowed_groups = ['general', 'seo', 'analytics', 'advanced'];

// Rebuild $keys_to_update based on allowed groups only
$keys_to_update = [];
foreach ($registry as $key => $def) {
    if (in_array($def['group'], $allowed_groups, true)) {
        $keys_to_update[] = $key;
    }
}

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = get_current_lang() === 'ar' ? 'رمز الحماية (CSRF Token) غير صالح.' : 'Invalid security token (CSRF).';
    } else {
        $newFilesTracked = [];
        $replacedOldImages = [];
        $prepared_vals = [];
        $validation_failed = false;

        // Start validation and upload phase
        try {
            foreach ($registry as $key => $def) {
                if (!in_array($def['group'], $allowed_groups, true)) {
                    continue;
                }

                if ($def['type'] === 'file') {
                    $file_key = $key . '_file';
                    $old_val = get_setting($key);

                    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] !== UPLOAD_ERR_NO_FILE) {
                        $prefix = $def['prefix'];
                        $uploaded_path = validate_and_upload_image($_FILES[$file_key], $newFilesTracked, 'settings', $prefix);
                        if ($uploaded_path) {
                            $prepared_vals[$key] = $uploaded_path;
                            if (!empty($old_val) && $old_val !== $uploaded_path) {
                                $replacedOldImages[] = $old_val;
                            }
                        } else {
                            $prepared_vals[$key] = $old_val;
                        }
                    } else {
                        $prepared_vals[$key] = $old_val;
                    }
                } else {
                    $posted_val = isset($_POST[$key]) ? trim($_POST[$key]) : '';

                    if ($def['type'] === 'toggle') {
                        $posted_val = isset($_POST[$key]) ? '1' : '0';
                    }

                    $val_result = validate_setting_value($key, $posted_val, $def);
                    if ($val_result !== true) {
                        if ($val_result === 'validation_too_long') {
                            $max = isset($def['max_length']) ? $def['max_length'] : 255;
                            $current_len = mb_strlen($posted_val);
                            $field_label = __($def['label_key']);
                            if (get_current_lang() === 'ar') {
                                $field_errors[$key] = "حقل '{$field_label}' طويل جداً. الحد الأقصى {$max} حرفاً (الطول الحالي: {$current_len} حرفاً).";
                            } else {
                                $field_errors[$key] = "Field '{$field_label}' is too long. Max allowed length is {$max} characters (current length: {$current_len} characters).";
                            }
                        } else {
                            $field_errors[$key] = __($val_result);
                        }
                        $validation_failed = true;
                    }

                    if ($def['type'] === 'map_iframe' && !$validation_failed && !empty($posted_val)) {
                        $posted_val = sanitize_google_map_iframe($posted_val);
                    }

                    $prepared_vals[$key] = $posted_val;
                }
            }

            if ($validation_failed) {
                if (!empty($field_errors)) {
                    $first_err_key = array_key_first($field_errors);
                    if ($first_err_key && isset($registry[$first_err_key])) {
                        $active_tab = $registry[$first_err_key]['group'];
                    }
                }
                throw new ProjectValidationException(get_current_lang() === 'ar' ? 'يرجى تصحيح الأخطاء الموضحة أدناه.' : 'Please correct the validation errors below.');
            }

            // Database save phase
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:key_name, :val_insert) 
                ON CONFLICT(key_name) DO UPDATE SET val = :val_update");
                
            if ($db_driver === 'mysql') {
                $stmt = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:key_name, :val_insert) 
                    ON DUPLICATE KEY UPDATE val = :val_update");
            }

            foreach ($keys_to_update as $key) {
                if (isset($prepared_vals[$key])) {
                    $val = $prepared_vals[$key];
                    $stmt->execute([
                        ':key_name' => $key,
                        ':val_insert' => $val,
                        ':val_update' => $val
                    ]);
                    $settings[$key] = $val;
                }
            }

            $pdo->commit();

            $cleanupSuccess = true;
            foreach ($replacedOldImages as $oldFile) {
                if (!empty($oldFile) && file_exists(dirname(__DIR__) . '/' . $oldFile)) {
                    if (!safe_delete_file($oldFile, 'settings')) {
                        $cleanupSuccess = false;
                    }
                }
            }

            $success_message = $cleanupSuccess ? __('settings_saved') : __('settings_saved') . ' ' . __('cleanup_incomplete_warning');

        } catch (ProjectValidationException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rollback_cleanup_files($newFilesTracked);
            $error_message = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rollback_cleanup_files($newFilesTracked);
            error_log("Database error in settings save: " . $e->getMessage());
            $error_message = __('database_operation_failed');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rollback_cleanup_files($newFilesTracked);
            error_log("Unexpected error in settings save: " . $e->getMessage());
            $error_message = __('unexpected_operation_failed');
        }
    }
}

$page_title = __('admin_site_settings');
$lang = get_current_lang();

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';

// Group settings by their defined tab/group
$grouped_settings = [];
foreach ($registry as $key => $def) {
    $group = $def['group'];
    $grouped_settings[$group][$key] = $def;
}

// Order of tabs to display
$tabs_order = [
    'general'      => ['icon' => 'sliders',      'label' => $lang === 'ar' ? 'الإعدادات العامة' : 'General'],
    'seo'          => ['icon' => 'search',       'label' => $lang === 'ar' ? 'إعدادات SEO' : 'SEO'],
    'analytics'    => ['icon' => 'bar-chart-2',  'label' => $lang === 'ar' ? 'التحليلات والمحركات' : 'Analytics'],
    'advanced'     => ['icon' => 'sliders-horizontal', 'label' => $lang === 'ar' ? 'إعدادات متقدمة' : 'Advanced']
];

// Determine active tab
$active_tab = isset($_POST['active_tab']) ? trim($_POST['active_tab']) : 'general';
if (!array_key_exists($active_tab, $tabs_order)) {
    $active_tab = 'general';
}
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <?php 
    render_admin_page_header($page_title, [
        ['label' => $lang === 'ar' ? 'إعدادات النظام والموقع' : 'Site Configuration', 'url' => '']
    ]);
    ?>

    <!-- Message Alerts -->
    <?php 
    render_admin_alert('danger', $error_message);
    render_admin_alert('success', $success_message);
    ?>

    <!-- Settings Tab Navigation -->
    <div style="display: flex; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid var(--admin-border-color); padding-bottom: 12px; margin-bottom: 24px;">
        <?php foreach ($tabs_order as $tab_key => $tab_info): ?>
            <button type="button" class="btn <?php echo ($active_tab === $tab_key) ? 'btn-primary' : 'btn-secondary'; ?> btn-sm settings-tab-btn" onclick="switchTab(event, '<?php echo $tab_key; ?>')" style="padding: 8px 16px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i data-lucide="<?php echo $tab_info['icon']; ?>" style="width: 15px; height: 15px;"></i>
                <span><?php echo htmlspecialchars($tab_info['label']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Main Settings Form -->
    <form action="settings.php" method="POST" enctype="multipart/form-data" id="settingsForm" data-track-unsaved="true">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="save_settings" value="1">
        <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">

        <?php foreach ($tabs_order as $tab_key => $tab_info): ?>
            <div class="settings-section-panel <?php echo ($active_tab === $tab_key) ? 'active' : ''; ?>" id="panel-<?php echo $tab_key; ?>" style="display: <?php echo ($active_tab === $tab_key) ? 'block' : 'none'; ?>;">
                <div class="form-section-card">
                    <h3>
                        <i data-lucide="<?php echo $tab_info['icon']; ?>"></i>
                        <span><?php echo htmlspecialchars($tab_info['label']); ?></span>
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <?php if (isset($grouped_settings[$tab_key])): ?>
                            <?php foreach ($grouped_settings[$tab_key] as $key => $def): ?>
                                <?php 
                                $field_value = isset($_POST[$key]) ? $_POST[$key] : get_setting($key);
                                $has_error = isset($field_errors[$key]);
                                ?>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <label for="<?php echo $key; ?>" style="font-weight: 600; font-size: 13px; color: var(--admin-text-main);">
                                        <?php echo htmlspecialchars(__($def['label_key'])); ?>
                                        <?php if (!empty($def['required'])): ?>
                                            <span style="color: var(--admin-danger);">*</span>
                                        <?php endif; ?>
                                    </label>

                                    <?php if ($def['type'] === 'text' || $def['type'] === 'email' || $def['type'] === 'url'): ?>
                                        <input type="<?php echo $def['type']; ?>" name="<?php echo $key; ?>" id="<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($field_value); ?>" <?php echo !empty($def['required']) ? 'required' : ''; ?> style="width: 100%; padding: 9px 12px; border: 1px solid <?php echo $has_error ? 'var(--admin-danger)' : 'var(--admin-border-color)'; ?>; border-radius: var(--radius-md); font-size: 13.5px;">
                                    
                                    <?php elseif ($def['type'] === 'textarea' || $def['type'] === 'map_iframe'): ?>
                                        <textarea name="<?php echo $key; ?>" id="<?php echo $key; ?>" class="form-control" rows="3" <?php echo !empty($def['required']) ? 'required' : ''; ?> style="width: 100%; padding: 9px 12px; border: 1px solid <?php echo $has_error ? 'var(--admin-danger)' : 'var(--admin-border-color)'; ?>; border-radius: var(--radius-md); font-size: 13.5px;"><?php echo htmlspecialchars($field_value); ?></textarea>
                                        <?php if ($def['type'] === 'map_iframe'): ?>
                                            <span style="font-size: 11.5px; color: var(--admin-text-muted);">
                                                <?php echo $lang === 'ar' ? 'يمكنك إدخال كود التضمين (iframe) بالكامل من خرائط جوجل أو رابط الخريطة المباشر.' : 'You can enter the full Google Maps embed code (iframe) or direct map URL.'; ?>
                                            </span>
                                        <?php endif; ?>

                                    <?php elseif ($def['type'] === 'select'): ?>
                                        <select name="<?php echo $key; ?>" id="<?php echo $key; ?>" class="filter-select" style="width: 100%; padding: 9px 12px; height: auto;">
                                            <?php foreach ($def['options'] as $opt_val => $opt_lbl): ?>
                                                <option value="<?php echo $opt_val; ?>" <?php echo ($field_value === $opt_val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt_lbl); ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                    <?php elseif ($def['type'] === 'toggle'): ?>
                                        <label style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                                            <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php echo ($field_value === '1') ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
                                            <span style="font-weight: 600; font-size: 13.5px; color: var(--admin-text-main);"><?php echo $lang === 'ar' ? 'تفعيل الخيار' : 'Enable option'; ?></span>
                                        </label>

                                    <?php elseif ($def['type'] === 'file'): ?>
                                        <div style="background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); padding: 16px; border-radius: var(--radius-md); display: flex; flex-wrap: wrap; gap: 20px; align-items: center;">
                                            <div style="flex: 2 1 280px;">
                                                <input type="file" name="<?php echo $key; ?>_file" id="<?php echo $key; ?>_file" class="form-control" accept="<?php echo implode(',', $def['allowed_types']); ?>" style="padding: 6px; width: 100%; border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); font-size: 13px;">
                                                <span style="font-size: 11.5px; color: var(--admin-text-muted); display: block; margin-top: 4px;">
                                                    <?php echo $lang === 'ar' ? 'الصيغ المسموحة: ' : 'Allowed formats: '; ?> <?php echo implode(', ', $def['allowed_types']); ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($field_value)): ?>
                                                <div style="flex: 1 1 200px; display: flex; align-items: center; gap: 12px; background: var(--admin-bg-card); padding: 8px 12px; border: 1px solid var(--admin-border-color); border-radius: var(--radius-md);">
                                                    <?php if ($key === 'site_favicon'): ?>
                                                        <img src="../<?php echo htmlspecialchars($field_value); ?>" alt="Favicon" style="width: 32px; height: 32px; object-fit: contain;">
                                                    <?php else: ?>
                                                        <img src="../<?php echo htmlspecialchars($field_value); ?>" alt="Preview" style="max-height: 48px; object-fit: contain; max-width: 100px;">
                                                    <?php endif; ?>
                                                    <span style="font-size: 11.5px; color: var(--admin-text-muted); font-family: monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 120px;">
                                                        <?php echo htmlspecialchars(basename($field_value)); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($has_error): ?>
                                        <span style="color: var(--admin-danger); font-size: 12.5px; font-weight: 600;">
                                            <?php echo htmlspecialchars($field_errors[$key]); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Enterprise Sticky Save Action Bar -->
        <?php render_admin_sticky_save_bar($lang === 'ar' ? 'جاهز لحفظ إعدادات النظام' : 'Ready to save site configuration', 'index.php', $lang === 'ar' ? 'حفظ الإعدادات' : 'Save Settings', true); ?>
    </form>
</main>

<script>
    function switchTab(event, tabId) {
        event.preventDefault();
        document.querySelectorAll('.settings-tab-btn').forEach(btn => {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        });
        document.querySelectorAll('.settings-section-panel').forEach(panel => {
            panel.classList.remove('active');
            panel.style.display = 'none';
        });

        event.currentTarget.classList.remove('btn-secondary');
        event.currentTarget.classList.add('btn-primary');
        
        const activePanel = document.getElementById('panel-' + tabId);
        if (activePanel) {
            activePanel.classList.add('active');
            activePanel.style.display = 'block';
        }

        document.getElementById('activeTabInput').value = tabId;
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
