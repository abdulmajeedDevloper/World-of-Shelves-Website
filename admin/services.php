<?php
// admin/services.php
// Admin Services list and management page with search, filters, pagination, and secure actions.

require_once dirname(__DIR__) . '/config/admin_init.php';

$success_message = get_flash('success');
$error_message = get_flash('error');

// Handle State Changes (Toggle Active, Toggle Featured, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash('error', get_current_lang() === 'ar' ? 'رمز الحماية غير صالح.' : 'Invalid security token.');
        header("Location: services.php");
        exit;
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $service_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($service_id <= 0) {
        set_flash('error', get_current_lang() === 'ar' ? 'معرف الخدمة غير صالح.' : 'Invalid service ID.');
        header("Location: services.php");
        exit;
    }

    // Verify service exists
    $service = get_service_by_id($pdo, $service_id);
    if (!$service) {
        set_flash('error', get_current_lang() === 'ar' ? 'الخدمة غير موجودة.' : 'Service not found.');
        header("Location: services.php");
        exit;
    }

    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM services WHERE id = :id");
            $stmt->execute([':id' => $service_id]);
            set_flash('success', get_current_lang() === 'ar' ? 'تم حذف الخدمة بنجاح.' : 'Service deleted successfully.');
        } catch (PDOException $e) {
            error_log("Error deleting service: " . $e->getMessage());
            set_flash('error', get_current_lang() === 'ar' ? 'فشل حذف الخدمة من قاعدة البيانات.' : 'Failed to delete service.');
        }
    } elseif ($action === 'toggle_active') {
        $new_val = $service['is_active'] ? 0 : 1;
        try {
            $stmt = $pdo->prepare("UPDATE services SET is_active = :val, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':val' => $new_val, ':id' => $service_id]);
            set_flash('success', get_current_lang() === 'ar' ? 'تم تحديث حالة تفعيل الخدمة.' : 'Service active status updated.');
        } catch (PDOException $e) {
            error_log("Error toggling service active status: " . $e->getMessage());
            set_flash('error', get_current_lang() === 'ar' ? 'فشل تحديث حالة التفعيل.' : 'Failed to update active status.');
        }
    } elseif ($action === 'toggle_featured') {
        $new_val = $service['is_featured'] ? 0 : 1;
        try {
            $stmt = $pdo->prepare("UPDATE services SET is_featured = :val, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':val' => $new_val, ':id' => $service_id]);
            set_flash('success', get_current_lang() === 'ar' ? 'تم تحديث حالة تمييز الخدمة.' : 'Service featured status updated.');
        } catch (PDOException $e) {
            error_log("Error toggling service featured status: " . $e->getMessage());
            set_flash('error', get_current_lang() === 'ar' ? 'فشل تحديث التمييز.' : 'Failed to update featured status.');
        }
    }

    header("Location: services.php");
    exit;
}

// Read parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(title_ar LIKE :search OR title_en LIKE :search OR slug LIKE :search OR short_desc_ar LIKE :search OR short_desc_en LIKE :search)";
    $params[':search'] = '%' . escape_like_wildcards($search) . '%';
}

