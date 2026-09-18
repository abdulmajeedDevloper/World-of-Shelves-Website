<?php
// admin/global-ui.php
// Dedicated editor dashboard for site Global UI elements (Branding, Navigation, Footer, Contact, Socials, FAB).

require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';
require_once dirname(__DIR__) . '/includes/settings-helper.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';

$is_cs = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'customer_service');
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
    error_log("Global UI media query failed: " . $e->getMessage());
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

    // 1. Process files and media selections separately (Full Admin Only)
    if (!$is_cs) {
        $image_fields = [
            'site_logo'        => ['file_name' => 'site_logo_file',        'select_name' => 'site_logo_media_id',        'folder' => 'branding'],
            'site_logo_dark'   => ['file_name' => 'site_logo_dark_file',   'select_name' => 'site_logo_dark_media_id',   'folder' => 'branding'],
            'default_og_image' => ['file_name' => 'default_og_image_file', 'select_name' => 'default_og_image_media_id', 'folder' => 'seo'],
            'footer_logo'      => ['file_name' => 'footer_logo_file',      'select_name' => 'footer_logo_media_id',      'folder' => 'branding']
        ];

        foreach ($image_fields as $key => $info) {
            $current_val = get_setting($key, '');
            $final_val = $current_val;

            // A. Direct upload takes priority
            if (isset($_FILES[$info['file_name']]) && $_FILES[$info['file_name']]['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $media_id = upload_to_media_library($pdo, $_FILES[$info['file_name']], $info['folder']);
                    if ($media_id) {
                        $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                        $stmt->execute([':id' => $media_id]);
                        $final_val = $stmt->fetchColumn();
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                    $validation_failed = true;
                    break;
                }
            }
            // B. Selector dropdown fallback
            elseif (!empty($_POST[$info['select_name']])) {
                $media_id = intval($_POST[$info['select_name']]);
                $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                $stmt->execute([':id' => $media_id]);
                $db_path = $stmt->fetchColumn();
                if ($db_path) {
                    $final_val = $db_path;
                }
            }

            $update_setting($key, $final_val);
        }

        // 2. Favicon Handling: Supports direct settings ICO/PNG upload, or Media Library selector fallback
        if (!$validation_failed) {
            $fav_key = 'site_favicon';
            $current_fav = get_setting($fav_key, '');
            $final_fav = $current_fav;

            // A. Direct settings file upload (accepts ICO)
            if (isset($_FILES['site_favicon_file']) && $_FILES['site_favicon_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $uploaded = validate_and_upload_image($_FILES['site_favicon_file'], $newFilesTracked, 'settings', 'favicon');
                    if ($uploaded) {
                        $final_fav = $uploaded;
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                    $validation_failed = true;
                }
            }
            // B. Selector dropdown fallback (compatible PNG/WebP only)
            elseif (!empty($_POST['site_favicon_media_id'])) {
                $media_id = intval($_POST['site_favicon_media_id']);
                $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                $stmt->execute([':id' => $media_id]);
                $db_path = $stmt->fetchColumn();
                if ($db_path) {
                    $final_fav = $db_path;
                }
            }

            $update_setting($fav_key, $final_fav);
        }
    }

    // 3. Process Toggles & Text Settings
    if (!$validation_failed) {
        $allowed_cs_keys = [
            'contact_phone', 'contact_phone_sec', 'whatsapp_number', 'contact_email', 
            'contact_address_ar', 'contact_address_en', 'working_hours_ar', 'working_hours_en',
            'social_facebook', 'social_instagram', 'social_twitter', 'social_linkedin', 
            'social_youtube', 'social_tiktok', 'social_snapchat'
        ];

        foreach ($registry as $key => $def) {
            // Ignore files (handled above) and keys not in POST (except toggles)
            if ($def['type'] === 'file') {
                continue;
            }
            if ($def['group'] !== 'global_ui' && $def['group'] !== 'general' && $def['group'] !== 'contact' && $def['group'] !== 'social_media' && $def['group'] !== 'branding') {
                continue;
            }

            // Customer Service security filter: ignore unauthorized keys
            if ($is_cs && !in_array($key, $allowed_cs_keys, true)) {
                continue;
            }

            if ($def['type'] === 'toggle') {
                $val = isset($_POST[$key]) ? '1' : '0';
                $update_setting($key, $val);
            } elseif (isset($_POST[$key])) {
                $val = trim($_POST[$key]);

                // A. Integer order weight bounds validation (0-999)
                if ($def['type'] === 'integer' || strpos($key, 'nav_order_') === 0 || $key === 'footer_services_limit') {
                    $int_val = filter_var($val, FILTER_VALIDATE_INT);
                    if ($int_val === false || $int_val < 0 || $int_val > 999) {
                        $error_message = (get_current_lang() === 'ar')
                            ? "قيمة الترتيب أو الحد الأقصى للحقل (" . $key . ") غير صالحة. يجب أن تكون بين 0 و 999."
                            : "Sort order or limit value for field (" . $key . ") is invalid. Must be between 0 and 999.";
                        $validation_failed = true;
                        break;
                    }
                    $val = (string)$int_val;
                }

                // B. Social Media HTTPS URLs validation
                if ($def['type'] === 'url' || $def['group'] === 'social_media') {
                    if (!empty($val)) {
                        if (strpos(strtolower($val), 'https://') !== 0) {
                            $error_message = (get_current_lang() === 'ar')
                                ? "رابط شبكة التواصل يجب أن يبدأ بـ HTTPS بروتوكول آمن."
                                : "Social URL must begin with secure HTTPS protocol.";
                            $validation_failed = true;
                            break;
                        }
                        if (filter_var($val, FILTER_VALIDATE_URL) === false) {
                            $error_message = (get_current_lang() === 'ar')
                                ? "رابط شبكة التواصل غير صالح."
                                : "Social URL format is invalid.";
                            $validation_failed = true;
                            break;
                        }
                    }
                }

                // C. Email validation
                if ($def['type'] === 'email' || $key === 'contact_email') {
                    if (!empty($val) && filter_var($val, FILTER_VALIDATE_EMAIL) === false) {
                        $error_message = (get_current_lang() === 'ar')
                            ? "البريد الإلكتروني للاتصال غير صالح."
                            : "Contact email address is invalid.";
                        $validation_failed = true;
                        break;
                    }
                }

                // D. Plain text validation for WhatsApp Message templates
                if (strpos($key, 'floating_whatsapp_msg_') === 0 || strpos($key, 'floating_visit_msg_') === 0) {
                    // Maximum length check
                    if (mb_strlen($val) > 500) {
                        $error_message = (get_current_lang() === 'ar')
                            ? "نموذج رسالة واتساب طويل جداً. الحد الأقصى هو 500 حرف."
                            : "WhatsApp message template is too long. Maximum is 500 characters.";
                        $validation_failed = true;
                        break;
                    }
                    // Strip control characters & HTML tags
                    $val = strip_tags($val);
                    $val = preg_replace('/[\x00-\x1F\x7F]/', '', $val);
                }

                // E. Google Maps Iframe validation & sanitization
                if ($key === 'contact_map_iframe') {
                    if (!empty($val)) {
                        $clean_iframe = sanitize_google_map_iframe($val);
                        if ($clean_iframe === false) {
                            $error_message = (get_current_lang() === 'ar')
                                ? "كود التضمين لخريطة جوجل غير صالح. يجب أن يحتوي على كود التضمين الرسمي."
                                : "Invalid Google Maps iframe embed code. Must contain the official embed URL.";
                            $validation_failed = true;
                            break;
                        }
                        $val = $clean_iframe;
                    }
                }

                $update_setting($key, $val);
            }
        }
    }

    // 4. Database Transaction Write
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
            // Roll back physical settings uploads if database write failed
            foreach ($newFilesTracked as $fpath) {
                if (file_exists(dirname(__DIR__) . '/' . $fpath)) {
                    unlink(dirname(__DIR__) . '/' . $fpath);
                }
            }
            error_log("Global UI transactional update failed: " . $e->getMessage());
            $error_message = (get_current_lang() === 'ar') ? 'حدث خطأ أثناء حفظ الإعدادات في قاعدة البيانات.' : 'An error occurred while saving settings in the database.';
        }
    }
}
}

