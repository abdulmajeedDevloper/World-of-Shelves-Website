<?php
// admin/orders.php
// Enterprise Orders & Quote Requests Management Center
require_once dirname(__DIR__) . '/config/admin_init.php';

$success_message = get_flash('success');
$error_message = get_flash('error');

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash('error', get_current_lang() === 'ar' ? 'رمز الحماية غير صالح.' : 'Invalid security token.');
    } else {
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = trim($_POST['status'] ?? '');
        $valid_statuses = ['pending', 'processing', 'completed', 'cancelled', 'quote_requested'];
        
        if ($order_id > 0 && in_array($new_status, $valid_statuses)) {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
                $stmt->execute([':status' => $new_status, ':id' => $order_id]);
                set_flash('success', get_current_lang() === 'ar' ? 'تم تحديث حالة الطلب بنجاح.' : 'Order status updated successfully.');
            } catch (PDOException $e) {
                error_log("Error updating order status: " . $e->getMessage());
                set_flash('error', get_current_lang() === 'ar' ? 'فشل تحديث حالة الطلب.' : 'Failed to update order status.');
            }
        }
    }
    header("Location: orders.php");
    exit;
}

// Read Filter and Search Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build Query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    if (is_numeric($search)) {
        $where_clauses[] = "(id = :search_id OR customer_name LIKE :search OR customer_phone LIKE :search OR customer_email LIKE :search)";
        $params[':search_id'] = (int)$search;
        $params[':search'] = '%' . escape_like_wildcards($search) . '%';
    } else {
        $where_clauses[] = "(customer_name LIKE :search OR customer_phone LIKE :search OR customer_email LIKE :search OR customer_address LIKE :search)";
        $params[':search'] = '%' . escape_like_wildcards($search) . '%';
    }
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_clauses[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count Total & Statistics
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders $where_sql");
    $count_stmt->execute($params);
    $total_records = (int)$count_stmt->fetchColumn();

    $stat_total = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stat_pending = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'quote_requested')")->fetchColumn();
    $stat_processing = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
    $stat_completed = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching orders statistics: " . $e->getMessage());
    $total_records = $stat_total = $stat_pending = $stat_processing = $stat_completed = 0;
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