if ($status_filter === 'active') {
    $where_clauses[] = "is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_clauses[] = "is_active = 0";
} elseif ($status_filter === 'featured') {
    $where_clauses[] = "is_active = 1 AND is_featured = 1";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total
$count_query = "SELECT COUNT(*) FROM services $where_sql";
try {
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_records = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting services: " . $e->getMessage());
    $total_records = 0;
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

// Fetch services
$select_query = "SELECT * FROM services $where_sql ORDER BY sort_order ASC, id ASC LIMIT $limit OFFSET $offset";
try {
    $stmt = $pdo->prepare($select_query);
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching services: " . $e->getMessage());
    $services = [];
}

$page_title = get_current_lang() === 'ar' ? 'إدارة الخدمات' : 'Manage Services';
// KPI statistics for summary strip
try {
    $stat_total = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    $stat_active = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active = 1")->fetchColumn();
    $stat_featured = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active = 1 AND is_featured = 1")->fetchColumn();
    $stat_inactive = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active = 0")->fetchColumn();
} catch (PDOException $e) {
    $stat_total = $stat_active = $stat_featured = $stat_inactive = 0;
}

// Helper to render service thumbnail or icon fallback
function get_service_thumb_html($service, $is_mobile = false) {
    $path = trim($service['image_path'] ?? '');
    if (!empty($path)) {
        $cleanPath = ltrim($path, '/');
        if (strpos($cleanPath, '../') === 0) {
            $cleanPath = substr($cleanPath, 3);
        }
        $localFile = dirname(__DIR__) . '/' . $cleanPath;
        if (file_exists($localFile)) {
            $style = $is_mobile ? 'width: 100%; height: 140px; object-fit: cover; border-radius: var(--radius-md) var(--radius-md) 0 0;' : 'width: 48px; height: 36px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--admin-border-color);';
            return '<img src="../' . htmlspecialchars($cleanPath) . '" alt="Service thumbnail" loading="lazy" style="' . $style . '">';
        }
    }
    // Fallback to Lucide icon container
    $icon = !empty($service['icon']) ? htmlspecialchars($service['icon']) : 'wrench';
    $containerStyle = $is_mobile ? 'width: 100%; height: 90px; background: var(--admin-bg-subtle); display: flex; align-items: center; justify-content: center; color: var(--admin-primary); border-radius: var(--radius-md) var(--radius-md) 0 0;' : 'width: 42px; height: 36px; background: var(--admin-bg-subtle); border-radius: var(--radius-md); border: 1px solid var(--admin-border-color); display: flex; align-items: center; justify-content: center; color: var(--admin-primary);';
    $iconSize = $is_mobile ? '32px' : '18px';
    return '<div style="' . $containerStyle . '"><i data-lucide="' . $icon . '" style="width:' . $iconSize . '; height:' . $iconSize . ';"></i></div>';
}

$page_title = get_current_lang() === 'ar' ? 'إدارة الخدمات' : 'Manage Services';
$lang = get_current_lang();
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <header class="admin-header" style="padding-bottom: 12px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                <a href="index.php" style="color: var(--text-muted); text-decoration: none;"><?php echo htmlspecialchars(__('brand_name')); ?></a>
                <span>/</span>
                <span><?php echo $lang === 'ar' ? 'خدمات الشركة' : 'Services'; ?></span>
            </div>
            <h1 style="font-size: 22px; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="services-settings.php" class="btn btn-secondary btn-sm" style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="settings" style="width:15px;"></i>
                <span><?php echo $lang === 'ar' ? 'إعدادات الصفحة' : 'Page Settings'; ?></span>
            </a>
            <a href="service-form.php" class="btn btn-primary btn-sm" style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i data-lucide="plus" style="width:15px;"></i>
                <span><?php echo $lang === 'ar' ? 'إضافة خدمة جديدة' : 'Add New Service'; ?></span>
            </a>
        </div>
    </header>

    <!-- Message Alerts -->
    <?php if (!empty($success_message)): ?>
        <div class="alert-success" style="background-color: var(--success-light); color: var(--success); padding: 14px; border-radius: var(--radius-md); margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="check-circle" style="width:18px;"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert-danger" style="background-color: var(--danger-light); color: var(--danger); padding: 14px; border-radius: var(--radius-md); margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="alert-triangle" style="width:18px;"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Services Summary Strip (4 KPI Summary Cards) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <?php 
        render_admin_kpi_card($lang === 'ar' ? 'إجمالي الخدمات' : 'Total Services', $stat_total, 'wrench', 'blue');
        render_admin_kpi_card($lang === 'ar' ? 'الخدمات المفعلة' : 'Active Services', $stat_active, 'check-circle', 'green');
        render_admin_kpi_card($lang === 'ar' ? 'الخدمات المميزة ⭐' : 'Featured Services ⭐', $stat_featured, 'star', 'amber');
        render_admin_kpi_card($lang === 'ar' ? 'الخدمات المعطلة' : 'Inactive Services', $stat_inactive, 'eye-off', 'gray');
        ?>
    </div>

    <!-- Adaptive Filter Controls Bar -->
    <section class="products-filter-bar">
        <form method="GET" action="services.php" class="products-filter-form">
            <div class="filter-input-search">
                <i data-lucide="search"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo $lang === 'ar' ? 'ابحث باسم الخدمة أو الـ slug...' : 'Search by service name or slug...'; ?>">
            </div>

            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'جميع الحالات' : 'All Statuses'; ?></option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'المفعلة فقط' : 'Active Only'; ?></option>
                <option value="featured" <?php echo $status_filter === 'featured' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'المميزة فقط ⭐' : 'Featured Only ⭐'; ?></option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'المعطلة فقط' : 'Inactive Only'; ?></option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 8px 14px; font-size: 13px;">
                <i data-lucide="filter" style="width:14px; height:14px;"></i>
                <span><?php echo $lang === 'ar' ? 'تطبيق الفلتر' : 'Apply Filter'; ?></span>
            </button>

            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <a href="services.php" class="filter-btn-reset">
                    <i data-lucide="rotate-ccw" style="width:14px; height:14px;"></i>
                    <span><?php echo $lang === 'ar' ? 'إعادة ضبط' : 'Reset'; ?></span>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Services Main Content -->
    <?php if (count($services) > 0): ?>
        <!-- Desktop High-Density Data Table (> 768px) -->
        <div class="products-desktop-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;"><?php echo $lang === 'ar' ? 'الصورة' : 'Icon'; ?></th>
                        <th><?php echo $lang === 'ar' ? 'عنوان الخدمة' : 'Service Title'; ?></th>
                        <th style="text-align: center; width: 80px;"><?php echo $lang === 'ar' ? 'الترتيب' : 'Order'; ?></th>
                        <th style="text-align: center; width: 90px;"><?php echo $lang === 'ar' ? 'الحالة' : 'Status'; ?></th>
                        <th style="text-align: center; width: 90px;"><?php echo $lang === 'ar' ? 'مميزة' : 'Featured'; ?></th>
                        <th style="text-align: center; width: 110px;"><?php echo $lang === 'ar' ? 'إجراءات' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $srv): 
                        $srv_id = (int)$srv['id'];
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <?php echo get_service_thumb_html($srv, false); ?>
                            </td>

                            <td>
                                <div class="project-identity-cell">
                                    <div class="project-identity-info">
                                        <a href="service-form.php?id=<?php echo $srv_id; ?>" class="project-identity-title-ar">
                                            <?php echo htmlspecialchars($srv['title_ar']); ?>
                                        </a>
                                        <span class="project-identity-title-en">
                                            <?php echo htmlspecialchars($srv['title_en']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td style="text-align: center;">
                                <span class="nav-badge" style="background: var(--admin-bg-subtle); color: var(--admin-text-main); font-weight: 700;">
                                    <?php echo (int)$srv['sort_order']; ?>
                                </span>
                            </td>

                            <td style="text-align: center;">
                                <form method="POST" action="services.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?php echo $srv_id; ?>">
                                    <button type="submit" class="nav-badge <?php echo $srv['is_active'] ? 'badge-success' : 'badge-danger'; ?>" style="border: none; cursor: pointer;">
                                        <?php echo $srv['is_active'] ? ($lang === 'ar' ? 'مفعل' : 'Active') : ($lang === 'ar' ? 'معطل' : 'Inactive'); ?>
                                    </button>
                                </form>
                            </td>

                            <td style="text-align: center;">
                                <form method="POST" action="services.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="toggle_featured">
                                    <input type="hidden" name="id" value="<?php echo $srv_id; ?>">
                                    <button type="submit" class="nav-badge <?php echo $srv['is_featured'] ? 'badge-success' : 'badge-secondary'; ?>" style="border: none; cursor: pointer; background: <?php echo $srv['is_featured'] ? 'rgba(245, 158, 11, 0.15)' : ''; ?>; color: <?php echo $srv['is_featured'] ? '#d97706' : ''; ?>;">
                                        <?php echo $srv['is_featured'] ? ($lang === 'ar' ? '⭐ مميزة' : '⭐ Featured') : ($lang === 'ar' ? 'عادية' : 'Normal'); ?>
                                    </button>
                                </form>
                            </td>

                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <a href="service-form.php?id=<?php echo $srv_id; ?>" class="btn btn-secondary btn-sm" title="<?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?>" style="padding: 5px 9px;">
                                        <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                    </a>

                                    <form method="POST" action="services.php" style="display: inline;" onsubmit="return confirmDelete(event, <?php echo $srv_id; ?>, <?php echo $srv['is_active']; ?>, <?php echo $srv['is_featured']; ?>);">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $srv_id; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="<?php echo $lang === 'ar' ? 'حذف' : 'Delete'; ?>" style="padding: 5px 9px; color: var(--admin-danger); border-color: var(--admin-danger-light);">
                                            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Touch Service Cards (< 767px) -->
        <div class="products-mobile-card-list">
            <?php foreach ($services as $srv): 
                $srv_id = (int)$srv['id'];
            ?>
                <div class="product-mobile-card">
                    <div style="margin-bottom: 10px;">
                        <?php echo get_service_thumb_html($srv, true); ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                        <div>
                            <strong style="font-size:15px; color:var(--admin-text-main); display:block; line-height: 1.3;"><?php echo htmlspecialchars($srv['title_ar']); ?></strong>
                            <span style="font-size:12.5px; color:var(--admin-text-muted); display:block; margin-top:2px;"><?php echo htmlspecialchars($srv['title_en']); ?></span>
                        </div>
                        <div style="display:flex; gap:4px; flex-direction:column; align-items:flex-end;">
                            <span class="nav-badge <?php echo $srv['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $srv['is_active'] ? ($lang === 'ar' ? 'مفعل' : 'Active') : ($lang === 'ar' ? 'معطل' : 'Inactive'); ?>
                            </span>
                            <?php if ($srv['is_featured']): ?>
                                <span class="nav-badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">
                                    <?php echo $lang === 'ar' ? '⭐ مميزة' : '⭐ Featured'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="product-mobile-card-meta">
                        <div>
                            <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الرمز (Slug):' : 'Slug:'; ?></span>
                            <strong style="font-size: 12px; word-break: break-all;"><?php echo htmlspecialchars($srv['slug']); ?></strong>
                        </div>
                        <div>
                            <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo $lang === 'ar' ? 'الترتيب:' : 'Order:'; ?></span>
                            <strong style="font-size: 12.5px;"><?php echo (int)$srv['sort_order']; ?></strong>
                        </div>
                    </div>

                    <div class="product-mobile-actions-row">
                        <form method="POST" action="services.php" style="flex: 1; margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?php echo $srv_id; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%; justify-content: center; font-size: 12.5px; font-weight: 600;">
                                <i data-lucide="<?php echo $srv['is_active'] ? 'eye-off' : 'eye'; ?>" style="width:14px;"></i>
                                <span><?php echo $srv['is_active'] ? ($lang === 'ar' ? 'تعطيل' : 'Deactivate') : ($lang === 'ar' ? 'تفعيل' : 'Activate'); ?></span>
                            </button>
                        </form>

                        <a href="service-form.php?id=<?php echo $srv_id; ?>" class="btn btn-primary btn-sm" style="flex: 1; justify-content: center; font-size: 12.5px; font-weight: 700;">
                            <i data-lucide="edit-3" style="width:14px;"></i>
                            <span><?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?></span>
                        </a>

                        <form method="POST" action="services.php" style="margin: 0;" onsubmit="return confirmDelete(event, <?php echo $srv_id; ?>, <?php echo $srv['is_active']; ?>, <?php echo $srv['is_featured']; ?>);">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $srv_id; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--admin-danger); border-color: var(--admin-danger-light); padding: 8px 14px;">
                                <i data-lucide="trash-2" style="width: 15px;"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Strip -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: var(--admin-bg-card); border: 1px solid var(--admin-border-color); border-radius: var(--radius-lg); margin-top: 20px; flex-wrap: wrap; gap: 12px;">
                <span style="font-size: 13px; color: var(--admin-text-muted);">
                    <?php echo $lang === 'ar' ? "عرض " . count($services) . " من إجمالي " . $total_records . " خدمة" : "Showing " . count($services) . " of " . $total_records . " services"; ?>
                </span>
                <div style="display: flex; gap: 6px;">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="services.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-sm <?php echo $page === $i ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 5px 11px; font-size: 13px; font-weight: 600;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Empty State -->
        <div style="background: var(--admin-bg-card); border: 1px solid var(--admin-border-color); border-radius: var(--radius-lg); padding: 48px 24px; text-align: center;">
            <i data-lucide="wrench" style="width: 48px; height: 48px; color: var(--admin-text-subtle); margin-bottom: 12px; display: inline-block;"></i>
            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--admin-text-main);"><?php echo $lang === 'ar' ? 'لم يتم العثور على خدمات مطابقة' : 'No matching services found'; ?></h3>
                <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0 0 16px 0;"><?php echo $lang === 'ar' ? 'جرب تغيير خيارات التصفية أو كلمة البحث' : 'Try adjusting your search query or filter options'; ?></p>
                <a href="services.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i data-lucide="rotate-ccw"></i>
                    <span><?php echo $lang === 'ar' ? 'إعادة ضبط التصفية' : 'Reset Filters'; ?></span>
                </a>
            <?php else: ?>
                <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--admin-text-main);"><?php echo $lang === 'ar' ? 'لم يتم إضافة أي خدمات بعد' : 'No services added yet'; ?></h3>
                <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0 0 16px 0;"><?php echo $lang === 'ar' ? 'ابدأ بإضافة أول خدمة يقدمها المصنع للعملاء' : 'Get started by creating the first service offered by the company'; ?></p>
                <a href="service-form.php" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                    <i data-lucide="plus"></i>
                    <span><?php echo $lang === 'ar' ? 'إضافة خدمة جديدة' : 'Add New Service'; ?></span>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<script>