// Reload settings into registry to display active values
$allowed_tabs = $is_cs ? ['contact', 'socials'] : ['branding', 'navigation', 'footer', 'contact', 'socials', 'fab'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs, true) ? $_GET['tab'] : (isset($_POST['active_tab']) ? trim($_POST['active_tab']) : $allowed_tabs[0]);
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = $allowed_tabs[0];
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

    <main class="admin-content">
        <header class="admin-header">
            <div>
                <h1 style="font-size: 28px;"><?php echo get_current_lang() === 'ar' ? 'إدارة عناصر الموقع العامة' : 'Global Site Management'; ?></h1>
                <p><?php echo get_current_lang() === 'ar' ? 'تخصيص الهوية، شعارات الموقع العامة، شريط التنقل، القائمة السفلية، ووسائل الاتصال العائمة.' : 'Customize site identity, logos, navigation menus, footer columns, and floating FAB controls.'; ?></p>
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
            <?php if (in_array('branding', $allowed_tabs, true)): ?>
                <button class="settings-tab-btn <?php echo $active_tab === 'branding' ? 'active' : ''; ?>" onclick="switchTab('branding')">
                    <i data-lucide="image"></i> <span><?php echo get_current_lang() === 'ar' ? 'الهوية والشعارات' : 'Branding'; ?></span>
                </button>
            <?php endif; ?>
            <?php if (in_array('navigation', $allowed_tabs, true)): ?>
                <button class="settings-tab-btn <?php echo $active_tab === 'navigation' ? 'active' : ''; ?>" onclick="switchTab('navigation')">
                    <i data-lucide="navigation"></i> <span><?php echo get_current_lang() === 'ar' ? 'شريط التنقل' : 'Navigation'; ?></span>
                </button>
            <?php endif; ?>
            <?php if (in_array('footer', $allowed_tabs, true)): ?>
                <button class="settings-tab-btn <?php echo $active_tab === 'footer' ? 'active' : ''; ?>" onclick="switchTab('footer')">
                    <i data-lucide="layout-template"></i> <span><?php echo get_current_lang() === 'ar' ? 'القائمة السفلية' : 'Footer'; ?></span>
                </button>
            <?php endif; ?>
            <?php if (in_array('contact', $allowed_tabs, true)): ?>
                <button class="settings-tab-btn <?php echo $active_tab === 'contact' ? 'active' : ''; ?>" onclick="switchTab('contact')">
                    <i data-lucide="phone"></i> <span><?php echo get_current_lang() === 'ar' ? 'معلومات الاتصال' : 'Contact'; ?></span>
                </button>
            <?php endif; ?>
            <?php if (in_array('socials', $allowed_tabs, true)): ?>
                <button class="settings-tab-btn <?php echo $active_tab === 'socials' ? 'active' : ''; ?>" onclick="switchTab('socials')">
                    <i data-lucide="share-2"></i> <span><?php echo get_current_lang() === 'ar' ? 'شبكات التواصل' : 'Socials'; ?></span>
                </button>
            <?php endif; ?>
            <?php if (in_array('fab', $allowed_tabs, true)): ?>
                <button class="settings-tab-btn <?php echo $active_tab === 'fab' ? 'active' : ''; ?>" onclick="switchTab('fab')">
                    <i data-lucide="message-circle"></i> <span><?php echo get_current_lang() === 'ar' ? 'العناصر العائمة والتوثيق' : 'FAB & Copyright'; ?></span>
                </button>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" id="globalUiForm" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">

            <!-- TAB 1: BRANDING PANEL -->
            <?php if (in_array('branding', $allowed_tabs, true)): ?>
            <div id="panel-branding" class="settings-section-panel <?php echo $active_tab === 'branding' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="image" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'هوية الموقع والشعارات' : 'Branding & Logo Assets'; ?></h2>
                    </div>
                    <div class="card-body">
                        <!-- Site Name & Tagline -->
                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'اسم الموقع (عربي)' : 'Site Name (AR)'; ?></label>
                                <input type="text" name="site_name_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('site_name_ar')); ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'اسم الموقع (إنجليزي)' : 'Site Name (EN)'; ?></label>
                                <input type="text" name="site_name_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('site_name_en')); ?>" required>
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'شعار الموقع (عربي)' : 'Tagline (AR)'; ?></label>
                                <input type="text" name="tagline_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('tagline_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'شعار الموقع (إنجليزي)' : 'Tagline (EN)'; ?></label>
                                <input type="text" name="tagline_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('tagline_en')); ?>">
                            </div>
                        </div>

                        <!-- Site Logo Text -->
                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'نص شعار الهيدر والفوتر (عربي)' : 'Site Logo Text (AR)'; ?></label>
                                <input type="text" name="site_logo_text_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('site_logo_text_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'نص شعار الهيدر والفوتر (إنجليزي)' : 'Site Logo Text (EN)'; ?></label>
                                <input type="text" name="site_logo_text_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('site_logo_text_en')); ?>">
                            </div>
                        </div>

                        <!-- Brand Logos Upload & Selectors -->
                        <div style="border-top: 1px solid var(--border-color); margin-top: 20px; padding-top: 20px;">
                            <h3><?php echo get_current_lang() === 'ar' ? 'رفع وتحديد ملفات الصور والشعارات' : 'Upload & Select Logo Files'; ?></h3>
                            
                            <!-- Main Logo -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label><?php echo get_current_lang() === 'ar' ? 'الشعار الرئيسي للموقع (فاتح)' : 'Main Site Logo (Light Mode)'; ?></label>
                                <?php $site_logo = get_setting('site_logo'); if(!empty($site_logo)): ?>
                                    <div style="margin-bottom: 10px;"><img src="../<?php echo htmlspecialchars($site_logo); ?>" style="max-height: 40px; background: #ddd; padding: 4px; border-radius: 4px;"></div>
                                <?php endif; ?>
                                <input type="file" name="site_logo_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="margin-bottom: 8px;">
                                <select name="site_logo_media_id" class="form-control">
                                    <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                                    <?php foreach ($media_items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" <?php echo $site_logo === $item['file_path'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['filename']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Dark Logo -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label><?php echo get_current_lang() === 'ar' ? 'شعار الموقع في الوضع المظلم (اختياري)' : 'Dark Mode Logo (Optional)'; ?></label>
                                <?php $site_logo_dark = get_setting('site_logo_dark'); if(!empty($site_logo_dark)): ?>
                                    <div style="margin-bottom: 10px;"><img src="../<?php echo htmlspecialchars($site_logo_dark); ?>" style="max-height: 40px; background: #333; padding: 4px; border-radius: 4px;"></div>
                                <?php endif; ?>
                                <input type="file" name="site_logo_dark_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="margin-bottom: 8px;">
                                <select name="site_logo_dark_media_id" class="form-control">
                                    <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                                    <?php foreach ($media_items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" <?php echo $site_logo_dark === $item['file_path'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['filename']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Favicon (Supports ICO upload) -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label><?php echo get_current_lang() === 'ar' ? 'أيقونة الموقع (Favicon - يدعم رفع ملفات ICO)' : 'Site Favicon (Supports direct ICO upload)'; ?></label>
                                <?php $site_favicon = get_setting('site_favicon'); if(!empty($site_favicon)): ?>
                                    <div style="margin-bottom: 10px;"><img src="../<?php echo htmlspecialchars($site_favicon); ?>" style="max-height: 32px;"></div>
                                <?php endif; ?>
                                <input type="file" name="site_favicon_file" class="form-control" accept="image/x-icon,image/png,image/vnd.microsoft.icon" style="margin-bottom: 8px;">
                                <select name="site_favicon_media_id" class="form-control">
                                    <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط (PNG/WebP فقط) --' : '-- Select from Media Library (PNG/WebP only) --'; ?></option>
                                    <?php foreach ($media_items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" <?php echo $site_favicon === $item['file_path'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['filename']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- OG Image -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label><?php echo get_current_lang() === 'ar' ? 'صورة المشاركة الافتراضية بمواقع التواصل (OG Image)' : 'Default Open Graph Share Image'; ?></label>
                                <?php $default_og = get_setting('default_og_image'); if(!empty($default_og)): ?>
                                    <div style="margin-bottom: 10px;"><img src="../<?php echo htmlspecialchars($default_og); ?>" style="max-height: 80px; border-radius: 4px;"></div>
                                <?php endif; ?>
                                <input type="file" name="default_og_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="margin-bottom: 8px;">
                                <select name="default_og_image_media_id" class="form-control">
                                    <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                                    <?php foreach ($media_items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" <?php echo $default_og === $item['file_path'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['filename']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Logos Alts -->
                            <div class="form-row" style="display: flex; gap: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'النص البديل للشعار (عربي)' : 'Logo Alt Text (AR)'; ?></label>
                                    <input type="text" name="logo_alt_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('logo_alt_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'النص البديل للشعار (إنجليزي)' : 'Logo Alt Text (EN)'; ?></label>
                                    <input type="text" name="logo_alt_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('logo_alt_en')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 2: NAVIGATION PANEL -->
            <?php if (in_array('navigation', $allowed_tabs, true)): ?>
            <div id="panel-navigation" class="settings-section-panel <?php echo $active_tab === 'navigation' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="navigation" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'عناصر القائمة الرئيسية والتنقل' : 'Main Navigation Settings'; ?></h2>
                    </div>
                    <div class="card-body">
                        <!-- Global switches -->
                        <div style="display: flex; gap: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="nav_show_lang_switcher" value="1" <?php echo get_setting('nav_show_lang_switcher', '1') === '1' ? 'checked' : ''; ?>>
                                <span><?php echo get_current_lang() === 'ar' ? 'إظهار مبدل اللغة بالهيدر' : 'Show Language Switcher in Header'; ?></span>
                            </label>
                            <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="nav_show_dark_mode" value="1" <?php echo get_setting('nav_show_dark_mode', '1') === '1' ? 'checked' : ''; ?>>
                                <span><?php echo get_current_lang() === 'ar' ? 'إظهار زر الوضع الداكن بالهيدر' : 'Show Dark Mode Toggle in Header'; ?></span>
                            </label>
                        </div>

                        <!-- Fixed Navigation routes settings table -->
                        <table class="table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color); text-align: start;">
                                    <th style="padding: 10px;"><?php echo get_current_lang() === 'ar' ? 'الصفحة المستهدفة' : 'Target Page'; ?></th>
                                    <th style="padding: 10px;"><?php echo get_current_lang() === 'ar' ? 'العرض في Navbar' : 'Show in Navbar'; ?></th>
                                    <th style="padding: 10px;"><?php echo get_current_lang() === 'ar' ? 'الترتيب (0-999)' : 'Order (0-999)'; ?></th>
                                    <th style="padding: 10px;"><?php echo get_current_lang() === 'ar' ? 'العنوان بالعربية' : 'Label (AR)'; ?></th>
                                    <th style="padding: 10px;"><?php echo get_current_lang() === 'ar' ? 'العنوان بالإنجليزية' : 'Label (EN)'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $nav_items_list = ['home', 'products', 'services', 'projects', 'about', 'contact'];
                                foreach ($nav_items_list as $nkey):
                                    // Resolve key labels settings
                                    $lbl_ar_key = ($nkey === 'home' || $nkey === 'products' || $nkey === 'about') ? 'nav_' . $nkey . '_ar' : 'nav_' . $nkey . '_ar';
                                    $lbl_en_key = ($nkey === 'home' || $nkey === 'products' || $nkey === 'about') ? 'nav_' . $nkey . '_en' : 'nav_' . $nkey . '_en';
                                    if ($nkey === 'services') { $lbl_ar_key = 'nav_services_ar'; $lbl_en_key = 'nav_services_en'; }
                                    if ($nkey === 'projects') { $lbl_ar_key = 'nav_projects_ar'; $lbl_en_key = 'nav_projects_en'; }
                                    if ($nkey === 'contact') { $lbl_ar_key = 'nav_contact_ar'; $lbl_en_key = 'nav_contact_en'; }
                                ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px; font-weight: bold;"><?php echo htmlspecialchars($nkey); ?>.php</td>
                                        <td style="padding: 12px;">
                                            <input type="checkbox" name="nav_show_<?php echo $nkey; ?>" value="1" <?php echo get_setting('nav_show_' . $nkey, '1') === '1' ? 'checked' : ''; ?>>
                                        </td>
                                        <td style="padding: 12px;">
                                            <input type="number" name="nav_order_<?php echo $nkey; ?>" class="form-control" style="width: 80px;" value="<?php echo htmlspecialchars(get_setting('nav_order_' . $nkey)); ?>" required min="0" max="999">
                                        </td>
                                        <td style="padding: 12px;">
                                            <input type="text" name="<?php echo $lbl_ar_key; ?>" class="form-control" value="<?php echo htmlspecialchars(get_setting($lbl_ar_key)); ?>">
                                        </td>
                                        <td style="padding: 12px;">
                                            <input type="text" name="<?php echo $lbl_en_key; ?>" class="form-control" value="<?php echo htmlspecialchars(get_setting($lbl_en_key)); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 3: FOOTER PANEL -->
            <?php if (in_array('footer', $allowed_tabs, true)): ?>
            <div id="panel-footer" class="settings-section-panel <?php echo $active_tab === 'footer' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="layout-template" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'عناصر وهيكل القائمة السفلية' : 'Footer Columns & Layout Settings'; ?></h2>
                    </div>
                    <div class="card-body">
                        <!-- Column 1: Identity -->
                        <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px;">
                            <h3><?php echo get_current_lang() === 'ar' ? 'العمود الأول: هوية وقصيرة المؤسسة' : 'Column 1: Company Identity & Info'; ?></h3>
                            <label class="switch-container" style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">
                                <input type="checkbox" name="footer_show_identity" value="1" <?php echo get_setting('footer_show_identity', '1') === '1' ? 'checked' : ''; ?>>
                                <span><?php echo get_current_lang() === 'ar' ? 'عرض عمود الهوية بالكامل' : 'Show Identity Column'; ?></span>
                            </label>

                            <!-- Footer Logo -->
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label><?php echo get_current_lang() === 'ar' ? 'شعار القائمة السفلية (اختياري - يقع للرئيسي كبديل)' : 'Footer Logo (Optional - falls back to Main Logo)'; ?></label>
                                <?php $footer_logo = get_setting('footer_logo'); if(!empty($footer_logo)): ?>
                                    <div style="margin-bottom: 10px;"><img src="../<?php echo htmlspecialchars($footer_logo); ?>" style="max-height: 40px; background: #ddd; padding: 4px;"></div>
                                <?php endif; ?>
                                <input type="file" name="footer_logo_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="margin-bottom: 8px;">
                                <select name="footer_logo_media_id" class="form-control">
                                    <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                                    <?php foreach ($media_items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" <?php echo $footer_logo === $item['file_path'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['filename']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row" style="display: flex; gap: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'نبذة تعريفية سفلية (عربي)' : 'Footer Description (AR)'; ?></label>
                                    <textarea name="footer_description_ar" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('footer_description_ar')); ?></textarea>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'نبذة تعريفية سفلية (إنجليزي)' : 'Footer Description (EN)'; ?></label>
                                    <textarea name="footer_description_en" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('footer_description_en')); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Services links -->
                        <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px;">
                            <h3><?php echo get_current_lang() === 'ar' ? 'العمود الثاني: روابط الخدمات النشطة' : 'Column 2: Dynamic Services Links'; ?></h3>
                            <label class="switch-container" style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">
                                <input type="checkbox" name="footer_show_services" value="1" <?php echo get_setting('footer_show_services', '1') === '1' ? 'checked' : ''; ?>>
                                <span><?php echo get_current_lang() === 'ar' ? 'إظهار عمود الخدمات في الفوتر' : 'Show Services Column'; ?></span>
                            </label>

                            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان العمود (عربي)' : 'Services Column Title (AR)'; ?></label>
                                    <input type="text" name="footer_services_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('footer_services_title_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان العمود (إنجليزي)' : 'Services Column Title (EN)'; ?></label>
                                    <input type="text" name="footer_services_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('footer_services_title_en')); ?>">
                                </div>
                                <div class="form-group" style="flex: 0.5;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'الحد الأقصى للروابط' : 'Max Links Limit'; ?></label>
                                    <input type="number" name="footer_services_limit" class="form-control" value="<?php echo htmlspecialchars(get_setting('footer_services_limit')); ?>" required min="1" max="10">
                                </div>
                            </div>
                        </div>

                        <!-- Column 3: Categories links -->
                        <div>
                            <h3><?php echo get_current_lang() === 'ar' ? 'العمود الثالث: تصنيفات المنتجات' : 'Column 3: Product Categories'; ?></h3>
                            <label class="switch-container" style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">
                                <input type="checkbox" name="footer_show_categories" value="1" <?php echo get_setting('footer_show_categories', '1') === '1' ? 'checked' : ''; ?>>
                                <span><?php echo get_current_lang() === 'ar' ? 'إظهار عمود تصنيفات المنتجات' : 'Show Product Categories Column'; ?></span>
                            </label>

                            <div class="form-row" style="display: flex; gap: 20px;">
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان تصنيفات الفوتر (عربي)' : 'Categories Title (AR)'; ?></label>
                                    <input type="text" name="footer_categories_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('footer_categories_title_ar')); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label><?php echo get_current_lang() === 'ar' ? 'عنوان تصنيفات الفوتر (إنجليزي)' : 'Categories Title (EN)'; ?></label>
                                    <input type="text" name="footer_categories_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('footer_categories_title_en')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 4: CONTACT PANEL -->
            <div id="panel-contact" class="settings-section-panel <?php echo $active_tab === 'contact' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="phone" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'معلومات وعناوين الاتصال المشتركة' : 'Shared Contact Details'; ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'الهاتف الرئيسي' : 'Primary Phone'; ?></label>
                                <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_phone')); ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'الهاتف الثانوي (اختياري)' : 'Secondary Phone'; ?></label>
                                <input type="text" name="contact_phone_sec" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_phone_sec')); ?>">
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'رقم واتساب (تنسيق دولي فقط بدون أصفار أو زائد: 96650...)' : 'WhatsApp Number (Digits only: 96650...)'; ?></label>
                                <input type="text" name="whatsapp_number" class="form-control" value="<?php echo htmlspecialchars(get_setting('whatsapp_number')); ?>" required pattern="[0-9]+">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'البريد الإلكتروني' : 'Email Address'; ?></label>
                                <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_email')); ?>">
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'العنوان الفعلي (عربي)' : 'Address (AR)'; ?></label>
                                <input type="text" name="contact_address_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_address_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'العنوان الفعلي (إنجليزي)' : 'Address (EN)'; ?></label>
                                <input type="text" name="contact_address_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_address_en')); ?>">
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'أوقات العمل (عربي)' : 'Working Hours (AR)'; ?></label>
                                <input type="text" name="working_hours_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('working_hours_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'أوقات العمل (إنجليزي)' : 'Working Hours (EN)'; ?></label>
                                <input type="text" name="working_hours_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('working_hours_en')); ?>">
                            </div>
                        </div>

                        <?php if (!$is_cs): ?>
                        <div class="form-group">
                            <label><?php echo get_current_lang() === 'ar' ? 'خريطة جوجل التفاعلية (Iframe HTML)' : 'Google Map Iframe Embed code'; ?></label>
                            <textarea name="contact_map_iframe" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('contact_map_iframe')); ?></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 5: SOCIAL MEDIA PANEL -->
            <div id="panel-socials" class="settings-section-panel <?php echo $active_tab === 'socials' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="share-2" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'شبكات التواصل الاجتماعي' : 'Social Media Links'; ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success" style="margin-bottom: 20px;">
                            <i data-lucide="check-circle" style="vertical-align: middle; margin-inline-end: 6px;"></i>
                            <span>
                                <?php echo get_current_lang() === 'ar' 
                                    ? 'روابط شبكات التواصل الاجتماعي تُحفظ بأمان وتُعرض تلقائياً وبشكل ديناميكي في أسفل القائمة السفلية (الفوتر) للموقع العام.' 
                                    : 'Social media links are stored securely and are displayed dynamically in the public website footer.'; ?>
                            </span>
                        </div>

                        <?php 
                        $social_channels = [
                            'social_facebook'  => 'Facebook URL',
                            'social_instagram' => 'Instagram URL',
                            'social_twitter'   => 'X / Twitter URL',
                            'social_linkedin'  => 'LinkedIn URL',
                            'social_youtube'   => 'YouTube URL',
                            'social_tiktok'    => 'TikTok URL',
                            'social_snapchat'  => 'Snapchat URL'
                        ];
                        foreach ($social_channels as $skey => $slabel):
                        ?>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label><?php echo $slabel; ?></label>
                                <input type="url" name="<?php echo $skey; ?>" class="form-control" placeholder="https://..." value="<?php echo htmlspecialchars(get_setting($skey)); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 6: FAB & COPYRIGHT PANEL -->
            <?php if (in_array('fab', $allowed_tabs, true)): ?>
            <div id="panel-fab" class="settings-section-panel <?php echo $active_tab === 'fab' ? 'active' : ''; ?>">
                <!-- Floating FAB Controls -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="message-circle" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'إعدادات أزرار الاتصال العائمة (FAB)' : 'Floating Contact Widget (FAB)'; ?></h2>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; gap: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; flex-wrap: wrap;">
                            <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="show_floating_whatsapp" value="1" <?php echo get_setting('show_floating_whatsapp', '1') === '1' ? 'checked' : ''; ?>>
                                <span><?php echo get_current_lang() === 'ar' ? 'عرض زر واتساب عائم' : 'Show WhatsApp FAB'; ?></span>
                            </label>
                            <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="show_floating_phone" value="1" <?php echo get_setting('show_floating_phone', '1') === '1' ? 'checked' : ''; ?>>
                                <span><?php echo get_current_lang() === 'ar' ? 'عرض زر الهاتف عائم' : 'Show Voice Call FAB'; ?></span>
                            </label>
                            <label class="switch-container" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="show_floating_visit" value="1" <?php echo get_setting('show_floating_visit', '1') === '1' ? 'checked' : ''; ?>>
                                <span><?php echo get_current_lang() === 'ar' ? 'عرض زر المعاينة عائم' : 'Show Site Visit FAB'; ?></span>
                            </label>
                        </div>

                        <!-- Plain text templates -->
                        <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'رسالة واتساب الافتراضية (عربي)' : 'WhatsApp Chat Message Template (AR)'; ?></label>
                                <input type="text" name="floating_whatsapp_msg_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('floating_whatsapp_msg_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'رسالة واتساب الافتراضية (إنجليزي)' : 'WhatsApp Chat Message Template (EN)'; ?></label>
                                <input type="text" name="floating_whatsapp_msg_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('floating_whatsapp_msg_en')); ?>">
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'رسالة حجز معاينة واتساب (عربي)' : 'Book Visit Message Template (AR)'; ?></label>
                                <input type="text" name="floating_visit_msg_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('floating_visit_msg_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'رسالة حجز معاينة واتساب (إنجليزي)' : 'Book Visit Message Template (EN)'; ?></label>
                                <input type="text" name="floating_visit_msg_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('floating_visit_msg_en')); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Copyright Panel -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i data-lucide="copyright" style="vertical-align: middle;"></i> <?php echo get_current_lang() === 'ar' ? 'إعدادات حقوق التوثيق (Copyright)' : 'Copyright Information'; ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info" style="margin-bottom: 15px;">
                            <i data-lucide="info" style="vertical-align: middle; margin-inline-end: 6px;"></i>
                            <span>
                                <?php echo get_current_lang() === 'ar' 
                                    ? 'استخدم المحارف النائبة الآمنة: {year} للسنة الحالية تلقائياً و {business_name} لاسم المؤسسة الفعلي.' 
                                    : 'Use safe placeholder tags: {year} for current year dynamically and {business_name} for the configured business name.'; ?>
                            </span>
                        </div>

                        <div class="form-row" style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'جملة الحقوق (عربي)' : 'Copyright Text (AR)'; ?></label>
                                <input type="text" name="copyright_text_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('copyright_text_ar')); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><?php echo get_current_lang() === 'ar' ? 'جملة الحقوق (إنجليزي)' : 'Copyright Text (EN)'; ?></label>
                                <input type="text" name="copyright_text_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('copyright_text_en')); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form Submit Buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                    <i data-lucide="save" style="vertical-align: middle; margin-inline-end: 6px;"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'حفظ كافة الإعدادات' : 'Save All Settings'; ?></span>
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
