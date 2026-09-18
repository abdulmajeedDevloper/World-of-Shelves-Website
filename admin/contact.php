<?php
// admin/contact.php
// Dedicated editor dashboard for Contact Page specific CMS (SEO, headings, visibilities, WhatsApp templates).

require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';
require_once dirname(__DIR__) . '/includes/settings-helper.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';

$success_message = '';
$error_message = '';
$field_errors = [];

// Get all registry settings
$registry = get_settings_registry();

// Fetch all available media library items for dropdown selectors
$media_items = [];
try {
    $media_stmt = $pdo->query("SELECT id, file_path, filename FROM media_library ORDER BY id DESC");
    $media_items = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Contact CMS media query failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = get_current_lang() === 'ar' ? 'رمز الحماية غير صالح.' : 'Invalid security token.';
    } else {
        $newFilesTracked = [];
        $validation_failed = false;
        $db_updates = [];

        // Helper functions for settings saving
        $update_setting = function($key, $value) use ($pdo, &$db_updates) {
            $db_updates[$key] = $value;
        };

    // 1. Process Custom OG Image direct upload / media selection
    $seo_og_key = 'seo_contact_og_image';
    $current_og = get_setting($seo_og_key, '');
    $final_og = $current_og;

    // A. Direct upload takes priority
    if (isset($_FILES['seo_contact_og_image_file']) && $_FILES['seo_contact_og_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $media_id = upload_to_media_library($pdo, $_FILES['seo_contact_og_image_file'], 'seo');
            if ($media_id) {
                $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                $stmt->execute([':id' => $media_id]);
                $final_og = $stmt->fetchColumn();
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $validation_failed = true;
        }
    }
    // B. Dropdown selector fallback
    elseif (!empty($_POST['seo_contact_og_image_media_id'])) {
        $media_id = intval($_POST['seo_contact_og_image_media_id']);
        $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
        $stmt->execute([':id' => $media_id]);
        $db_path = $stmt->fetchColumn();
        if ($db_path) {
            $final_og = $db_path;
        }
    }

    if (!$validation_failed) {
        $update_setting($seo_og_key, $final_og);

        // 2. Process all other Contact page settings
        foreach ($registry as $key => $def) {
            if ($def['group'] !== 'contact_page' || $key === $seo_og_key) {
                continue;
            }

            if ($def['type'] === 'toggle') {
                $val = isset($_POST[$key]) ? '1' : '0';
                $update_setting($key, $val);
            } elseif (isset($_POST[$key])) {
                $val = trim($_POST[$key]);

                // Plain text WhatsApp message template checks
                if (strpos($key, 'contact_whatsapp_template_') === 0) {
                    if (mb_strlen($val) > 500) {
                        $error_message = (get_current_lang() === 'ar')
                            ? "نموذج رسالة واتساب طويل جداً. الحد الأقصى هو 500 حرف."
                            : "WhatsApp message template is too long. Maximum is 500 characters.";
                        $validation_failed = true;
                        break;
                    }
                    $val = strip_tags($val);
                    $val = preg_replace('/[\x00-\x1F\x7F]/', '', $val);
                }

                $update_setting($key, $val);
            }
        }
    }

    // 3. Database Transaction Write
    if (!$validation_failed && count($db_updates) > 0) {
        try {
            $pdo->beginTransaction();
            
            $upsert_query = ($db_driver === 'mysql')
                ? "INSERT INTO settings (key_name, val) VALUES (:key_name, :val) ON DUPLICATE KEY UPDATE val = :val2"
                : "INSERT INTO settings (key_name, val) VALUES (:key_name, :val) ON CONFLICT(key_name) DO UPDATE SET val = :val2";
                
            $stmt = $pdo->prepare($upsert_query);

            foreach ($db_updates as $k => $v) {
                $stmt->execute([
                    ':key_name' => $k,
                    ':val'      => $v,
                    ':val2'     => $v
                ]);
            }

            $pdo->commit();
            $success_message = (get_current_lang() === 'ar') ? 'تم حفظ التغييرات بنجاح!' : 'Changes saved successfully!';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Contact Page CMS update failed: " . $e->getMessage());
            $error_message = (get_current_lang() === 'ar') ? 'حدث خطأ أثناء حفظ الإعدادات.' : 'An error occurred while saving settings.';
        }
    }
}
}