function confirmDelete(e, id, isActive, isFeatured) {
    let warningMsg = "";
    if (isActive && isFeatured) {
        warningMsg = "<?php echo $lang === 'ar' ? 'تنبيه: هذه الخدمة نشطة ومميزة على الصفحة الرئيسية حالياً. هل أنت متأكد من رغبتك بالحذف؟' : 'Warning: This service is currently Active and Featured on the homepage. Are you sure you want to delete it?'; ?>";
    } else if (isActive) {
        warningMsg = "<?php echo $lang === 'ar' ? 'تنبيه: هذه الخدمة نشطة حالياً. هل أنت متأكد من رغبتك بالحذف؟' : 'Warning: This service is currently Active. Are you sure you want to delete it?'; ?>";
    } else if (isFeatured) {
        warningMsg = "<?php echo $lang === 'ar' ? 'تنبيه: هذه الخدمة مميزة على الصفحة الرئيسية حالياً. هل أنت متأكد من رغبتك بالحذف؟' : 'Warning: This service is currently Featured on the homepage. Are you sure you want to delete it?'; ?>";
    } else {
        warningMsg = "<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف هذه الخدمة نهائياً؟' : 'Are you sure you want to delete this service permanently?'; ?>";
    }

    if (!confirm(warningMsg)) {
        e.preventDefault();
        return false;
    }
    return true;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
