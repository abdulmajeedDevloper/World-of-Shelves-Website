<?php
// admin/projects.php - World of Shelves Portfolio CMS
require_once dirname(__DIR__) . '/config/admin_init.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

// Handle POST actions (toggles, sort order, deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = $_POST['action'];

    // 1. Toggle Flags, Sort Order Updates, Deletion
    if ($postAction === 'toggle_flag' || $postAction === 'update_sort_order' || $postAction === 'delete_project') {
        // Validate CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            set_flash('error', __('csrf_invalid'));
            header('Location: projects.php');
            exit;
        }

        // Validate ID
        $projectId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$projectId || $projectId <= 0) {
            set_flash('error', __('invalid_project_id'));
            header('Location: projects.php');
            exit;
        }

        if ($postAction === 'toggle_flag' && isset($_POST['flag'])) {
            $field = $_POST['flag'];
            $allowedFlags = ['is_featured', 'is_latest', 'is_active'];
            if (!in_array($field, $allowedFlags, true)) {
                set_flash('error', __('invalid_flag'));
                header('Location: projects.php');
                exit;
            }

            try {
                // Confirm project exists before toggling
                $stmt = $pdo->prepare("SELECT $field FROM projects WHERE id = :id");
                $stmt->execute([':id' => $projectId]);
                $current = $stmt->fetchColumn();
                
                if ($current === false) {
                    set_flash('error', __('project_not_found'));
                } else {
                    $newValue = ((int)$current) ? 0 : 1;
                    $update = $pdo->prepare("UPDATE projects SET $field = :value, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $update->execute([':value' => $newValue, ':id' => $projectId]);
                    
                    // Verify project still exists after UPDATE
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE id = :id");
                    $stmtCheck->execute([':id' => $projectId]);
                    if ((int)$stmtCheck->fetchColumn() === 0) {
                        set_flash('error', __('project_not_found'));
                    } else {
                        set_flash('success', __('visibility_updated_success'));
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error in project toggle: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            }
            header('Location: projects.php');
            exit;

        } elseif ($postAction === 'update_sort_order') {
            // Validate sort order range (0 to 9999)
            $sortValue = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);
            if ($sortValue === false || $sortValue < 0 || $sortValue > 9999) {
                set_flash('error', __('invalid_sort_order'));
                header('Location: projects.php');
                exit;
            }

            try {
                // Confirm project exists before updating
                $stmt = $pdo->prepare("SELECT sort_order FROM projects WHERE id = :id");
                $stmt->execute([':id' => $projectId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current) {
                    set_flash('error', __('project_not_found'));
                } else {
                    if ((int)$current['sort_order'] !== $sortValue) {
                        $update = $pdo->prepare("UPDATE projects SET sort_order = :value, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $update->execute([':value' => $sortValue, ':id' => $projectId]);
                    }
                    
                    // Verify project still exists after UPDATE
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE id = :id");
                    $stmtCheck->execute([':id' => $projectId]);
                    if ((int)$stmtCheck->fetchColumn() === 0) {
                        set_flash('error', __('project_not_found'));
                    } else {
                        set_flash('success', __('visibility_updated_success'));
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error in sort order update: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            }
            header('Location: projects.php');
            exit;

        } elseif ($postAction === 'delete_project') {
            $project = null;
            $filesToDelete = [];

            try {
                // 1. Confirm the project exists before starting deletion (inside protected exception handling)
                $projStmt = $pdo->prepare("SELECT id, main_image, before_image, after_image FROM projects WHERE id = :id");
                $projStmt->execute([':id' => $projectId]);
                $project = $projStmt->fetch(PDO::FETCH_ASSOC);

                if (!$project) {
                    set_flash('error', __('project_not_found'));
                    header('Location: projects.php');
                    exit;
                }

                if (!empty($project['main_image'])) $filesToDelete[] = $project['main_image'];
                if (!empty($project['before_image'])) $filesToDelete[] = $project['before_image'];
                if (!empty($project['after_image'])) $filesToDelete[] = $project['after_image'];

                // 2. Fetch gallery images (if this fails, abort deletion entirely before transaction starts)
                $galStmt = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = :project_id");
                $galStmt->execute([':project_id' => $projectId]);
                $galleryImages = $galStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($galleryImages as $gi) {
                    if (!empty($gi['image_path'])) {
                        $filesToDelete[] = $gi['image_path'];
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error pre-deletion project fetch failed: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
                header('Location: projects.php');
                exit;
            }

            try {
                // 3. Begin database transaction
                $pdo->beginTransaction();

                // 4. Explicitly delete project_images first as a cross-database safeguard
                $delGal = $pdo->prepare("DELETE FROM project_images WHERE project_id = :project_id");
                $delGal->execute([':project_id' => $projectId]);

                // 5. Delete projects row
                $delProj = $pdo->prepare("DELETE FROM projects WHERE id = :id");
                $delProj->execute([':id' => $projectId]);

                // 6. Verify Project Deletion Affected Rows
                if ($delProj->rowCount() !== 1) {
                    throw new Exception('project_not_found_on_delete');
                }

                $pdo->commit();

                // 7. Delete physical files after transaction commits successfully
                $approvedDirectory = dirname(__DIR__) . '/assets/images/projects';
                $baseRealPath = realpath($approvedDirectory);
                $cleanupFailed = false;

                if ($baseRealPath === false || !is_dir($baseRealPath)) {
                    error_log("Sprint Admin 4.2.1 Error: Approved base directory does not exist or is invalid: " . $approvedDirectory);
                    $cleanupFailed = true;
                } else {
                    $baseDir = rtrim($baseRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                    foreach ($filesToDelete as $relPath) {
                        $relPath = str_replace('\\', '/', $relPath);
                        $fullPath = dirname(__DIR__) . '/' . $relPath;
                        $resolvedPath = realpath($fullPath);
                        
                        // String prefix comparison matching base directory + trailing DIRECTORY_SEPARATOR
                        if ($resolvedPath && $baseDir && strpos($resolvedPath, $baseDir) === 0 && is_file($resolvedPath)) {
                            if (!unlink($resolvedPath)) {
                                error_log("Sprint Admin 4.2.1 Warning: Could not delete physical file: " . $relPath);
                                $cleanupFailed = true;
                            }
                        }
                    }
                }

                if ($cleanupFailed) {
                    set_flash('success', __('project_delete_success') . ' ' . __('cleanup_incomplete_warning'));
                } else {
                    set_flash('success', __('project_delete_success'));
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getMessage() === 'project_not_found_on_delete') {
                    set_flash('error', __('project_not_found'));
                } else {
                    error_log("Database transaction failed during project deletion: " . $e->getMessage());
                    set_flash('error', __('database_operation_failed'));
                }
            }
            header('Location: projects.php');
            exit;
        }
    }

    if ($postAction === 'update_projects_header_settings') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            set_flash('error', __('csrf_invalid'));
            header('Location: projects.php');
            exit;
        }

        $title_ar = trim($_POST['projects_page_title_ar'] ?? '');
        $title_en = trim($_POST['projects_page_title_en'] ?? '');
        $subtitle_ar = trim($_POST['projects_page_subtitle_ar'] ?? '');
        $subtitle_en = trim($_POST['projects_page_subtitle_en'] ?? '');

        $sett_ins = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:k, :v) ON DUPLICATE KEY UPDATE val = :v_dup");
        if ($db_driver === 'sqlite') {
            $sett_ins = $pdo->prepare("INSERT INTO settings (key_name, val) VALUES (:k, :v) ON CONFLICT(key_name) DO UPDATE SET val = excluded.val");
        }

        $settings_data = [
            'projects_page_title_ar' => $title_ar,
            'projects_page_title_en' => $title_en,
            'projects_page_subtitle_ar' => $subtitle_ar,
            'projects_page_subtitle_en' => $subtitle_en,
        ];

        foreach ($settings_data as $k => $v) {
            if ($db_driver === 'sqlite') {
                $sett_ins->execute([':k' => $k, ':v' => $v]);
            } else {
                $sett_ins->execute([':k' => $k, ':v' => $v, ':v_dup' => $v]);
            }
        }

        set_flash('success', (get_current_lang() === 'ar' ? 'تم تحديث ترويسة صفحة المشاريع بنجاح' : 'Projects page header settings updated successfully'));
        header('Location: projects.php');
        exit;
    }
}

// Capture Filter GET Parameters
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category_id']) ? filter_var($_GET['category_id'], FILTER_VALIDATE_INT) : 0;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_featured = isset($_GET['featured']) ? trim($_GET['featured']) : '';

// Build dynamic WHERE clause
$where_clauses = [];
$params = [];

if (!empty($filter_search)) {
    $where_clauses[] = "(p.title_ar LIKE :search OR p.title_en LIKE :search OR p.id = :exact_id)";
    $params[':search'] = '%' . $filter_search . '%';
    $params[':exact_id'] = is_numeric($filter_search) ? (int)$filter_search : 0;
}

if ($filter_category && $filter_category > 0) {
    $where_clauses[] = "p.category_id = :filter_category";
    $params[':filter_category'] = $filter_category;
}

if ($filter_status === 'active') {
    $where_clauses[] = "p.is_active = 1";
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = "p.is_active = 0";
}

if ($filter_featured === 'yes') {
    $where_clauses[] = "p.is_featured = 1";
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Helper for project cover thumbnail rendering
function get_project_cover_html($img_url, $is_mobile = false) {
    $class = $is_mobile ? 'project-mobile-card-cover' : 'project-cell-cover';
    if (empty($img_url)) {
        return '<div class="' . $class . '" style="display:flex; align-items:center; justify-content:center; color: var(--admin-text-subtle);"><i data-lucide="image" style="width:24px;"></i></div>';
    }
    $clean_path = ltrim($img_url, '/');
    if (strpos($clean_path, '../') === 0) {
        $clean_path = substr($clean_path, 3);
    }
    $local = dirname(__DIR__) . '/' . $clean_path;
    if (file_exists($local)) {
        return '<img src="../' . htmlspecialchars($clean_path) . '" class="' . $class . '" alt="Project cover" loading="lazy">';
    }
    return '<div class="' . $class . '" style="display:flex; align-items:center; justify-content:center; color: var(--admin-text-subtle);"><i data-lucide="image" style="width:24px;"></i></div>';
}

// Fetch filtered projects
try {
    $stmt = $pdo->prepare("SELECT p.*, pc.name_ar AS category_name_ar, pc.name_en AS category_name_en 
                          FROM projects p 
                          LEFT JOIN project_categories pc ON p.category_id = pc.id 
                          $where_sql
                          ORDER BY p.sort_order ASC, p.id DESC");
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary Statistics
    $stat_total = (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $stat_active = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE is_active = 1")->fetchColumn();
    $stat_featured = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE is_featured = 1")->fetchColumn();
    $stat_categories = (int)$pdo->query("SELECT COUNT(*) FROM project_categories")->fetchColumn();

    // Available categories list for filter
    $all_cats_stmt = $pdo->query("SELECT id, name_ar, name_en FROM project_categories ORDER BY sort_order ASC, id ASC");
    $all_categories = $all_cats_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error fetching projects list: " . $e->getMessage());
    $projects = [];
    $all_categories = [];
    $stat_total = 0;
    $stat_active = 0;
    $stat_featured = 0;
    $stat_categories = 0;
}

$page_title = __('admin_manage_projects');
$lang = get_current_lang();
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Admin Content Area -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <header class="admin-header" style="padding-bottom: 12px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                <a href="index.php" style="color: var(--text-muted); text-decoration: none;"><?php echo htmlspecialchars(__('brand_name')); ?></a>
                <span>/</span>
                <span><?php echo $lang === 'ar' ? 'معرض المشاريع' : 'Portfolio'; ?></span>
                <span>/</span>
                <span><?php echo htmlspecialchars($page_title); ?></span>
            </div>
            <h1 style="font-size: 22px; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="project-categories.php" class="btn btn-secondary btn-sm" style="font-size: 12.5px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="tag" style="width:15px; height:15px;"></i>
                <span><?php echo $lang === 'ar' ? 'إدارة التصنيفات' : 'Categories'; ?></span>
            </a>
            <a href="../projects" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 12.5px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="external-link" style="width:15px; height:15px;"></i>
                <span><?php echo $lang === 'ar' ? 'معاينة المعرض' : 'View Portfolio'; ?></span>
            </a>
            <a href="project-form.php" class="btn btn-primary btn-sm" style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i data-lucide="plus" style="width:16px; height:16px;"></i>
                <span><?php echo __('admin_add_project'); ?></span>
            </a>
        </div>
    </header>

    <!-- Message Alerts -->
    <?php
    $flashError = get_flash('error');
    $flashSuccess = get_flash('success');
    if ($flashError): ?>
        <div class="alert-danger" style="background-color: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
        <div class="alert-success" style="background-color: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?php echo htmlspecialchars($flashSuccess); ?>
        </div>
    <?php endif; ?>

    <!-- Projects Summary Strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <?php
        render_admin_kpi_card($lang === 'ar' ? 'إجمالي المشاريع' : 'Total Projects', $stat_total, 'briefcase', 'blue');
        render_admin_kpi_card($lang === 'ar' ? 'المشاريع المفعلة' : 'Active Projects', $stat_active, 'check-circle', 'green');
        render_admin_kpi_card($lang === 'ar' ? 'المشاريع المميزة' : 'Featured Projects', $stat_featured, 'star', 'amber');
        render_admin_kpi_card($lang === 'ar' ? 'تصنيفات المعرض' : 'Categories', $stat_categories, 'tag', 'blue');
        ?>
    </div>

    <!-- Projects Page Header CMS Settings Card -->
    <section class="form-section-card" style="margin-bottom: 24px;">
        <h3>
            <i data-lucide="layout"></i>
            <span><?php echo $lang === 'ar' ? 'إعدادات ترويسة صفحة معرض المشاريع' : 'Projects Page Header Settings'; ?></span>
        </h3>

        <form action="projects.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="update_projects_header_settings">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الصفحة الرئيسي (عربي)' : 'Main Page Title (Arabic)'; ?></label>
                    <input type="text" name="projects_page_title_ar" class="form-control" value="<?php echo htmlspecialchars(get_setting('projects_page_title_ar', 'معرض مشاريعنا المنفذة')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>
                <div>
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'عنوان الصفحة الرئيسي (إنجليزي)' : 'Main Page Title (English)'; ?></label>
                    <input type="text" name="projects_page_title_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('projects_page_title_en', 'Our Executed Projects Portfolio')); ?>" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'وصف الصفحة الفرعي (عربي)' : 'Page Subtitle (Arabic)'; ?></label>
                    <textarea name="projects_page_subtitle_ar" class="form-control" rows="2" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;"><?php echo htmlspecialchars(get_setting('projects_page_subtitle_ar')); ?></textarea>
                </div>
                <div>
                    <label class="form-label" style="font-weight:600; font-size:13px; margin-bottom:6px; display:block;"><?php echo $lang === 'ar' ? 'وصف الصفحة الفرعي (إنجليزي)' : 'Page Subtitle (English)'; ?></label>
                    <textarea name="projects_page_subtitle_en" class="form-control" rows="2" required style="width:100%; padding:8px 12px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md); font-size:13.5px;"><?php echo htmlspecialchars(get_setting('projects_page_subtitle_en')); ?></textarea>
                </div>
            </div>

            <div>
                <button type="submit" class="btn btn-primary btn-sm" style="padding: 7px 14px; font-size: 13px; font-weight:600;">
                    <i data-lucide="save" style="width: 15px; height: 15px;"></i>
                    <span><?php echo $lang === 'ar' ? 'حفظ إعدادات الترويسة' : 'Save Header Settings'; ?></span>
                </button>
            </div>
        </form>
    </section>

    <!-- Adaptive Filter Controls Bar -->
    <section class="products-filter-bar">
        <form method="GET" action="projects.php" class="products-filter-form">
            <div class="filter-input-search">
                <i data-lucide="search"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="<?php echo $lang === 'ar' ? 'ابحث بعنوان المشروع أو المعرف...' : 'Search by title or ID...'; ?>">
            </div>

            <select name="category_id" class="filter-select" onchange="this.form.submit()">
                <option value=""><?php echo $lang === 'ar' ? 'جميع التصنيفات' : 'All Categories'; ?></option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?php echo (int)$cat['id']; ?>" <?php echo $filter_category === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lang === 'ar' ? $cat['name_ar'] : $cat['name_en']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value=""><?php echo $lang === 'ar' ? 'جميع الحالات' : 'All Statuses'; ?></option>
                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'مفعل فقط' : 'Active Only'; ?></option>
                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'معطل فقط' : 'Inactive Only'; ?></option>
            </select>

            <select name="featured" class="filter-select" onchange="this.form.submit()">
                <option value=""><?php echo $lang === 'ar' ? 'المميزة والكل' : 'All Projects'; ?></option>
                <option value="yes" <?php echo $filter_featured === 'yes' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'المميزة فقط ⭐' : 'Featured Only ⭐'; ?></option>
            </select>

            <?php if (!empty($filter_search) || $filter_category > 0 || !empty($filter_status) || !empty($filter_featured)): ?>
                <a href="projects.php" class="filter-btn-reset">
                    <i data-lucide="rotate-ccw" style="width:14px; height:14px;"></i>
                    <span><?php echo $lang === 'ar' ? 'إعادة ضبط' : 'Reset'; ?></span>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Main Content Presentation -->
    <?php if (count($projects) > 0): ?>
        <!-- Desktop / Tablet View -->
        <div class="products-desktop-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?php echo $lang === 'ar' ? 'تفاصيل المشروع' : 'Project Identity'; ?></th>
                        <th><?php echo $lang === 'ar' ? 'التصنيف' : 'Category'; ?></th>
                        <th style="text-align: center;"><?php echo $lang === 'ar' ? 'المؤشرات' : 'Indicators'; ?></th>
                        <th style="text-align: center; width: 80px;"><?php echo __('sort_order'); ?></th>
                        <th style="text-align: center;"><?php echo __('admin_actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $proj): 
                        $cat_disp = ($lang === 'ar') ? ($proj['category_name_ar'] ?? '') : ($proj['category_name_en'] ?? '');
                        if (empty($cat_disp)) {
                            $cat_disp = get_project_category_label(!empty($proj['category_id']) ? $proj['category_id'] : $proj['category'], $lang);
                        }
                    ?>
                        <tr>
                            <td>
                                <div class="product-cell-identity">
                                    <?php echo get_project_cover_html($proj['main_image']); ?>
                                    <div class="product-cell-titles">
                                        <span class="product-cell-title-ar"><?php echo htmlspecialchars($proj['title_ar']); ?></span>
                                        <span class="product-cell-title-en"><?php echo htmlspecialchars($proj['title_en']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="nav-badge" style="background: var(--admin-bg-subtle); color: var(--admin-text-main); font-weight: 600;">
                                    <?php echo htmlspecialchars($cat_disp); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <!-- Featured -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_flag">
                                        <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                                        <input type="hidden" name="flag" value="is_featured">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_featured'); ?>" style="color: <?php echo (!empty($proj['is_featured']) && $proj['is_featured']) ? '#f59e0b' : '#cbd5e1'; ?>;">
                                            <i data-lucide="star" style="width: 17px;"></i>
                                        </button>
                                    </form>
                                    <!-- Latest -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_flag">
                                        <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                                        <input type="hidden" name="flag" value="is_latest">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_latest'); ?>" style="color: <?php echo (!empty($proj['is_latest']) && $proj['is_latest']) ? '#3b82f6' : '#cbd5e1'; ?>;">
                                            <i data-lucide="award" style="width: 17px;"></i>
                                        </button>
                                    </form>
                                    <!-- Active -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_flag">
                                        <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                                        <input type="hidden" name="flag" value="is_active">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_active'); ?>" style="color: <?php echo (!isset($proj['is_active']) || $proj['is_active']) ? '#10b981' : '#ef4444'; ?>;">
                                            <i data-lucide="<?php echo (!isset($proj['is_active']) || $proj['is_active']) ? 'eye' : 'eye-off'; ?>" style="width: 17px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_sort_order">
                                    <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="number" name="sort_order" min="0" max="9999" value="<?php echo isset($proj['sort_order']) ? intval($proj['sort_order']) : 0; ?>" style="width: 55px; text-align: center; padding: 4px; border: 1px solid var(--admin-border-color); border-radius: var(--radius-sm);" onchange="this.form.submit()">
                                </form>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                    <a href="project-form.php?id=<?php echo $proj['id']; ?>" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 5px 10px;">
                                        <i data-lucide="edit-3" style="width: 14px;"></i>
                                        <span><?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?></span>
                                    </a>
                                    <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('<?php echo __('delete_confirmation'); ?>')">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 5px 10px; color: var(--admin-danger); border-color: var(--admin-danger-light);">
                                            <i data-lucide="trash-2" style="width: 14px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Touch Management Cards (< 767px) -->
        <div class="products-mobile-card-list">
            <?php foreach ($projects as $proj): 
                $cat_disp = ($lang === 'ar') ? ($proj['category_name_ar'] ?? '') : ($proj['category_name_en'] ?? '');
                if (empty($cat_disp)) {
                    $cat_disp = get_project_category_label(!empty($proj['category_id']) ? $proj['category_id'] : $proj['category'], $lang);
                }
            ?>
                <div class="product-mobile-card">
                    <?php echo get_project_cover_html($proj['main_image'], true); ?>

                    <div style="padding-top: 4px;">
                        <span style="font-size: 16px; font-weight: 800; color: var(--admin-text-main); display: block;"><?php echo htmlspecialchars($proj['title_ar']); ?></span>
                        <span style="font-size: 12.5px; color: var(--admin-text-muted); display: block;"><?php echo htmlspecialchars($proj['title_en']); ?></span>
                    </div>

                    <div class="product-mobile-card-meta">
                        <div>
                            <span class="nav-badge" style="background: var(--admin-bg-subtle); color: var(--admin-text-main); font-weight: 600;">
                                <?php echo htmlspecialchars($cat_disp); ?>
                            </span>
                        </div>
                        <div>
                            <span class="nav-badge <?php echo (!isset($proj['is_active']) || $proj['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo (!isset($proj['is_active']) || $proj['is_active']) ? ($lang === 'ar' ? 'مفعل' : 'Active') : ($lang === 'ar' ? 'معطل' : 'Inactive'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Flags & Toggles Row -->
                    <div class="product-mobile-toggles-row">
                        <!-- Featured -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_flag">
                            <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                            <input type="hidden" name="flag" value="is_featured">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_featured'); ?>" style="color: <?php echo (!empty($proj['is_featured']) && $proj['is_featured']) ? '#f59e0b' : '#cbd5e1'; ?>;">
                                <i data-lucide="star" style="width: 20px; height: 20px;"></i>
                            </button>
                        </form>
                        <!-- Latest -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_flag">
                            <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                            <input type="hidden" name="flag" value="is_latest">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_latest'); ?>" style="color: <?php echo (!empty($proj['is_latest']) && $proj['is_latest']) ? '#3b82f6' : '#cbd5e1'; ?>;">
                                <i data-lucide="award" style="width: 20px; height: 20px;"></i>
                            </button>
                        </form>
                        <!-- Active -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_flag">
                            <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                            <input type="hidden" name="flag" value="is_active">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_active'); ?>" style="color: <?php echo (!isset($proj['is_active']) || $proj['is_active']) ? '#10b981' : '#ef4444'; ?>;">
                                <i data-lucide="<?php echo (!isset($proj['is_active']) || $proj['is_active']) ? 'eye' : 'eye-off'; ?>" style="width: 20px; height: 20px;"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Primary Actions Row -->
                    <div class="product-mobile-actions-row">
                        <a href="project-form.php?id=<?php echo $proj['id']; ?>" class="btn btn-primary btn-sm" style="flex: 1; justify-content: center; font-size: 13px; font-weight: 700;">
                            <i data-lucide="edit-3" style="width: 16px;"></i>
                            <span><?php echo $lang === 'ar' ? 'تعديل المشروع' : 'Edit Project'; ?></span>
                        </a>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('<?php echo __('delete_confirmation'); ?>')">
                            <input type="hidden" name="action" value="delete_project">
                            <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--admin-danger); border-color: var(--admin-danger-light); padding: 8px 14px;">
                                <i data-lucide="trash-2" style="width: 16px;"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div style="background: var(--admin-bg-card); border: 1px solid var(--admin-border-color); border-radius: var(--radius-lg); padding: 48px 24px; text-align: center;">
            <i data-lucide="briefcase" style="width: 48px; height: 48px; color: var(--admin-text-subtle); margin-bottom: 12px; display: inline-block;"></i>
            <?php if (!empty($filter_search) || $filter_category > 0 || !empty($filter_status) || !empty($filter_featured)): ?>
                <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--admin-text-main);"><?php echo $lang === 'ar' ? 'لم يتم العثور على مشاريع مطابقة' : 'No matching projects found'; ?></h3>
                <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0 0 16px 0;"><?php echo $lang === 'ar' ? 'جرب تغيير خيارات التصفية أو كلمة البحث' : 'Try adjusting your search query or filter options'; ?></p>
                <a href="projects.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i data-lucide="rotate-ccw"></i>
                    <span><?php echo $lang === 'ar' ? 'إعادة ضبط التصفية' : 'Reset Filters'; ?></span>
                </a>
            <?php else: ?>
                <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--admin-text-main);"><?php echo $lang === 'ar' ? 'لم يتم تسجيل أي مشاريع بعد' : 'No projects registered yet'; ?></h3>
                <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0 0 16px 0;"><?php echo $lang === 'ar' ? 'ابدأ بإضافة أول مشروع لمعرض الأعمال المنفذة' : 'Get started by adding your first project to the portfolio'; ?></p>
                <a href="project-form.php" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                    <i data-lucide="plus"></i>
                    <span><?php echo __('admin_add_project'); ?></span>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
