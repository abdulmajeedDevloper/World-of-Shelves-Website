<?php
// admin/project-categories.php
require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/portfolio-helper.php';

ensure_project_categories_table($pdo);

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

// Helper to generate a unique category_key (slug) from English name
function generate_category_key($name_en, $pdo) {
    $slugSource = strtolower(trim($name_en));
    $slugSource = preg_replace('/[^a-z0-9\s-]/', '', $slugSource);
    $slugSource = preg_replace('/[\s-]+/', '-', $slugSource);
    $slugSource = trim($slugSource, '-');
    
    if (empty($slugSource)) {
        $slugSource = 'cat-' . bin2hex(random_bytes(3));
    }

    $originalKey = $slugSource;
    $suffix = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM project_categories WHERE category_key = :k");
        $stmt->execute([':k' => $slugSource]);
        if ((int)$stmt->fetchColumn() === 0) {
            break;
        }
        $slugSource = $originalKey . '-' . $suffix;
        $suffix++;
    }
    return $slugSource;
}

// Handle Form Submissions (POST actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', __('csrf_invalid'));
        header('Location: project-categories.php');
        exit;
    }

    $postAction = $_POST['action'] ?? '';

    // 1. Add Category
    if ($postAction === 'add_category') {
        $name_ar = trim($_POST['name_ar'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $sort_order = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';
        $seo_title_ar = isset($_POST['seo_title_ar']) ? trim($_POST['seo_title_ar']) : '';
        $seo_title_en = isset($_POST['seo_title_en']) ? trim($_POST['seo_title_en']) : '';
        $meta_desc_ar = isset($_POST['meta_desc_ar']) ? trim($_POST['meta_desc_ar']) : '';
        $meta_desc_en = isset($_POST['meta_desc_en']) ? trim($_POST['meta_desc_en']) : '';
        $description_ar = isset($_POST['description_ar']) ? trim($_POST['description_ar']) : '';
        $description_en = isset($_POST['description_en']) ? trim($_POST['description_en']) : '';

        $slug_is_manual = !empty($slug);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = strtolower(trim($name_en));
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
            $slug = preg_replace('/[\s-]+/', '-', $slug);
            $slug = trim($slug, '-');
        }

        if ($sort_order === false || $sort_order < 0) $sort_order = 0;

        if ($name_ar === '' || $name_en === '') {
            set_flash('error', __('invalid_input'));
        } else {
            try {
                $category_key = generate_category_key($name_en, $pdo);
                
                if (empty($slug)) {
                    $slug = $category_key;
                }
                
                // Uniqueness check
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM project_categories WHERE slug = :slug");
                $stmt_check->execute([':slug' => $slug]);
                $exists = (int)$stmt_check->fetchColumn();
                
                if ($exists > 0) {
                    if ($slug_is_manual) {
                        set_flash('error', get_current_lang() === 'ar' 
                            ? "معرّف الرابط (Slug) مستخدم بالفعل في تصنيف آخر. الرجاء اختيار معرّف فريد."
                            : "The URL Slug is already in use by another category. Please choose a unique one.");
                    } else {
                        $base_slug = $slug;
                        $counter = 1;
                        while (true) {
                            $final_slug = $base_slug . '-' . $counter;
                            $stmt_check->execute([':slug' => $final_slug]);
                            if ((int)$stmt_check->fetchColumn() === 0) {
                                $slug = $final_slug;
                                break;
                            }
                            $counter++;
                        }
                        $exists = 0;
                    }
                }

                if ($exists === 0) {
                    $stmt = $pdo->prepare("INSERT INTO project_categories (category_key, name_ar, name_en, sort_order, is_active, slug, seo_title_ar, seo_title_en, meta_desc_ar, meta_desc_en, description_ar, description_en) VALUES (:k, :ar, :en, :so, :act, :slug, :seo_title_ar, :seo_title_en, :meta_desc_ar, :meta_desc_en, :description_ar, :description_en)");
                    $stmt->execute([
                        ':k' => $category_key,
                        ':ar' => $name_ar,
                        ':en' => $name_en,
                        ':so' => $sort_order,
                        ':act' => $is_active,
                        ':slug' => $slug,
                        ':seo_title_ar' => $seo_title_ar,
                        ':seo_title_en' => $seo_title_en,
                        ':meta_desc_ar' => $meta_desc_ar,
                        ':meta_desc_en' => $meta_desc_en,
                        ':description_ar' => $description_ar,
                        ':description_en' => $description_en
                    ]);
                    set_flash('success', (get_current_lang() === 'ar' ? 'تمت إضافة التصنيف بنجاح' : 'Category added successfully'));
                }
            } catch (PDOException $e) {
                error_log("Database error adding project category: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            }
        }
        header('Location: project-categories.php');
        exit;

    // 2. Edit Category
    } elseif ($postAction === 'edit_category') {
        $cat_id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $name_ar = trim($_POST['name_ar'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $sort_order = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';
        $seo_title_ar = isset($_POST['seo_title_ar']) ? trim($_POST['seo_title_ar']) : '';
        $seo_title_en = isset($_POST['seo_title_en']) ? trim($_POST['seo_title_en']) : '';
        $meta_desc_ar = isset($_POST['meta_desc_ar']) ? trim($_POST['meta_desc_ar']) : '';
        $meta_desc_en = isset($_POST['meta_desc_en']) ? trim($_POST['meta_desc_en']) : '';
        $description_ar = isset($_POST['description_ar']) ? trim($_POST['description_ar']) : '';
        $description_en = isset($_POST['description_en']) ? trim($_POST['description_en']) : '';

        $old_category = null;
        if ($cat_id > 0) {
            $stmt_old = $pdo->prepare("SELECT * FROM project_categories WHERE id = :id");
            $stmt_old->execute([':id' => $cat_id]);
            $old_category = $stmt_old->fetch(PDO::FETCH_ASSOC);
        }

        if (empty($slug) && $old_category && !empty($old_category['slug'])) {
            $slug = $old_category['slug'];
        }

        $slug_is_manual = !empty($slug);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = strtolower(trim($name_en));
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
            $slug = preg_replace('/[\s-]+/', '-', $slug);
            $slug = trim($slug, '-');
            if (empty($slug)) {
                $slug = 'project-category-' . $cat_id;
            }
        }

        if ($sort_order === false || $sort_order < 0) $sort_order = 0;

        if (!$cat_id || $cat_id <= 0 || $name_ar === '' || $name_en === '') {
            set_flash('error', __('invalid_input'));
        } else {
            try {
                // Uniqueness check
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM project_categories WHERE slug = :slug AND id != :id");
                $stmt_check->execute([':slug' => $slug, ':id' => $cat_id]);
                $exists = (int)$stmt_check->fetchColumn();

                if ($exists > 0) {
                    set_flash('error', get_current_lang() === 'ar' 
                        ? "معرّف الرابط (Slug) مستخدم بالفعل في تصنيف آخر. الرجاء اختيار معرّف فريد."
                        : "The URL Slug is already in use by another category. Please choose a unique one.");
                } else {
                    $stmt = $pdo->prepare("UPDATE project_categories SET name_ar = :ar, name_en = :en, sort_order = :so, is_active = :act, slug = :slug, seo_title_ar = :seo_title_ar, seo_title_en = :seo_title_en, meta_desc_ar = :meta_desc_ar, meta_desc_en = :meta_desc_en, description_ar = :description_ar, description_en = :description_en, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $stmt->execute([
                        ':ar' => $name_ar,
                        ':en' => $name_en,
                        ':so' => $sort_order,
                        ':act' => $is_active,
                        ':slug' => $slug,
                        ':seo_title_ar' => $seo_title_ar,
                        ':seo_title_en' => $seo_title_en,
                        ':meta_desc_ar' => $meta_desc_ar,
                        ':meta_desc_en' => $meta_desc_en,
                        ':description_ar' => $description_ar,
                        ':description_en' => $description_en,
                        ':id' => $cat_id
                    ]);
                    set_flash('success', (get_current_lang() === 'ar' ? 'تم تحديث بيانات التصنيف بنجاح' : 'Category updated successfully'));
                }
            } catch (PDOException $e) {
                error_log("Database error editing project category: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            }
        }
        header('Location: project-categories.php');
        exit;

    // 3. Toggle Active
    } elseif ($postAction === 'toggle_active') {
        $cat_id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($cat_id && $cat_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE project_categories SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $stmt->execute([':id' => $cat_id]);
                set_flash('success', __('visibility_updated_success'));
            } catch (PDOException $e) {
                error_log("Database error toggling project category active: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            }
        }
        header('Location: project-categories.php');
        exit;

    // 4. Delete Category (Safeguard check: block if projects are linked)
    } elseif ($postAction === 'delete_category') {
        $cat_id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($cat_id && $cat_id > 0) {
            try {
                // Fetch category_key first
                $keyStmt = $pdo->prepare("SELECT category_key FROM project_categories WHERE id = :id");
                $keyStmt->execute([':id' => $cat_id]);
                $cat_key = $keyStmt->fetchColumn();

                if ($cat_key) {
                    // Check linked projects by category_id foreign key or legacy category string
                    $projCheck = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE category_id = :id OR category = :k");
                    $projCheck->execute([':id' => $cat_id, ':k' => $cat_key]);
                    $linked_count = (int)$projCheck->fetchColumn();

                    if ($linked_count > 0) {
                        $msg_ar = "لا يمكن حذف هذا التصنيف لأنه مرتبط بـ {$linked_count} مشروع(اً). يمكنك إلغاء تفعيله بدلاً من ذلك أو إعادة تعيين مشاريع هذا التصنيف أولاً.";
                        $msg_en = "Cannot delete this category because {$linked_count} project(s) are assigned to it. Deactivate the category or reassign linked projects first.";
                        set_flash('error', get_current_lang() === 'ar' ? $msg_ar : $msg_en);
                    } else {
                        $delStmt = $pdo->prepare("DELETE FROM project_categories WHERE id = :id");
                        $delStmt->execute([':id' => $cat_id]);
                        set_flash('success', (get_current_lang() === 'ar' ? 'تم حذف التصنيف بنجاح' : 'Category deleted successfully'));
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error deleting project category: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            }
        }
        header('Location: project-categories.php');
        exit;
    }
}

// Fetch all categories for table
try {
    $stmt = $pdo->query("SELECT * FROM project_categories ORDER BY sort_order ASC, id ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching project categories: " . $e->getMessage());
    $categories = [];
}

// Load edit record if requested
$edit_category = null;
if ($action === 'edit' && $id > 0) {
    foreach ($categories as $c) {
        if ((int)$c['id'] === $id) {
            $edit_category = $c;
            break;
        }
    }
}

$page_title = (get_current_lang() === 'ar') ? 'إدارة تصنيفات المشاريع' : 'Manage Project Categories';
// Query assigned projects count per category for usage protection
$usage_map = [];
try {
    $usage_stmt = $pdo->query("SELECT category_id, COUNT(*) as cnt FROM projects WHERE category_id IS NOT NULL GROUP BY category_id");
    while ($r = $usage_stmt->fetch(PDO::FETCH_ASSOC)) {
        $usage_map[(int)$r['category_id']] = (int)$r['cnt'];
    }
} catch (PDOException $e) {
    error_log("Failed to fetch category usage map: " . $e->getMessage());
}

$page_title = (get_current_lang() === 'ar') ? 'إدارة تصنيفات المشاريع' : 'Manage Project Categories';
$lang = get_current_lang();
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<main class="admin-content">
    <!-- Compact Page Header -->
    <header class="admin-header" style="padding-bottom: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                <a href="index.php" style="color: var(--text-muted); text-decoration: none;"><?php echo htmlspecialchars(__('brand_name')); ?></a>
                <span>/</span>
                <a href="projects.php" style="color: var(--text-muted); text-decoration: none;"><?php echo $lang === 'ar' ? 'المشاريع' : 'Projects'; ?></a>
                <span>/</span>
                <span><?php echo htmlspecialchars($page_title); ?></span>
            </div>
            <h1 style="font-size: 22px; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>

        <a href="projects.php" class="btn btn-secondary btn-sm" style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;">
            <i data-lucide="arrow-right" class="rtl-only" style="width:15px;"></i>
            <i data-lucide="arrow-left" class="ltr-only" style="width:15px;"></i>
            <span><?php echo $lang === 'ar' ? 'العودة للمشاريع' : 'Back to Projects'; ?></span>
        </a>
    </header>

    <!-- Message Alerts -->
    <?php
    $flashError = get_flash('error');
    $flashSuccess = get_flash('success');
    if ($flashError): ?>
        <div class="alert-danger" style="background-color: var(--danger-light); color: var(--danger); padding: 14px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
        <div class="alert-success" style="background-color: var(--success-light); color: var(--success); padding: 14px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?php echo htmlspecialchars($flashSuccess); ?>
        </div>
    <?php endif; ?>

    <!-- Layout Grid: Form (Left/Top) & Table/Cards (Right/Bottom) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; align-items: start;">
        
        <!-- Add / Edit Form Card -->
        <section class="form-section-card">
            <h3>
                <i data-lucide="tag"></i>
                <span><?php echo $edit_category ? ($lang === 'ar' ? 'تعديل التصنيف' : 'Edit Category') : ($lang === 'ar' ? 'إضافة تصنيف جديد' : 'Add New Category'); ?></span>
            </h3>

            <form action="project-categories.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_category ? 'edit_category' : 'add_category'; ?>">
                <?php if ($edit_category): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$edit_category['id']; ?>">
                <?php endif; ?>

                <?php if ($edit_category): ?>
                    <div style="margin-bottom: 16px;">
                        <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'رمز التصنيف الداخلي (غير قابل للتعديل)' : 'Internal Category Key (Read-only)'; ?></label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_category['category_key']); ?>" readonly style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px; background: var(--admin-bg-subtle); cursor: not-allowed;">
                    </div>
                <?php endif; ?>

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'اسم التصنيف بالعربية *' : 'Category Name (Arabic) *'; ?></label>
                    <input type="text" name="name_ar" class="form-control" required value="<?php echo htmlspecialchars($edit_category['name_ar'] ?? ''); ?>" placeholder="مثال: أرفف المستودعات والمصانع" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'اسم التصنيف بالإنجليزية *' : 'Category Name (English) *'; ?></label>
                    <input type="text" name="name_en" class="form-control" required value="<?php echo htmlspecialchars($edit_category['name_en'] ?? ''); ?>" placeholder="e.g. Warehouses & Industrial" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'ترتيب العرض' : 'Sort Order'; ?></label>
                    <input type="number" name="sort_order" class="form-control" min="0" max="999" value="<?php echo (int)($edit_category['sort_order'] ?? 0); ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 13.5px;">
                        <input type="checkbox" name="is_active" value="1" <?php echo (!isset($edit_category) || !empty($edit_category['is_active'])) ? 'checked' : ''; ?>>
                        <span><?php echo $lang === 'ar' ? 'تفعيل التصنيف (يظهر في فلتر الموقع)' : 'Active (Visible in Public Filter)'; ?></span>
                    </label>
                </div>

                <!-- SEO & Content Fields -->
                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
                <h4 style="font-size: 13.5px; font-weight: 700; margin-bottom: 12px; color: var(--admin-text-main);"><?php echo $lang === 'ar' ? 'إعدادات تحسين محركات البحث (SEO)' : 'SEO Settings'; ?></h4>
                
                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'معرّف الرابط (Slug)' : 'URL Slug'; ?></label>
                    <input type="text" name="slug" id="slug" class="form-control" dir="ltr" value="<?php echo $edit_category ? htmlspecialchars($edit_category['slug'] ?? '') : ''; ?>" placeholder="e.g. industrial-shelves" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان SEO (بالعربية)' : 'SEO Title (Arabic)'; ?></label>
                    <input type="text" name="seo_title_ar" class="form-control" dir="rtl" value="<?php echo $edit_category ? htmlspecialchars($edit_category['seo_title_ar'] ?? '') : ''; ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان SEO (بالإنجليزية)' : 'SEO Title (English)'; ?></label>
                    <input type="text" name="seo_title_en" class="form-control" dir="ltr" value="<?php echo $edit_category ? htmlspecialchars($edit_category['seo_title_en'] ?? '') : ''; ?>" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'وصف SEO (بالعربية)' : 'Meta Description (Arabic)'; ?></label>
                    <textarea name="meta_desc_ar" class="form-control" dir="rtl" rows="2" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px; resize:vertical;"><?php echo $edit_category ? htmlspecialchars($edit_category['meta_desc_ar'] ?? '') : ''; ?></textarea>
                </div>

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'وصف SEO (بالإنجليزية)' : 'Meta Description (English)'; ?></label>
                    <textarea name="meta_desc_en" class="form-control" dir="ltr" rows="2" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px; resize:vertical;"><?php echo $edit_category ? htmlspecialchars($edit_category['meta_desc_en'] ?? '') : ''; ?></textarea>
                </div>

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'الوصف التعريفي (بالعربية)' : 'Intro Description (Arabic)'; ?></label>
                    <textarea name="description_ar" class="form-control" dir="rtl" rows="3" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px; resize:vertical;"><?php echo $edit_category ? htmlspecialchars($edit_category['description_ar'] ?? '') : ''; ?></textarea>
                </div>

                <div style="margin-bottom: 24px;">
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'الوصف التعريفي (بالإنجليزية)' : 'Intro Description (English)'; ?></label>
                    <textarea name="description_en" class="form-control" dir="ltr" rows="3" style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px; resize:vertical;"><?php echo $edit_category ? htmlspecialchars($edit_category['description_en'] ?? '') : ''; ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary btn-sm" style="flex: 1; font-weight:700; padding: 9px 14px;">
                        <i data-lucide="check" style="width:16px;"></i>
                        <span><?php echo $edit_category ? ($lang === 'ar' ? 'حفظ التعديلات' : 'Save Changes') : ($lang === 'ar' ? 'إضافة التصنيف' : 'Add Category'); ?></span>
                    </button>

                    <?php if ($edit_category): ?>
                        <a href="project-categories.php" class="btn btn-secondary btn-sm" style="padding: 9px 14px;">
                            <span><?php echo $lang === 'ar' ? 'إلغاء' : 'Cancel'; ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Categories List Table Card -->
        <section class="form-section-card">
            <h3>
                <i data-lucide="list"></i>
                <span><?php echo $lang === 'ar' ? 'قائمة التصنيفات الحالية' : 'Current Categories'; ?></span>
            </h3>

            <?php if (count($categories) === 0): ?>
                <p style="color: var(--admin-text-muted); text-align: center; padding: 30px 0; font-size: 13.5px;"><?php echo $lang === 'ar' ? 'لا توجد تصنيفات مسجلة.' : 'No categories found.'; ?></p>
            <?php else: ?>
                <!-- Desktop Data Table View -->
                <div class="products-desktop-table-card">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo $lang === 'ar' ? 'اسم التصنيف' : 'Category Name'; ?></th>
                                <th style="text-align: center;"><?php echo $lang === 'ar' ? 'المشاريع' : 'Projects'; ?></th>
                                <th style="text-align: center; width: 60px;"><?php echo $lang === 'ar' ? 'الترتيب' : 'Order'; ?></th>
                                <th style="text-align: center; width: 70px;"><?php echo $lang === 'ar' ? 'الحالة' : 'Status'; ?></th>
                                <th style="text-align: center; width: 110px;"><?php echo $lang === 'ar' ? 'إجراءات' : 'Actions'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): 
                                $cat_id = (int)$cat['id'];
                                $assigned_count = isset($usage_map[$cat_id]) ? $usage_map[$cat_id] : 0;
                            ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; flex-direction:column; gap:2px;">
                                            <strong style="font-size:13.5px; color:var(--admin-text-main);"><?php echo htmlspecialchars($cat['name_ar']); ?></strong>
                                            <span style="font-size:12px; color:var(--admin-text-muted);"><?php echo htmlspecialchars($cat['name_en']); ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="nav-badge" style="background: var(--admin-bg-subtle); color: var(--admin-text-main); font-weight: 700;">
                                            <?php echo $assigned_count; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;"><?php echo (int)$cat['sort_order']; ?></td>
                                    <td style="text-align: center;">
                                        <span class="nav-badge <?php echo $cat['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $cat['is_active'] ? ($lang === 'ar' ? 'مفعل' : 'Active') : ($lang === 'ar' ? 'معطل' : 'Inactive'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 6px; justify-content: center;">
                                            <a href="?action=edit&id=<?php echo $cat_id; ?>" class="btn btn-secondary btn-sm" title="<?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?>" style="padding: 4px 8px;">
                                                <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                            </a>
                                            <?php if ($assigned_count > 0): ?>
                                                <button type="button" class="btn btn-secondary btn-sm" disabled title="<?php echo $lang === 'ar' ? 'لا يمكن الحذف لوجود مشاريع مرتبطة' : 'Cannot delete assigned category'; ?>" style="padding: 4px 8px; opacity: 0.4; cursor: not-allowed;">
                                                    <i data-lucide="lock" style="width: 14px; height: 14px;"></i>
                                                </button>
                                            <?php else: ?>
                                                <form action="project-categories.php" method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $lang === 'ar' ? 'هل أنت متأكد من رغبتك في حذف هذا التصنيف؟' : 'Are you sure you want to delete this category?'; ?>');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="id" value="<?php echo $cat_id; ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" title="<?php echo $lang === 'ar' ? 'حذف' : 'Delete'; ?>" style="padding: 4px 8px; color: var(--admin-danger); border-color: var(--admin-danger-light);">
                                                        <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Category Cards (< 767px) -->
                <div class="products-mobile-card-list">
                    <?php foreach ($categories as $cat): 
                        $cat_id = (int)$cat['id'];
                        $assigned_count = isset($usage_map[$cat_id]) ? $usage_map[$cat_id] : 0;
                    ?>
                        <div class="product-mobile-card">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <strong style="font-size:15px; color:var(--admin-text-main); display:block;"><?php echo htmlspecialchars($cat['name_ar']); ?></strong>
                                    <span style="font-size:12px; color:var(--admin-text-muted); display:block;"><?php echo htmlspecialchars($cat['name_en']); ?></span>
                                </div>
                                <span class="nav-badge <?php echo $cat['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $cat['is_active'] ? ($lang === 'ar' ? 'مفعل' : 'Active') : ($lang === 'ar' ? 'معطل' : 'Inactive'); ?>
                                </span>
                            </div>

                            <div class="product-mobile-card-meta">
                                <div>
                                    <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'المشاريع المرتبطة:' : 'Assigned Projects:'; ?></span>
                                    <strong style="font-size: 12.5px;"><?php echo $assigned_count; ?></strong>
                                </div>
                                <div>
                                    <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الترتيب:' : 'Order:'; ?></span>
                                    <strong style="font-size: 12.5px;"><?php echo (int)$cat['sort_order']; ?></strong>
                                </div>
                            </div>

                            <div class="product-mobile-actions-row">
                                <a href="?action=edit&id=<?php echo $cat_id; ?>" class="btn btn-secondary btn-sm" style="flex: 1; justify-content: center; font-size: 13px; font-weight: 600;">
                                    <i data-lucide="edit-3" style="width: 15px;"></i>
                                    <span><?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?></span>
                                </a>

                                <?php if ($assigned_count > 0): ?>
                                    <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.4; cursor: not-allowed; padding: 8px 14px;">
                                        <i data-lucide="lock" style="width: 15px;"></i>
                                    </button>
                                <?php else: ?>
                                    <form action="project-categories.php" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('<?php echo $lang === 'ar' ? 'هل أنت متأكد من رغبتك في حذف هذا التصنيف؟' : 'Are you sure you want to delete this category?'; ?>');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="id" value="<?php echo $cat_id; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--admin-danger); border-color: var(--admin-danger-light); padding: 8px 14px;">
                                            <i data-lucide="trash-2" style="width: 15px;"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
let slugManuallyEdited = false;
const isEditMode = <?php echo ($edit_category) ? 'true' : 'false'; ?>;

document.addEventListener('DOMContentLoaded', () => {
    const nameEnInput = document.querySelector('input[name="name_en"]');
    const slugInput = document.getElementById('slug');
    
    if (slugInput) {
        slugInput.addEventListener('input', () => {
            slugManuallyEdited = true;
        });
    }

    if (nameEnInput && slugInput) {
        nameEnInput.addEventListener('input', () => {
            if (isEditMode && slugInput.value.trim() !== '') return;
            if (slugManuallyEdited) return;
            const val = nameEnInput.value.trim().toLowerCase();
            slugInput.value = val.replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        });
    }
});
</script>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
