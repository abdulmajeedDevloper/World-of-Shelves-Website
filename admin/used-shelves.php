<?php
// admin/used-shelves.php
// Administration interface for Used Shelves Page CMS.

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
    'visibility'  => ['icon' => 'sliders', 'label' => ($lang === 'ar' ? 'ظهور وترتيب الأقسام' : 'Visibility & Ordering')],
    'hero'        => ['icon' => 'sparkles', 'label' => ($lang === 'ar' ? 'البانر العلوي' : 'Hero Banner')],
    'buy_process' => ['icon' => 'warehouse', 'label' => ($lang === 'ar' ? 'المشتريات والخطوات' : 'Buy & Steps')],
    'factors_faq' => ['icon' => 'help-circle', 'label' => ($lang === 'ar' ? 'العوامل والأسئلة' : 'Factors & FAQs')],
    'cta'         => ['icon' => 'phone-call', 'label' => ($lang === 'ar' ? 'دعوة الاتصال' : 'Call to Action')],
    'seo'         => ['icon' => 'search', 'label' => ($lang === 'ar' ? 'محركات البحث' : 'SEO Settings')]
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
    'buy'     => ['label_ar' => 'ماذا نشتري', 'label_en' => 'What We Buy', 'default_order' => 20],
    'process' => ['label_ar' => 'خطوات الشراء', 'label_en' => 'Purchase Process', 'default_order' => 30],
    'factors' => ['label_ar' => 'عوامل التقييم', 'label_en' => 'Evaluation Factors', 'default_order' => 40],
    'faq'     => ['label_ar' => 'الأسئلة الشائعة', 'label_en' => 'FAQ Accordion', 'default_order' => 50],
    'cta'     => ['label_ar' => 'دعوة الاتصال (CTA)', 'label_en' => 'Call to Action', 'default_order' => 60]
];

// POST update processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_used'])) {
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
                $show_field = "used_show_" . $key;
                $order_field = "used_order_" . $key;
                
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
                'used_hero_title_ar', 'used_hero_title_en',
                'used_hero_subtitle_ar', 'used_hero_subtitle_en',
                'used_hero_cta_label_ar', 'used_hero_cta_label_en',
                'used_hero_secondary_label_ar', 'used_hero_secondary_label_en',
                'used_hero_wa_msg_ar', 'used_hero_wa_msg_en',
                
                'used_buy_title_ar', 'used_buy_title_en',
                'used_buy_desc_ar', 'used_buy_desc_en',
                'used_buy_1_title_ar', 'used_buy_1_title_en', 'used_buy_1_desc_ar', 'used_buy_1_desc_en',
                'used_buy_2_title_ar', 'used_buy_2_title_en', 'used_buy_2_desc_ar', 'used_buy_2_desc_en',
                'used_buy_3_title_ar', 'used_buy_3_title_en', 'used_buy_3_desc_ar', 'used_buy_3_desc_en',
                'used_buy_4_title_ar', 'used_buy_4_title_en', 'used_buy_4_desc_ar', 'used_buy_4_desc_en',
                
                'used_process_title_ar', 'used_process_title_en',
                'used_process_desc_ar', 'used_process_desc_en',
                'used_step_1_title_ar', 'used_step_1_title_en', 'used_step_1_desc_ar', 'used_step_1_desc_en',
                'used_step_2_title_ar', 'used_step_2_title_en', 'used_step_2_desc_ar', 'used_step_2_desc_en',
                'used_step_3_title_ar', 'used_step_3_title_en', 'used_step_3_desc_ar', 'used_step_3_desc_en',
                'used_step_4_title_ar', 'used_step_4_title_en', 'used_step_4_desc_ar', 'used_step_4_desc_en',
                
                'used_factors_title_ar', 'used_factors_title_en',
                'used_factor_1_title_ar', 'used_factor_1_title_en', 'used_factor_1_desc_ar', 'used_factor_1_desc_en',
                'used_factor_2_title_ar', 'used_factor_2_title_en', 'used_factor_2_desc_ar', 'used_factor_2_desc_en',
                'used_factor_3_title_ar', 'used_factor_3_title_en', 'used_factor_3_desc_ar', 'used_factor_3_desc_en',
                'used_factor_4_title_ar', 'used_factor_4_title_en', 'used_factor_4_desc_ar', 'used_factor_4_desc_en',
                
                'used_faq_title_ar', 'used_faq_title_en',
                'used_faq_1_q_ar', 'used_faq_1_q_en', 'used_faq_1_a_ar', 'used_faq_1_a_en',
                'used_faq_2_q_ar', 'used_faq_2_q_en', 'used_faq_2_a_ar', 'used_faq_2_a_en',
                'used_faq_3_q_ar', 'used_faq_3_q_en', 'used_faq_3_a_ar', 'used_faq_3_a_en',
                'used_faq_4_q_ar', 'used_faq_4_q_en', 'used_faq_4_a_ar', 'used_faq_4_a_en',
                'used_faq_5_q_ar', 'used_faq_5_q_en', 'used_faq_5_a_ar', 'used_faq_5_a_en',
                
                'cta_used_title_ar', 'cta_used_title_en',
                'cta_used_desc_ar', 'cta_used_desc_en',
                'cta_used_whatsapp_msg_ar', 'cta_used_whatsapp_msg_en',
                
                'seo_used_title_ar', 'seo_used_title_en',
                'seo_used_desc_ar', 'seo_used_desc_en'
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
                $curr_seo_og = get_setting('seo_used_og_image', '');
                $final_seo_og = $process_image_field('seo_used_og_image_file', 'seo_used_og_image_media_id', $curr_seo_og);
                $prepared_vals['seo_used_og_image'] = $final_seo_og;
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
            $success_message = $lang === 'ar' ? 'تم حفظ إعدادات صفحة الأرفف المستعملة بنجاح.' : 'Used Shelves settings saved successfully.';

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
            error_log("Database error in used save: " . $e->getMessage());
            $error_message = $lang === 'ar' ? 'حدث خطأ في قاعدة البيانات أثناء الحفظ.' : 'Database operation failed.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rollback_cleanup_files($newFilesTracked);
            error_log("Unexpected error in used save: " . $e->getMessage());
            $error_message = $lang === 'ar' ? 'حدث خطأ غير متوقع أثناء الحفظ.' : 'Unexpected operation failed.';
        }
    }
}

