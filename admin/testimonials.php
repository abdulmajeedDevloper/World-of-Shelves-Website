<?php
// admin/testimonials.php
require_once dirname(__DIR__) . '/config/admin_init.php';

$error_message = '';
$success_message = '';

// Handle POST actions (toggles, sort order, deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = $_POST['action'];

    if ($postAction === 'toggle_flag' || $postAction === 'update_sort_order' || $postAction === 'delete_testimonial') {
        // Validate CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            set_flash('error', __('csrf_invalid'));
            header('Location: testimonials.php');
            exit;
        }

        // Validate ID
        $testimonialId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$testimonialId || $testimonialId <= 0) {
            set_flash('error', __('invalid_testimonial_id'));
            header('Location: testimonials.php');
            exit;
        }

        if ($postAction === 'toggle_flag' && isset($_POST['flag'])) {
            $field = $_POST['flag'];
            $allowedFlags = ['is_featured', 'is_active'];
            if (!in_array($field, $allowedFlags, true)) {
                set_flash('error', __('invalid_flag'));
                header('Location: testimonials.php');
                exit;
            }

            try {
                // Confirm existence before toggling
                $stmt = $pdo->prepare("SELECT $field FROM testimonials WHERE id = :id");
                $stmt->execute([':id' => $testimonialId]);
                $current = $stmt->fetchColumn();

                if ($current === false) {
                    set_flash('error', __('testimonial_not_found'));
                } else {
                    $newValue = ((int)$current) ? 0 : 1;
                    $update = $pdo->prepare("UPDATE testimonials SET $field = :value, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $update->execute([':value' => $newValue, ':id' => $testimonialId]);

                    // Verify it still exists
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM testimonials WHERE id = :id");
                    $stmtCheck->execute([':id' => $testimonialId]);
                    if ((int)$stmtCheck->fetchColumn() === 0) {
                        set_flash('error', __('testimonial_not_found'));
                    } else {
                        set_flash('success', __('visibility_updated_success'));
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error in testimonial toggle: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            }
            header('Location: testimonials.php');
            exit;

        } elseif ($postAction === 'update_sort_order') {
            // Validate sort order range (0 to 9999)
            $sortValue = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);
            if ($sortValue === false || $sortValue < 0 || $sortValue > 9999) {
                set_flash('error', __('invalid_sort_order'));
                header('Location: testimonials.php');
                exit;
            }

            try {
                // Confirm existence before updating
                $stmt = $pdo->prepare("SELECT sort_order FROM testimonials WHERE id = :id");
                $stmt->execute([':id' => $testimonialId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$current) {
                    set_flash('error', __('testimonial_not_found'));
                } else {
                    if ((int)$current['sort_order'] !== $sortValue) {
                        $update = $pdo->prepare("UPDATE testimonials SET sort_order = :value, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $update->execute([':value' => $sortValue, ':id' => $testimonialId]);
                    }

                    // Verify it still exists
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM testimonials WHERE id = :id");
                    $stmtCheck->execute([':id' => $testimonialId]);
                    if ((int)$stmtCheck->fetchColumn() === 0) {
                        set_flash('error', __('testimonial_not_found'));
                    } else {
                        set_flash('success', __('visibility_updated_success'));
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error in testimonial sort order update: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            }
            header('Location: testimonials.php');
            exit;

        } elseif ($postAction === 'delete_testimonial') {
            try {
                $pdo->beginTransaction();

                // Confirm testimonial exists
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM testimonials WHERE id = :id");
                $stmtCheck->execute([':id' => $testimonialId]);
                if ((int)$stmtCheck->fetchColumn() !== 1) {
                    throw new Exception('testimonial_not_found_on_delete');
                }

                $delStmt = $pdo->prepare("DELETE FROM testimonials WHERE id = :id");
                $delStmt->execute([':id' => $testimonialId]);

                if ($delStmt->rowCount() !== 1) {
                    throw new Exception('testimonial_not_found_on_delete');
                }

                $pdo->commit();
                set_flash('success', __('testimonial_deleted'));
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getMessage() === 'testimonial_not_found_on_delete') {
                    set_flash('error', __('testimonial_not_found'));
                } else {
                    error_log("Database transaction failed during testimonial deletion: " . $e->getMessage());
                    set_flash('error', __('database_operation_failed'));
                }
            }
            header('Location: testimonials.php');
            exit;
        }
    }
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Fetch all testimonials for listing
try {
    $where_clauses = [];
    $params = [];

    if ($search !== '') {
        $where_clauses[] = "(name_ar LIKE :search OR name_en LIKE :search OR company_ar LIKE :search OR company_en LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($status_filter === 'active') {
        $where_clauses[] = "is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $where_clauses[] = "is_active = 0";
    } elseif ($status_filter === 'featured') {
        $where_clauses[] = "is_featured = 1";
    }

    $sql = "SELECT * FROM testimonials";
    if (count($where_clauses) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $sql .= " ORDER BY sort_order ASC, id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count KPIs
    $total_count = (int)$pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
    $featured_count = (int)$pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_featured = 1")->fetchColumn();
    $active_count = (int)$pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_active = 1")->fetchColumn();

} catch (PDOException $e) {
    error_log("Database error fetching testimonials list: " . $e->getMessage());
    $testimonials = [];
    $total_count = 0;
    $featured_count = 0;
    $active_count = 0;
}

$page_title = __('admin_manage_testimonials');
$lang = get_current_lang();

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <?php 
    render_admin_page_header($page_title, [
        ['label' => $lang === 'ar' ? 'آراء التقييمات' : 'Testimonials', 'url' => '']
    ], 'testimonial-form.php', $lang === 'ar' ? 'إضافة تقييم جديد' : 'Add Testimonial');
    ?>

    <!-- Flash Message Alerts -->
    <?php
    $flashError = get_flash('error');
    $flashSuccess = get_flash('success');
    render_admin_alert('danger', $flashError);
    render_admin_alert('success', $flashSuccess);
    ?>

    <!-- KPI Summary Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <?php 
        render_admin_kpi_card($lang === 'ar' ? 'إجمالي التقييمات' : 'Total Testimonials', $total_count, 'message-square', 'blue');
        render_admin_kpi_card($lang === 'ar' ? 'مميز بالرئيسية' : 'Featured Reviews', $featured_count, 'star', 'amber');
        render_admin_kpi_card($lang === 'ar' ? 'نشط بالموقع' : 'Active Online', $active_count, 'eye', 'emerald');
        ?>
    </div>

    <!-- Filter & Search Toolbar -->
    <form method="GET" action="testimonials.php" class="filter-bar" style="margin-bottom: 24px;">
        <div class="search-box" style="flex: 1; max-width: 380px;">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="q" class="search-input" placeholder="<?php echo $lang === 'ar' ? 'البحث باسم العميل أو الشركة...' : 'Search client or company...'; ?>" value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <select name="status" class="filter-select" onchange="this.form.submit()" style="width: auto; min-width: 160px;">
            <option value=""><?php echo $lang === 'ar' ? 'جميع الحالات' : 'All Statuses'; ?></option>
            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'نشط فقط' : 'Active Only'; ?></option>
            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'غير نشط' : 'Inactive Only'; ?></option>
            <option value="featured" <?php echo $status_filter === 'featured' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'المميزة بالرئيسية' : 'Featured Only'; ?></option>
        </select>

        <?php if ($search !== '' || $status_filter !== ''): ?>
            <a href="testimonials.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                <span><?php echo $lang === 'ar' ? 'إعادة ضبط' : 'Reset Filters'; ?></span>
            </a>
        <?php endif; ?>
    </form>

    <?php if (count($testimonials) > 0): ?>
        <!-- Desktop Enterprise Table -->
        <div class="products-desktop-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 240px;"><?php echo __('client_name'); ?></th>
                        <th><?php echo __('company_name'); ?></th>
                        <th style="width: 130px; text-align: center;"><?php echo __('t_stars'); ?></th>
                        <th style="width: 110px; text-align: center;"><?php echo __('featured'); ?></th>
                        <th style="width: 110px; text-align: center;"><?php echo __('active'); ?></th>
                        <th style="width: 110px; text-align: center;"><?php echo __('sort_order'); ?></th>
                        <th style="width: 100px; text-align: center;"><?php echo __('admin_actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testimonials as $t): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--admin-text-main);">
                                    <?php echo htmlspecialchars($lang === 'ar' ? $t['name_ar'] : $t['name_en']); ?>
                                </div>
                                <div style="font-size: 11px; color: var(--admin-text-muted);">
                                    AR: <?php echo htmlspecialchars($t['name_ar']); ?> | EN: <?php echo htmlspecialchars($t['name_en']); ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: var(--admin-text-subtle);">
                                    <?php echo htmlspecialchars($lang === 'ar' ? ($t['company_ar'] ?: '-') : ($t['company_en'] ?: '-')); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 2px; color: #f59e0b;">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i data-lucide="star" style="width: 13px; height: 13px; fill: <?php echo ($s <= $t['stars']) ? '#f59e0b' : 'none'; ?>; stroke: #f59e0b;"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" style="margin: 0; display: inline-block;">
                                    <input type="hidden" name="action" value="toggle_flag">
                                    <input type="hidden" name="flag" value="is_featured">
                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0;">
                                        <?php 
                                        if (!empty($t['is_featured']) && $t['is_featured']) {
                                            render_admin_badge($lang === 'ar' ? 'مميز' : 'Featured', 'amber');
                                        } else {
                                            render_admin_badge($lang === 'ar' ? 'عادي' : 'Normal', 'slate');
                                        }
                                        ?>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" style="margin: 0; display: inline-block;">
                                    <input type="hidden" name="action" value="toggle_flag">
                                    <input type="hidden" name="flag" value="is_active">
                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0;">
                                        <?php 
                                        if (!isset($t['is_active']) || $t['is_active']) {
                                            render_admin_badge($lang === 'ar' ? 'نشط' : 'Active', 'emerald');
                                        } else {
                                            render_admin_badge($lang === 'ar' ? 'مخفي' : 'Hidden', 'rose');
                                        }
                                        ?>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" style="margin: 0; display: flex; justify-content: center;">
                                    <input type="hidden" name="action" value="update_sort_order">
                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="number" name="sort_order" value="<?php echo (int)($t['sort_order'] ?? 0); ?>" min="0" max="9999" style="width: 70px; text-align: center; padding: 4px 6px; font-size: 12.5px; border: 1px solid var(--admin-border-color); border-radius: var(--radius-md);" onchange="this.form.submit();">
                                </form>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <a href="testimonial-form.php?id=<?php echo $t['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 5px 9px;" title="<?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?>">
                                        <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                    </a>
                                    <form method="POST" action="testimonials.php" style="margin: 0; display: inline;" onsubmit="return confirm('<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف هذا التقييم؟' : 'Delete this testimonial?'; ?>');">
                                        <input type="hidden" name="action" value="delete_testimonial">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="padding: 5px 9px; color: var(--admin-danger);" title="<?php echo $lang === 'ar' ? 'حذف' : 'Delete'; ?>">
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

        <!-- Mobile Touch Management Cards -->
        <div class="products-mobile-card-list">
            <?php foreach ($testimonials as $t): ?>
                <div class="products-mobile-card">
                    <div class="products-mobile-card-header">
                        <div>
                            <div class="products-mobile-card-title"><?php echo htmlspecialchars($lang === 'ar' ? $t['name_ar'] : $t['name_en']); ?></div>
                            <div class="products-mobile-card-category"><?php echo htmlspecialchars($lang === 'ar' ? ($t['company_ar'] ?: '-') : ($t['company_en'] ?: '-')); ?></div>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <?php 
                            if (!empty($t['is_featured']) && $t['is_featured']) {
                                render_admin_badge($lang === 'ar' ? 'مميز' : 'Featured', 'amber');
                            }
                            if (!isset($t['is_active']) || $t['is_active']) {
                                render_admin_badge($lang === 'ar' ? 'نشط' : 'Active', 'emerald');
                            } else {
                                render_admin_badge($lang === 'ar' ? 'مخفي' : 'Hidden', 'rose');
                            }
                            ?>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-top: 1px solid var(--admin-border-color); border-bottom: 1px solid var(--admin-border-color); margin-bottom: 12px;">
                        <div style="display: flex; gap: 2px; color: #f59e0b;">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i data-lucide="star" style="width: 14px; height: 14px; fill: <?php echo ($s <= $t['stars']) ? '#f59e0b' : 'none'; ?>; stroke: #f59e0b;"></i>
                            <?php endfor; ?>
                        </div>
                        <div style="font-size: 12px; color: var(--admin-text-muted);">
                            <?php echo $lang === 'ar' ? 'الترتيب: ' : 'Order: '; ?> <strong><?php echo (int)($t['sort_order'] ?? 0); ?></strong>
                        </div>
                    </div>

                    <div class="products-mobile-card-actions">
                        <a href="testimonial-form.php?id=<?php echo $t['id']; ?>" class="btn btn-secondary btn-sm" style="flex: 1; justify-content: center;">
                            <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                            <span><?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?></span>
                        </a>
                        <form method="POST" action="testimonials.php" style="margin: 0;" onsubmit="return confirm('<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف هذا التقييم؟' : 'Delete this testimonial?'; ?>');">
                            <input type="hidden" name="action" value="delete_testimonial">
                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--admin-danger);" title="<?php echo $lang === 'ar' ? 'حذف' : 'Delete'; ?>">
                                <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <?php 
        render_admin_empty_state(
            $lang === 'ar' ? 'لا توجد آراء تقييمات مسجلة حالياً' : 'No testimonials found',
            $lang === 'ar' ? 'يمكنك إضافة أول تقييم لعميلك لعرضه في الصفحة الرئيسية للشركة.' : 'Add your first customer review to showcase on the main homepage.',
            'testimonial-form.php',
            $lang === 'ar' ? 'إضافة تقييم جديد' : 'Add Testimonial',
            'message-square'
        );
        ?>
    <?php endif; ?>
</main>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
