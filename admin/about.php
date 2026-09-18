<?php
// admin/about.php
// Dedicated editor dashboard for About Us CMS.

require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';
require_once dirname(__DIR__) . '/includes/settings-helper.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';
require_once dirname(__DIR__) . '/includes/about-helper.php';

$success_message = '';
$error_message = '';
$field_errors = [];

// Get all registry settings
$registry = get_settings_registry();

// Sections list for about page
$sections_list = [
    'banner'    => ['label_ar' => 'قسم البانر العلوي', 'label_en' => 'Top Banner Section', 'default_order' => 10],
    'story'     => ['label_ar' => 'قسم القصة والرؤية والرسالة', 'label_en' => 'Story, Mission & Vision Section', 'default_order' => 20],
    'stats'     => ['label_ar' => 'قسم الإحصائيات (مشترك)', 'label_en' => 'Business Statistics (Shared)', 'default_order' => 30],
    'expertise' => ['label_ar' => 'قسم خبرتنا المتخصصة', 'label_en' => 'Specialized Expertise Section', 'default_order' => 40],
    'values'    => ['label_ar' => 'قسم قيمنا الرئيسية', 'label_en' => 'Key Values Section', 'default_order' => 50],
    'timeline'  => ['label_ar' => 'قسم الجدول الزمني للمسيرة', 'label_en' => 'Journey Timeline Section', 'default_order' => 60],
    'cta'       => ['label_ar' => 'قسم الدعوة للإجراء (CTA)', 'label_en' => 'Call to Action Section', 'default_order' => 70]
];

$cta_actions_allowlist = ['whatsapp', 'products', 'services', 'projects', 'contact', 'none'];

// Fetch Media Library images for selectors (latest 200)
$media_list = [];
try {
    $media_stmt = $pdo->query("SELECT id, filename, file_path, folder FROM media_library ORDER BY id DESC LIMIT 200");
    $media_list = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed fetching media list in about dashboard: " . $e->getMessage());
}

$active_tab = isset($_POST['active_tab']) ? trim($_POST['active_tab']) : 'visibility';
$tabs_order = [
    'visibility' => ['icon' => 'sliders',   'label' => get_current_lang() === 'ar' ? 'أقسام الصفحة' : 'Page Sections'],
    'story'      => ['icon' => 'info',      'label' => get_current_lang() === 'ar' ? 'القصة والخبرة' : 'Story & Expertise'],
    'values'     => ['icon' => 'shield',    'label' => get_current_lang() === 'ar' ? 'قيمنا' : 'Our Values'],
    'timeline'   => ['icon' => 'calendar',  'label' => get_current_lang() === 'ar' ? 'المسيرة الزمنية' : 'Timeline'],
    'cta'        => ['icon' => 'phone',     'label' => get_current_lang() === 'ar' ? 'الدعوة للإجراء' : 'CTA Block'],
    'seo'        => ['icon' => 'search',    'label' => get_current_lang() === 'ar' ? 'إعدادات SEO' : 'SEO Settings']
];

