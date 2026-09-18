<?php
// admin/service-form.php
// Add and Edit Service form with validations, media upload/selector integration, and slug generation.

require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';

$success_message = '';
$error_message = '';
$field_errors = [];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;

$service = null;
if ($is_edit) {
    $service = get_service_by_id($pdo, $id);
    if (!$service) {
        set_flash('error', get_current_lang() === 'ar' ? 'الخدمة غير موجودة.' : 'Service not found.');
        header("Location: services.php");
        exit;
    }
}

// Fetch all available media library files for the selector
$media_files = [];
try {
    $media_stmt = $pdo->query("SELECT id, file_path, filename FROM media_library ORDER BY id DESC");
    $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching media files: " . $e->getMessage());
}

$allowed_icons = get_allowed_services_icons();
$allowed_custom_actions = get_allowed_custom_actions();
$allowed_homepage_actions = get_allowed_homepage_actions();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = get_current_lang() === 'ar' ? 'رمز الحماية (CSRF Token) غير صالح.' : 'Invalid security token (CSRF).';
    } else {
        // Read inputs
        $title_ar = trim($_POST['title_ar']);
        $title_en = trim($_POST['title_en']);
        $short_desc_ar = trim($_POST['short_desc_ar']);
        $short_desc_en = trim($_POST['short_desc_en']);
        $icon = trim($_POST['icon']);
        $benefit_1_ar = trim($_POST['benefit_1_ar']);
        $benefit_1_en = trim($_POST['benefit_1_en']);
        $benefit_2_ar = trim($_POST['benefit_2_ar']);
        $benefit_2_en = trim($_POST['benefit_2_en']);
        $benefit_3_ar = trim($_POST['benefit_3_ar']);
        $benefit_3_en = trim($_POST['benefit_3_en']);
        $custom_action = trim($_POST['custom_action']);
        $custom_action_label_ar = trim($_POST['custom_action_label_ar']);
        $custom_action_label_en = trim($_POST['custom_action_label_en']);
        $homepage_action = trim($_POST['homepage_action']);
        $homepage_action_label_ar = trim($_POST['homepage_action_label_ar']);
        $homepage_action_label_en = trim($_POST['homepage_action_label_en']);
        
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

        $seo_title_ar = trim($_POST['seo_title_ar']);
        $seo_title_en = trim($_POST['seo_title_en']);
        $seo_description_ar = trim($_POST['seo_description_ar']);
        $seo_description_en = trim($_POST['seo_description_en']);

        $slug_input = isset($_POST['slug']) ? trim($_POST['slug']) : '';

        // Validation
        if (empty($title_ar)) {
            $field_errors['title_ar'] = get_current_lang() === 'ar' ? 'العنوان بالعربية مطلوب.' : 'Title in Arabic is required.';
        }
        if (empty($title_en)) {
            $field_errors['title_en'] = get_current_lang() === 'ar' ? 'العنوان بالإنجليزية مطلوب.' : 'Title in English is required.';
        }
        if (!in_array($icon, $allowed_icons)) {
            $field_errors['icon'] = get_current_lang() === 'ar' ? 'الأيقونة المحددة غير صالحة.' : 'Selected icon is invalid.';
        }
        if (!in_array($custom_action, $allowed_custom_actions)) {
            $field_errors['custom_action'] = get_current_lang() === 'ar' ? 'الإجراء الفرعي المحدد غير صالح.' : 'Selected custom action is invalid.';
        }
        if (!in_array($homepage_action, $allowed_homepage_actions)) {
            $field_errors['homepage_action'] = get_current_lang() === 'ar' ? 'إجراء الصفحة الرئيسية المحدد غير صالح.' : 'Selected homepage action is invalid.';
        }

        // Handle image selection/upload
        $image_path = $is_edit ? $service['image_path'] : null;
        $newFilesTracked = [];

        try {
            // Check direct file upload first
            if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
                // Register in Media Library
                $media_id = upload_to_media_library($pdo, $_FILES['service_image'], 'services');
                if ($media_id) {
                    $stmt_p = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id LIMIT 1");
                    $stmt_p->execute([':id' => $media_id]);
                    $path_val = $stmt_p->fetchColumn();
                    if ($path_val) {
                        $image_path = $path_val;
                    }
                }
            } elseif (!empty($_POST['selected_media_id'])) {
                // Media Library Selector takes fallback
                $sel_id = (int)$_POST['selected_media_id'];
                $stmt_p = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id LIMIT 1");
                $stmt_p->execute([':id' => $sel_id]);
                $path_val = $stmt_p->fetchColumn();
                if ($path_val) {
                    $image_path = $path_val;
                }
            } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                $image_path = null;
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $field_errors['service_image'] = $e->getMessage();
        }

        if (count($field_errors) === 0 && empty($error_message)) {
            // Determine Slug
            if ($is_edit) {
                // Do not automatically change an existing slug unless explicitly modified or was empty
                if (empty($slug_input) || $slug_input !== $service['slug']) {
                    $slug = generate_service_slug($pdo, !empty($slug_input) ? $slug_input : $title_en, $id);
                } else {
                    $slug = $service['slug'];
                }
            } else {
                $slug = generate_service_slug($pdo, !empty($slug_input) ? $slug_input : $title_en);
            }

            // Save to DB
            if ($is_edit) {
                try {
                    $stmt = $pdo->prepare("UPDATE services SET 
                        slug = :slug,
                        title_ar = :title_ar,
                        title_en = :title_en,
                        short_desc_ar = :short_desc_ar,
                        short_desc_en = :short_desc_en,
                        icon = :icon,
                        image_path = :image_path,
                        benefit_1_ar = :benefit_1_ar,
                        benefit_1_en = :benefit_1_en,
                        benefit_2_ar = :benefit_2_ar,
                        benefit_2_en = :benefit_2_en,
                        benefit_3_ar = :benefit_3_ar,
                        benefit_3_en = :benefit_3_en,
                        custom_action = :custom_action,
                        custom_action_label_ar = :custom_action_label_ar,
                        custom_action_label_en = :custom_action_label_en,
                        homepage_action = :homepage_action,
                        homepage_action_label_ar = :homepage_action_label_ar,
                        homepage_action_label_en = :homepage_action_label_en,
                        is_featured = :is_featured,
                        is_active = :is_active,
                        sort_order = :sort_order,
                        seo_title_ar = :seo_title_ar,
                        seo_title_en = :seo_title_en,
                        seo_description_ar = :seo_description_ar,
                        seo_description_en = :seo_description_en,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id");

                    $stmt->execute([
                        ':slug' => $slug,
                        ':title_ar' => $title_ar,
                        ':title_en' => $title_en,
                        ':short_desc_ar' => $short_desc_ar,
                        ':short_desc_en' => $short_desc_en,
                        ':icon' => $icon,
                        ':image_path' => $image_path,
                        ':benefit_1_ar' => $benefit_1_ar,
                        ':benefit_1_en' => $benefit_1_en,
                        ':benefit_2_ar' => $benefit_2_ar,
                        ':benefit_2_en' => $benefit_2_en,
                        ':benefit_3_ar' => $benefit_3_ar,
                        ':benefit_3_en' => $benefit_3_en,
                        ':custom_action' => $custom_action,
                        ':custom_action_label_ar' => $custom_action_label_ar,
                        ':custom_action_label_en' => $custom_action_label_en,
                        ':homepage_action' => $homepage_action,
                        ':homepage_action_label_ar' => $homepage_action_label_ar,
                        ':homepage_action_label_en' => $homepage_action_label_en,
                        ':is_featured' => $is_featured,
                        ':is_active' => $is_active,
                        ':sort_order' => $sort_order,
                        ':seo_title_ar' => $seo_title_ar,
                        ':seo_title_en' => $seo_title_en,
                        ':seo_description_ar' => $seo_description_ar,
                        ':seo_description_en' => $seo_description_en,
                        ':id' => $id
                    ]);

                    set_flash('success', get_current_lang() === 'ar' ? 'تم تحديث الخدمة بنجاح.' : 'Service updated successfully.');
                    header("Location: services.php");
                    exit;
                } catch (PDOException $e) {
                    error_log("Error updating service: " . $e->getMessage());
                    $error_message = get_current_lang() === 'ar' ? 'فشل تحديث الخدمة في قاعدة البيانات.' : 'Database operation failed.';
                }
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO services (
                        slug, title_ar, title_en, short_desc_ar, short_desc_en, icon, image_path,
                        benefit_1_ar, benefit_1_en, benefit_2_ar, benefit_2_en, benefit_3_ar, benefit_3_en,
                        custom_action, custom_action_label_ar, custom_action_label_en,
                        homepage_action, homepage_action_label_ar, homepage_action_label_en,
                        is_featured, is_active, sort_order,
                        seo_title_ar, seo_title_en, seo_description_ar, seo_description_en
                    ) VALUES (
                        :slug, :title_ar, :title_en, :short_desc_ar, :short_desc_en, :icon, :image_path,
                        :benefit_1_ar, :benefit_1_en, :benefit_2_ar, :benefit_2_en, :benefit_3_ar, :benefit_3_en,
                        :custom_action, :custom_action_label_ar, :custom_action_label_en,
                        :homepage_action, :homepage_action_label_ar, :homepage_action_label_en,
                        :is_featured, :is_active, :sort_order,
                        :seo_title_ar, :seo_title_en, :seo_description_ar, :seo_description_en
                    )");

                    $stmt->execute([
                        ':slug' => $slug,
                        ':title_ar' => $title_ar,
                        ':title_en' => $title_en,
                        ':short_desc_ar' => $short_desc_ar,
                        ':short_desc_en' => $short_desc_en,
                        ':icon' => $icon,
                        ':image_path' => $image_path,
                        ':benefit_1_ar' => $benefit_1_ar,
                        ':benefit_1_en' => $benefit_1_en,
                        ':benefit_2_ar' => $benefit_2_ar,
                        ':benefit_2_en' => $benefit_2_en,
                        ':benefit_3_ar' => $benefit_3_ar,
                        ':benefit_3_en' => $benefit_3_en,
                        ':custom_action' => $custom_action,
                        ':custom_action_label_ar' => $custom_action_label_ar,
                        ':custom_action_label_en' => $custom_action_label_en,
                        ':homepage_action' => $homepage_action,
                        ':homepage_action_label_ar' => $homepage_action_label_ar,
                        ':homepage_action_label_en' => $homepage_action_label_en,
                        ':is_featured' => $is_featured,
                        ':is_active' => $is_active,
                        ':sort_order' => $sort_order,
                        ':seo_title_ar' => $seo_title_ar,
                        ':seo_title_en' => $seo_title_en,
                        ':seo_description_ar' => $seo_description_ar,
                        ':seo_description_en' => $seo_description_en
                    ]);

                    set_flash('success', get_current_lang() === 'ar' ? 'تم إنشاء الخدمة بنجاح.' : 'Service created successfully.');
                    header("Location: services.php");
                    exit;
                } catch (PDOException $e) {
                    error_log("Error creating service: " . $e->getMessage());
                    $error_message = get_current_lang() === 'ar' ? 'فشل إنشاء الخدمة في قاعدة البيانات.' : 'Database operation failed.';
                }
            }
        }
    }
}

