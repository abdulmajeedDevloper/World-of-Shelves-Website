<?php
// admin/homepage.php
// Dedicated editor dashboard for Homepage CMS.

require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';

$success_message = '';
$error_message = '';

// Authoritative sections mapping
$sections_list = [
    'hero'          => ['label_ar' => 'قسم الهيرو (العلوي)', 'label_en' => 'Hero Section', 'default_order' => 10],
    'trust_strip'   => ['label_ar' => 'شريط الثقة المبسط', 'label_en' => 'Trust Strip', 'default_order' => 20],
    'trust_badges'  => ['label_ar' => 'بطاقات التقييم والمميزات', 'label_en' => 'Trust Badges', 'default_order' => 30],
    'services'      => ['label_ar' => 'معاينة الخدمات', 'label_en' => 'Services Preview', 'default_order' => 40, 'partially_manageable' => true],
    'areas'         => ['label_ar' => 'مجالات وتطبيقات الخدمة', 'label_en' => 'Service Areas / Applications', 'default_order' => 50],
    'categories'    => ['label_ar' => 'تصنيفات المنتجات', 'label_en' => 'Product Categories', 'default_order' => 60, 'partially_manageable' => true],
    'products'      => ['label_ar' => 'قسم المنتجات المعروضة', 'label_en' => 'Featured Products Section', 'default_order' => 70],
    'before_after'  => ['label_ar' => 'قبل وبعد التركيب', 'label_en' => 'Before & After Section', 'default_order' => 80, 'partially_manageable' => true],
    'projects'      => ['label_ar' => 'قسم المشاريع المميزة', 'label_en' => 'Featured Projects Section', 'default_order' => 90],
    'stats'         => ['label_ar' => 'شريط الإحصائيات الرقمية', 'label_en' => 'Business Statistics', 'default_order' => 100],
    'testimonials'  => ['label_ar' => 'آراء وتوصيات العملاء', 'label_en' => 'Testimonials Section', 'default_order' => 110],
    'clients'       => ['label_ar' => 'شعار شركاء النجاح', 'label_en' => 'Client Logos', 'default_order' => 120, 'partially_manageable' => true],
    'how_we_work'   => ['label_ar' => 'خطوات العمل وكيف نعمل', 'label_en' => 'How We Work Steps', 'default_order' => 130, 'partially_manageable' => true],
    'why_choose_us' => ['label_ar' => 'لماذا تختارنا (المميزات)', 'label_en' => 'Why Choose Us Features', 'default_order' => 140, 'partially_manageable' => true],
    'cta'           => ['label_ar' => 'صندوق الدعوة للعمل (CTA)', 'label_en' => 'Call to Action Block', 'default_order' => 150]
];

$cta_actions_allowlist = ['whatsapp', 'products.php', 'services.php', 'projects.php', 'contact.php', 'about.php'];
$product_sources_allowlist = ['featured', 'new', 'requested', 'latest'];
$project_sources_allowlist = ['featured', 'latest', 'featured_latest'];
$testimonial_sources_allowlist = ['featured', 'active', 'latest_active'];

