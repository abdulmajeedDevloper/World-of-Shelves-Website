<?php
// admin/index.php
// Executive Dashboard & Operations Center.

require_once dirname(__DIR__) . '/config/admin_init.php';

$displayName = $_SESSION['admin_username'] ?? 'Admin';
$lang = get_current_lang();
$is_cs = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'customer_service');

// Executive KPIs
$total_products = 0;
$active_products = 0;
$total_projects = 0;
$total_services = 0;
$total_orders = 0;
$pending_orders = 0;
$total_messages = 0;
$unread_messages = 0;
$total_testimonials = 0;
$total_media = 0;

$recent_orders = [];
$recent_messages = [];

try {
    // 0. Fetch Current Admin Details
    $username_session = $_SESSION['admin_username'] ?? '';
    $admin_stmt = $pdo->prepare("SELECT full_name FROM users WHERE username = :username LIMIT 1");
    $admin_stmt->execute([':username' => $username_session]);
    $admin_user = $admin_stmt->fetch();
    if ($admin_user && !empty($admin_user['full_name'])) {
        $displayName = $admin_user['full_name'];
    }

    // 1. Products Metrics
    $total_products = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $active_products = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

    // 2. Projects & Services Metrics
    $total_projects = (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $total_services = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();

    // 3. Orders Metrics & Recent Orders
    $total_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pending_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('new', 'pending')")->fetchColumn();
    $recent_orders_stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");
    $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Messages / Inquiries Metrics & Recent Messages
    $messages_table_exists = false;
    $msg_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='contact_requests'");
    if ($msg_check && $msg_check->fetchColumn()) {
        $messages_table_exists = true;
        $total_messages = (int)$pdo->query("SELECT COUNT(*) FROM contact_requests")->fetchColumn();
        $unread_messages = (int)$pdo->query("SELECT COUNT(*) FROM contact_requests WHERE is_read = 0")->fetchColumn();
        $recent_msg_stmt = $pdo->query("SELECT * FROM contact_requests ORDER BY id DESC LIMIT 5");
        $recent_messages = $recent_msg_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Testimonials Metrics
    $total_testimonials = (int)$pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();

    // 6. Media Library Metrics
    $total_media = (int)$pdo->query("SELECT COUNT(*) FROM media_library")->fetchColumn();

} catch (PDOException $e) {
    error_log('[WorldOfShelves] Dashboard metric error: ' . $e->getMessage());
}

$page_title = __('admin_dashboard');

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Executive Workspace Container -->
<main class="admin-content">
    <!-- Compact Executive Page Header -->
    <?php 
    render_admin_page_header(
        $lang === 'ar' ? 'مركز العمليات والتنفيذ' : 'Executive Operations Center',
        [['label' => $lang === 'ar' ? 'لوحة التحكم القيادية' : 'Executive Dashboard', 'url' => '']]
    );
    ?>

    <!-- Welcome Executive Banner -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 22px 24px; border-radius: var(--radius-lg); margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.15);">
        <div>
            <div style="font-size: 13px; text-transform: uppercase; tracking: 1px; opacity: 0.8; margin-bottom: 4px; font-weight: 600;">
                <?php echo $lang === 'ar' ? 'نظام عالم الرفوف لتخطيط الموارد' : 'World of Shelves Enterprise CMS'; ?>
            </div>
            <h2 style="font-size: 22px; font-weight: 800; margin: 0 0 6px 0; color: #fff;">
                <?php echo ($lang === 'ar' ? 'مرحباً بك مجدداً، ' : 'Welcome back, ') . htmlspecialchars($displayName); ?> 👋
            </h2>
            <p style="margin: 0; font-size: 13.5px; opacity: 0.85; max-width: 600px;">
                <?php echo $lang === 'ar' ? 'ملخص أداء المنتجات، الطلبات الواردة، رسائل العملاء، وإحصائيات المحتوى بلمسة واحدة.' : 'Live summary of products, incoming orders, customer inquiries, and system content metrics.'; ?>
            </p>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (!$is_cs): ?>
                <a href="products.php" class="btn btn-primary btn-sm" style="background: var(--admin-primary); border: none;">
                    <i data-lucide="plus" style="width: 14px; height: 14px;"></i>
                    <span><?php echo $lang === 'ar' ? 'إضافة منتج' : 'Add Product'; ?></span>
                </a>
            <?php endif; ?>
            <a href="orders.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2);">
                <i data-lucide="shopping-bag" style="width: 14px; height: 14px;"></i>
                <span><?php echo $lang === 'ar' ? 'الطلبات (' . $pending_orders . ')' : 'Orders (' . $pending_orders . ')'; ?></span>
            </a>
        </div>
    </div>

    <!-- Executive KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
        <?php 
        render_admin_kpi_card($lang === 'ar' ? 'إجمالي المنتجات' : 'Total Products', $total_products, 'package', 'blue');
        render_admin_kpi_card($lang === 'ar' ? 'طلبات الأسعار الجديدة' : 'Pending Orders', $pending_orders, 'shopping-bag', 'amber');
        render_admin_kpi_card($lang === 'ar' ? 'الرسائل غير المقروءة' : 'Unread Messages', $unread_messages, 'message-square', 'rose');
        render_admin_kpi_card($lang === 'ar' ? 'المشاريع المنجزة' : 'Projects Done', $total_projects, 'folder', 'emerald');
        render_admin_kpi_card($lang === 'ar' ? 'الخدمات المتاحة' : 'Active Services', $total_services, 'layers', 'indigo');
        render_admin_kpi_card($lang === 'ar' ? 'آراء التقييمات' : 'Client Reviews', $total_testimonials, 'star', 'purple');
        ?>
    </div>

    <!-- Quick Navigation Shortcuts -->
    <?php if (!$is_cs): ?>
    <div class="form-section-card" style="margin-bottom: 28px;">
        <h3>
            <i data-lucide="compass"></i>
            <span><?php echo $lang === 'ar' ? 'وصول سريع لأقسام الإدارة' : 'Quick Management Shortcuts'; ?></span>
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
            <a href="products.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="package" style="width: 22px; height: 22px; color: var(--admin-primary);"></i>
                <span><?php echo $lang === 'ar' ? 'المنتجات' : 'Products'; ?></span>
            </a>

            <a href="projects.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="folder" style="width: 22px; height: 22px; color: #10b981;"></i>
                <span><?php echo $lang === 'ar' ? 'المشاريع' : 'Projects'; ?></span>
            </a>

            <a href="services.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="layers" style="width: 22px; height: 22px; color: #6366f1;"></i>
                <span><?php echo $lang === 'ar' ? 'الخدمات' : 'Services'; ?></span>
            </a>

            <a href="orders.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="shopping-bag" style="width: 22px; height: 22px; color: #f59e0b;"></i>
                <span><?php echo $lang === 'ar' ? 'الطلبات' : 'Orders'; ?></span>
            </a>

            <a href="messages.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="message-square" style="width: 22px; height: 22px; color: #ef4444;"></i>
                <span><?php echo $lang === 'ar' ? 'الرسائل' : 'Messages'; ?></span>
            </a>

            <a href="testimonials.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="star" style="width: 22px; height: 22px; color: #8b5cf6;"></i>
                <span><?php echo $lang === 'ar' ? 'التقييمات' : 'Testimonials'; ?></span>
            </a>

            <a href="used-shelves.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="warehouse" style="width: 22px; height: 22px; color: #06b6d4;"></i>
                <span><?php echo $lang === 'ar' ? 'الأرفف المستعملة' : 'Used Shelves'; ?></span>
            </a>

            <a href="settings.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="settings" style="width: 22px; height: 22px; color: #64748b;"></i>
                <span><?php echo $lang === 'ar' ? 'الإعدادات' : 'Settings'; ?></span>
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="form-section-card" style="margin-bottom: 28px;">
        <h3>
            <i data-lucide="compass"></i>
            <span><?php echo $lang === 'ar' ? 'وصول سريع لأقسام الإدارة' : 'Quick Management Shortcuts'; ?></span>
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
            <a href="orders.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="shopping-bag" style="width: 22px; height: 22px; color: #f59e0b;"></i>
                <span><?php echo $lang === 'ar' ? 'الطلبات' : 'Orders'; ?></span>
            </a>

            <a href="messages.php" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="message-square" style="width: 22px; height: 22px; color: #ef4444;"></i>
                <span><?php echo $lang === 'ar' ? 'الرسائل' : 'Messages'; ?></span>
            </a>

            <a href="global-ui.php?tab=socials" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="share-2" style="width: 22px; height: 22px; color: #10b981;"></i>
                <span><?php echo $lang === 'ar' ? 'شبكات التواصل' : 'Socials'; ?></span>
            </a>

            <a href="global-ui.php?tab=contact" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px; background: var(--admin-bg-subtle); border: 1px solid var(--admin-border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--admin-text-main); font-weight: 600; font-size: 13px; text-align: center; transition: all 0.2s;">
                <i data-lucide="phone" style="width: 22px; height: 22px; color: #6366f1;"></i>
                <span><?php echo $lang === 'ar' ? 'معلومات الاتصال' : 'Contact'; ?></span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Operations Two-Column Split Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px;">
        <!-- Left: Recent Orders & Quotes -->
        <div class="form-section-card" style="margin-bottom: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 16px;">
                    <i data-lucide="shopping-bag"></i>
                    <span><?php echo $lang === 'ar' ? 'أحدث الطلبات وطلبات الأسعار' : 'Latest Orders & Quotes'; ?></span>
                </h3>
                <a href="orders.php" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 4px 10px; text-decoration: none;">
                    <?php echo $lang === 'ar' ? 'عرض الكل' : 'View All'; ?>
                </a>
            </div>

            <?php if (count($recent_orders) > 0): ?>
                <div class="products-desktop-table-card" style="margin-bottom: 0;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php echo __('admin_customer'); ?></th>
                                <th style="text-align: center;"><?php echo __('admin_status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $ord): ?>
                                <tr>
                                    <td style="font-weight: 700;">#<?php echo $ord['id']; ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--admin-text-main); font-size: 13px;">
                                            <?php echo htmlspecialchars($ord['customer_name']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--admin-text-muted);">
                                            <?php echo htmlspecialchars($ord['customer_phone']); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php 
                                        $st = $ord['status'] ?? 'new';
                                        if ($st === 'completed') {
                                            render_admin_badge($lang === 'ar' ? 'مكتمل' : 'Completed', 'emerald');
                                        } elseif ($st === 'processing') {
                                            render_admin_badge($lang === 'ar' ? 'قيد المعالجة' : 'Processing', 'indigo');
                                        } elseif ($st === 'cancelled') {
                                            render_admin_badge($lang === 'ar' ? 'ملغى' : 'Cancelled', 'rose');
                                        } else {
                                            render_admin_badge($lang === 'ar' ? 'جديد' : 'New', 'amber');
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?php render_admin_empty_state('shopping-bag', $lang === 'ar' ? 'لا توجد طلبات حديثة' : 'No recent orders', $lang === 'ar' ? 'ستظهر الطلبات الجديدة وطلبات تسعير الرفوف هنا تلقائياً.' : 'New pricing orders will appear here automatically.', $lang === 'ar' ? 'سجل الطلبات' : 'Orders History', 'orders.php'); ?>
            <?php endif; ?>
        </div>

        <!-- Right: Recent Customer Messages & Inquiries -->
        <div class="form-section-card" style="margin-bottom: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 16px;">
                    <i data-lucide="message-square"></i>
                    <span><?php echo $lang === 'ar' ? 'أحدث استفسارات العملاء' : 'Latest Customer Inquiries'; ?></span>
                </h3>
                <a href="messages.php" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 4px 10px; text-decoration: none;">
                    <?php echo $lang === 'ar' ? 'عرض الكل' : 'View All'; ?>
                </a>
            </div>

            <?php if (count($recent_messages) > 0): ?>
                <div class="products-desktop-table-card" style="margin-bottom: 0;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo $lang === 'ar' ? 'العميل' : 'Sender'; ?></th>
                                <th><?php echo $lang === 'ar' ? 'الموضوع' : 'Subject'; ?></th>
                                <th style="text-align: center;"><?php echo $lang === 'ar' ? 'الحالة' : 'Status'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_messages as $msg): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--admin-text-main); font-size: 13px;">
                                            <?php echo htmlspecialchars($msg['name']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--admin-text-muted);">
                                            <?php echo htmlspecialchars($msg['phone']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 12.5px; color: var(--admin-text-subtle);">
                                            <?php echo htmlspecialchars(mb_strimwidth($msg['subject'] ?? ($lang === 'ar' ? 'استفسار عام' : 'General Inquiry'), 0, 24, '...')); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php 
                                        if (!empty($msg['is_read'])) {
                                            render_admin_badge($lang === 'ar' ? 'مقروء' : 'Read', 'slate');
                                        } else {
                                            render_admin_badge($lang === 'ar' ? 'جديد' : 'Unread', 'rose');
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?php render_admin_empty_state('message-square', $lang === 'ar' ? 'لا توجد رسائل حديثة' : 'No recent messages', $lang === 'ar' ? 'ستظهر الرسائل المبعوثة عبر نموذج اتصل بنا هنا.' : 'Messages sent via contact form will appear here.', $lang === 'ar' ? 'صندوق الرسائل' : 'Inbox', 'messages.php'); ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
