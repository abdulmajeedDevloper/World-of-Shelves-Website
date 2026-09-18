<?php
// admin/services-settings.php
// Dedicated editor dashboard for Services page "Why Choose" feature cards.

require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';
require_once dirname(__DIR__) . '/includes/settings-helper.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';

$success_message = '';
$error_message = '';
$field_errors = [];

// Fetch Media Library images for selectors (latest 200)
$media_list = [];
try {
    $media_stmt = $pdo->query("SELECT id, filename, file_path, folder FROM media_library ORDER BY id DESC LIMIT 200");
    $media_list = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed fetching media list in services settings: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = get_current_lang() === 'ar' ? 'رمز الحماية (CSRF Token) غير صالح.' : 'Invalid security token (CSRF).';
    } else {
        $pdo->beginTransaction();
        $validation_failed = false;
        $prepared_vals = [];
        
        try {
            // Helper to process uploaded file or media selection
            $process_image_field = function($file_field_name, $select_field_name, $remove_field_name, $current_val) use ($pdo, &$error_message, &$validation_failed) {
                // 1. Check if remove option is checked
                if (isset($_POST[$remove_field_name]) && $_POST[$remove_field_name] === '1') {
                    return '';
                }
                
                // 2. Direct upload takes priority
                if (isset($_FILES[$file_field_name]) && $_FILES[$file_field_name]['error'] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        // Register in Media Library (folder category 'services')
                        $media_id = upload_to_media_library($pdo, $_FILES[$file_field_name], 'services');
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
                
                // 3. Otherwise, selected media ID is used
                if (!empty($_POST[$select_field_name])) {
                    $media_id = intval($_POST[$select_field_name]);
                    $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                    $stmt->execute([':id' => $media_id]);
                    $db_path = $stmt->fetchColumn();
                    if ($db_path) {
                        return $db_path;
                    }
                }
                
                // 4. Keep current value
                return $current_val;
            };

            // Explicit setting keys for search indexing: services_why_1_image, services_why_2_image, services_why_3_image, services_why_4_image, services_why_5_image, services_why_6_image
            // Loop through all 6 cards and parse fields
            for ($i = 1; $i <= 6; $i++) {
                $title_ar_key = "services_why_{$i}_title_ar";
                $title_en_key = "services_why_{$i}_title_en";
                $desc_ar_key = "services_why_{$i}_desc_ar";
                $desc_en_key = "services_why_{$i}_desc_en";
                $icon_key = "services_why_{$i}_icon";
                $image_key = "services_why_{$i}_image";

                $title_ar_val = isset($_POST[$title_ar_key]) ? trim($_POST[$title_ar_key]) : '';
                $title_en_val = isset($_POST[$title_en_key]) ? trim($_POST[$title_en_key]) : '';
                $desc_ar_val = isset($_POST[$desc_ar_key]) ? trim($_POST[$desc_ar_key]) : '';
                $desc_en_val = isset($_POST[$desc_en_key]) ? trim($_POST[$desc_en_key]) : '';
                $icon_val = isset($_POST[$icon_key]) ? trim($_POST[$icon_key]) : '';

                // Simple validations
                if (empty($title_ar_val)) {
                    $field_errors[$title_ar_key] = get_current_lang() === 'ar' ? 'العنوان بالعربية مطلوب.' : 'Title in Arabic is required.';
                    $validation_failed = true;
                }
                if (empty($title_en_val)) {
                    $field_errors[$title_en_key] = get_current_lang() === 'ar' ? 'العنوان بالإنجليزية مطلوب.' : 'Title in English is required.';
                    $validation_failed = true;
                }
                if (empty($desc_ar_val)) {
                    $field_errors[$desc_ar_key] = get_current_lang() === 'ar' ? 'الوصف بالعربية مطلوب.' : 'Description in Arabic is required.';
                    $validation_failed = true;
                }
                if (empty($desc_en_val)) {
                    $field_errors[$desc_en_key] = get_current_lang() === 'ar' ? 'الوصف بالإنجليزية مطلوب.' : 'Description in English is required.';
                    $validation_failed = true;
                }
                if (empty($icon_val)) {
                    $field_errors[$icon_key] = get_current_lang() === 'ar' ? 'أيقونة Lucide مطلوبة.' : 'Lucide icon is required.';
                    $validation_failed = true;
                }

                $curr_image = get_setting($image_key);
                $file_field_name = "{$image_key}_file";
                $select_field_name = "{$image_key}_media_id";
                $remove_field_name = "remove_{$image_key}";

                $image_val = $process_image_field($file_field_name, $select_field_name, $remove_field_name, $curr_image);

                if (!$validation_failed) {
                    $prepared_vals[$title_ar_key] = $title_ar_val;
                    $prepared_vals[$title_en_key] = $title_en_val;
                    $prepared_vals[$desc_ar_key] = $desc_ar_val;
                    $prepared_vals[$desc_en_key] = $desc_en_val;
                    $prepared_vals[$icon_key] = $icon_val;
                    $prepared_vals[$image_key] = $image_val;
                }
            }

            if (!$validation_failed && count($prepared_vals) > 0) {
                // Save to database settings table
                $update_stmt = $pdo->prepare("UPDATE settings SET val = :val WHERE key_name = :key_name");
                foreach ($prepared_vals as $k => $v) {
                    $update_stmt->execute([':val' => $v, ':key_name' => $k]);
                }
                
                $pdo->commit();
                $success_message = get_current_lang() === 'ar' ? 'تم حفظ التعديلات بنجاح.' : 'Settings saved successfully.';
                
                // Reload settings global
                $settings_rows = $pdo->query("SELECT * FROM settings")->fetchAll();
                foreach ($settings_rows as $row) {
                    $GLOBALS['settings'][$row['key_name']] = $row['val'];
                }
            } else {
                $pdo->rollBack();
                if (empty($error_message)) {
                    $error_message = get_current_lang() === 'ar' ? 'يرجى تصحيح الأخطاء الواردة أدناه.' : 'Please correct the validation errors below.';
                }
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed saving services settings: " . $e->getMessage());
            $error_message = get_current_lang() === 'ar' ? 'فشل حفظ الإعدادات في قاعدة البيانات.' : 'Failed saving settings to database.';
        }
    }
}

$page_title = get_current_lang() === 'ar' ? 'إعدادات قسم مميزات الخدمات' : 'Services Feature Settings';
$lang = get_current_lang();

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <?php 
    render_admin_page_header($page_title, [
        ['label' => $lang === 'ar' ? 'خدمات الشركة' : 'Services', 'url' => 'services.php'],
        ['label' => $lang === 'ar' ? 'إعدادات المميزات' : 'Feature Settings', 'url' => '']
    ]);
    ?>

    <!-- Message Alerts -->
    <?php 
    render_admin_alert('danger', $error_message);
    render_admin_alert('success', $success_message);
    ?>

    <!-- Services Settings Form -->
    <form method="POST" action="services-settings.php" enctype="multipart/form-data" data-track-unsaved="true">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="save_settings" value="1">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <?php for ($i = 1; $i <= 6; $i++): 
                $title_ar_key = "services_why_{$i}_title_ar";
                $title_en_key = "services_why_{$i}_title_en";
                $desc_ar_key = "services_why_{$i}_desc_ar";
                $desc_en_key = "services_why_{$i}_desc_en";
                $icon_key = "services_why_{$i}_icon";
                $image_key = "services_why_{$i}_image";

                $title_ar_val = isset($_POST[$title_ar_key]) ? $_POST[$title_ar_key] : get_setting($title_ar_key);
                $title_en_val = isset($_POST[$title_en_key]) ? $_POST[$title_en_key] : get_setting($title_en_key);
                $desc_ar_val = isset($_POST[$desc_ar_key]) ? $_POST[$desc_ar_key] : get_setting($desc_ar_key);
                $desc_en_val = isset($_POST[$desc_en_key]) ? $_POST[$desc_en_key] : get_setting($desc_en_key);
                $icon_val = isset($_POST[$icon_key]) ? $_POST[$icon_key] : get_setting($icon_key);
                $image_val = get_setting($image_key);
            ?>
                <div class="form-section-card" style="margin-bottom: 0;">
                    <h3>
                        <i data-lucide="<?php echo !empty($icon_val) ? htmlspecialchars($icon_val) : 'star'; ?>"></i>
                        <span><?php echo ($lang === 'ar' ? 'كرت المميزات #' : 'Feature Card #') . $i; ?></span>
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label for="<?php echo $title_ar_key; ?>" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo $lang === 'ar' ? 'العنوان (عربي)' : 'Title (AR)'; ?></label>
                                <input type="text" name="<?php echo $title_ar_key; ?>" id="<?php echo $title_ar_key; ?>" class="form-control" dir="rtl" value="<?php echo htmlspecialchars($title_ar_val); ?>" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                            </div>
                            <div>
                                <label for="<?php echo $title_en_key; ?>" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo $lang === 'ar' ? 'العنوان (إنجليزي)' : 'Title (EN)'; ?></label>
                                <input type="text" name="<?php echo $title_en_key; ?>" id="<?php echo $title_en_key; ?>" class="form-control" dir="ltr" value="<?php echo htmlspecialchars($title_en_val); ?>" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                            </div>
                        </div>

                        <div>
                            <label for="<?php echo $desc_ar_key; ?>" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo $lang === 'ar' ? 'الوصف (عربي)' : 'Description (AR)'; ?></label>
                            <textarea name="<?php echo $desc_ar_key; ?>" id="<?php echo $desc_ar_key; ?>" class="form-control" dir="rtl" rows="2" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);"><?php echo htmlspecialchars($desc_ar_val); ?></textarea>
                        </div>

                        <div>
                            <label for="<?php echo $desc_en_key; ?>" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo $lang === 'ar' ? 'الوصف (إنجليزي)' : 'Description (EN)'; ?></label>
                            <textarea name="<?php echo $desc_en_key; ?>" id="<?php echo $desc_en_key; ?>" class="form-control" dir="ltr" rows="2" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);"><?php echo htmlspecialchars($desc_en_val); ?></textarea>
                        </div>

                        <div>
                            <label for="<?php echo $icon_key; ?>" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo $lang === 'ar' ? 'أيقونة Lucide' : 'Lucide Icon'; ?></label>
                            <input type="text" name="<?php echo $icon_key; ?>" id="<?php echo $icon_key; ?>" class="form-control" value="<?php echo htmlspecialchars($icon_val); ?>" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                        </div>

                        <!-- Image Upload / Media Selector -->
                        <div style="border-top: 1px solid var(--admin-border-color); padding-top: 12px; margin-top: 4px;">
                            <label class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'صورة الكرت (اختياري)' : 'Card Image (Optional)'; ?></label>
                            
                            <?php if (!empty($image_val)): ?>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; background: var(--admin-bg-light); padding: 8px; border-radius: var(--radius-sm); border: 1px solid var(--admin-border-color);">
                                    <img src="<?php echo htmlspecialchars(get_site_url() . '/' . ltrim($image_val, '/')); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm);">
                                    <div style="flex-grow: 1;">
                                        <span style="font-size: 11.5px; word-break: break-all; display: block; color: var(--admin-text-muted);"><?php echo htmlspecialchars(basename($image_val)); ?></span>
                                        <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--color-danger); cursor: pointer; margin-top: 4px;">
                                            <input type="checkbox" name="remove_<?php echo $image_key; ?>" value="1">
                                            <span><?php echo $lang === 'ar' ? 'حذف الصورة الحالية' : 'Remove current image'; ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                                <div>
                                    <span style="font-size: 11.5px; color: var(--admin-text-muted); display: block; margin-bottom: 4px;"><?php echo $lang === 'ar' ? 'رفع ملف صورة جديد:' : 'Upload new image file:'; ?></span>
                                    <input type="file" name="<?php echo $image_key; ?>_file" accept="image/*" style="font-size: 12px; width: 100%;">
                                </div>
                                
                                <div style="border-top: 1px dashed var(--admin-border-color); padding-top: 8px; margin-top: 4px;">
                                    <span style="font-size: 11.5px; color: var(--admin-text-muted); display: block; margin-bottom: 4px;"><?php echo $lang === 'ar' ? 'أو اختر من مكتبة الوسائط:' : 'Or choose from Media Library:'; ?></span>
                                    <select name="<?php echo $image_key; ?>_media_id" style="width:100%; padding:6px; font-size:12.5px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                                        <option value=""><?php echo $lang === 'ar' ? '-- اختر صورة --' : '-- Select Image --'; ?></option>
                                        <?php foreach ($media_list as $media): ?>
                                            <option value="<?php echo $media['id']; ?>"><?php echo htmlspecialchars($media['filename']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <span style="font-size: 11px; color: var(--admin-text-muted); line-height: 1.4; display: block; margin-top: 6px;">
                                <?php echo $lang === 'ar' ? 'إرشاد: عند تحديد صورة، سيتم عرضها بدلاً من الأيقونة.' : 'Tip: If an image is selected, it will be displayed instead of the icon.'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Enterprise Sticky Save Action Bar -->
        <?php render_admin_sticky_save_bar($lang === 'ar' ? 'جاهز لحفظ مميزات الخدمات' : 'Ready to save services features', 'services.php', $lang === 'ar' ? 'حفظ إعدادات المميزات' : 'Save Features', true); ?>
    </form>
</main>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
