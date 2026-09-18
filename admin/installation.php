<?php
// admin/installation.php
// Administration interface for Installation Page CMS.

require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/settings-helper.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';

$lang = get_current_lang();
$registry = get_settings_registry();

$success_message = '';
$error_message = '';
$field_errors = [];
$validation_failed = false;

// Tabs config
$tabs_order = [
    'visibility' => ['icon' => 'sliders', 'label' => ($lang === 'ar' ? 'ظهور وترتيب الأقسام' : 'Visibility & Ordering')],
    'hero'       => ['icon' => 'sparkles', 'label' => ($lang === 'ar' ? 'البانر العلوي' : 'Hero Banner')],
    'where'      => ['icon' => 'map-pin', 'label' => ($lang === 'ar' ? 'أماكن التركيب' : 'Where We Install')],
    'process'    => ['icon' => 'git-commit', 'label' => ($lang === 'ar' ? 'خطوات التركيب' : 'Installation Steps')],
    'why'        => ['icon' => 'help-circle', 'label' => ($lang === 'ar' ? 'لماذا تختارنا' : 'Why Pro Installation')],
    'cta'        => ['icon' => 'phone-call', 'label' => ($lang === 'ar' ? 'دعوة الاتصال' : 'Call to Action')],
    'seo'        => ['icon' => 'search', 'label' => ($lang === 'ar' ? 'محركات البحث' : 'SEO Settings')]
];

$active_tab = isset($_POST['active_tab']) ? trim($_POST['active_tab']) : 'visibility';
if (!array_key_exists($active_tab, $tabs_order)) {
    $active_tab = 'visibility';
}

// Fetch Media list for picker
$media_list = [];
try {
    $m_stmt = $pdo->query("SELECT id, filename, folder, file_path FROM media_library ORDER BY id DESC");
    $media_list = $m_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch media list: " . $e->getMessage());
}

// Section list for visibility/ordering
$sections_list = [
    'hero'    => ['label_ar' => 'البانر العلوي', 'label_en' => 'Hero Banner', 'default_order' => 10],
    'where'   => ['label_ar' => 'أين نقوم بالتركيب', 'label_en' => 'Where We Install', 'default_order' => 20],
    'process' => ['label_ar' => 'خطوات التركيب', 'label_en' => 'Installation Steps', 'default_order' => 30],
    'why'     => ['label_ar' => 'لماذا تختارنا', 'label_en' => 'Why Pro Installation', 'default_order' => 40],
    'cta'     => ['label_ar' => 'دعوة الاتصال (CTA)', 'label_en' => 'Call to Action', 'default_order' => 50]
];