$page_title = $lang === 'ar' ? 'إدارة صفحة الأرفف المستعملة' : 'Manage Used Shelves';

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <?php 
    render_admin_page_header($page_title, [
        ['label' => $lang === 'ar' ? 'الأرفف المستعملة والخدمات' : 'Used Shelves', 'url' => '']
    ]);
    ?>

    <!-- Message Alerts -->
    <?php 
    render_admin_alert('danger', $error_message);
    render_admin_alert('success', $success_message);
    ?>

    <!-- Enterprise Tab Navigation -->
    <div style="display: flex; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid var(--admin-border-color); padding-bottom: 12px; margin-bottom: 24px;">
        <?php foreach ($tabs_order as $tab_key => $tab_info): ?>
            <button type="button" class="btn <?php echo ($active_tab === $tab_key) ? 'btn-primary' : 'btn-secondary'; ?> btn-sm settings-tab-btn" onclick="switchTab(event, '<?php echo $tab_key; ?>')" style="padding: 8px 16px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i data-lucide="<?php echo $tab_info['icon']; ?>" style="width: 15px; height: 15px;"></i>
                <span><?php echo htmlspecialchars($tab_info['label']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Main Editor Form -->
    <form action="used-shelves.php" method="POST" enctype="multipart/form-data" id="usedForm" data-track-unsaved="true">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="save_used" value="1">
        <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">

        <!-- TAB: VISIBILITY & ORDER -->
        <div class="settings-section-panel <?php echo ($active_tab === 'visibility') ? 'active' : ''; ?>" id="panel-visibility" style="display: <?php echo ($active_tab === 'visibility') ? 'block' : 'none'; ?>;">
            <div class="form-section-card">
                <h3>
                    <i data-lucide="sliders"></i>
                    <span><?php echo $lang === 'ar' ? 'ظهور وترتيب الأقسام' : 'Section Visibility & Sorting'; ?></span>
                </h3>
                
                <div class="products-desktop-table-card" style="margin-bottom: 0;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="text-align: start;"><?php echo $lang === 'ar' ? 'القسم' : 'Section'; ?></th>
                                <th style="text-align: center; width: 120px;"><?php echo $lang === 'ar' ? 'الحالة' : 'Status'; ?></th>
                                <th style="text-align: center; width: 140px;"><?php echo $lang === 'ar' ? 'الترتيب' : 'Order Weight'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sections_list as $key => $sec): ?>
                                <?php 
                                $show_val = get_setting("used_show_" . $key, '1');
                                $order_val = get_setting("used_order_" . $key, (string)$sec['default_order']);
                                ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <?php echo htmlspecialchars($lang === 'ar' ? $sec['label_ar'] : $sec['label_en']); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="used_show_<?php echo $key; ?>" value="1" <?php echo ($show_val === '1') ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
                                            <span style="font-size: 13px; font-weight: 600;"><?php echo $show_val === '1' ? ($lang === 'ar' ? 'ظاهر' : 'Visible') : ($lang === 'ar' ? 'مخفي' : 'Hidden'); ?></span>
                                        </label>
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" name="used_order_<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($order_val); ?>" min="0" max="999" style="width: 90px; margin: 0 auto; text-align: center; padding: 6px 10px; border: 1px solid var(--admin-border-color); border-radius: var(--radius-md);" required>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB: HERO -->
        <div class="settings-section-panel <?php echo ($active_tab === 'hero') ? 'active' : ''; ?>" id="panel-hero" style="display: <?php echo ($active_tab === 'hero') ? 'block' : 'none'; ?>;">
            <div class="form-section-card">
                <h3>
                    <i data-lucide="sparkles"></i>
                    <span><?php echo $lang === 'ar' ? 'البانر العلوي (Hero Banner)' : 'Hero Banner Section'; ?></span>
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'العنوان الرئيسي (عربي)' : 'Title (AR)'; ?></label>
                        <input type="text" name="used_hero_title_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(get_setting('used_hero_title_ar', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'العنوان الرئيسي (إنجليزي)' : 'Title (EN)'; ?></label>
                        <input type="text" name="used_hero_title_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(get_setting('used_hero_title_en', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                    
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'الوصف الفرعي (عربي)' : 'Subtitle (AR)'; ?></label>
                        <textarea name="used_hero_subtitle_ar" class="form-control" dir="rtl" rows="2" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;"><?php echo htmlspecialchars(get_setting('used_hero_subtitle_ar', '')); ?></textarea>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'الوصف الفرعي (إنجليزي)' : 'Subtitle (EN)'; ?></label>
                        <textarea name="used_hero_subtitle_en" class="form-control" dir="ltr" rows="2" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;"><?php echo htmlspecialchars(get_setting('used_hero_subtitle_en', '')); ?></textarea>
                    </div>

                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'نص زر واتساب (عربي)' : 'WhatsApp Button Label (AR)'; ?></label>
                        <input type="text" name="used_hero_cta_label_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(get_setting('used_hero_cta_label_ar', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'نص زر واتساب (إنجليزي)' : 'WhatsApp Button Label (EN)'; ?></label>
                        <input type="text" name="used_hero_cta_label_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(get_setting('used_hero_cta_label_en', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>

                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'نص الزر الثاني (عربي)' : 'Secondary Button Label (AR)'; ?></label>
                        <input type="text" name="used_hero_secondary_label_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(get_setting('used_hero_secondary_label_ar', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'نص الزر الثاني (إنجليزي)' : 'Secondary Button Label (EN)'; ?></label>
                        <input type="text" name="used_hero_secondary_label_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(get_setting('used_hero_secondary_label_en', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: BUY & PROCESS -->
        <div class="settings-section-panel <?php echo ($active_tab === 'buy_process') ? 'active' : ''; ?>" id="panel-buy_process" style="display: <?php echo ($active_tab === 'buy_process') ? 'block' : 'none'; ?>;">
            <div class="form-section-card">
                <h3>
                    <i data-lucide="warehouse"></i>
                    <span><?php echo $lang === 'ar' ? 'قسم ماذا نشتري وخطوات الشراء' : 'What We Buy & Purchase Steps'; ?></span>
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان "ماذا نشتري" (عربي)' : 'What We Buy Title (AR)'; ?></label>
                        <input type="text" name="used_buy_title_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(get_setting('used_buy_title_ar', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان "ماذا نشتري" (إنجليزي)' : 'What We Buy Title (EN)'; ?></label>
                        <input type="text" name="used_buy_title_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(get_setting('used_buy_title_en', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <?php for ($k = 1; $k <= 4; $k++): ?>
                        <div style="background: var(--admin-bg-subtle); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--admin-border-color);">
                            <strong style="font-size: 13px; color: var(--admin-primary); display: block; margin-bottom: 10px;"><?php echo ($lang === 'ar' ? 'عنصر الشراء #' : 'Item #') . $k; ?></strong>
                            <input type="text" name="used_buy_<?php echo $k; ?>_title_ar" placeholder="Title (AR)" value="<?php echo htmlspecialchars(get_setting("used_buy_{$k}_title_ar", '')); ?>" required style="width:100%; padding:7px 10px; font-size:12.5px; margin-bottom:8px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                            <input type="text" name="used_buy_<?php echo $k; ?>_title_en" placeholder="Title (EN)" value="<?php echo htmlspecialchars(get_setting("used_buy_{$k}_title_en", '')); ?>" required style="width:100%; padding:7px 10px; font-size:12.5px; margin-bottom:8px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                            <textarea name="used_buy_<?php echo $k; ?>_desc_ar" placeholder="Desc (AR)" rows="2" required style="width:100%; padding:7px 10px; font-size:12.5px; margin-bottom:8px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);"><?php echo htmlspecialchars(get_setting("used_buy_{$k}_desc_ar", '')); ?></textarea>
                            <textarea name="used_buy_<?php echo $k; ?>_desc_en" placeholder="Desc (EN)" rows="2" required style="width:100%; padding:7px 10px; font-size:12.5px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);"><?php echo htmlspecialchars(get_setting("used_buy_{$k}_desc_en", '')); ?></textarea>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- TAB: FACTORS & FAQ -->
        <div class="settings-section-panel <?php echo ($active_tab === 'factors_faq') ? 'active' : ''; ?>" id="panel-factors_faq" style="display: <?php echo ($active_tab === 'factors_faq') ? 'block' : 'none'; ?>;">
            <div class="form-section-card">
                <h3>
                    <i data-lucide="help-circle"></i>
                    <span><?php echo $lang === 'ar' ? 'العوامل والأسئلة الشائعة' : 'Factors & FAQ Section'; ?></span>
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الأسئلة الشائعة (عربي)' : 'FAQ Title (AR)'; ?></label>
                        <input type="text" name="used_faq_title_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(get_setting('used_faq_title_ar', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الأسئلة الشائعة (إنجليزي)' : 'FAQ Title (EN)'; ?></label>
                        <input type="text" name="used_faq_title_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(get_setting('used_faq_title_en', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php for ($q = 1; $q <= 5; $q++): ?>
                        <div style="background: var(--admin-bg-subtle); padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid var(--admin-border-color);">
                            <strong style="font-size: 13px; color: var(--admin-primary); display: block; margin-bottom: 8px;"><?php echo ($lang === 'ar' ? 'سؤال #' : 'Question #') . $q; ?></strong>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 8px;">
                                <input type="text" name="used_faq_<?php echo $q; ?>_q_ar" placeholder="Question (AR)" value="<?php echo htmlspecialchars(get_setting("used_faq_{$q}_q_ar", '')); ?>" required style="width:100%; padding:7px 10px; font-size:12.5px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                                <input type="text" name="used_faq_<?php echo $q; ?>_q_en" placeholder="Question (EN)" value="<?php echo htmlspecialchars(get_setting("used_faq_{$q}_q_en", '')); ?>" required style="width:100%; padding:7px 10px; font-size:12.5px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <textarea name="used_faq_<?php echo $q; ?>_a_ar" placeholder="Answer (AR)" rows="2" required style="width:100%; padding:7px 10px; font-size:12.5px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);"><?php echo htmlspecialchars(get_setting("used_faq_{$q}_a_ar", '')); ?></textarea>
                                <textarea name="used_faq_<?php echo $q; ?>_a_en" placeholder="Answer (EN)" rows="2" required style="width:100%; padding:7px 10px; font-size:12.5px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);"><?php echo htmlspecialchars(get_setting("used_faq_{$q}_a_en", '')); ?></textarea>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- TAB: CTA -->
        <div class="settings-section-panel <?php echo ($active_tab === 'cta') ? 'active' : ''; ?>" id="panel-cta" style="display: <?php echo ($active_tab === 'cta') ? 'block' : 'none'; ?>;">
            <div class="form-section-card">
                <h3>
                    <i data-lucide="phone-call"></i>
                    <span><?php echo $lang === 'ar' ? 'قسم دعوة الاتصال (CTA)' : 'Call to Action Section'; ?></span>
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الدعوة (عربي)' : 'CTA Title (AR)'; ?></label>
                        <input type="text" name="cta_used_title_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(get_setting('cta_used_title_ar', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الدعوة (إنجليزي)' : 'CTA Title (EN)'; ?></label>
                        <input type="text" name="cta_used_title_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(get_setting('cta_used_title_en', '')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: SEO -->
        <div class="settings-section-panel <?php echo ($active_tab === 'seo') ? 'active' : ''; ?>" id="panel-seo" style="display: <?php echo ($active_tab === 'seo') ? 'block' : 'none'; ?>;">
            <div class="form-section-card">
                <h3>
                    <i data-lucide="search"></i>
                    <span><?php echo $lang === 'ar' ? 'إعدادات محركات البحث (SEO)' : 'SEO Metadata Settings'; ?></span>
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الصفحة (SEO Title AR)' : 'SEO Title (AR)'; ?></label>
                        <input type="text" name="seo_used_title_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(get_setting('seo_used_title_ar', '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الصفحة (SEO Title EN)' : 'SEO Title (EN)'; ?></label>
                        <input type="text" name="seo_used_title_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(get_setting('seo_used_title_en', '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    </div>
                </div>

                <div style="background: var(--admin-bg-subtle); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--admin-border-color);">
                    <label style="font-weight: 600; font-size: 13px; color: var(--admin-text-main); display: block; margin-bottom: 8px;"><?php echo $lang === 'ar' ? 'صورة OpenGraph للمشاركة' : 'Custom Social OpenGraph Image'; ?></label>
                    <?php 
                    $curr_seo_og = get_setting('seo_used_og_image', '');
                    if (!empty($curr_seo_og)): ?>
                        <div style="margin-bottom: 12px;">
                            <img src="../<?php echo htmlspecialchars($curr_seo_og); ?>" style="max-height: 100px; border-radius: var(--radius-sm); border: 1px solid var(--admin-border-color); object-fit: contain;">
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
                        <div>
                            <span style="font-size: 12px; color: var(--admin-text-muted); display: block; margin-bottom: 4px;"><?php echo __('homepage_direct_upload'); ?></span>
                            <input type="file" name="seo_used_og_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding: 6px; width: 100%; border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); font-size: 12.5px;">
                        </div>
                        <div>
                            <span style="font-size: 12px; color: var(--admin-text-muted); display: block; margin-bottom: 4px;"><?php echo __('homepage_select_media'); ?></span>
                            <select name="seo_used_og_image_media_id" class="filter-select" style="width: 100%; padding: 6px 10px; height: 38px;">
                                <option value=""><?php echo $lang === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                                <?php foreach ($media_list as $m): 
                                    $sel = ($curr_seo_og === $m['file_path']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $m['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($m['folder'] . ' / ' . $m['filename']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enterprise Sticky Save Action Bar -->
        <?php render_admin_sticky_save_bar($lang === 'ar' ? 'جاهز لحفظ إعدادات الأرفف المستعملة' : 'Ready to save Used Shelves settings', 'index.php', $lang === 'ar' ? 'حفظ البيانات' : 'Save Settings', true); ?>
    </form>
</main>

<script>
function switchTab(evt, tabId) {
    evt.preventDefault();
    document.querySelectorAll('.settings-tab-btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-secondary');
    });
    document.querySelectorAll('.settings-section-panel').forEach(p => {
        p.classList.remove('active');
        p.style.display = 'none';
    });
    
    const activePanel = document.getElementById('panel-' + tabId);
    if (activePanel) {
        activePanel.classList.add('active');
        activePanel.style.display = 'block';
    }
    evt.currentTarget.classList.remove('btn-secondary');
    evt.currentTarget.classList.add('btn-primary');
    
    document.getElementById('activeTabInput').value = tabId;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