// Fetch Filtered Orders List
try {
    $stmt = $pdo->prepare("SELECT * FROM orders $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
}

$lang = get_current_lang();
$page_title = __('admin_manage_orders');

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <?php 
    render_admin_page_header($page_title, [
        ['label' => $lang === 'ar' ? 'طلبات العملاء والعروض' : 'Orders & Quotes', 'url' => '']
    ]);
    ?>

    <!-- Message Alerts -->
    <?php 
    render_admin_alert('success', $success_message);
    render_admin_alert('danger', $error_message);
    ?>

    <!-- Orders Summary Strip (4 KPI Summary Cards) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <?php 
        render_admin_kpi_card($lang === 'ar' ? 'إجمالي الطلبات' : 'Total Orders', $stat_total, 'shopping-bag', 'blue');
        render_admin_kpi_card($lang === 'ar' ? 'جديد / طلب سعر' : 'New / Quotes', $stat_pending, 'clock', 'amber');
        render_admin_kpi_card($lang === 'ar' ? 'قيد المعالجة' : 'Processing', $stat_processing, 'refresh-cw', 'blue');
        render_admin_kpi_card($lang === 'ar' ? 'مكتملة' : 'Completed', $stat_completed, 'check-circle', 'green');
        ?>
    </div>

    <!-- Adaptive Filter Controls Bar -->
    <section class="products-filter-bar">
        <form method="GET" action="orders.php" class="products-filter-form">
            <div class="filter-input-search">
                <i data-lucide="search"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo $lang === 'ar' ? 'ابحث باسم العميل، الهاتف، الإيميل، رقم الطلب...' : 'Search by name, phone, email, #ID...'; ?>">
            </div>

            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'جميع الحالات' : 'All Statuses'; ?></option>
                <option value="quote_requested" <?php echo $status_filter === 'quote_requested' ? 'selected' : ''; ?>><?php echo __('admin_status_quote_requested'); ?></option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo __('admin_status_pending'); ?></option>
                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>><?php echo __('admin_status_processing'); ?></option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>><?php echo __('admin_status_completed'); ?></option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>><?php echo __('admin_status_cancelled'); ?></option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 8px 14px; font-size: 13px;">
                <i data-lucide="filter" style="width:14px; height:14px;"></i>
                <span><?php echo $lang === 'ar' ? 'تطبيق الفلتر' : 'Apply Filter'; ?></span>
            </button>

            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <a href="orders.php" class="filter-btn-reset">
                    <i data-lucide="rotate-ccw" style="width:14px; height:14px;"></i>
                    <span><?php echo $lang === 'ar' ? 'إعادة ضبط' : 'Reset'; ?></span>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Orders Content View -->
    <?php if (count($orders) > 0): ?>
        <!-- Desktop High-Density Data Table (> 768px) -->
        <div class="products-desktop-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 70px; text-align: center;"><?php echo __('admin_order_id'); ?></th>
                        <th><?php echo __('admin_customer'); ?></th>
                        <th><?php echo __('admin_phone_col'); ?></th>
                        <th><?php echo __('orders_col_address'); ?></th>
                        <th style="text-align: center;"><?php echo __('admin_install_col'); ?></th>
                        <th style="text-align: center; width: 110px;"><?php echo __('admin_status'); ?></th>
                        <th style="text-align: center; width: 170px;"><?php echo __('orders_col_update_status'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $ord): 
                        $ord_id = (int)$ord['id'];
                        $st = $ord['status'];
                        $badgeType = 'neutral';
                        if ($st === 'completed') $badgeType = 'success';
                        elseif ($st === 'processing') $badgeType = 'info';
                        elseif ($st === 'quote_requested' || $st === 'pending') $badgeType = 'warning';
                        elseif ($st === 'cancelled') $badgeType = 'danger';
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <strong style="font-size: 13px; color: var(--admin-text-main);">#<?php echo $ord_id; ?></strong>
                            </td>

                            <td>
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <strong style="font-size: 13.5px; color: var(--admin-text-main);"><?php echo htmlspecialchars($ord['customer_name']); ?></strong>
                                    <?php if (!empty($ord['customer_email'])): ?>
                                        <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo htmlspecialchars($ord['customer_email']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <a href="tel:<?php echo htmlspecialchars($ord['customer_phone']); ?>" style="color: var(--admin-primary); text-decoration: none; font-weight: 600; font-size: 13px; dir: ltr; display: inline-block;">
                                    <?php echo htmlspecialchars($ord['customer_phone']); ?>
                                </a>
                            </td>

                            <td style="max-width: 200px;">
                                <span style="font-size: 12.5px; color: var(--admin-text-main); display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($ord['customer_address']); ?>
                                </span>
                            </td>

                            <td style="text-align: center;">
                                <?php if (!empty($ord['require_installation'])): ?>
                                    <span class="nav-badge badge-warning" style="font-size: 11.5px;">
                                        <i data-lucide="wrench" style="width:12px; height:12px; display:inline-block; vertical-align:middle;"></i>
                                        <span><?php echo __('admin_install_yes'); ?></span>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo __('admin_install_no'); ?></span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align: center;">
                                <?php echo render_admin_badge(__('admin_status_' . $st), $badgeType); ?>
                            </td>

                            <td style="text-align: center;">
                                <form action="orders.php" method="POST" style="display: flex; gap: 6px; align-items: center; justify-content: center; margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $ord_id; ?>">
                                    
                                    <select name="status" class="filter-select" style="padding: 5px 8px; font-size: 12px; height: 32px;" onchange="this.form.submit()">
                                        <option value="quote_requested" <?php echo $st === 'quote_requested' ? 'selected' : ''; ?>><?php echo __('admin_status_quote_requested'); ?></option>
                                        <option value="pending" <?php echo $st === 'pending' ? 'selected' : ''; ?>><?php echo __('admin_status_pending'); ?></option>
                                        <option value="processing" <?php echo $st === 'processing' ? 'selected' : ''; ?>><?php echo __('admin_status_processing'); ?></option>
                                        <option value="completed" <?php echo $st === 'completed' ? 'selected' : ''; ?>><?php echo __('admin_status_completed'); ?></option>
                                        <option value="cancelled" <?php echo $st === 'cancelled' ? 'selected' : ''; ?>><?php echo __('admin_status_cancelled'); ?></option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Touch Cards (< 767px) -->
        <div class="products-mobile-card-list">
            <?php foreach ($orders as $ord): 
                $ord_id = (int)$ord['id'];
                $st = $ord['status'];
                $badgeType = 'neutral';
                if ($st === 'completed') $badgeType = 'success';
                elseif ($st === 'processing') $badgeType = 'info';
                elseif ($st === 'quote_requested' || $st === 'pending') $badgeType = 'warning';
                elseif ($st === 'cancelled') $badgeType = 'danger';
            ?>
                <div class="product-mobile-card">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                        <div>
                            <span style="font-size:12px; font-weight:700; color:var(--admin-text-muted);">#<?php echo $ord_id; ?></span>
                            <strong style="font-size:15px; color:var(--admin-text-main); display:block; margin-top:2px;"><?php echo htmlspecialchars($ord['customer_name']); ?></strong>
                        </div>
                        <?php echo render_admin_badge(__('admin_status_' . $st), $badgeType); ?>
                    </div>

                    <div class="product-mobile-card-meta">
                        <div>
                            <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo __('admin_phone_col'); ?>:</span>
                            <a href="tel:<?php echo htmlspecialchars($ord['customer_phone']); ?>" style="font-size: 12.5px; font-weight: 700; color: var(--admin-primary); text-decoration: none;">
                                <?php echo htmlspecialchars($ord['customer_phone']); ?>
                            </a>
                        </div>
                        <div>
                            <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo __('admin_install_col'); ?>:</span>
                            <strong style="font-size: 12px; color: <?php echo !empty($ord['require_installation']) ? 'var(--admin-warning)' : 'var(--admin-text-muted)'; ?>;">
                                <?php echo !empty($ord['require_installation']) ? __('admin_install_yes') : __('admin_install_no'); ?>
                            </strong>
                        </div>
                    </div>

                    <?php if (!empty($ord['customer_address'])): ?>
                        <div style="font-size:12px; color:var(--admin-text-muted); background:var(--admin-bg-subtle); padding:8px 10px; border-radius:var(--radius-sm); margin-bottom:12px;">
                            <i data-lucide="map-pin" style="width:12px; height:12px; display:inline-block; vertical-align:middle; color:var(--admin-text-subtle);"></i>
                            <span><?php echo htmlspecialchars($ord['customer_address']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="product-mobile-actions-row">
                        <form action="orders.php" method="POST" style="flex:1; margin:0;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $ord_id; ?>">
                            <select name="status" class="filter-select" style="width:100%; height:38px; font-size:12.5px; padding:6px;" onchange="this.form.submit()">
                                <option value="quote_requested" <?php echo $st === 'quote_requested' ? 'selected' : ''; ?>><?php echo __('admin_status_quote_requested'); ?></option>
                                <option value="pending" <?php echo $st === 'pending' ? 'selected' : ''; ?>><?php echo __('admin_status_pending'); ?></option>
                                <option value="processing" <?php echo $st === 'processing' ? 'selected' : ''; ?>><?php echo __('admin_status_processing'); ?></option>
                                <option value="completed" <?php echo $st === 'completed' ? 'selected' : ''; ?>><?php echo __('admin_status_completed'); ?></option>
                                <option value="cancelled" <?php echo $st === 'cancelled' ? 'selected' : ''; ?>><?php echo __('admin_status_cancelled'); ?></option>
                            </select>
                        </form>

                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $ord['customer_phone']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding: 8px 12px; color: #10b981;" title="WhatsApp">
                            <i data-lucide="message-circle" style="width:16px;"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Strip -->
        <?php render_admin_pagination($page, $total_pages, $total_records, count($orders), $lang === 'ar' ? 'طلب' : 'orders', 'orders.php', ['search' => $search, 'status' => $status_filter]); ?>

    <?php else: ?>
        <!-- Empty State -->
        <?php 
        $emptyTitle = !empty($search) || $status_filter !== 'all' ? ($lang === 'ar' ? 'لم يتم العثور على طلبات مطابقة' : 'No matching orders found') : ($lang === 'ar' ? 'لا توجد طلبات عملاء حتى الآن' : 'No customer orders yet');
        $emptyDesc = !empty($search) || $status_filter !== 'all' ? ($lang === 'ar' ? 'جرب تغيير كلمة البحث أو حالة الفلتر' : 'Try adjusting search keywords or status filter') : ($lang === 'ar' ? 'ستظهر طلبات الأسعار والشرائ هنا فور قيام العملاء بإرسالها' : 'Customer quote and purchase requests will appear here once submitted');
        render_admin_empty_state('shopping-bag', $emptyTitle, $emptyDesc, !empty($search) || $status_filter !== 'all' ? ($lang === 'ar' ? 'إعادة ضبط التصفية' : 'Reset Filters') : '', 'orders.php', true);
        ?>
    <?php endif; ?>
</main>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