// POST update processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_installation'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = $lang === 'ar' ? 'رمز حماية CSRF غير صالح.' : 'Invalid CSRF token protection.';
        $validation_failed = true;
    } else {
        $newFilesTracked = [];
        $prepared_vals = [];

        try {
            $pdo->beginTransaction();

            // Callback to process image files or media selections safely
            $process_image_field = function($file_field_name, $select_field_name, $current_val) use ($pdo, &$newFilesTracked) {
                // 1. Direct upload is verified first
                if (isset($_FILES[$file_field_name]) && $_FILES[$file_field_name]['error'] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $up_path = upload_to_media_library($pdo, $_FILES[$file_field_name], 'seo');
                        if ($up_path) {
                            $newFilesTracked[] = $up_path;
                            return $up_path;
                        }
                    } catch (ProjectValidationException $ex) {
                        throw new ProjectValidationException($ex->getMessage());
                    }
                }
                
                // 2. Selected media ID checks
                if (!empty($_POST[$select_field_name])) {
                    $media_id = intval($_POST[$select_field_name]);
                    $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                    $stmt->execute([':id' => $media_id]);
                    $db_path = $stmt->fetchColumn();
                    if ($db_path) {
                        return $db_path;
                    }
                }
                
                return $current_val;
            };

            // 1. Section Visibility & Sorting
            foreach ($sections_list as $key => $sec) {
                $show_field = "install_show_" . $key;
                $order_field = "install_order_" . $key;
                
                $show_val = isset($_POST[$show_field]) ? '1' : '0';
                $order_raw = isset($_POST[$order_field]) ? trim($_POST[$order_field]) : '';
                $order_val = filter_var($order_raw, FILTER_VALIDATE_INT);
                
                if ($order_val === false || $order_val < 0 || $order_val > 999) {
                    $error_message = ($lang === 'ar')
                        ? "ترتيب قسم (" . $sec['label_ar'] . ") غير صالح. يجب أن يكون رقماً بين 0 و 999."
                        : "Sort order for (" . $sec['label_en'] . ") is invalid. Must be between 0 and 999.";
                    $validation_failed = true;
                    break;
                }
                
                $prepared_vals[$show_field] = $show_val;
                $prepared_vals[$order_field] = (string)$order_val;
            }

            // 2. Text / Textarea Field Updates
            $text_keys = [
                'install_hero_title_ar', 'install_hero_title_en',
                'install_hero_subtitle_ar', 'install_hero_subtitle_en',
                'install_hero_cta_label_ar', 'install_hero_cta_label_en',
                'install_hero_secondary_label_ar', 'install_hero_secondary_label_en',
                'install_hero_wa_msg_ar', 'install_hero_wa_msg_en',
                
                'install_where_title_ar', 'install_where_title_en',
                'install_where_desc_ar', 'install_where_desc_en',
                'install_loc_1_title_ar', 'install_loc_1_title_en', 'install_loc_1_desc_ar', 'install_loc_1_desc_en',
                'install_loc_2_title_ar', 'install_loc_2_title_en', 'install_loc_2_desc_ar', 'install_loc_2_desc_en',
                'install_loc_3_title_ar', 'install_loc_3_title_en', 'install_loc_3_desc_ar', 'install_loc_3_desc_en',
                'install_loc_4_title_ar', 'install_loc_4_title_en', 'install_loc_4_desc_ar', 'install_loc_4_desc_en',
                
                'install_phases_title_ar', 'install_phases_title_en',
                'install_phases_desc_ar', 'install_phases_desc_en',
                'install_step_1_title_ar', 'install_step_1_title_en', 'install_step_1_desc_ar', 'install_step_1_desc_en',
                'install_step_2_title_ar', 'install_step_2_title_en', 'install_step_2_desc_ar', 'install_step_2_desc_en',
                'install_step_3_title_ar', 'install_step_3_title_en', 'install_step_3_desc_ar', 'install_step_3_desc_en',
                'install_step_4_title_ar', 'install_step_4_title_en', 'install_step_4_desc_ar', 'install_step_4_desc_en',
                
                'install_why_title_ar', 'install_why_title_en',
                'install_why_1_title_ar', 'install_why_1_title_en', 'install_why_1_desc_ar', 'install_why_1_desc_en',
                'install_why_2_title_ar', 'install_why_2_title_en', 'install_why_2_desc_ar', 'install_why_2_desc_en',
                'install_why_3_title_ar', 'install_why_3_title_en', 'install_why_3_desc_ar', 'install_why_3_desc_en',
                
                'cta_install_title_ar', 'cta_install_title_en',
                'cta_install_desc_ar', 'cta_install_desc_en',
                'cta_install_whatsapp_msg_ar', 'cta_install_whatsapp_msg_en',
                
                'seo_install_title_ar', 'seo_install_title_en',
                'seo_install_desc_ar', 'seo_install_desc_en'
            ];

            if (!$validation_failed) {
                foreach ($text_keys as $tk) {
                    $posted_val = isset($_POST[$tk]) ? trim($_POST[$tk]) : '';
                    if (isset($registry[$tk])) {
                        $val_result = validate_setting_value($tk, $posted_val, $registry[$tk]);
                        if ($val_result !== true) {
                            $field_errors[$tk] = __($val_result);
                            $validation_failed = true;
                        }
                    }
                    $prepared_vals[$tk] = $posted_val;
                }
            }

            // 3. Image Process
            if (!$validation_failed) {
                $curr_seo_og = get_setting('seo_install_og_image', '');
                $final_seo_og = $process_image_field('seo_install_og_image_file', 'seo_install_og_image_media_id', $curr_seo_og);
                $prepared_vals['seo_install_og_image'] = $final_seo_og;
            }

            if ($validation_failed) {
                throw new ProjectValidationException($lang === 'ar' ? 'يرجى تصحيح الأخطاء الموضحة أدناه.' : 'Please correct the validation errors below.');
            }

            // Database save phase
            $stmt = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:key_name, :val_insert) 
                ON CONFLICT(key_name) DO UPDATE SET val = :val_update"); // SQLite
                
            if ($db_driver === 'mysql') {
                $stmt = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:key_name, :val_insert) 
                    ON DUPLICATE KEY UPDATE val = :val_update"); // MySQL
            }

            foreach ($prepared_vals as $key => $val) {
                $stmt->execute([
                    ':key_name' => $key,
                    ':val_insert' => $val,
                    ':val_update' => $val
                ]);
            }

            $pdo->commit();
            $success_message = $lang === 'ar' ? 'تم حفظ إعدادات صفحة التركيب بنجاح.' : 'Installation settings saved successfully.';

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
            error_log("Database error in installation save: " . $e->getMessage());
            $error_message = $lang === 'ar' ? 'حدث خطأ في قاعدة البيانات أثناء الحفظ.' : 'Database operation failed.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rollback_cleanup_files($newFilesTracked);
            error_log("Unexpected error in installation save: " . $e->getMessage());
            $error_message = $lang === 'ar' ? 'حدث خطأ غير متوقع أثناء الحفظ.' : 'Unexpected operation failed.';
        }
    }
}