if (!array_key_exists($active_tab, $tabs_order)) {
    $active_tab = 'visibility';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_about'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = get_current_lang() === 'ar' ? 'رمز الحماية (CSRF Token) غير صالح.' : 'Invalid security token (CSRF).';
    } else {
        $pdo->beginTransaction();
        $newFilesTracked = [];
        $validation_failed = false;
        $prepared_vals = [];
        
        try {
            // Helper to process uploaded file or media selection
            $process_image_field = function($file_field_name, $select_field_name, $current_val) use ($pdo, &$newFilesTracked, &$error_message, &$validation_failed) {
                // 1. Direct upload takes priority
                if (isset($_FILES[$file_field_name]) && $_FILES[$file_field_name]['error'] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $media_id = upload_to_media_library($pdo, $_FILES[$file_field_name], 'about');
                        if ($media_id) {
                            $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                            $stmt->execute([':id' => $media_id]);
                            return $stmt->fetchColumn();
                        }
                    } catch (Exception $e) {
                        $error_message = $e->getMessage();
                        $validation_failed = true;
                        return $current_val;
                    }
                }
                
                // 2. Otherwise, selected media ID is used
                if (!empty($_POST[$select_field_name])) {
                    $media_id = intval($_POST[$select_field_name]);
                    $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                    $stmt->execute([':id' => $media_id]);
                    $db_path = $stmt->fetchColumn();
                    if ($db_path) {
                        return $db_path;
                    }
                }
                
                // 3. Keep current
                return $current_val;
            };

            // 1. Section Visibility & Sorting
            foreach ($sections_list as $key => $sec) {
                $show_field = "about_show_" . $key;
                $order_field = "about_order_" . $key;
                
                $show_val = isset($_POST[$show_field]) ? '1' : '0';
                $order_raw = isset($_POST[$order_field]) ? trim($_POST[$order_field]) : '';
                $order_val = filter_var($order_raw, FILTER_VALIDATE_INT);
                
                if ($order_val === false || $order_val < 0 || $order_val > 999) {
                    $error_message = (get_current_lang() === 'ar')
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
                'about_us_title_ar', 'about_us_title_en',
                'about_banner_subtitle_ar', 'about_banner_subtitle_en',
                'about_story_title_ar', 'about_story_title_en',
                'about_story_desc_ar', 'about_story_desc_en',
                'about_mission_title_ar', 'about_mission_title_en',
                'about_mission_desc_ar', 'about_mission_desc_en',
                'about_vision_title_ar', 'about_vision_title_en',
                'about_vision_desc_ar', 'about_vision_desc_en',
                'about_exp_title_ar', 'about_exp_title_en',
                'about_exp_desc_ar', 'about_exp_desc_en',
                'about_values_title_ar', 'about_values_title_en',
                'about_value_1_title_ar', 'about_value_1_title_en', 'about_value_1_desc_ar', 'about_value_1_desc_en',
                'about_value_2_title_ar', 'about_value_2_title_en', 'about_value_2_desc_ar', 'about_value_2_desc_en',
                'about_value_3_title_ar', 'about_value_3_title_en', 'about_value_3_desc_ar', 'about_value_3_desc_en',
                'about_timeline_title_ar', 'about_timeline_title_en',
                'about_timeline_subtitle_ar', 'about_timeline_subtitle_en',
                'about_milestone_1_title_ar', 'about_milestone_1_title_en', 'about_milestone_1_desc_ar', 'about_milestone_1_desc_en',
                'about_milestone_2_title_ar', 'about_milestone_2_title_en', 'about_milestone_2_desc_ar', 'about_milestone_2_desc_en',
                'about_milestone_3_title_ar', 'about_milestone_3_title_en', 'about_milestone_3_desc_ar', 'about_milestone_3_desc_en',
                'about_milestone_4_title_ar', 'about_milestone_4_title_en', 'about_milestone_4_desc_ar', 'about_milestone_4_desc_en',
                'about_cta_title_ar', 'about_cta_title_en',
                'about_cta_desc_ar', 'about_cta_desc_en',
                'about_cta_action',
                'seo_about_title_ar', 'seo_about_title_en',
                'seo_about_desc_ar', 'seo_about_desc_en'
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

            // Verify Action Target allowlist
            if (!$validation_failed && !in_array($prepared_vals['about_cta_action'], $cta_actions_allowlist, true)) {
                $field_errors['about_cta_action'] = get_current_lang() === 'ar' ? 'الخيار المحدد غير صالح.' : 'Invalid option selected.';
                $validation_failed = true;
            }

            // 3. Image Process
            if (!$validation_failed) {
                // about_image
                $curr_about_image = get_setting('about_image', 'assets/images/shelf4.png');
                $final_about_image = $process_image_field('about_image_file', 'about_image_media_id', $curr_about_image);
                $prepared_vals['about_image'] = $final_about_image;

                // about_cta_bg_image
                $curr_cta_bg = get_setting('about_cta_bg_image', '');
                $final_cta_bg = $process_image_field('about_cta_bg_image_file', 'about_cta_bg_image_media_id', $curr_cta_bg);
                $prepared_vals['about_cta_bg_image'] = $final_cta_bg;

                // seo_about_og_image
                $curr_seo_og = get_setting('seo_about_og_image', '');
                $final_seo_og = $process_image_field('seo_about_og_image_file', 'seo_about_og_image_media_id', $curr_seo_og);
                $prepared_vals['seo_about_og_image'] = $final_seo_og;
            }

            if ($validation_failed) {
                throw new ProjectValidationException(get_current_lang() === 'ar' ? 'يرجى تصحيح الأخطاء الموضحة أدناه.' : 'Please correct the validation errors below.');
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
            $success_message = get_current_lang() === 'ar' ? 'تم حفظ إعدادات من نحن بنجاح.' : 'About Us settings saved successfully.';

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
            error_log("Database error in about save: " . $e->getMessage());
            $error_message = get_current_lang() === 'ar' ? 'حدث خطأ في قاعدة البيانات أثناء الحفظ.' : 'Database operation failed.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rollback_cleanup_files($newFilesTracked);
            error_log("Unexpected error in about save: " . $e->getMessage());
            $error_message = get_current_lang() === 'ar' ? 'حدث خطأ غير متوقع أثناء الحفظ.' : 'Unexpected operation failed.';
        }
    }
}

$page_title = get_current_lang() === 'ar' ? 'إدارة صفحة من نحن' : 'Manage About Us';
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<main class="admin-content">
    <header class="admin-header">
        <div>
            <h1 style="font-size: 28px;"><?php echo $page_title; ?></h1>
            <p><?php echo get_current_lang() === 'ar' ? 'تخصيص كامل لمحتوى صفحة من نحن وأقسامها وإعدادات الـ SEO' : 'Full customization of the About Us page content, sections, and SEO'; ?></p>
        </div>
        
        <a href="?lang=<?php echo get_current_lang() === 'ar' ? 'en' : 'ar'; ?>" class="lang-toggle">
            <i data-lucide="globe"></i>
            <span><?php echo get_current_lang() === 'ar' ? 'English' : 'العربية'; ?></span>
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

    <!-- Responsive Tab Navigations -->
    <div class="settings-nav-tabs" style="display: flex; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 24px;">
        <?php foreach ($tabs_order as $tab_key => $tab_info): ?>
            <button type="button" class="settings-tab-btn <?php echo ($active_tab === $tab_key) ? 'active' : ''; ?>" onclick="switchTab(event, '<?php echo $tab_key; ?>')" style="padding: 10px 16px; border: 1px solid var(--border-color); background: var(--bg-main); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
                <i data-lucide="<?php echo $tab_info['icon']; ?>" style="width: 16px; height: 16px;"></i>
                <span><?php echo htmlspecialchars($tab_info['label']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- About Editor Form -->
    <form action="about.php" method="POST" enctype="multipart/form-data" id="aboutForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="save_about" value="1">
        <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">

        <!-- TAB: VISIBILITY & ORDER -->
        <div class="settings-section-panel <?php echo ($active_tab === 'visibility') ? 'active' : ''; ?>" id="panel-visibility" style="display: <?php echo ($active_tab === 'visibility') ? 'block' : 'none'; ?>;">
            <section class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo get_current_lang() === 'ar' ? 'ظهور وترتيب الأقسام' : 'Section Visibility & Sorting'; ?>
                </h3>
                
                <table class="admin-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: start; padding: 12px; border-bottom: 2px solid var(--border-color);"><?php echo get_current_lang() === 'ar' ? 'القسم' : 'Section'; ?></th>
                            <th style="text-align: center; padding: 12px; border-bottom: 2px solid var(--border-color);"><?php echo get_current_lang() === 'ar' ? 'الحالة' : 'Status'; ?></th>
                            <th style="text-align: center; padding: 12px; border-bottom: 2px solid var(--border-color);"><?php echo get_current_lang() === 'ar' ? 'الترتيب' : 'Order Weight'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections_list as $key => $sec): ?>
                            <?php 
                            $show_val = get_setting("about_show_" . $key, '1');
                            $order_val = get_setting("about_order_" . $key, (string)$sec['default_order']);
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars(get_current_lang() === 'ar' ? $sec['label_ar'] : $sec['label_en']); ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 22px;">
                                        <input type="checkbox" name="about_show_<?php echo $key; ?>" value="1" <?php echo ($show_val === '1') ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                                        <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                    </label>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <input type="number" name="about_order_<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($order_val); ?>" min="0" max="999" style="width: 100px; margin: 0 auto; text-align: center;" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- TAB: STORY & EXPERTISE -->
        <div class="settings-section-panel <?php echo ($active_tab === 'story') ? 'active' : ''; ?>" id="panel-story" style="display: <?php echo ($active_tab === 'story') ? 'block' : 'none'; ?>;">
            <section class="admin-table-card" style="padding: 24px; display: flex; flex-direction: column; gap: 20px;">
                <h3 style="font-size: 18px; margin-bottom: 10px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo get_current_lang() === 'ar' ? 'البانر والقصة والخبرة' : 'Banner, Story & Expertise'; ?>
                </h3>

                <h4 style="color: var(--accent-primary); margin-top: 10px;"><?php echo get_current_lang() === 'ar' ? 'بانر أعلى الصفحة (Banner)' : 'Top Page Banner'; ?></h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان البانر الرئيسي (Ar)' : 'Banner Title (Ar)'; ?></label>
                        <input type="text" name="about_us_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_us_title_ar', '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان البانر الرئيسي (En)' : 'Banner Title (En)'; ?></label>
                        <input type="text" name="about_us_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_us_title_en', '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'العنوان الفرعي للبانر (Ar)' : 'Banner Subtitle (Ar)'; ?></label>
                        <input type="text" name="about_banner_subtitle_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_banner_subtitle_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'العنوان الفرعي للبانر (En)' : 'Banner Subtitle (En)'; ?></label>
                        <input type="text" name="about_banner_subtitle_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_banner_subtitle_en', '')); ?>">
                    </div>
                </div>

                <hr style="border-color: var(--border-color); margin: 15px 0;">

                <h4 style="color: var(--accent-primary);"><?php echo get_current_lang() === 'ar' ? 'قصة الشركة (Story)' : 'Company Story'; ?></h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان القصة (Ar)' : 'Story Title (Ar)'; ?></label>
                        <input type="text" name="about_story_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_story_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان القصة (En)' : 'Story Title (En)'; ?></label>
                        <input type="text" name="about_story_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_story_title_en', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'تفاصيل قصة الشركة (Ar)' : 'Story Details (Ar)'; ?></label>
                        <textarea name="about_story_desc_ar" class="form-control" rows="5"><?php echo htmlspecialchars(get_setting('about_story_desc_ar', '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'تفاصيل قصة الشركة (En)' : 'Story Details (En)'; ?></label>
                        <textarea name="about_story_desc_en" class="form-control" rows="5"><?php echo htmlspecialchars(get_setting('about_story_desc_en', '')); ?></textarea>
                    </div>
                </div>

                <hr style="border-color: var(--border-color); margin: 15px 0;">

                <h4 style="color: var(--accent-primary);"><?php echo get_current_lang() === 'ar' ? 'الرسالة والرؤية' : 'Mission & Vision'; ?></h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان الرسالة (Ar)' : 'Mission Title (Ar)'; ?></label>
                        <input type="text" name="about_mission_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_mission_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان الرسالة (En)' : 'Mission Title (En)'; ?></label>
                        <input type="text" name="about_mission_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_mission_title_en', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'تفاصيل الرسالة (Ar)' : 'Mission Details (Ar)'; ?></label>
                        <textarea name="about_mission_desc_ar" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('about_mission_desc_ar', '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'تفاصيل الرسالة (En)' : 'Mission Details (En)'; ?></label>
                        <textarea name="about_mission_desc_en" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('about_mission_desc_en', '')); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان الرؤية (Ar)' : 'Vision Title (Ar)'; ?></label>
                        <input type="text" name="about_vision_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_vision_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان الرؤية (En)' : 'Vision Title (En)'; ?></label>
                        <input type="text" name="about_vision_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_vision_title_en', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'تفاصيل الرؤية (Ar)' : 'Vision Details (Ar)'; ?></label>
                        <textarea name="about_vision_desc_ar" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('about_vision_desc_ar', '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'تفاصيل الرؤية (En)' : 'Vision Details (En)'; ?></label>
                        <textarea name="about_vision_desc_en" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('about_vision_desc_en', '')); ?></textarea>
                    </div>
                </div>

                <hr style="border-color: var(--border-color); margin: 15px 0;">

                <h4 style="color: var(--accent-primary);"><?php echo get_current_lang() === 'ar' ? 'صورة القسم الرئيسية' : 'Story Section Main Image'; ?></h4>
                <div class="homepage-image-selector-group">
                    <?php 
                    $curr_about_image = get_setting('about_image', 'assets/images/shelf4.png');
                    if (!empty($curr_about_image)): ?>
                        <div style="margin-bottom:10px;">
                            <img src="../<?php echo htmlspecialchars($curr_about_image); ?>" style="max-height: 120px; border-radius: var(--radius-sm); border:1px solid var(--border-color);">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label style="font-size:12px;"><?php echo __('homepage_direct_upload'); ?></label>
                        <input type="file" name="about_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding: 5px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12px;"><?php echo __('homepage_select_media'); ?></label>
                        <select name="about_image_media_id" class="form-control">
                            <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                            <?php 
                            $found_current = false;
                            foreach ($media_list as $m) {
                                $sel = ($curr_about_image === $m['file_path']) ? 'selected' : '';
                                if ($sel) $found_current = true;
                                echo '<option value="' . $m['id'] . '" ' . $sel . '>' . htmlspecialchars($m['folder'] . ' / ' . $m['filename']) . '</option>';
                            }
                            if (!$found_current && !empty($curr_about_image)) {
                                try {
                                    $f_stmt = $pdo->prepare("SELECT id, filename, folder FROM media_library WHERE file_path = :path");
                                    $f_stmt->execute([':path' => $curr_about_image]);
                                    $f_row = $f_stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($f_row) {
                                        echo '<option value="' . $f_row['id'] . '" selected>' . htmlspecialchars($f_row['folder'] . ' / ' . $f_row['filename']) . ' (المحددة حالياً)</option>';
                                    }
                                } catch (PDOException $e) {}
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <hr style="border-color: var(--border-color); margin: 15px 0;">

                <h4 style="color: var(--accent-primary);"><?php echo get_current_lang() === 'ar' ? 'خبرتنا المتخصصة (Expertise)' : 'Specialized Expertise'; ?></h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان الخبرة (Ar)' : 'Expertise Title (Ar)'; ?></label>
                        <input type="text" name="about_exp_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_exp_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان الخبرة (En)' : 'Expertise Title (En)'; ?></label>
                        <input type="text" name="about_exp_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_exp_title_en', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'تفاصيل الخبرة (Ar)' : 'Expertise Details (Ar)'; ?></label>
                        <textarea name="about_exp_desc_ar" class="form-control" rows="4"><?php echo htmlspecialchars(get_setting('about_exp_desc_ar', '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'تفاصيل الخبرة (En)' : 'Expertise Details (En)'; ?></label>
                        <textarea name="about_exp_desc_en" class="form-control" rows="4"><?php echo htmlspecialchars(get_setting('about_exp_desc_en', '')); ?></textarea>
                    </div>
                </div>
            </section>
        </div>

        <!-- TAB: VALUES -->
        <div class="settings-section-panel <?php echo ($active_tab === 'values') ? 'active' : ''; ?>" id="panel-values" style="display: <?php echo ($active_tab === 'values') ? 'block' : 'none'; ?>;">
            <section class="admin-table-card" style="padding: 24px; display: flex; flex-direction: column; gap: 20px;">
                <h3 style="font-size: 18px; margin-bottom: 10px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo get_current_lang() === 'ar' ? 'قيمنا الرئيسية (3 قيم ثابتة الأيقونات)' : 'Key Values Section (3 fixed icons)'; ?>
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان قسم القيم (Ar)' : 'Values Section Title (Ar)'; ?></label>
                        <input type="text" name="about_values_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_values_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان قسم القيم (En)' : 'Values Section Title (En)'; ?></label>
                        <input type="text" name="about_values_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_values_title_en', '')); ?>">
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 10px;">
                    <!-- Value 1 -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><i data-lucide="shield" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-inline-end: 5px;"></i> <?php echo get_current_lang() === 'ar' ? 'القيمة الأولى (الأيقونة: shield)' : 'Value 1 (Icon: shield)'; ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>العنوان (Ar)</label>
                                <input type="text" name="about_value_1_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_1_title_ar', '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>العنوان (En)</label>
                                <input type="text" name="about_value_1_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_1_title_en', '')); ?>">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>الوصف (Ar)</label>
                                <input type="text" name="about_value_1_desc_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_1_desc_ar', '')); ?>">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>الوصف (En)</label>
                                <input type="text" name="about_value_1_desc_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_1_desc_en', '')); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Value 2 -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><i data-lucide="zap" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-inline-end: 5px;"></i> <?php echo get_current_lang() === 'ar' ? 'القيمة الثانية (الأيقونة: zap)' : 'Value 2 (Icon: zap)'; ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>العنوان (Ar)</label>
                                <input type="text" name="about_value_2_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_2_title_ar', '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>العنوان (En)</label>
                                <input type="text" name="about_value_2_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_2_title_en', '')); ?>">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>الوصف (Ar)</label>
                                <input type="text" name="about_value_2_desc_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_2_desc_ar', '')); ?>">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>الوصف (En)</label>
                                <input type="text" name="about_value_2_desc_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_2_desc_en', '')); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Value 3 -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><i data-lucide="scale" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-inline-end: 5px;"></i> <?php echo get_current_lang() === 'ar' ? 'القيمة الثالثة (الأيقونة: scale)' : 'Value 3 (Icon: scale)'; ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>العنوان (Ar)</label>
                                <input type="text" name="about_value_3_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_3_title_ar', '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>العنوان (En)</label>
                                <input type="text" name="about_value_3_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_3_title_en', '')); ?>">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>الوصف (Ar)</label>
                                <input type="text" name="about_value_3_desc_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_3_desc_ar', '')); ?>">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>الوصف (En)</label>
                                <input type="text" name="about_value_3_desc_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_value_3_desc_en', '')); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- TAB: TIMELINE -->
        <div class="settings-section-panel <?php echo ($active_tab === 'timeline') ? 'active' : ''; ?>" id="panel-timeline" style="display: <?php echo ($active_tab === 'timeline') ? 'block' : 'none'; ?>;">
            <section class="admin-table-card" style="padding: 24px; display: flex; flex-direction: column; gap: 20px;">
                <h3 style="font-size: 18px; margin-bottom: 10px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo get_current_lang() === 'ar' ? 'الجدول الزمني ومسيرة النمو (عدد 4 محطات ثابتة)' : 'Growth Timeline & Milestones (exactly 4 fixed steps)'; ?>
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان قسم الجدول الزمني (Ar)' : 'Timeline Title (Ar)'; ?></label>
                        <input type="text" name="about_timeline_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_timeline_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان قسم الجدول الزمني (En)' : 'Timeline Title (En)'; ?></label>
                        <input type="text" name="about_timeline_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_timeline_title_en', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'الوصف الفرعي لقسم الجدول الزمني (Ar)' : 'Timeline Subtitle (Ar)'; ?></label>
                        <input type="text" name="about_timeline_subtitle_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_timeline_subtitle_ar', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'الوصف الفرعي لقسم الجدول الزمني (En)' : 'Timeline Subtitle (En)'; ?></label>
                        <input type="text" name="about_timeline_subtitle_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_timeline_subtitle_en', '')); ?>">
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 10px;">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo get_current_lang() === 'ar' ? "المحطة رقم 0{$i}" : "Milestone 0{$i}"; ?></strong>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="form-group">
                                    <label>العنوان (Ar)</label>
                                    <input type="text" name="about_milestone_<?php echo $i; ?>_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("about_milestone_{$i}_title_ar", '')); ?>">
                                </div>
                                <div class="form-group">
                                    <label>العنوان (En)</label>
                                    <input type="text" name="about_milestone_<?php echo $i; ?>_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("about_milestone_{$i}_title_en", '')); ?>">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>الوصف (Ar)</label>
                                    <input type="text" name="about_milestone_<?php echo $i; ?>_desc_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("about_milestone_{$i}_desc_ar", '')); ?>">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>الوصف (En)</label>
                                    <input type="text" name="about_milestone_<?php echo $i; ?>_desc_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("about_milestone_{$i}_desc_en", '')); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </section>
        </div>

        <!-- TAB: CTA -->
        <div class="settings-section-panel <?php echo ($active_tab === 'cta') ? 'active' : ''; ?>" id="panel-cta" style="display: <?php echo ($active_tab === 'cta') ? 'block' : 'none'; ?>;">
            <section class="admin-table-card" style="padding: 24px; display: flex; flex-direction: column; gap: 20px;">
                <h3 style="font-size: 18px; margin-bottom: 10px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo get_current_lang() === 'ar' ? 'صندوق الدعوة للإجراء (CTA Block)' : 'Contextual Call to Action (CTA) Block'; ?>
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'العنوان الرئيسي (Ar)' : 'CTA Title (Ar)'; ?></label>
                        <input type="text" name="about_cta_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_cta_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'العنوان الرئيسي (En)' : 'CTA Title (En)'; ?></label>
                        <input type="text" name="about_cta_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('about_cta_title_en', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'الوصف التفصيلي (Ar)' : 'CTA Description (Ar)'; ?></label>
                        <textarea name="about_cta_desc_ar" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('about_cta_desc_ar', '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'الوصف التفصيلي (En)' : 'CTA Description (En)'; ?></label>
                        <textarea name="about_cta_desc_en" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('about_cta_desc_en', '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'وجهة الزر عند الضغط (Action)' : 'Target Action Target (Link)'; ?></label>
                        <select name="about_cta_action" class="form-control">
                            <option value="whatsapp" <?php echo (get_setting('about_cta_action', 'whatsapp') === 'whatsapp') ? 'selected' : ''; ?>><?php echo get_current_lang() === 'ar' ? 'واتساب / WhatsApp (موصى به)' : 'WhatsApp / Quote (Default)'; ?></option>
                            <option value="products" <?php echo (get_setting('about_cta_action') === 'products') ? 'selected' : ''; ?>><?php echo get_current_lang() === 'ar' ? 'المنتجات / Products' : 'Products Page'; ?></option>
                            <option value="services" <?php echo (get_setting('about_cta_action') === 'services') ? 'selected' : ''; ?>><?php echo get_current_lang() === 'ar' ? 'الخدمات / Services' : 'Services Page'; ?></option>
                            <option value="projects" <?php echo (get_setting('about_cta_action') === 'projects') ? 'selected' : ''; ?>><?php echo get_current_lang() === 'ar' ? 'المشاريع / Projects' : 'Projects Page'; ?></option>
                            <option value="contact" <?php echo (get_setting('about_cta_action') === 'contact') ? 'selected' : ''; ?>><?php echo get_current_lang() === 'ar' ? 'الاتصال / Contact' : 'Contact Page'; ?></option>
                            <option value="none" <?php echo (get_setting('about_cta_action') === 'none') ? 'selected' : ''; ?>><?php echo get_current_lang() === 'ar' ? 'بلا زر / None' : 'No Buttons'; ?></option>
                        </select>
                    </div>
                </div>

                <div class="homepage-image-selector-group">
                    <label style="font-weight:bold;"><?php echo get_current_lang() === 'ar' ? 'صورة الخلفية المخصصة (اختياري)' : 'CTA Custom Background Image (Optional)'; ?></label>
                    <?php 
                    $curr_cta_bg = get_setting('about_cta_bg_image', '');
                    if (!empty($curr_cta_bg)): ?>
                        <div style="margin-bottom:10px;">
                            <img src="../<?php echo htmlspecialchars($curr_cta_bg); ?>" style="max-height: 120px; border-radius: var(--radius-sm); border:1px solid var(--border-color);">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label style="font-size:12px;"><?php echo __('homepage_direct_upload'); ?></label>
                        <input type="file" name="about_cta_bg_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding: 5px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12px;"><?php echo __('homepage_select_media'); ?></label>
                        <select name="about_cta_bg_image_media_id" class="form-control">
                            <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                            <?php 
                            $found_current = false;
                            foreach ($media_list as $m) {
                                $sel = ($curr_cta_bg === $m['file_path']) ? 'selected' : '';
                                if ($sel) $found_current = true;
                                echo '<option value="' . $m['id'] . '" ' . $sel . '>' . htmlspecialchars($m['folder'] . ' / ' . $m['filename']) . ' (المحددة حالياً)</option>';
                            }
                            if (!$found_current && !empty($curr_cta_bg)) {
                                try {
                                    $f_stmt = $pdo->prepare("SELECT id, filename, folder FROM media_library WHERE file_path = :path");
                                    $f_stmt->execute([':path' => $curr_cta_bg]);
                                    $f_row = $f_stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($f_row) {
                                        echo '<option value="' . $f_row['id'] . '" selected>' . htmlspecialchars($f_row['folder'] . ' / ' . $f_row['filename']) . ' (المحددة حالياً)</option>';
                                    }
                                } catch (PDOException $e) {}
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </section>
        </div>

        <!-- TAB: SEO -->
        <div class="settings-section-panel <?php echo ($active_tab === 'seo') ? 'active' : ''; ?>" id="panel-seo" style="display: <?php echo ($active_tab === 'seo') ? 'block' : 'none'; ?>;">
            <section class="admin-table-card" style="padding: 24px; display: flex; flex-direction: column; gap: 20px;">
                <h3 style="font-size: 18px; margin-bottom: 10px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; color: var(--accent-primary);">
                    <?php echo get_current_lang() === 'ar' ? 'إعدادات تحسين محركات البحث لصفحة من نحن (SEO)' : 'SEO Metadata Settings for About Us Page'; ?>
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان الصفحة لمحركات البحث (SEO Title Ar)' : 'SEO Title (Ar)'; ?></label>
                        <input type="text" name="seo_about_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('seo_about_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'عنوان الصفحة لمحركات البحث (SEO Title En)' : 'SEO Title (En)'; ?></label>
                        <input type="text" name="seo_about_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('seo_about_title_en', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'وصف الصفحة لمحركات البحث (SEO Desc Ar)' : 'SEO Description (Ar)'; ?></label>
                        <input type="text" name="seo_about_desc_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('seo_about_desc_ar', '')); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'وصف الصفحة لمحركات البحث (SEO Desc En)' : 'SEO Description (En)'; ?></label>
                        <input type="text" name="seo_about_desc_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('seo_about_desc_en', '')); ?>">
                    </div>
                </div>

                <div class="homepage-image-selector-group">
                    <label style="font-weight:bold;"><?php echo get_current_lang() === 'ar' ? 'صورة OpenGraph المخصصة لمواقع التواصل' : 'Custom Social Share / OpenGraph Image'; ?></label>
                    <?php 
                    $curr_seo_og = get_setting('seo_about_og_image', '');
                    if (!empty($curr_seo_og)): ?>
                        <div style="margin-bottom:10px;">
                            <img src="../<?php echo htmlspecialchars($curr_seo_og); ?>" style="max-height: 120px; border-radius: var(--radius-sm); border:1px solid var(--border-color);">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label style="font-size:12px;"><?php echo __('homepage_direct_upload'); ?></label>
                        <input type="file" name="seo_about_og_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding: 5px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12px;"><?php echo __('homepage_select_media'); ?></label>
                        <select name="seo_about_og_image_media_id" class="form-control">
                            <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                            <?php 
                            $found_current = false;
                            foreach ($media_list as $m) {
                                $sel = ($curr_seo_og === $m['file_path']) ? 'selected' : '';
                                if ($sel) $found_current = true;
                                echo '<option value="' . $m['id'] . '" ' . $sel . '>' . htmlspecialchars($m['folder'] . ' / ' . $m['filename']) . ' (المحددة حالياً)</option>';
                            }
                            if (!$found_current && !empty($curr_seo_og)) {
                                try {
                                    $f_stmt = $pdo->prepare("SELECT id, filename, folder FROM media_library WHERE file_path = :path");
                                    $f_stmt->execute([':path' => $curr_seo_og]);
                                    $f_row = $f_stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($f_row) {
                                        echo '<option value="' . $f_row['id'] . '" selected>' . htmlspecialchars($f_row['folder'] . ' / ' . $f_row['filename']) . ' (المحددة حالياً)</option>';
                                    }
                                } catch (PDOException $e) {}
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </section>
        </div>

        <!-- Sticky Submit Bar -->
        <div style="position: sticky; bottom: 0; background: var(--bg-main); padding: 16px 24px; margin-top: 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; z-index: 100;">
            <button type="submit" class="btn btn-primary" style="font-weight: 700; padding: 12px 24px;">
                <i data-lucide="save" style="width: 18px; height: 18px; vertical-align: middle; margin-inline-end: 6px;"></i>
                <?php echo __('save_settings'); ?>
            </button>
        </div>
    </form>
</main>

<script>
function switchTab(evt, tabId) {
    // Hide all panels
    const panels = document.querySelectorAll('.settings-section-panel');
    panels.forEach(p => {
        p.classList.remove('active');
        p.style.display = 'none';
    });
    
    // Deactivate all tab buttons
    const buttons = document.querySelectorAll('.settings-tab-btn');
    buttons.forEach(b => b.classList.remove('active'));
    
    // Show current panel and activate button
    const activePanel = document.getElementById('panel-' + tabId);
    if (activePanel) {
        activePanel.classList.add('active');
        activePanel.style.display = 'block';
    }
    evt.currentTarget.classList.add('active');
    
    // Set hidden input value
    document.getElementById('activeTabInput').value = tabId;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