$lang = get_current_lang();
$page_title = $is_edit 
    ? ($lang === 'ar' ? 'تعديل الخدمة: ' . $service['title_ar'] : 'Edit Service: ' . $service['title_en']) 
    : ($lang === 'ar' ? 'إضافة خدمة جديدة' : 'Add New Service');

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <header class="admin-header" style="padding-bottom: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                <a href="index.php" style="color: var(--text-muted); text-decoration: none;"><?php echo htmlspecialchars(__('brand_name')); ?></a>
                <span>/</span>
                <a href="services.php" style="color: var(--text-muted); text-decoration: none;"><?php echo $lang === 'ar' ? 'خدمات الشركة' : 'Services'; ?></a>
                <span>/</span>
                <span><?php echo htmlspecialchars($page_title); ?></span>
            </div>
            <h1 style="font-size: 22px; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>

        <a href="services.php" class="btn btn-secondary btn-sm" style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;">
            <i data-lucide="arrow-right" class="rtl-only" style="width:15px;"></i>
            <i data-lucide="arrow-left" class="ltr-only" style="width:15px;"></i>
            <span><?php echo $lang === 'ar' ? 'العودة للخدمات' : 'Back to Services'; ?></span>
        </a>
    </header>

    <!-- Message Alerts -->
    <?php if (!empty($error_message)): ?>
        <div class="alert-danger" style="background-color: var(--danger-light); color: var(--danger); padding: 14px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="alert-triangle" style="width: 18px;"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Service Form -->
    <form method="POST" action="service-form.php<?php echo $is_edit ? '?id=' . $id : ''; ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="save_service" value="1">

        <!-- Section 1: Basic Information -->
        <div class="form-section-card">
            <h3>
                <i data-lucide="wrench"></i>
                <span><?php echo $lang === 'ar' ? '1. المعلومات الأساسية للخدمة' : '1. Basic Information'; ?></span>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div>
                    <label for="title_ar" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الخدمة (عربي) *' : 'Service Title (Arabic) *'; ?></label>
                    <input type="text" name="title_ar" id="title_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(isset($_POST['title_ar']) ? $_POST['title_ar'] : ($is_edit ? $service['title_ar'] : '')); ?>" required style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    <?php if (isset($field_errors['title_ar'])): ?>
                        <span style="color: var(--admin-danger); font-size: 12px; font-weight: 600; display: block; margin-top: 4px;"><?php echo htmlspecialchars($field_errors['title_ar']); ?></span>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="title_en" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الخدمة (إنجليزي) *' : 'Service Title (English) *'; ?></label>
                    <input type="text" name="title_en" id="title_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(isset($_POST['title_en']) ? $_POST['title_en'] : ($is_edit ? $service['title_en'] : '')); ?>" required style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                    <?php if (isset($field_errors['title_en'])): ?>
                        <span style="color: var(--admin-danger); font-size: 12px; font-weight: 600; display: block; margin-top: 4px;"><?php echo htmlspecialchars($field_errors['title_en']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                <div>
                    <label for="slug" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'الـ Slug (اتركه فارغاً للتوليد التلقائي)' : 'Slug (auto-generated if empty)'; ?></label>
                    <input type="text" name="slug" id="slug" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(isset($_POST['slug']) ? $_POST['slug'] : ($is_edit ? $service['slug'] : '')); ?>" style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>

                <div>
                    <label for="icon" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'الأيقونة (Lucide Icon) *' : 'Icon (Lucide Icon) *'; ?></label>
                    <select name="icon" id="icon" class="form-control" style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                        <?php foreach ($allowed_icons as $ico): ?>
                            <option value="<?php echo $ico; ?>" <?php echo (isset($_POST['icon']) && $_POST['icon'] === $ico) || ($is_edit && $service['icon'] === $ico) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ico); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($field_errors['icon'])): ?>
                        <span style="color: var(--admin-danger); font-size: 12px; font-weight: 600; display: block; margin-top: 4px;"><?php echo htmlspecialchars($field_errors['icon']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section 2: Descriptions & Benefits -->
        <div class="form-section-card">
            <h3>
                <i data-lucide="file-text"></i>
                <span><?php echo $lang === 'ar' ? '2. الوصف والمميزات الرئيسية' : '2. Description & Benefits'; ?></span>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
                <div>
                    <label for="short_desc_ar" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'وصف قصير (عربي)' : 'Short Description (Arabic)'; ?></label>
                    <textarea name="short_desc_ar" id="short_desc_ar" class="form-control" dir="rtl" rows="3" style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;"><?php echo htmlspecialchars(isset($_POST['short_desc_ar']) ? $_POST['short_desc_ar'] : ($is_edit ? $service['short_desc_ar'] : '')); ?></textarea>
                </div>

                <div>
                    <label for="short_desc_en" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'وصف قصير (إنجليزي)' : 'Short Description (English)'; ?></label>
                    <textarea name="short_desc_en" id="short_desc_en" class="form-control" dir="ltr" rows="3" style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;"><?php echo htmlspecialchars(isset($_POST['short_desc_en']) ? $_POST['short_desc_en'] : ($is_edit ? $service['short_desc_en'] : '')); ?></textarea>
                </div>
            </div>

            <span style="font-size: 13px; font-weight: 700; color: var(--admin-text-main); display: block; margin-bottom: 12px;"><?php echo $lang === 'ar' ? 'مميزات الخدمة الأساسية (3 نقاط ثنائية اللغة)' : 'Service Benefits (3 Bilingual Bullet Points)'; ?></span>

            <!-- Benefit 1 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 12px;">
                <div>
                    <label for="benefit_1_ar" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الميزة الأولى (عربي)' : 'Benefit 1 (Arabic)'; ?></label>
                    <input type="text" name="benefit_1_ar" id="benefit_1_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(isset($_POST['benefit_1_ar']) ? $_POST['benefit_1_ar'] : ($is_edit ? $service['benefit_1_ar'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                </div>
                <div>
                    <label for="benefit_1_en" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الميزة الأولى (إنجليزي)' : 'Benefit 1 (English)'; ?></label>
                    <input type="text" name="benefit_1_en" id="benefit_1_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(isset($_POST['benefit_1_en']) ? $_POST['benefit_1_en'] : ($is_edit ? $service['benefit_1_en'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                </div>
            </div>

            <!-- Benefit 2 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 12px;">
                <div>
                    <label for="benefit_2_ar" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الميزة الثانية (عربي)' : 'Benefit 2 (Arabic)'; ?></label>
                    <input type="text" name="benefit_2_ar" id="benefit_2_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(isset($_POST['benefit_2_ar']) ? $_POST['benefit_2_ar'] : ($is_edit ? $service['benefit_2_ar'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                </div>
                <div>
                    <label for="benefit_2_en" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الميزة الثانية (إنجليزي)' : 'Benefit 2 (English)'; ?></label>
                    <input type="text" name="benefit_2_en" id="benefit_2_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(isset($_POST['benefit_2_en']) ? $_POST['benefit_2_en'] : ($is_edit ? $service['benefit_2_en'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                </div>
            </div>

            <!-- Benefit 3 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                <div>
                    <label for="benefit_3_ar" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الميزة الثالثة (عربي)' : 'Benefit 3 (Arabic)'; ?></label>
                    <input type="text" name="benefit_3_ar" id="benefit_3_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(isset($_POST['benefit_3_ar']) ? $_POST['benefit_3_ar'] : ($is_edit ? $service['benefit_3_ar'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                </div>
                <div>
                    <label for="benefit_3_en" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الميزة الثالثة (إنجليزي)' : 'Benefit 3 (English)'; ?></label>
                    <input type="text" name="benefit_3_en" id="benefit_3_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(isset($_POST['benefit_3_en']) ? $_POST['benefit_3_en'] : ($is_edit ? $service['benefit_3_en'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                </div>
            </div>
        </div>

        <!-- Section 3: Image Upload & Media Experience -->
        <div class="form-section-card">
            <h3>
                <i data-lucide="image"></i>
                <span><?php echo $lang === 'ar' ? '3. صورة الخدمة وإدارة الوسائط' : '3. Service Image & Media'; ?></span>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                <div>
                    <label for="service_image" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'رفع صورة جديدة مباشرة' : 'Upload New Image Directly'; ?></label>
                    <input type="file" name="service_image" id="service_image" accept="image/jpeg,image/png,image/webp" class="form-control" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                    
                    <?php if ($is_edit && !empty($service['image_path'])): ?>
                        <div style="margin-top: 12px; display: flex; align-items: center; gap: 12px; background: var(--admin-bg-subtle); padding: 10px; border-radius: var(--radius-md); border: 1px solid var(--admin-border-color);">
                            <img src="../<?php echo htmlspecialchars($service['image_path']); ?>" alt="Current Image" loading="lazy" style="width: 80px; height: 50px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--admin-border-color);">
                            <label style="font-size: 13px; font-weight:600; color: var(--admin-danger); cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="remove_image" value="1">
                                <span><?php echo $lang === 'ar' ? 'إزالة الصورة الحالية' : 'Remove current image'; ?></span>
                            </label>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="selected_media_id" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'أو اختر من مكتبة الوسائط' : 'Or Select from Media Library'; ?></label>
                    <select name="selected_media_id" id="selected_media_id" class="form-control" style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                        <option value=""><?php echo $lang === 'ar' ? '-- اختر من المكتبة --' : '-- Select from Library --'; ?></option>
                        <?php foreach ($media_files as $media): ?>
                            <option value="<?php echo $media['id']; ?>" <?php echo ($is_edit && $service['image_path'] === $media['file_path']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($media['filename']); ?> (<?php echo htmlspecialchars($media['file_path']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section 4: Routing Actions & Publishing -->
        <div class="form-section-card">
            <h3>
                <i data-lucide="sliders"></i>
                <span><?php echo $lang === 'ar' ? '4. الإجراءات والتوجيه ونشر الخدمة' : '4. Routing & Publishing'; ?></span>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div>
                    <label for="custom_action" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'إجراء صفحة الخدمات' : 'Services Page Action'; ?></label>
                    <select name="custom_action" id="custom_action" class="form-control" style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                        <?php foreach ($allowed_custom_actions as $act): ?>
                            <option value="<?php echo $act; ?>" <?php echo (isset($_POST['custom_action']) && $_POST['custom_action'] === $act) || ($is_edit && $service['custom_action'] === $act) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($act); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="homepage_action" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'إجراء كرت الصفحة الرئيسية' : 'Homepage Preview Action'; ?></label>
                    <select name="homepage_action" id="homepage_action" class="form-control" style="width:100%; padding:9px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                        <?php foreach ($allowed_homepage_actions as $act): ?>
                            <option value="<?php echo $act; ?>" <?php echo (isset($_POST['homepage_action']) && $_POST['homepage_action'] === $act) || ($is_edit && $service['homepage_action'] === $act) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($act); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
                <div>
                    <label for="custom_action_label_ar" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'عنوان زر الإجراء (عربي)' : 'Action Button Label (Arabic)'; ?></label>
                    <input type="text" name="custom_action_label_ar" id="custom_action_label_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(isset($_POST['custom_action_label_ar']) ? $_POST['custom_action_label_ar'] : ($is_edit ? $service['custom_action_label_ar'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                </div>

                <div>
                    <label for="custom_action_label_en" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'عنوان زر الإجراء (إنجليزي)' : 'Action Button Label (English)'; ?></label>
                    <input type="text" name="custom_action_label_en" id="custom_action_label_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(isset($_POST['custom_action_label_en']) ? $_POST['custom_action_label_en'] : ($is_edit ? $service['custom_action_label_en'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13px;">
                </div>
            </div>

            <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?php echo (!isset($_POST['save_service']) && ($is_edit ? $service['is_active'] : 1)) || (isset($_POST['save_service']) && isset($_POST['is_active'])) ? 'checked' : ''; ?>>
                    <span><?php echo $lang === 'ar' ? 'نشط وتظهر للجمهور' : 'Active (visible to public)'; ?></span>
                </label>

                <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="is_featured" value="1" <?php echo (!isset($_POST['save_service']) && ($is_edit ? $service['is_featured'] : 0)) || (isset($_POST['save_service']) && isset($_POST['is_featured'])) ? 'checked' : ''; ?>>
                    <span><?php echo $lang === 'ar' ? 'مميزة على الصفحة الرئيسية ⭐' : 'Featured on homepage ⭐'; ?></span>
                </label>
            </div>

            <div style="max-width: 200px;">
                <label for="sort_order" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo __('sort_order'); ?></label>
                <input type="number" name="sort_order" id="sort_order" class="form-control" min="0" max="9999" value="<?php echo htmlspecialchars(isset($_POST['sort_order']) ? $_POST['sort_order'] : ($is_edit ? $service['sort_order'] : 0)); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
            </div>
        </div>

        <!-- Section 5: SEO Metadata Settings -->
        <div class="form-section-card">
            <h3>
                <i data-lucide="search"></i>
                <span><?php echo $lang === 'ar' ? '5. إعدادات محركات البحث (SEO)' : '5. SEO Settings'; ?></span>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div>
                    <label for="seo_title_ar" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان SEO (عربي)' : 'SEO Title (Arabic)'; ?></label>
                    <input type="text" name="seo_title_ar" id="seo_title_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars(isset($_POST['seo_title_ar']) ? $_POST['seo_title_ar'] : ($is_edit ? $service['seo_title_ar'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>
                <div>
                    <label for="seo_title_en" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان SEO (إنجليزي)' : 'SEO Title (English)'; ?></label>
                    <input type="text" name="seo_title_en" id="seo_title_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars(isset($_POST['seo_title_en']) ? $_POST['seo_title_en'] : ($is_edit ? $service['seo_title_en'] : '')); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                <div>
                    <label for="seo_description_ar" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'وصف SEO (عربي)' : 'SEO Description (Arabic)'; ?></label>
                    <textarea name="seo_description_ar" id="seo_description_ar" class="form-control" dir="rtl" rows="2" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;"><?php echo htmlspecialchars(isset($_POST['seo_description_ar']) ? $_POST['seo_description_ar'] : ($is_edit ? $service['seo_description_ar'] : '')); ?></textarea>
                </div>
                <div>
                    <label for="seo_description_en" class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'وصف SEO (إنجليزي)' : 'SEO Description (English)'; ?></label>
                    <textarea name="seo_description_en" id="seo_description_en" class="form-control" dir="ltr" rows="2" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;"><?php echo htmlspecialchars(isset($_POST['seo_description_en']) ? $_POST['seo_description_en'] : ($is_edit ? $service['seo_description_en'] : '')); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sticky Save Action Bar -->
        <div class="sticky-save-bar">
            <div class="sticky-save-bar-info">
                <i data-lucide="check-circle" style="color: var(--admin-success); width: 18px;"></i>
                <span><?php echo $is_edit ? ($lang === 'ar' ? 'جاهز لتحديث بيانات الخدمة' : 'Ready to update service') : ($lang === 'ar' ? 'جاهز لحفظ الخدمة الجديدة' : 'Ready to save new service'); ?></span>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="services.php" class="btn btn-secondary btn-sm" style="color: #ffffff; border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); font-weight: 600;">
                    <span><?php echo __('cancel'); ?></span>
                </a>
                <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 700; padding: 8px 18px;">
                    <i data-lucide="save" style="width: 16px;"></i>
                    <span><?php echo $is_edit ? ($lang === 'ar' ? 'تحديث الخدمة' : 'Update Service') : ($lang === 'ar' ? 'حفظ الخدمة' : 'Save Service'); ?></span>
                </button>
            </div>
        </div>
    </form>
</main>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