$page_title = $lang === 'ar' ? 'إدارة صفحة التركيب' : 'Manage Installation Page';
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<main class="admin-content">
    <header class="admin-header">
        <div>
            <h1 style="font-size: 28px;"><?php echo $page_title; ?></h1>
            <p><?php echo $lang === 'ar' ? 'تخصيص محتوى صفحة التركيب وأقسامها وإعدادات الـ SEO' : 'Full customization of Installation page content, sections, and SEO'; ?></p>
        </div>
        
        <a href="?lang=<?php echo $lang === 'ar' ? 'en' : 'ar'; ?>" class="lang-toggle">
            <i data-lucide="globe"></i>
            <span><?php echo $lang === 'ar' ? 'English' : 'العربية'; ?></span>
        </a>
    </header>

    <?php if (!empty($error_message)): ?>
        <div style="background-color: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <div style="background-color: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <!-- Tabs Navigations -->
    <div class="settings-nav-tabs" style="display: flex; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 24px;">
        <?php foreach ($tabs_order as $tab_key => $tab_info): ?>
            <button type="button" class="settings-tab-btn <?php echo ($active_tab === $tab_key) ? 'active' : ''; ?>" onclick="switchTab(event, '<?php echo $tab_key; ?>')" style="padding: 10px 16px; border: 1px solid var(--border-color); background: var(--bg-main); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
                <i data-lucide="<?php echo $tab_info['icon']; ?>" style="width: 16px; height: 16px;"></i>
                <span><?php echo htmlspecialchars($tab_info['label']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Main Editor Form -->
    <form action="installation.php" method="POST" enctype="multipart/form-data" id="installationForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="save_installation" value="1">
        <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">

        <!-- TAB: VISIBILITY & ORDER -->
        <div class="settings-section-panel <?php echo ($active_tab === 'visibility') ? 'active' : ''; ?>" id="panel-visibility" style="display: <?php echo ($active_tab === 'visibility') ? 'block' : 'none'; ?>;">
            <section class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo $lang === 'ar' ? 'ظهور وترتيب الأقسام' : 'Section Visibility & Sorting'; ?>
                </h3>
                
                <table class="admin-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: start; padding: 12px; border-bottom: 2px solid var(--border-color);"><?php echo $lang === 'ar' ? 'القسم' : 'Section'; ?></th>
                            <th style="text-align: center; padding: 12px; border-bottom: 2px solid var(--border-color);"><?php echo $lang === 'ar' ? 'الحالة' : 'Status'; ?></th>
                            <th style="text-align: center; padding: 12px; border-bottom: 2px solid var(--border-color);"><?php echo $lang === 'ar' ? 'الترتيب' : 'Order Weight'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections_list as $key => $sec): ?>
                            <?php 
                            $show_val = get_setting("install_show_" . $key, '1');
                            $order_val = get_setting("install_order_" . $key, (string)$sec['default_order']);
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($lang === 'ar' ? $sec['label_ar'] : $sec['label_en']); ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 22px;">
                                        <input type="checkbox" name="install_show_<?php echo $key; ?>" value="1" <?php echo ($show_val === '1') ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                                        <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                    </label>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <input type="number" name="install_order_<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($order_val); ?>" min="0" max="999" style="width: 80px; text-align: center; margin: 0 auto; display: block;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- TAB: HERO BANNER -->
        <div class="settings-section-panel <?php echo ($active_tab === 'hero') ? 'active' : ''; ?>" id="panel-hero" style="display: <?php echo ($active_tab === 'hero') ? 'block' : 'none'; ?>;">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo $lang === 'ar' ? 'محتوى البانر العلوي (Hero Banner)' : 'Hero Banner Content'; ?>
                </h3>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">العنوان بالعربية (Title Arabic)</label>
                    <input type="text" name="install_hero_title_ar" class="form-control <?php echo isset($field_errors['install_hero_title_ar']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars(get_setting('install_hero_title_ar')); ?>" required>
                    <?php if (isset($field_errors['install_hero_title_ar'])): ?><div class="error-feedback"><?php echo $field_errors['install_hero_title_ar']; ?></div><?php endif; ?>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">العنوان بالإنجليزية (Title English)</label>
                    <input type="text" name="install_hero_title_en" class="form-control <?php echo isset($field_errors['install_hero_title_en']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars(get_setting('install_hero_title_en')); ?>" required>
                    <?php if (isset($field_errors['install_hero_title_en'])): ?><div class="error-feedback"><?php echo $field_errors['install_hero_title_en']; ?></div><?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">الوصف الفرعي بالعربية (Subtitle Arabic)</label>
                    <textarea name="install_hero_subtitle_ar" class="form-control <?php echo isset($field_errors['install_hero_subtitle_ar']) ? 'is-invalid' : ''; ?>" rows="3" required><?php echo htmlspecialchars(get_setting('install_hero_subtitle_ar')); ?></textarea>
                    <?php if (isset($field_errors['install_hero_subtitle_ar'])): ?><div class="error-feedback"><?php echo $field_errors['install_hero_subtitle_ar']; ?></div><?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">الوصف الفرعي بالإنجليزية (Subtitle English)</label>
                    <textarea name="install_hero_subtitle_en" class="form-control <?php echo isset($field_errors['install_hero_subtitle_en']) ? 'is-invalid' : ''; ?>" rows="3" required><?php echo htmlspecialchars(get_setting('install_hero_subtitle_en')); ?></textarea>
                    <?php if (isset($field_errors['install_hero_subtitle_en'])): ?><div class="error-feedback"><?php echo $field_errors['install_hero_subtitle_en']; ?></div><?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">نص زر الواتساب بالعربية (WhatsApp CTA Label Arabic)</label>
                    <input type="text" name="install_hero_cta_label_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_hero_cta_label_ar')); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">نص زر الواتساب بالإنجليزية (WhatsApp CTA Label English)</label>
                    <input type="text" name="install_hero_cta_label_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_hero_cta_label_en')); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">نص الزر الثانوي بالعربية (Secondary Button Label Arabic)</label>
                    <input type="text" name="install_hero_secondary_label_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_hero_secondary_label_ar')); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">نص الزر الثانوي بالإنجليزية (Secondary Button Label English)</label>
                    <input type="text" name="install_hero_secondary_label_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_hero_secondary_label_en')); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">رسالة الواتساب التلقائية بالعربية (WhatsApp Message Arabic)</label>
                    <input type="text" name="install_hero_wa_msg_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_hero_wa_msg_ar')); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">رسالة الواتساب التلقائية بالإنجليزية (WhatsApp Message English)</label>
                    <input type="text" name="install_hero_wa_msg_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_hero_wa_msg_en')); ?>" required>
                </div>
            </div>
        </div>

        <!-- TAB: WHERE WE INSTALL -->
        <div class="settings-section-panel <?php echo ($active_tab === 'where') ? 'active' : ''; ?>" id="panel-where" style="display: <?php echo ($active_tab === 'where') ? 'block' : 'none'; ?>;">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo $lang === 'ar' ? 'قسم أين نقوم بالتركيب (Where We Install)' : 'Where We Install Section'; ?>
                </h3>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">عنوان القسم بالعربية (Section Title Arabic)</label>
                    <input type="text" name="install_where_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_where_title_ar')); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">عنوان القسم بالإنجليزية (Section Title English)</label>
                    <input type="text" name="install_where_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_where_title_en')); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">وصف القسم بالعربية (Section Description Arabic)</label>
                    <textarea name="install_where_desc_ar" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting('install_where_desc_ar')); ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">وصف القسم بالإنجليزية (Section Description English)</label>
                    <textarea name="install_where_desc_en" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting('install_where_desc_en')); ?></textarea>
                </div>

                <!-- Cards (1-4) -->
                <?php for($i = 1; $i <= 4; $i++): ?>
                    <div style="margin-top: 24px; padding: 16px; border: 1px dashed var(--border-color); border-radius: var(--radius-sm); background: var(--bg-sidebar);">
                        <h4 style="font-size: 15px; margin-bottom: 12px; font-weight: 700; color: var(--text-primary);">
                            <?php echo ($lang === 'ar') ? "البطاقة {$i} (موقع التركيب)" : "Card {$i} (Installation Location)"; ?>
                        </h4>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">الاسم بالعربية</label>
                            <input type="text" name="install_loc_<?php echo $i; ?>_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("install_loc_{$i}_title_ar")); ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">الاسم بالإنجليزية</label>
                            <input type="text" name="install_loc_<?php echo $i; ?>_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("install_loc_{$i}_title_en")); ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">الوصف بالعربية</label>
                            <textarea name="install_loc_<?php echo $i; ?>_desc_ar" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting("install_loc_{$i}_desc_ar")); ?></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">الوصف بالإنجليزية</label>
                            <textarea name="install_loc_<?php echo $i; ?>_desc_en" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting("install_loc_{$i}_desc_en")); ?></textarea>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- TAB: INSTALLATION STEPS -->
        <div class="settings-section-panel <?php echo ($active_tab === 'process') ? 'active' : ''; ?>" id="panel-process" style="display: <?php echo ($active_tab === 'process') ? 'block' : 'none'; ?>;">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo $lang === 'ar' ? 'قسم خطوات ومراحل التركيب (Installation Steps)' : 'Installation Steps Section'; ?>
                </h3>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">عنوان القسم بالعربية (Section Title Arabic)</label>
                    <input type="text" name="install_phases_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_phases_title_ar')); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">عنوان القسم بالإنجليزية (Section Title English)</label>
                    <input type="text" name="install_phases_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_phases_title_en')); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">وصف القسم بالعربية (Section Description Arabic)</label>
                    <textarea name="install_phases_desc_ar" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting('install_phases_desc_ar')); ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">وصف القسم بالإنجليزية (Section Description English)</label>
                    <textarea name="install_phases_desc_en" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting('install_phases_desc_en')); ?></textarea>
                </div>

                <!-- Steps (1-4) -->
                <?php for($i = 1; $i <= 4; $i++): ?>
                    <div style="margin-top: 24px; padding: 16px; border: 1px dashed var(--border-color); border-radius: var(--radius-sm); background: var(--bg-sidebar);">
                        <h4 style="font-size: 15px; margin-bottom: 12px; font-weight: 700; color: var(--text-primary);">
                            <?php echo ($lang === 'ar') ? "الخطوة {$i} (مراحل التركيب)" : "Step {$i} (Installation Phase)"; ?>
                        </h4>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">عنوان الخطوة بالعربية</label>
                            <input type="text" name="install_step_<?php echo $i; ?>_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("install_step_{$i}_title_ar")); ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">عنوان الخطوة بالإنجليزية</label>
                            <input type="text" name="install_step_<?php echo $i; ?>_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("install_step_{$i}_title_en")); ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">الوصف بالعربية</label>
                            <textarea name="install_step_<?php echo $i; ?>_desc_ar" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting("install_step_{$i}_desc_ar")); ?></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">الوصف بالإنجليزية</label>
                            <textarea name="install_step_<?php echo $i; ?>_desc_en" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting("install_step_{$i}_desc_en")); ?></textarea>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- TAB: WHY PRO INSTALLATION -->
        <div class="settings-section-panel <?php echo ($active_tab === 'why') ? 'active' : ''; ?>" id="panel-why" style="display: <?php echo ($active_tab === 'why') ? 'block' : 'none'; ?>;">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo $lang === 'ar' ? 'قسم لماذا تختارنا (Why Professional Installation)' : 'Why Pro Installation Section'; ?>
                </h3>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">عنوان القسم بالعربية (Section Title Arabic)</label>
                    <input type="text" name="install_why_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_why_title_ar')); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">عنوان القسم بالإنجليزية (Section Title English)</label>
                    <input type="text" name="install_why_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('install_why_title_en')); ?>" required>
                </div>

                <!-- Benefits (1-3) -->
                <?php for($i = 1; $i <= 3; $i++): ?>
                    <div style="margin-top: 24px; padding: 16px; border: 1px dashed var(--border-color); border-radius: var(--radius-sm); background: var(--bg-sidebar);">
                        <h4 style="font-size: 15px; margin-bottom: 12px; font-weight: 700; color: var(--text-primary);">
                            <?php echo ($lang === 'ar') ? "الميزة الفريدة {$i}" : "Unique Benefit {$i}"; ?>
                        </h4>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">عنوان الميزة بالعربية</label>
                            <input type="text" name="install_why_<?php echo $i; ?>_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("install_why_{$i}_title_ar")); ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">عنوان الميزة بالإنجليزية</label>
                            <input type="text" name="install_why_<?php echo $i; ?>_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("install_why_{$i}_title_en")); ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">الوصف بالعربية</label>
                            <textarea name="install_why_<?php echo $i; ?>_desc_ar" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting("install_why_{$i}_desc_ar")); ?></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 13px; display: block; margin-bottom: 4px;">الوصف بالإنجليزية</label>
                            <textarea name="install_why_<?php echo $i; ?>_desc_en" class="form-control" rows="2" required><?php echo htmlspecialchars(get_setting("install_why_{$i}_desc_en")); ?></textarea>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- TAB: CALL TO ACTION -->
        <div class="settings-section-panel <?php echo ($active_tab === 'cta') ? 'active' : ''; ?>" id="panel-cta" style="display: <?php echo ($active_tab === 'cta') ? 'block' : 'none'; ?>;">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo $lang === 'ar' ? 'إعدادات دعوة الاتصال (Contextual CTA Block)' : 'Contextual Call to Action settings'; ?>
                </h3>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">العنوان بالعربية (CTA Title Arabic)</label>
                    <input type="text" name="cta_install_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('cta_install_title_ar')); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">العنوان بالإنجليزية (CTA Title English)</label>
                    <input type="text" name="cta_install_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('cta_install_title_en')); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">الوصف بالعربية (CTA Description Arabic)</label>
                    <textarea name="cta_install_desc_ar" class="form-control" rows="3" required><?php echo htmlspecialchars(get_setting('cta_install_desc_ar')); ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">الوصف بالإنجليزية (CTA Description English)</label>
                    <textarea name="cta_install_desc_en" class="form-control" rows="3" required><?php echo htmlspecialchars(get_setting('cta_install_desc_en')); ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">رسالة الواتساب التلقائية بالعربية (WhatsApp Message Arabic)</label>
                    <input type="text" name="cta_install_whatsapp_msg_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('cta_install_whatsapp_msg_ar')); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">رسالة الواتساب التلقائية بالإنجليزية (WhatsApp Message English)</label>
                    <input type="text" name="cta_install_whatsapp_msg_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('cta_install_whatsapp_msg_en')); ?>" required>
                </div>
            </div>
        </div>

        <!-- TAB: SEO SETTINGS -->
        <div class="settings-section-panel <?php echo ($active_tab === 'seo') ? 'active' : ''; ?>" id="panel-seo" style="display: <?php echo ($active_tab === 'seo') ? 'block' : 'none'; ?>;">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo $lang === 'ar' ? 'إعدادات تهيئة محركات البحث (SEO Settings)' : 'Search Engine Optimization settings'; ?>
                </h3>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">عنوان الميتا بالعربية (Meta Title Arabic)</label>
                    <input type="text" name="seo_install_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('seo_install_title_ar')); ?>">
                    <span style="font-size: 12px; color: var(--text-secondary);"><?php echo $lang === 'ar' ? 'تنبيه: سيتم استخدام عنوان البانر العلوي كعنوان افتراضي إذا ترك فارغاً.' : 'Note: Hero title will be used as fallback if left empty.'; ?></span>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">عنوان الميتا بالإنجليزية (Meta Title English)</label>
                    <input type="text" name="seo_install_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('seo_install_title_en')); ?>">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">وصف الميتا بالعربية (Meta Description Arabic)</label>
                    <textarea name="seo_install_desc_ar" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('seo_install_desc_ar')); ?></textarea>
                    <span style="font-size: 12px; color: var(--text-secondary);"><?php echo $lang === 'ar' ? 'تنبيه: سيتم استخدام وصف البانر العلوي كوصف افتراضي إذا ترك فارغاً.' : 'Note: Hero subtitle will be used as fallback if left empty.'; ?></span>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">وصف الميتا بالإنجليزية (Meta Description English)</label>
                    <textarea name="seo_install_desc_en" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('seo_install_desc_en')); ?></textarea>
                </div>

                <!-- Media Library Selection / Upload for OG Image -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">
                        <?php echo $lang === 'ar' ? 'صورة مشاركة شبكات التواصل (OpenGraph Cover Image)' : 'Social Share Cover Image (OpenGraph)'; ?>
                    </label>
                    
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                        <input type="file" name="seo_install_og_image_file" class="form-control" style="max-width: 250px;">
                        
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <select name="seo_install_og_image_media_id" class="form-control" style="max-width: 200px;">
                                <option value=""><?php echo $lang === 'ar' ? '--- اختر من مكتبة الوسائط ---' : '--- Select from Media Library ---'; ?></option>
                                <?php foreach ($media_list as $media): ?>
                                    <option value="<?php echo $media['id']; ?>" <?php echo (get_setting('seo_install_og_image') === $media['file_path']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($media['filename']); ?> (<?php echo htmlspecialchars($media['folder']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php 
                    $curr_og = get_setting('seo_install_og_image', ''); 
                    if (!empty($curr_og)):
                    ?>
                        <div style="margin-top: 12px; padding: 8px; border: 1px solid var(--border-color); display: inline-block; border-radius: var(--radius-sm);">
                            <img src="<?php echo htmlspecialchars(get_site_url() . '/' . $curr_og); ?>" alt="SEO Installation OpenGraph" style="max-height: 100px; display: block; border-radius: var(--radius-xs);">
                            <span style="font-size: 11px; color: var(--text-secondary); display: block; text-align: center; margin-top: 4px;">
                                <?php echo htmlspecialchars($curr_og); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Submit Panel -->
        <div style="margin-top: 24px; text-align: end;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i data-lucide="check" style="width: 18px; height: 18px;"></i>
                <span><?php echo $lang === 'ar' ? 'حفظ التغييرات' : 'Save Changes'; ?></span>
            </button>
        </div>
    </form>
</main>

<script>
function switchTab(event, tabId) {
    event.preventDefault();
    
    // Hide all panels
    const panels = document.querySelectorAll('.settings-section-panel');
    panels.forEach(p => p.style.display = 'none');
    
    // Remove active class from buttons
    const buttons = document.querySelectorAll('.settings-tab-btn');
    buttons.forEach(b => b.classList.remove('active'));
    
    // Show active panel & button
    document.getElementById('panel-' + tabId).style.display = 'block';
    event.currentTarget.classList.add('active');
    
    // Update hidden inputs
    document.getElementById('activeTabInput').value = tabId;
}
</script>

<?php
require_once dirname(__DIR__) . '/includes/admin-footer.php';
?>