// Fetch Media Library images for selectors (latest 100)
$media_list = [];
try {
    $media_stmt = $pdo->query("SELECT id, filename, file_path, folder FROM media_library ORDER BY id DESC LIMIT 100");
    $media_list = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed fetching media list in homepage dashboard: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = get_current_lang() === 'ar' ? 'رمز الحماية (CSRF Token) غير صالح.' : 'Invalid security token (CSRF).';
    } else {
        $pdo->beginTransaction();
        $newFilesTracked = [];
        $validation_failed = false;
        
        try {
            // Helper to update setting key safely
            $update_setting = function($key, $val) use ($pdo, $db_driver) {
                $upsert_query = ($db_driver === 'mysql')
                    ? "INSERT INTO settings (key_name, val) VALUES (:key, :val) ON DUPLICATE KEY UPDATE val = :val2"
                    : "INSERT INTO settings (key_name, val) VALUES (:key, :val) ON CONFLICT(key_name) DO UPDATE SET val = :val2";
                $stmt = $pdo->prepare($upsert_query);
                $stmt->execute([':key' => $key, ':val' => $val, ':val2' => $val]);
            };

            // Helper to process uploaded file or media selection
            $process_image_field = function($file_field_name, $select_field_name, $current_val) use ($pdo, &$newFilesTracked, &$error_message, &$validation_failed) {
                // 1. Direct upload takes priority
                if (isset($_FILES[$file_field_name]) && $_FILES[$file_field_name]['error'] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $media_id = upload_to_media_library($pdo, $_FILES[$file_field_name], 'homepage');
                        if ($media_id) {
                            $stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id");
                            $stmt->execute([':id' => $media_id]);
                            return $stmt->fetchColumn();
                        }
                    } catch (Throwable $e) {
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

            // -----------------------------------------------------------------
            // Validate & Save: Section Visibility & Sorting
            // -----------------------------------------------------------------
            foreach ($sections_list as $key => $sec) {
                $show_field = "homepage_show_" . $key;
                $order_field = "homepage_order_" . $key;
                
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
                
                $update_setting($show_field, $show_val);
                $update_setting($order_field, (string)$order_val);
            }

            // -----------------------------------------------------------------
            // Validate & Save: Hero Section
            // -----------------------------------------------------------------
            if (!$validation_failed) {
                $hero_title_ar = trim($_POST['hero_title_ar'] ?? '');
                $hero_title_en = trim($_POST['hero_title_en'] ?? '');
                $hero_subtitle_ar = trim($_POST['hero_subtitle_ar'] ?? '');
                $hero_subtitle_en = trim($_POST['hero_subtitle_en'] ?? '');
                
                $primary_label_ar = trim($_POST['hero_cta_primary_label_ar'] ?? '');
                $primary_label_en = trim($_POST['hero_cta_primary_label_en'] ?? '');
                $secondary_label_ar = trim($_POST['hero_cta_secondary_label_ar'] ?? '');
                $secondary_label_en = trim($_POST['hero_cta_secondary_label_en'] ?? '');
                
                $primary_action = trim($_POST['hero_cta_primary_action'] ?? '');
                $secondary_action = trim($_POST['hero_cta_secondary_action'] ?? '');
                
                $show_hero = isset($_POST['homepage_show_hero']);
                if ($show_hero && (empty($hero_title_ar) || empty($hero_title_en) || empty($hero_subtitle_ar) || empty($hero_subtitle_en))) {
                    $error_message = get_current_lang() === 'ar' ? 'بيانات قسم الهيرو غير مكتملة. العنوان والوصف مطلوبة.' : 'Hero Section data is incomplete. Title and subtitle are required.';
                    $validation_failed = true;
                } elseif (!in_array($primary_action, $cta_actions_allowlist, true) || !in_array($secondary_action, $cta_actions_allowlist, true)) {
                    $error_message = get_current_lang() === 'ar' ? 'الإجراء المختار للزر الرئيسي أو الثانوي غير صالح.' : 'Selected primary or secondary CTA target action is invalid.';
                    $validation_failed = true;
                } else {
                    $update_setting('hero_title_ar', $hero_title_ar);
                    $update_setting('hero_title_en', $hero_title_en);
                    $update_setting('hero_subtitle_ar', $hero_subtitle_ar);
                    $update_setting('hero_subtitle_en', $hero_subtitle_en);
                    $update_setting('hero_cta_primary_label_ar', $primary_label_ar);
                    $update_setting('hero_cta_primary_label_en', $primary_label_en);
                    $update_setting('hero_cta_secondary_label_ar', $secondary_label_ar);
                    $update_setting('hero_cta_secondary_label_en', $secondary_label_en);
                    $update_setting('hero_cta_primary_action', $primary_action);
                    $update_setting('hero_cta_secondary_action', $secondary_action);
                    
                    // Image priority process
                    $curr_hero = get_setting('hero_image', 'assets/images/shelf1.png');
                    $final_hero = $process_image_field('hero_image_file', 'hero_image_media_id', $curr_hero);
                    $update_setting('hero_image', $final_hero);
                }
            }

            // -----------------------------------------------------------------
            // Validate & Save: Section Headings
            // -----------------------------------------------------------------
            if (!$validation_failed) {
                $heading_keys = [
                    'homepage_services_title_ar', 'homepage_services_title_en', 'homepage_services_subtitle_ar', 'homepage_services_subtitle_en',
                    'homepage_services_cta_label_ar', 'homepage_services_cta_label_en',
                    'homepage_areas_title_ar', 'homepage_areas_title_en', 'homepage_areas_subtitle_ar', 'homepage_areas_subtitle_en',
                    'homepage_products_title_ar', 'homepage_products_title_en',
                    'homepage_before_after_title_ar', 'homepage_before_after_title_en',
                    'homepage_projects_title_ar', 'homepage_projects_title_en', 'homepage_projects_subtitle_ar', 'homepage_projects_subtitle_en',
                    'homepage_testimonials_title_ar', 'homepage_testimonials_title_en', 'homepage_testimonials_subtitle_ar', 'homepage_testimonials_subtitle_en',
                    'homepage_clients_title_ar', 'homepage_clients_title_en',
                    'homepage_how_we_work_title_ar', 'homepage_how_we_work_title_en',
                    'homepage_why_us_title_ar', 'homepage_why_us_title_en'
                ];
                foreach ($heading_keys as $hk) {
                    $update_setting($hk, trim($_POST[$hk] ?? ''));
                }
            }

            // -----------------------------------------------------------------
            // Validate & Save: Module Integrations
            // -----------------------------------------------------------------
            if (!$validation_failed) {
                $show_products = isset($_POST['homepage_show_products']);
                $show_projects = isset($_POST['homepage_show_projects']);
                $show_testimonials = isset($_POST['homepage_show_testimonials']);

                $prod_limit = $show_products ? filter_var($_POST['homepage_products_limit'] ?? '', FILTER_VALIDATE_INT) : 3;
                $proj_limit = $show_projects ? filter_var($_POST['homepage_projects_limit'] ?? '', FILTER_VALIDATE_INT) : 3;
                $test_limit = $show_testimonials ? filter_var($_POST['homepage_testimonials_limit'] ?? '', FILTER_VALIDATE_INT) : 6;
                
                $prod_src = trim($_POST['homepage_products_source'] ?? 'featured');
                $proj_src = trim($_POST['homepage_projects_source'] ?? 'featured');
                $test_src = trim($_POST['homepage_testimonials_source'] ?? 'featured');

                if ($show_products && ($prod_limit === false || $prod_limit < 1 || $prod_limit > 12)) {
                    $error_message = get_current_lang() === 'ar' ? 'الحد الأقصى لعرض المنتجات غير صالح.' : 'Product display limit is invalid.';
                    $validation_failed = true;
                } elseif ($show_projects && ($proj_limit === false || $proj_limit < 1 || $proj_limit > 12)) {
                    $error_message = get_current_lang() === 'ar' ? 'الحد الأقصى لعرض المشاريع غير صالح.' : 'Project display limit is invalid.';
                    $validation_failed = true;
                } elseif ($show_testimonials && ($test_limit === false || $test_limit < 1 || $test_limit > 20)) {
                    $error_message = get_current_lang() === 'ar' ? 'الحد الأقصى لعرض آراء العملاء غير صالح.' : 'Testimonial display limit is invalid.';
                    $validation_failed = true;
                } elseif ($show_products && !in_array($prod_src, $product_sources_allowlist, true)) {
                    $error_message = get_current_lang() === 'ar' ? 'مصدر فحص وتصفية المنتجات غير صالح.' : 'Product filter source is invalid.';
                    $validation_failed = true;
                } elseif ($show_projects && !in_array($proj_src, $project_sources_allowlist, true)) {
                    $error_message = get_current_lang() === 'ar' ? 'مصدر فحص وتصفية المشاريع غير صالح.' : 'Project filter source is invalid.';
                    $validation_failed = true;
                } elseif ($show_testimonials && !in_array($test_src, $testimonial_sources_allowlist, true)) {
                    $error_message = get_current_lang() === 'ar' ? 'مصدر فحص وتصفية الآراء غير صالح.' : 'Testimonial filter source is invalid.';
                    $validation_failed = true;
                } else {
                    $update_setting('homepage_products_limit', (string)$prod_limit);
                    $update_setting('homepage_products_source', $prod_src);
                    $update_setting('homepage_projects_limit', (string)$proj_limit);
                    $update_setting('homepage_projects_source', $proj_src);
                    $update_setting('homepage_testimonials_limit', (string)$test_limit);
                    $update_setting('homepage_testimonials_source', $test_src);
                }
            }

            // -----------------------------------------------------------------
            // Validate & Save: 4 Statistics Cards
            // -----------------------------------------------------------------
            if (!$validation_failed) {
                $show_stats = isset($_POST['homepage_show_stats']);
                for ($i = 1; $i <= 4; $i++) {
                    $val = trim($_POST["homepage_stat_{$i}_value"] ?? '');
                    $suffix = trim($_POST["homepage_stat_{$i}_suffix"] ?? '');
                    $title_ar = trim($_POST["homepage_stat_{$i}_title_ar"] ?? '');
                    $title_en = trim($_POST["homepage_stat_{$i}_title_en"] ?? '');
                    $desc_ar = trim($_POST["homepage_stat_{$i}_desc_ar"] ?? '');
                    $desc_en = trim($_POST["homepage_stat_{$i}_desc_en"] ?? '');
                    
                    if ($show_stats && (empty($val) || empty($title_ar) || empty($title_en))) {
                        $error_message = get_current_lang() === 'ar' 
                            ? "بيانات بطاقة الإحصائيات رقم {$i} غير مكتملة. القيمة والعنوان مطلوبة." 
                            : "Statistics Card {$i} data is incomplete. Value and Title are required.";
                        $validation_failed = true;
                        break;
                    }
                    
                    $update_setting("homepage_stat_{$i}_value", $val);
                    $update_setting("homepage_stat_{$i}_suffix", $suffix);
                    $update_setting("homepage_stat_{$i}_title_ar", $title_ar);
                    $update_setting("homepage_stat_{$i}_title_en", $title_en);
                    $update_setting("homepage_stat_{$i}_desc_ar", $desc_ar);
                    $update_setting("homepage_stat_{$i}_desc_en", $desc_en);
                }
            }

            // -----------------------------------------------------------------
            // Validate & Save: 6 Service Areas Cards
            // -----------------------------------------------------------------
            if (!$validation_failed) {
                $show_areas = isset($_POST['homepage_show_areas']);
                for ($i = 1; $i <= 6; $i++) {
                    $icon = trim($_POST["homepage_area_{$i}_icon"] ?? '');
                    $title_ar = trim($_POST["homepage_area_{$i}_title_ar"] ?? '');
                    $title_en = trim($_POST["homepage_area_{$i}_title_en"] ?? '');
                    $desc_ar = trim($_POST["homepage_area_{$i}_desc_ar"] ?? '');
                    $desc_en = trim($_POST["homepage_area_{$i}_desc_en"] ?? '');
                    
                    if ($show_areas && (empty($title_ar) || empty($title_en))) {
                        $error_message = get_current_lang() === 'ar' 
                            ? "بيانات مجال الخدمة رقم {$i} غير مكتملة. العنوان مطلوب." 
                            : "Service Area Card {$i} data is incomplete. Title is required.";
                        $validation_failed = true;
                        break;
                    }
                    
                    $update_setting("homepage_area_{$i}_icon", $icon);
                    $update_setting("homepage_area_{$i}_title_ar", $title_ar);
                    $update_setting("homepage_area_{$i}_title_en", $title_en);
                    $update_setting("homepage_area_{$i}_desc_ar", $desc_ar);
                    $update_setting("homepage_area_{$i}_desc_en", $desc_en);
                }
            }

            // -----------------------------------------------------------------
            // Validate & Save: CTA Section
            // -----------------------------------------------------------------
            if (!$validation_failed) {
                $cta_title_ar = trim($_POST['cta_home_title_ar'] ?? '');
                $cta_title_en = trim($_POST['cta_home_title_en'] ?? '');
                $cta_desc_ar = trim($_POST['cta_home_desc_ar'] ?? '');
                $cta_desc_en = trim($_POST['cta_home_desc_en'] ?? '');
                
                $update_setting('cta_home_title_ar', $cta_title_ar);
                $update_setting('cta_home_title_en', $cta_title_en);
                $update_setting('cta_home_desc_ar', $cta_desc_ar);
                $update_setting('cta_home_desc_en', $cta_desc_en);
                
                $curr_bg = get_setting('cta_home_bg_image', '');
                $final_bg = $process_image_field('cta_home_bg_image_file', 'cta_home_bg_image_media_id', $curr_bg);
                $update_setting('cta_home_bg_image', $final_bg);
            }

            // Finish transaction
            if (!$validation_failed) {
                $pdo->commit();
                $success_message = __('homepage_save_success');
            } else {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log(sprintf(
                "[Homepage Save Error] Msg: %s | File: %s | Line: %d | Code: %s",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getCode()
            ));
            $error_message = get_current_lang() === 'ar' 
                ? 'حدث خطأ أثناء حفظ الإعدادات. تفاصيل الخطأ مسجلة في سجل النظام.' 
                : 'An error occurred while saving settings. Details have been logged.';
        }
    }
}

$page_title = __('admin_homepage_title');
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';

$lang = get_current_lang();
?>

<main class="admin-content">
    <header class="admin-header">
        <div>
            <h1 style="font-size: 28px;"><?php echo __('admin_homepage_title'); ?></h1>
            <p><?php echo get_current_lang() === 'ar' ? 'إدارة بنية ومحتوى وتكامل الصفحة الرئيسية' : 'Configure layout structure, titles, and integrations of public index'; ?></p>
        </div>
        
        <a href="?lang=<?php echo $lang === 'ar' ? 'en' : 'ar'; ?>" class="lang-toggle">
            <i data-lucide="globe"></i>
            <span><?php echo $lang === 'ar' ? 'English' : 'العربية'; ?></span>
        </a>
    </header>

    <!-- Messages alerts -->
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

    <div class="homepage-tabs-container">
        <button class="homepage-tab-btn active" onclick="switchTab(event, 'tab-visibility')"><?php echo __('homepage_tab_visibility'); ?></button>
        <button class="homepage-tab-btn" onclick="switchTab(event, 'tab-hero')"><?php echo __('homepage_tab_hero'); ?></button>
        <button class="homepage-tab-btn" onclick="switchTab(event, 'tab-headings')"><?php echo __('homepage_tab_headings'); ?></button>
        <button class="homepage-tab-btn" onclick="switchTab(event, 'tab-modules')"><?php echo __('homepage_tab_modules'); ?></button>
        <button class="homepage-tab-btn" onclick="switchTab(event, 'tab-stats')"><?php echo __('homepage_tab_stats'); ?></button>
        <button class="homepage-tab-btn" onclick="switchTab(event, 'tab-areas')"><?php echo __('homepage_tab_areas'); ?></button>
        <button class="homepage-tab-btn" onclick="switchTab(event, 'tab-cta')"><?php echo __('homepage_tab_cta'); ?></button>
    </div>

    <form action="homepage.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <!-- TAB: VISIBILITY & ORDER -->
        <div id="tab-visibility" class="homepage-tab-content active">
            <div class="admin-table-card">
                <div style="padding: 16px 20px; font-weight: bold; border-bottom: 1px solid var(--border-color);">
                    <?php echo get_current_lang() === 'ar' ? 'عرض وترتيب أقسام الصفحة الرئيسية' : 'Set visibility and sort order weights'; ?>
                </div>
                
                <div style="display:flex; flex-direction:column;">
                    <div class="homepage-section-row" style="font-weight:bold; background: var(--bg-body);">
                        <div><?php echo __('homepage_section_key'); ?></div>
                        <div><?php echo __('homepage_section_show'); ?></div>
                        <div><?php echo __('homepage_section_order'); ?></div>
                    </div>
                    
                    <?php foreach ($sections_list as $key => $sec): 
                        $show_val = get_setting('homepage_show_' . $key, '1');
                        $order_val = get_setting('homepage_order_' . $key, (string)$sec['default_order']);
                    ?>
                        <div class="homepage-section-row">
                            <div>
                                <strong style="color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? $sec['label_ar'] : $sec['label_en']); ?></strong>
                                <br>
                                <small style="color:var(--text-muted); font-family: monospace; font-size:11px;"><?php echo $key; ?></small>
                                <?php if (!empty($sec['partially_manageable'])): ?>
                                    <span style="display:inline-block; font-size:10px; background:var(--accent-light); padding:1px 6px; border-radius:3px; margin-inline-start:8px;">جزئي / Partial</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="homepage_show_<?php echo $key; ?>" value="1" <?php echo ($show_val === '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div>
                                <input type="number" name="homepage_order_<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($order_val); ?>" min="0" max="999" required style="width: 80px; height: 38px !important; padding: 6px 10px !important;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TAB: HERO -->
        <div id="tab-hero" class="homepage-tab-content">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="margin-bottom:20px;"><?php echo get_current_lang() === 'ar' ? 'إعدادات قسم الهيرو الترحيبي' : 'Hero Section CMS'; ?></h3>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'العنوان الترحيبي (بالعربية)' : 'Hero Title (Arabic)'; ?></label>
                        <input type="text" name="hero_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('hero_title_ar', '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'العنوان الترحيبي (بالإنجليزية)' : 'Hero Title (English)'; ?></label>
                        <input type="text" name="hero_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('hero_title_en', '')); ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'الوصف الفرعي (بالعربية)' : 'Hero Subtitle (Arabic)'; ?></label>
                        <textarea name="hero_subtitle_ar" class="form-control" rows="3" required><?php echo htmlspecialchars(get_setting('hero_subtitle_ar', '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'الوصف الفرعي (بالإنجليزية)' : 'Hero Subtitle (English)'; ?></label>
                        <textarea name="hero_subtitle_en" class="form-control" rows="3" required><?php echo htmlspecialchars(get_setting('hero_subtitle_en', '')); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'نص زر الإجراء الرئيسي (بالعربية)' : 'Primary CTA Label (Arabic)'; ?></label>
                        <input type="text" name="hero_cta_primary_label_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('hero_cta_primary_label_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'نص زر الإجراء الرئيسي (بالإنجليزية)' : 'Primary CTA Label (English)'; ?></label>
                        <input type="text" name="hero_cta_primary_label_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('hero_cta_primary_label_en', '')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'وجهة الزر الرئيسي' : 'Primary CTA Destination'; ?></label>
                        <select name="hero_cta_primary_action" class="form-control">
                            <option value="products.php" <?php echo (get_setting('hero_cta_primary_action') === 'products.php') ? 'selected' : ''; ?>>المنتجات / Products</option>
                            <option value="whatsapp" <?php echo (get_setting('hero_cta_primary_action') === 'whatsapp') ? 'selected' : ''; ?>>واتساب / WhatsApp</option>
                            <option value="services.php" <?php echo (get_setting('hero_cta_primary_action') === 'services.php') ? 'selected' : ''; ?>>الخدمات / Services</option>
                            <option value="projects.php" <?php echo (get_setting('hero_cta_primary_action') === 'projects.php') ? 'selected' : ''; ?>>المشاريع / Projects</option>
                            <option value="contact.php" <?php echo (get_setting('hero_cta_primary_action') === 'contact.php') ? 'selected' : ''; ?>>الاتصال / Contact</option>
                            <option value="about.php" <?php echo (get_setting('hero_cta_primary_action') === 'about.php') ? 'selected' : ''; ?>>من نحن / About</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'وجهة الزر الثانوي' : 'Secondary CTA Destination'; ?></label>
                        <select name="hero_cta_secondary_action" class="form-control">
                            <option value="whatsapp" <?php echo (get_setting('hero_cta_secondary_action', 'whatsapp') === 'whatsapp') ? 'selected' : ''; ?>>واتساب / WhatsApp</option>
                            <option value="products.php" <?php echo (get_setting('hero_cta_secondary_action') === 'products.php') ? 'selected' : ''; ?>>المنتجات / Products</option>
                            <option value="services.php" <?php echo (get_setting('hero_cta_secondary_action') === 'services.php') ? 'selected' : ''; ?>>الخدمات / Services</option>
                            <option value="projects.php" <?php echo (get_setting('hero_cta_secondary_action') === 'projects.php') ? 'selected' : ''; ?>>المشاريع / Projects</option>
                            <option value="contact.php" <?php echo (get_setting('hero_cta_secondary_action') === 'contact.php') ? 'selected' : ''; ?>>الاتصال / Contact</option>
                            <option value="about.php" <?php echo (get_setting('hero_cta_secondary_action') === 'about.php') ? 'selected' : ''; ?>>من نحن / About</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'نص زر الإجراء الثانوي (بالعربية)' : 'Secondary CTA Label (Arabic)'; ?></label>
                        <input type="text" name="hero_cta_secondary_label_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('hero_cta_secondary_label_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'نص زر الإجراء الثانوي (بالإنجليزية)' : 'Secondary CTA Label (English)'; ?></label>
                        <input type="text" name="hero_cta_secondary_label_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('hero_cta_secondary_label_en', '')); ?>">
                    </div>
                    
                    <!-- Image Selection Group -->
                    <div class="homepage-image-selector-group" style="grid-column: span 2;">
                        <label style="font-weight:bold;"><?php echo get_current_lang() === 'ar' ? 'صورة الهيرو الرئيسية' : 'Hero Main Image'; ?></label>
                        
                        <?php 
                        $curr_hero = get_setting('hero_image', 'assets/images/shelf1.png');
                        if (!empty($curr_hero)): ?>
                            <div style="margin-bottom:10px;">
                                <img src="../<?php echo htmlspecialchars($curr_hero); ?>" style="max-height: 120px; border-radius: var(--radius-sm); border:1px solid var(--border-color);">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label style="font-size:12px;"><?php echo __('homepage_direct_upload'); ?></label>
                            <input type="file" name="hero_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding: 5px;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;"><?php echo __('homepage_select_media'); ?></label>
                            <select name="hero_image_media_id" class="form-control">
                                <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                                <?php 
                                // Ensure currently selected is preserved and displayed even if old
                                $found_current = false;
                                foreach ($media_list as $m) {
                                    $sel = ($curr_hero === $m['file_path']) ? 'selected' : '';
                                    if ($sel) $found_current = true;
                                    echo '<option value="' . $m['id'] . '" ' . $sel . '>' . htmlspecialchars($m['folder'] . ' / ' . $m['filename']) . '</option>';
                                }
                                // If not found in the latest 100, fetch it specifically
                                if (!$found_current && !empty($curr_hero)) {
                                    try {
                                        $f_stmt = $pdo->prepare("SELECT id, filename, folder FROM media_library WHERE file_path = :path");
                                        $f_stmt->execute([':path' => $curr_hero]);
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
                </div>
            </div>
        </div>

        <!-- TAB: HEADINGS -->
        <div id="tab-headings" class="homepage-tab-content">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="margin-bottom:20px;"><?php echo get_current_lang() === 'ar' ? 'العناوين والنصوص التعريفية للأقسام' : 'Section Titles & Subtitles'; ?></h3>
                
                <div class="homepage-alert-note">
                    <i data-lucide="info" style="width:16px; height:16px; display:inline-block; vertical-align:middle; margin-inline-end:6px;"></i>
                    <span style="display:inline-block; vertical-align:middle;"><?php echo __('homepage_partially_manageable_note'); ?></span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <!-- Services Preview -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'معاينة الخدمات' : 'Services Preview'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_services_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_services_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_services_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_services_title_en', '')); ?>"></div>
                            <div class="form-group" style="grid-column: span 2;"><label>الوصف الفرعي (Ar)</label><input type="text" name="homepage_services_subtitle_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_services_subtitle_ar', '')); ?>"></div>
                            <div class="form-group" style="grid-column: span 2;"><label>الوصف الفرعي (En)</label><input type="text" name="homepage_services_subtitle_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_services_subtitle_en', '')); ?>"></div>
                            <div class="form-group"><label>زر الذهاب للخدمات (Ar)</label><input type="text" name="homepage_services_cta_label_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_services_cta_label_ar', '')); ?>"></div>
                            <div class="form-group"><label>زر الذهاب للخدمات (En)</label><input type="text" name="homepage_services_cta_label_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_services_cta_label_en', '')); ?>"></div>
                        </div>
                    </div>

                    <!-- Service Areas -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'مجالات وتطبيقات الخدمة' : 'Service Areas / Applications'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_areas_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_areas_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_areas_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_areas_title_en', '')); ?>"></div>
                            <div class="form-group" style="grid-column: span 2;"><label>الوصف الفرعي (Ar)</label><input type="text" name="homepage_areas_subtitle_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_areas_subtitle_ar', '')); ?>"></div>
                            <div class="form-group" style="grid-column: span 2;"><label>الوصف الفرعي (En)</label><input type="text" name="homepage_areas_subtitle_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_areas_subtitle_en', '')); ?>"></div>
                        </div>
                    </div>

                    <!-- Featured Products -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'قسم المنتجات المعروضة' : 'Featured Products Section'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_products_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_products_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_products_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_products_title_en', '')); ?>"></div>
                        </div>
                    </div>

                    <!-- Before & After -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'قبل وبعد التركيب' : 'Before & After Section'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_before_after_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_before_after_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_before_after_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_before_after_title_en', '')); ?>"></div>
                        </div>
                    </div>

                    <!-- Featured Projects -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'قسم المشاريع المميزة' : 'Featured Projects Section'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_projects_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_projects_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_projects_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_projects_title_en', '')); ?>"></div>
                            <div class="form-group" style="grid-column: span 2;"><label>الوصف الفرعي (Ar)</label><input type="text" name="homepage_projects_subtitle_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_projects_subtitle_ar', '')); ?>"></div>
                            <div class="form-group" style="grid-column: span 2;"><label>الوصف الفرعي (En)</label><input type="text" name="homepage_projects_subtitle_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_projects_subtitle_en', '')); ?>"></div>
                        </div>
                    </div>

                    <!-- Testimonials -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'آراء وتوصيات العملاء' : 'Testimonials Section'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_testimonials_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_testimonials_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_testimonials_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_testimonials_title_en', '')); ?>"></div>
                            <div class="form-group" style="grid-column: span 2;"><label>الوصف الفرعي (Ar)</label><input type="text" name="homepage_testimonials_subtitle_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_testimonials_subtitle_ar', '')); ?>"></div>
                            <div class="form-group" style="grid-column: span 2;"><label>الوصف الفرعي (En)</label><input type="text" name="homepage_testimonials_subtitle_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_testimonials_subtitle_en', '')); ?>"></div>
                        </div>
                    </div>

                    <!-- Client Logos -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'شركاء النجاح' : 'Client Logos Section'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_clients_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_clients_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_clients_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_clients_title_en', '')); ?>"></div>
                        </div>
                    </div>

                    <!-- How We Work -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'خطوات العمل' : 'How We Work Section'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_how_we_work_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_how_we_work_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_how_we_work_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_how_we_work_title_en', '')); ?>"></div>
                        </div>
                    </div>

                    <!-- Why Choose Us -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'لماذا تختارنا' : 'Why Choose Us Section'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group"><label>العنوان (Ar)</label><input type="text" name="homepage_why_us_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_why_us_title_ar', '')); ?>"></div>
                            <div class="form-group"><label>العنوان (En)</label><input type="text" name="homepage_why_us_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_why_us_title_en', '')); ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: MODULE INTEGRATION -->
        <div id="tab-modules" class="homepage-tab-content">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="margin-bottom:20px;"><?php echo get_current_lang() === 'ar' ? 'تكامل الأقسام المعتمدة على البيانات الديناميكية' : 'Products, Projects & Testimonials Query Integration'; ?></h3>
                
                <div style="display:flex; flex-direction:column; gap:24px;">
                    <!-- Products Integration -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'قسم المنتجات المعروضة' : 'Products Integration'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>عدد المنتجات الأقصى للعرض (1-12)</label>
                                <input type="number" name="homepage_products_limit" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_products_limit', '3')); ?>" min="1" max="12" required>
                            </div>
                            <div class="form-group">
                                <label>مصدر فحص وتصفية المنتجات</label>
                                <select name="homepage_products_source" class="form-control">
                                    <option value="featured" <?php echo (get_setting('homepage_products_source') === 'featured') ? 'selected' : ''; ?>>المنتجات المميزة (is_featured = 1)</option>
                                    <option value="new" <?php echo (get_setting('homepage_products_source') === 'new') ? 'selected' : ''; ?>>المنتجات الجديدة (is_new = 1)</option>
                                    <option value="requested" <?php echo (get_setting('homepage_products_source') === 'requested') ? 'selected' : ''; ?>>الأكثر طلباً (is_most_requested = 1)</option>
                                    <option value="latest" <?php echo (get_setting('homepage_products_source') === 'latest') ? 'selected' : ''; ?>>أحدث المنتجات المضافة (آخر المرفوعات)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Projects Integration -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'قسم المشاريع المعروضة' : 'Projects Integration'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>عدد المشاريع الأقصى للعرض (1-12)</label>
                                <input type="number" name="homepage_projects_limit" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_projects_limit', '3')); ?>" min="1" max="12" required>
                            </div>
                            <div class="form-group">
                                <label>مصدر فحص وتصفية المشاريع</label>
                                <select name="homepage_projects_source" class="form-control">
                                    <option value="featured" <?php echo (get_setting('homepage_projects_source') === 'featured') ? 'selected' : ''; ?>>المشاريع المميزة (is_featured = 1)</option>
                                    <option value="latest" <?php echo (get_setting('homepage_projects_source') === 'latest') ? 'selected' : ''; ?>>أحدث المشاريع (is_latest = 1)</option>
                                    <option value="featured_latest" <?php echo (get_setting('homepage_projects_source') === 'featured_latest') ? 'selected' : ''; ?>>المميزة أولاً ثم الأحدث (Featured then Latest)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonials Integration -->
                    <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo htmlspecialchars($lang === 'ar' ? 'قسم آراء وتوصيات العملاء' : 'Testimonials Integration'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>عدد الآراء الأقصى للعرض (1-20)</label>
                                <input type="number" name="homepage_testimonials_limit" class="form-control" value="<?php echo htmlspecialchars(get_setting('homepage_testimonials_limit', '6')); ?>" min="1" max="20" required>
                            </div>
                            <div class="form-group">
                                <label>مصدر فحص وتصفية الآراء</label>
                                <select name="homepage_testimonials_source" class="form-control">
                                    <option value="featured" <?php echo (get_setting('homepage_testimonials_source') === 'featured') ? 'selected' : ''; ?>>المميزة فقط (is_featured = 1)</option>
                                    <option value="active" <?php echo (get_setting('homepage_testimonials_source') === 'active') ? 'selected' : ''; ?>>جميع الآراء النشطة (is_active = 1)</option>
                                    <option value="latest_active" <?php echo (get_setting('homepage_testimonials_source') === 'latest_active') ? 'selected' : ''; ?>>أحدث الآراء النشطة</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: STATISTICS -->
        <div id="tab-stats" class="homepage-tab-content">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="margin-bottom:20px;"><?php echo get_current_lang() === 'ar' ? 'إدارة بطاقات شريط الإحصائيات (عدد 4)' : 'Edit Business Statistics Cards (exactly 4)'; ?></h3>
                
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo get_current_lang() === 'ar' ? "البطاقة رقم {$i}" : "Card {$i}"; ?></strong>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="form-group">
                                    <label>القيمة الرقمية (الهدف)</label>
                                    <input type="text" name="homepage_stat_<?php echo $i; ?>_value" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_stat_{$i}_value", '')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>اللاحقة الرقمية (مثال: + أو %)</label>
                                    <input type="text" name="homepage_stat_<?php echo $i; ?>_suffix" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_stat_{$i}_suffix", '+')); ?>">
                                </div>
                                <div class="form-group">
                                    <label>العنوان (Ar)</label>
                                    <input type="text" name="homepage_stat_<?php echo $i; ?>_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_stat_{$i}_title_ar", '')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>العنوان (En)</label>
                                    <input type="text" name="homepage_stat_<?php echo $i; ?>_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_stat_{$i}_title_en", '')); ?>" required>
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>الوصف (Ar)</label>
                                    <input type="text" name="homepage_stat_<?php echo $i; ?>_desc_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_stat_{$i}_desc_ar", '')); ?>">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>الوصف (En)</label>
                                    <input type="text" name="homepage_stat_<?php echo $i; ?>_desc_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_stat_{$i}_desc_en", '')); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- TAB: SERVICE AREAS -->
        <div id="tab-areas" class="homepage-tab-content">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="margin-bottom:20px;"><?php echo get_current_lang() === 'ar' ? 'إدارة مجالات وتطبيقات تقديم الخدمة (عدد 6)' : 'Edit Service Area Application Cards (exactly 6)'; ?></h3>
                
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <div style="background:var(--bg-body); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <strong style="display:block; margin-bottom:12px; color:var(--text-main);"><?php echo get_current_lang() === 'ar' ? "المجال رقم {$i}" : "Area {$i}"; ?></strong>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>أيقونة لوسيد المعتمدة (Icon)</label>
                                    <select name="homepage_area_<?php echo $i; ?>_icon" class="form-control">
                                        <?php 
                                        $icons_list = ['warehouse' => 'مستودع', 'shopping-bag' => 'حقيبة تسوق', 'archive' => 'أرشيف', 'factory' => 'مصنع', 'cog' => 'ترس', 'home' => 'منزل', 'layers' => 'طبقات', 'package' => 'صندوق', 'wrench' => 'مفتاح تركيب'];
                                        $curr_icon = get_setting("homepage_area_{$i}_icon", 'warehouse');
                                        foreach ($icons_list as $key => $lbl) {
                                            $sel = ($curr_icon === $key) ? 'selected' : '';
                                            echo '<option value="' . $key . '" ' . $sel . '>' . $lbl . ' (' . $key . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>العنوان (Ar)</label>
                                    <input type="text" name="homepage_area_<?php echo $i; ?>_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_area_{$i}_title_ar", '')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>العنوان (En)</label>
                                    <input type="text" name="homepage_area_<?php echo $i; ?>_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_area_{$i}_title_en", '')); ?>" required>
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>الوصف (Ar)</label>
                                    <input type="text" name="homepage_area_<?php echo $i; ?>_desc_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_area_{$i}_desc_ar", '')); ?>">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>الوصف (En)</label>
                                    <input type="text" name="homepage_area_<?php echo $i; ?>_desc_en" class="form-control" value="<?php echo htmlspecialchars(get_setting("homepage_area_{$i}_desc_en", '')); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- TAB: CTA -->
        <div id="tab-cta" class="homepage-tab-content">
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="margin-bottom:20px;"><?php echo get_current_lang() === 'ar' ? 'إعدادات قسم الدعوة للعمل (CTA)' : 'Contextual CTA Section Settings'; ?></h3>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'العنوان الرئيسي للـ CTA (بالعربية)' : 'CTA Section Title (Arabic)'; ?></label>
                        <input type="text" name="cta_home_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('cta_home_title_ar', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo get_current_lang() === 'ar' ? 'العنوان الرئيسي للـ CTA (بالإنجليزية)' : 'CTA Section Title (English)'; ?></label>
                        <input type="text" name="cta_home_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('cta_home_title_en', '')); ?>">
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'الوصف الفرعي (بالعربية)' : 'CTA Section Description (Arabic)'; ?></label>
                        <textarea name="cta_home_desc_ar" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('cta_home_desc_ar', '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><?php echo get_current_lang() === 'ar' ? 'الوصف الفرعي (بالإنجليزية)' : 'CTA Section Description (English)'; ?></label>
                        <textarea name="cta_home_desc_en" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('cta_home_desc_en', '')); ?></textarea>
                    </div>

                    <!-- Image Selection Group -->
                    <div class="homepage-image-selector-group" style="grid-column: span 2;">
                        <label style="font-weight:bold;"><?php echo get_current_lang() === 'ar' ? 'صورة الخلفية لقسم الـ CTA' : 'CTA Section Background Image'; ?></label>
                        
                        <?php 
                        $curr_bg = get_setting('cta_home_bg_image', '');
                        if (!empty($curr_bg)): ?>
                            <div style="margin-bottom:10px;">
                                <img src="../<?php echo htmlspecialchars($curr_bg); ?>" style="max-height: 120px; border-radius: var(--radius-sm); border:1px solid var(--border-color);">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label style="font-size:12px;"><?php echo __('homepage_direct_upload'); ?></label>
                            <input type="file" name="cta_home_bg_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding: 5px;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;"><?php echo __('homepage_select_media'); ?></label>
                            <select name="cta_home_bg_image_media_id" class="form-control">
                                <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر من مكتبة الوسائط --' : '-- Select from Media Library --'; ?></option>
                                <?php 
                                // Ensure currently selected is preserved and displayed even if old
                                $found_current_bg = false;
                                foreach ($media_list as $m) {
                                    $sel = ($curr_bg === $m['file_path']) ? 'selected' : '';
                                    if ($sel) $found_current_bg = true;
                                    echo '<option value="' . $m['id'] . '" ' . $sel . '>' . htmlspecialchars($m['folder'] . ' / ' . $m['filename']) . '</option>';
                                }
                                // If not found in the latest 100, fetch it specifically
                                if (!$found_current_bg && !empty($curr_bg)) {
                                    try {
                                        $f_stmt = $pdo->prepare("SELECT id, filename, folder FROM media_library WHERE file_path = :path");
                                        $f_stmt->execute([':path' => $curr_bg]);
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
                </div>
            </div>
        </div>

        <div style="margin-top: 30px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="height: 46px; padding: 0 30px; font-weight: bold;">
                <i data-lucide="save" style="margin-inline-end: 8px;"></i>
                <span><?php echo get_current_lang() === 'ar' ? 'حفظ إعدادات الصفحة الرئيسية' : 'Save Homepage Settings'; ?></span>
            </button>
        </div>
    </form>
</main>

<script>
    // Setup conditional required inputs handling
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form');
        if (!form) return;
        
        // Scan all inputs with 'required' and mark them
        const requiredElements = form.querySelectorAll('[required]');
        requiredElements.forEach(el => {
            el.setAttribute('data-required', 'true');
        });

        // Initial sync of required attributes based on active tab
        syncRequiredAttributes();
    });

    function syncRequiredAttributes() {
        const panels = document.querySelectorAll('.homepage-tab-content');
        panels.forEach(panel => {
            const isActive = panel.classList.contains('active');
            const elements = panel.querySelectorAll('[data-required="true"]');
            elements.forEach(el => {
                if (isActive) {
                    el.setAttribute('required', 'required');
                } else {
                    el.removeAttribute('required');
                }
            });
        });
    }

    // Tab switching controller
    function switchTab(evt, tabId) {
        // Hide all tab contents
        const contents = document.getElementsByClassName('homepage-tab-content');
        for (let i = 0; i < contents.length; i++) {
            contents[i].classList.remove('active');
        }

        // Deactivate all buttons
        const buttons = document.getElementsByClassName('homepage-tab-btn');
        for (let i = 0; i < buttons.length; i++) {
            buttons[i].classList.remove('active');
        }

        // Show active content and set active class on button
        const activePanel = document.getElementById(tabId);
        if (activePanel) {
            activePanel.classList.add('active');
        }
        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add('active');
        }

        // Synchronize required attributes for visible/hidden panels
        syncRequiredAttributes();
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