$active_tab = isset($_POST['active_tab']) ? trim($_POST['active_tab']) : 'presentation';
if (!in_array($active_tab, ['presentation', 'visibility', 'seo', 'shared'], true)) {
    $active_tab = 'presentation';
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

    <main class="admin-content">
        <header class="admin-header">
            <div>
                <h1 style="font-size: 28px;"><?php echo get_current_lang() === 'ar' ? 'إدارة محتوى صفحة الاتصال' : 'Contact Page Management'; ?></h1>
                <p><?php echo get_current_lang() === 'ar' ? 'تخصيص العناوين والترجمات، إعدادات العرض لنموذج الخريطة، وأزرار التواصل الخاصة بصفحة التواصل.' : 'Customize headings, section visibilities, map rendering, and SEO metadata specifically for the Contact page.'; ?></p>
            </div>
            
            <a href="?lang=<?php echo get_current_lang() === 'ar' ? 'en' : 'ar'; ?>" class="lang-toggle">
                <i data-lucide="globe"></i>
                <span><?php echo get_current_lang() === 'ar' ? 'English' : 'العربية'; ?></span>
            </a>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Premium Tabbed Dashboard Layout -->
        <div class="settings-nav-tabs" style="margin-bottom: 25px; display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; flex-wrap: wrap;">
            <button class="settings-tab-btn <?php echo $active_tab === 'presentation' ? 'active' : ''; ?>" onclick="switchTab('presentation')">
                <i data-lucide="layout"></i> <span><?php echo get_current_lang() === 'ar' ? 'عناوين الصفحة والبطاقات' : 'Headings & Labels'; ?></span>
            </button>
            <button class="settings-tab-btn <?php echo $active_tab === 'visibility' ? 'active' : ''; ?>" onclick="switchTab('visibility')">
                <i data-lucide="eye"></i> <span><?php echo get_current_lang() === 'ar' ? 'خيارات العرض والظهور' : 'Visibilities'; ?></span>
            </button>
            <button class="settings-tab-btn <?php echo $active_tab === 'seo' ? 'active' : ''; ?>" onclick="switchTab('seo')">
                <i data-lucide="search"></i> <span><?php echo get_current_lang() === 'ar' ? 'بيانات SEO المخصصة' : 'Contact SEO'; ?></span>
            </button>
            <button class="settings-tab-btn <?php echo $active_tab === 'shared' ? 'active' : ''; ?>" onclick="switchTab('shared')">
                <i data-lucide="phone"></i> <span><?php echo get_current_lang() === 'ar' ? 'تفاصيل الاتصال العامة (قراءة فقط)' : 'Shared Contact Info (Read-Only)'; ?></span>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="contactUiForm" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">

            <!-- TAB 1: PRESENTATION PANEL -->
            <div id="panel-presentation" class="settings-section-panel <?php echo $active_tab === 'presentation' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="layout" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'العناوين ونصوص التوجيه' : 'Headings & Action Labels'; ?></h2>
                    </div>
                    <div class="card-body">
                        <!-- Hero Section Content -->
                        <h3><?php echo get_current_lang() === 'ar' ? 'قسم الهيرو التعريفي (Hero Banner)' : 'Hero Banner Section'; ?></h3>
                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'عنوان الصفحة الرئيسي (عربي)' : 'Hero Title (AR)'; ?></label>
                                <input type="text" name="contact_hero_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_hero_title_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'عنوان الصفحة الرئيسي (إنجليزي)' : 'Hero Title (EN)'; ?></label>
                                <input type="text" name="contact_hero_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_hero_title_en')); ?>">
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 25px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'الوصف الفرعي للهيرو (عربي)' : 'Hero Description (AR)'; ?></label>
                                <textarea name="contact_hero_desc_ar" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('contact_hero_desc_ar')); ?></textarea>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'الوصف الفرعي للهيرو (إنجليزي)' : 'Hero Description (EN)'; ?></label>
                                <textarea name="contact_hero_desc_en" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('contact_hero_desc_en')); ?></textarea>
                            </div>
                        </div>

                        <!-- Card Titles -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 20px; margin-bottom: 25px;">
                            <h3><?php echo get_current_lang() === 'ar' ? 'عناوين بطاقات الصفحة' : 'Form & Info Card Titles'; ?></h3>
                            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان نموذج المراسلة (عربي)' : 'Form Heading (AR)'; ?></label>
                                    <input type="text" name="contact_form_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_form_title_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان نموذج المراسلة (إنجليزي)' : 'Form Heading (EN)'; ?></label>
                                    <input type="text" name="contact_form_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_form_title_en')); ?>">
                                </div>
                            </div>

                            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'نص زر إرسال النموذج (عربي)' : 'Form Submit Button (AR)'; ?></label>
                                    <input type="text" name="contact_form_submit_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_form_submit_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'نص زر إرسال النموذج (إنجليزي)' : 'Form Submit Button (EN)'; ?></label>
                                    <input type="text" name="contact_form_submit_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_form_submit_en')); ?>">
                                </div>
                            </div>

                            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان بطاقة معلومات التواصل (عربي)' : 'Info Card Title (AR)'; ?></label>
                                    <input type="text" name="contact_info_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_info_title_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان بطاقة معلومات التواصل (إنجليزي)' : 'Info Card Title (EN)'; ?></label>
                                    <input type="text" name="contact_info_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_info_title_en')); ?>">
                                </div>
                            </div>

                            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان بطاقة الخريطة (عربي)' : 'Map Card Title (AR)'; ?></label>
                                    <input type="text" name="contact_location_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_location_title_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان بطاقة الخريطة (إنجليزي)' : 'Map Card Title (EN)'; ?></label>
                                    <input type="text" name="contact_location_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_location_title_en')); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- WhatsApp Action Controls -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                            <h3><?php echo get_current_lang() === 'ar' ? 'قالب أزرار الواتساب بالبطاقة' : 'WhatsApp Button text & templates'; ?></h3>
                            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'نص زر واتساب بالبطاقة (عربي)' : 'WhatsApp Button text (AR)'; ?></label>
                                    <input type="text" name="contact_whatsapp_btn_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_whatsapp_btn_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'نص زر واتساب بالبطاقة (إنجليزي)' : 'WhatsApp Button text (EN)'; ?></label>
                                    <input type="text" name="contact_whatsapp_btn_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_whatsapp_btn_en')); ?>">
                                </div>
                            </div>

                            <div class="form-row" style="display: flex; gap: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'قالب رسالة الواتساب الجاهزة (عربي - نص عادي فقط)' : 'WhatsApp Template Message (AR - Plain text only)'; ?></label>
                                    <input type="text" name="contact_whatsapp_template_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_whatsapp_template_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'قالب رسالة الواتساب الجاهزة (إنجليزي - نص عادي فقط)' : 'WhatsApp Template Message (EN - Plain text only)'; ?></label>
                                    <input type="text" name="contact_whatsapp_template_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_whatsapp_template_en')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: VISIBILITY PANEL -->
            <div id="panel-visibility" class="settings-section-panel <?php echo $active_tab === 'visibility' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="eye" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'ظهور وإخفاء أقسام صفحة التواصل' : 'Section Visibilities'; ?></h2>
                    </div>
                    <div class="card-body" style="display: flex; flex-direction: column; gap: 15px;">
                        <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="contact_show_hero" value="1" <?php echo get_setting('contact_show_hero', '1') === '1' ? 'checked' : ''; ?>>
                            <span><?php echo get_current_lang() === 'ar' ? 'عرض قسم الهيرو التعريفي العلوي' : 'Show Hero Banner Section'; ?></span>
                        </label>
                        <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="contact_show_form" value="1" <?php echo get_setting('contact_show_form', '1') === '1' ? 'checked' : ''; ?>>
                            <span><?php echo get_current_lang() === 'ar' ? 'عرض نموذج المراسلات (Contact Form)' : 'Show Contact Form'; ?></span>
                        </label>
                        <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="contact_show_info" value="1" <?php echo get_setting('contact_show_info', '1') === '1' ? 'checked' : ''; ?>>
                            <span><?php echo get_current_lang() === 'ar' ? 'عرض بطاقة معلومات الاتصال بالهاتف والبريد' : 'Show Contact Info details'; ?></span>
                        </label>
                        <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="contact_show_map" value="1" <?php echo get_setting('contact_show_map', '1') === '1' ? 'checked' : ''; ?>>
                            <span><?php echo get_current_lang() === 'ar' ? 'عرض خريطة جوجل المضمّنة بالموقع' : 'Show Google Map'; ?></span>
                        </label>
                        <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="contact_show_whatsapp_button" value="1" <?php echo get_setting('contact_show_whatsapp_button', '1') === '1' ? 'checked' : ''; ?>>
                            <span><?php echo get_current_lang() === 'ar' ? 'عرض زر الواتساب تحت بطاقة معلومات الاتصال' : 'Show WhatsApp Chat Button'; ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- TAB 3: SEO PANEL -->
            <div id="panel-seo" class="settings-section-panel <?php echo $active_tab === 'seo' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="search" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'بيانات البحث الوصفية لصفحة التواصل' : 'Contact Page Custom SEO'; ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'عنوان الصفحة المخصص بمحركات البحث (عربي)' : 'SEO Title (AR)'; ?></label>
                                <input type="text" name="seo_contact_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('seo_contact_title_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'عنوان الصفحة المخصص بمحركات البحث (إنجليزي)' : 'SEO Title (EN)'; ?></label>
                                <input type="text" name="seo_contact_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('seo_contact_title_en')); ?>">
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'الوصف الوصفي المخصص بمحركات البحث (عربي)' : 'SEO Description (AR)'; ?></label>
                                <textarea name="seo_contact_desc_ar" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('seo_contact_desc_ar')); ?></textarea>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'الوصف الوصفي المخصص بمحركات البحث (إنجليزي)' : 'SEO Description (EN)'; ?></label>
                                <textarea name="seo_contact_desc_en" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('seo_contact_desc_en')); ?></textarea>
                            </div>
                        </div>

                        <!-- SEO Custom OG Image -->
                        <div class="form-group">
                            <label><?php echo get_current_lang() === 'ar' ? 'صورة مشاركة روابط التواصل المخصصة (OG Image)' : 'Custom Open Graph Share Image'; ?></label>
                            <?php $contact_og = get_setting('seo_contact_og_image'); if(!empty($contact_og)): ?>
                                <div style="margin-bottom: 10px;"><img src="../<?php echo htmlspecialchars($contact_og); ?>" style="max-height: 85px; border-radius: 4px;"></div>
                            <?php endif; ?>
                            <input type="file" name="seo_contact_og_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="margin-bottom: 8px;">
                            <select name="seo_contact_og_image_media_id" class="form-control">
                                <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                                <?php foreach ($media_items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" <?php echo $contact_og === $item['file_path'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['filename']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: SHARED CONTACT INFO (READ ONLY SUMMARY) -->
            <div id="panel-shared" class="settings-section-panel <?php echo $active_tab === 'shared' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="phone" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'تفاصيل الاتصال الأساسية المشتركة بالموقع' : 'Shared Primary Contact Information'; ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info" style="margin-bottom: 20px; border-right: 4px solid var(--accent-primary);">
                            <i data-lucide="info" style="vertical-align: middle; margin-inline-end: 6px;"></i>
                            <span>
                                <?php echo get_current_lang() === 'ar' 
                                    ? 'تنبيه: لتجنب تكرار البيانات وتضاربها، يتم تعديل معلومات الاتصال الأساسية (الهاتف، البريد الإلكتروني، العنوان، خريطة الموقع، والواتساب الرئيسي) بشكل موحد عبر الرابط التالي:' 
                                    : 'Notice: To prevent duplicate configuration and data inconsistency, primary contact details (Phone, Email, Addresses, Google Maps URL, and WhatsApp) must be managed centrally at:'; ?>
                            </span>
                            <br><br>
                            <a href="global-ui.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 0.9rem;">
                                <i data-lucide="globe"></i>
                                <span><?php echo get_current_lang() === 'ar' ? 'إدارة عناصر الموقع العامة 🠆' : 'Manage Global Site Elements 🠆'; ?></span>
                            </a>
                        </div>

                        <!-- Read-only lists -->
                        <div style="display: flex; flex-direction: column; gap: 15px; background: var(--bg-body); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div><strong><?php echo get_current_lang() === 'ar' ? 'الهاتف الرئيسي:' : 'Primary Phone:'; ?></strong> <?php echo htmlspecialchars(get_setting('contact_phone')); ?></div>
                            <div><strong><?php echo get_current_lang() === 'ar' ? 'رقم الواتساب الرئيسي:' : 'Primary WhatsApp:'; ?></strong> <?php echo htmlspecialchars(get_setting('whatsapp_number')); ?></div>
                            <div><strong><?php echo get_current_lang() === 'ar' ? 'البريد الإلكتروني للاتصال:' : 'Primary Email:'; ?></strong> <?php echo htmlspecialchars(get_setting('contact_email')); ?></div>
                            <div><strong><?php echo get_current_lang() === 'ar' ? 'العنوان بالعربية:' : 'Address (AR):'; ?></strong> <?php echo htmlspecialchars(get_setting('contact_address_ar')); ?></div>
                            <div><strong><?php echo get_current_lang() === 'ar' ? 'العنوان بالإنجليزية:' : 'Address (EN):'; ?></strong> <?php echo htmlspecialchars(get_setting('contact_address_en')); ?></div>
                            <div><strong><?php echo get_current_lang() === 'ar' ? 'ساعات العمل:' : 'Working Hours:'; ?></strong> <?php echo htmlspecialchars(get_setting('working_hours_' . $lang)); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Submit Buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                    <i data-lucide="save" style="vertical-align: middle; margin-inline-end: 6px;"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'حفظ إعدادات الصفحة' : 'Save Page Settings'; ?></span>
                </button>
            </div>
        </form>

<script>
function switchTab(tabId) {
    // Hide all panels
    const panels = document.querySelectorAll('.settings-section-panel');
    panels.forEach(p => p.classList.remove('active'));
    
    // Deactivate all tab buttons
    const buttons = document.querySelectorAll('.settings-tab-btn');
    buttons.forEach(b => b.classList.remove('active'));
    
    // Activate target
    document.getElementById('panel-' + tabId).classList.add('active');
    
    // Find matching button and activate
    buttons.forEach(b => {
        if (b.getAttribute('onclick').includes(tabId)) {
            b.classList.add('active');
        }
    });

    // Save in hidden input
    document.getElementById('activeTabInput').value = tabId;
}
</script>

<?php
require_once dirname(__DIR__) . '/includes/admin-footer.php';
?>
