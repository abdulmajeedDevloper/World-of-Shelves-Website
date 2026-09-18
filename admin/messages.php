<?php
// admin/messages.php
// Enterprise Customer Inquiries & Contact Messages Center
require_once dirname(__DIR__) . '/config/admin_init.php';

$success_message = get_flash('success');
$error_message = get_flash('error');
$is_cs = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'customer_service');

// Handle Deletion (POST & GET fallback with CSRF)
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    if ($is_cs) {
        set_flash('error', get_current_lang() === 'ar' ? 'غير مصرح لمسؤول الخدمة بحذف الرسائل.' : 'Customer Service is not authorized to delete messages.');
    } elseif (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash('error', get_current_lang() === 'ar' ? 'رمز الحماية غير صالح.' : 'Invalid security token.');
    } else {
        $delete_id = (int)($_POST['id'] ?? 0);
        if ($delete_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
                $stmt->execute([$delete_id]);
                set_flash('success', get_current_lang() === 'ar' ? 'تم حذف الرسالة بنجاح.' : 'Message deleted successfully.');
            } catch (PDOException $e) {
                error_log("Error deleting message: " . $e->getMessage());
                set_flash('error', get_current_lang() === 'ar' ? 'فشل حذف الرسالة.' : 'Failed to delete message.');
            }
        }
    }
    header("Location: messages.php");
    exit;
} elseif (isset($_GET['delete_id'])) {
    if ($is_cs) {
        set_flash('error', get_current_lang() === 'ar' ? 'غير مصرح لمسؤول الخدمة بحذف الرسائل.' : 'Customer Service is not authorized to delete messages.');
    } else {
        $delete_id = (int)$_GET['delete_id'];
        if ($delete_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
                $stmt->execute([$delete_id]);
                set_flash('success', get_current_lang() === 'ar' ? 'تم حذف الرسالة بنجاح.' : 'Message deleted successfully.');
            } catch (PDOException $e) {
                error_log("Error deleting message: " . $e->getMessage());
            }
        }
    }
    header("Location: messages.php");
    exit;
}

// Handle Mark as Read / Unread
if (isset($_POST['action']) && $_POST['action'] === 'toggle_read') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash('error', get_current_lang() === 'ar' ? 'رمز الحماية غير صالح.' : 'Invalid security token.');
    } else {
        $toggle_id = (int)($_POST['id'] ?? 0);
        if ($toggle_id > 0) {
            try {
                $status_stmt = $pdo->prepare("SELECT is_read FROM messages WHERE id = ?");
                $status_stmt->execute([$toggle_id]);
                $current_status = $status_stmt->fetchColumn();
                if ($current_status !== false) {
                    $new_status = $current_status ? 0 : 1;
                    $update_stmt = $pdo->prepare("UPDATE messages SET is_read = ? WHERE id = ?");
                    $update_stmt->execute([$new_status, $toggle_id]);
                    set_flash('success', get_current_lang() === 'ar' ? 'تم تحديث حالة الرسالة بنجاح.' : 'Message status updated successfully.');
                }
            } catch (PDOException $e) {
                error_log("Error toggling message status: " . $e->getMessage());
            }
        }
    }
    header("Location: messages.php");
    exit;
} elseif (isset($_GET['toggle_read'])) {
    $toggle_id = (int)$_GET['toggle_read'];
    if ($toggle_id > 0) {
        try {
            $status_stmt = $pdo->prepare("SELECT is_read FROM messages WHERE id = ?");
            $status_stmt->execute([$toggle_id]);
            $current_status = $status_stmt->fetchColumn();
            if ($current_status !== false) {
                $new_status = $current_status ? 0 : 1;
                $update_stmt = $pdo->prepare("UPDATE messages SET is_read = ? WHERE id = ?");
                $update_stmt->execute([$new_status, $toggle_id]);
                set_flash('success', get_current_lang() === 'ar' ? 'تم تحديث حالة الرسالة بنجاح.' : 'Message status updated.');
            }
        } catch (PDOException $e) {
            error_log("Error toggling message read status: " . $e->getMessage());
        }
    }
    header("Location: messages.php");
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
    $where_clauses[] = "(name LIKE :search OR email LIKE :search OR phone LIKE :search OR message LIKE :search)";
    $params[':search'] = '%' . escape_like_wildcards($search) . '%';
}

if ($status_filter === 'unread') {
    $where_clauses[] = "is_read = 0";
} elseif ($status_filter === 'read') {
    $where_clauses[] = "is_read = 1";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count Statistics
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages $where_sql");
    $count_stmt->execute($params);
    $total_records = (int)$count_stmt->fetchColumn();

    $stat_total = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $stat_unread = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn();
    $stat_read = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 1")->fetchColumn();
    $stat_today = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURRENT_DATE()")->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching message statistics: " . $e->getMessage());
    $total_records = $stat_total = $stat_unread = $stat_read = $stat_today = 0;
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

// Fetch Filtered Messages
try {
    $stmt = $pdo->prepare("SELECT * FROM messages $where_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    $messages = [];
}

$lang = get_current_lang();
$page_title = __('admin_manage_messages');

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <?php 
    render_admin_page_header($page_title, [
        ['label' => $lang === 'ar' ? 'رسائل التواصل والاستفسارات' : 'Contact Inquiries', 'url' => '']
    ]);
    ?>

    <!-- Message Alerts -->
    <?php 
    render_admin_alert('success', $success_message);
    render_admin_alert('danger', $error_message);
    ?>

    <!-- Messages Summary Strip (4 KPI Summary Cards) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <?php 
        render_admin_kpi_card($lang === 'ar' ? 'إجمالي الرسائل' : 'Total Messages', $stat_total, 'mail', 'blue');
        render_admin_kpi_card($lang === 'ar' ? 'رسائل جديدة / غير مقروءة' : 'New / Unread', $stat_unread, 'mail-warning', 'red');
        render_admin_kpi_card($lang === 'ar' ? 'رسائل مقروءة' : 'Read Messages', $stat_read, 'mail-check', 'green');
        render_admin_kpi_card($lang === 'ar' ? 'استفسارات اليوم' : 'Today Inquiries', $stat_today, 'calendar', 'amber');
        ?>
    </div>

    <!-- Adaptive Filter Controls Bar -->
    <section class="products-filter-bar">
        <form method="GET" action="messages.php" class="products-filter-form">
            <div class="filter-input-search">
                <i data-lucide="search"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo $lang === 'ar' ? 'ابحث باسم المرسل، الإيميل، الهاتف، محتوى الرسالة...' : 'Search by sender name, email, phone, message...'; ?>">
            </div>

            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'جميع الرسائل' : 'All Messages'; ?></option>
                <option value="unread" <?php echo $status_filter === 'unread' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'غير مقروءة (جديدة)' : 'Unread (New)'; ?></option>
                <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'مقروءة' : 'Read'; ?></option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 8px 14px; font-size: 13px;">
                <i data-lucide="filter" style="width:14px; height:14px;"></i>
                <span><?php echo $lang === 'ar' ? 'تطبيق الفلتر' : 'Apply Filter'; ?></span>
            </button>

            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <a href="messages.php" class="filter-btn-reset">
                    <i data-lucide="rotate-ccw" style="width:14px; height:14px;"></i>
                    <span><?php echo $lang === 'ar' ? 'إعادة ضبط' : 'Reset'; ?></span>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Messages List Container -->
    <?php if (count($messages) > 0): ?>
        <!-- Desktop High-Density Data Table (> 768px) -->
        <div class="products-desktop-table-card">
            <table class="admin-table" style="table-layout: fixed; width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 55px; text-align: center;">#</th>
                        <th style="width: 130px;"><?php echo __('admin_msg_name'); ?></th>
                        <th style="width: 170px;"><?php echo __('admin_msg_contact'); ?></th>
                        <th><?php echo __('admin_msg_content'); ?></th>
                        <th style="width: 130px; text-align: center;"><?php echo __('admin_msg_date'); ?></th>
                        <th style="text-align: center; width: 85px;"><?php echo $lang === 'ar' ? 'الحالة' : 'Status'; ?></th>
                        <th style="text-align: center; width: 120px;"><?php echo __('admin_actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): 
                        $msg_id = (int)$msg['id'];
                        $is_read = (bool)$msg['is_read'];
                    ?>
                        <tr style="<?php echo !$is_read ? 'background-color: rgba(59, 130, 246, 0.04);' : ''; ?>">
                            <td style="text-align: center;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <?php if (!$is_read): ?>
                                        <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--admin-danger); display: inline-block; flex-shrink: 0;"></span>
                                    <?php endif; ?>
                                    <strong style="font-size: 13px; color: var(--admin-text-main);">#<?php echo $msg_id; ?></strong>
                                </div>
                            </td>

                            <td style="word-break: break-word; overflow-wrap: anywhere;">
                                <strong style="font-size: 13.5px; color: var(--admin-text-main); display: block; word-break: break-word; overflow-wrap: anywhere;"><?php echo htmlspecialchars($msg['name']); ?></strong>
                            </td>

                            <td style="word-break: break-all; overflow-wrap: anywhere;">
                                <div style="display: flex; flex-direction: column; gap: 2px; word-break: break-all; overflow-wrap: anywhere;">
                                    <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>" style="color: var(--admin-primary); text-decoration: none; font-weight: 600; font-size: 12.5px; word-break: break-all; overflow-wrap: anywhere; display: inline-block;">
                                        <?php echo htmlspecialchars($msg['email']); ?>
                                    </a>
                                    <?php if (!empty($msg['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($msg['phone']); ?>" style="font-size: 12px; color: var(--admin-text-muted); text-decoration: none; dir: ltr; display: inline-block;">
                                            <?php echo htmlspecialchars($msg['phone']); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td style="word-break: break-all; overflow-wrap: anywhere;">
                                <div style="font-size: 13px; color: var(--admin-text-main); max-height: 54px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.4; word-break: break-all; overflow-wrap: anywhere; white-space: normal;">
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                </div>
                            </td>

                            <td style="text-align: center; font-size: 12px; color: var(--admin-text-muted);">
                                <?php echo date('Y-m-d H:i', strtotime($msg['created_at'])); ?>
                            </td>

                            <td style="text-align: center;">
                                <?php echo render_admin_badge($is_read ? ($lang === 'ar' ? 'مقروءة' : 'Read') : ($lang === 'ar' ? 'جديدة' : 'New'), $is_read ? 'neutral' : 'danger'); ?>
                            </td>

                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <!-- View Message Modal Button -->
                                    <button type="button" class="btn btn-primary btn-sm" title="<?php echo $lang === 'ar' ? 'عرض تفاصيل الرسالة بالكامل' : 'View full message details'; ?>" onclick='openMessageModal(<?php echo json_encode($msg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="padding: 4px 8px;">
                                        <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                                    </button>

                                    <!-- Toggle Read Status -->
                                    <form action="messages.php" method="POST" style="display: inline; margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="toggle_read">
                                        <input type="hidden" name="id" value="<?php echo $msg_id; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="<?php echo $is_read ? ($lang === 'ar' ? 'تعيين كغير مقروءة' : 'Mark as unread') : ($lang === 'ar' ? 'تعيين كمقروءة' : 'Mark as read'); ?>" style="padding: 4px 8px;">
                                            <i data-lucide="<?php echo $is_read ? 'eye-off' : 'eye'; ?>" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </form>

                                    <!-- Delete Message -->
                                    <?php if (!$is_cs): ?>
                                    <form action="messages.php" method="POST" style="display: inline; margin: 0;" onsubmit="return confirmAction(event, '<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف هذه الرسالة نهائياً؟' : 'Are you sure you want to delete this message?'; ?>');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $msg_id; ?>">
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

        <!-- Mobile Touch Cards (< 767px) -->
        <div class="products-mobile-card-list">
            <?php foreach ($messages as $msg): 
                $msg_id = (int)$msg['id'];
                $is_read = (bool)$msg['is_read'];
            ?>
                <div class="product-mobile-card" style="<?php echo !$is_read ? 'border-inline-start: 4px solid var(--admin-danger);' : ''; ?>">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                        <div>
                            <span style="font-size:12px; font-weight:700; color:var(--admin-text-muted);">#<?php echo $msg_id; ?> • <?php echo date('Y-m-d H:i', strtotime($msg['created_at'])); ?></span>
                            <strong style="font-size:15px; color:var(--admin-text-main); display:block; margin-top:2px;"><?php echo htmlspecialchars($msg['name']); ?></strong>
                        </div>
                        <?php echo render_admin_badge($is_read ? ($lang === 'ar' ? 'مقروءة' : 'Read') : ($lang === 'ar' ? 'جديدة' : 'New'), $is_read ? 'neutral' : 'danger'); ?>
                    </div>

                    <div class="product-mobile-card-meta">
                        <div>
                            <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo __('admin_msg_contact'); ?>:</span>
                            <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>" style="font-size: 12.5px; font-weight: 700; color: var(--admin-primary); text-decoration: none;">
                                <?php echo htmlspecialchars($msg['email']); ?>
                            </a>
                        </div>
                        <?php if (!empty($msg['phone'])): ?>
                            <div>
                                <span style="font-size: 12px; color: var(--admin-text-muted);"><?php echo __('admin_phone_col'); ?>:</span>
                                <a href="tel:<?php echo htmlspecialchars($msg['phone']); ?>" style="font-size: 12.5px; font-weight: 700; color: var(--admin-text-main); text-decoration: none; dir: ltr;">
                                    <?php echo htmlspecialchars($msg['phone']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="font-size:13px; color:var(--admin-text-main); background:var(--admin-bg-subtle); padding:10px 12px; border-radius:var(--radius-sm); margin-bottom:12px; line-height:1.5; max-height: 60px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </div>

                    <div class="product-mobile-actions-row">
                        <button type="button" class="btn btn-primary btn-sm" style="flex:1; height:38px; font-size:12.5px; font-weight:700; justify-content:center;" onclick='openMessageModal(<?php echo json_encode($msg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                            <i data-lucide="file-text" style="width:14px;"></i>
                            <span><?php echo $lang === 'ar' ? 'عرض الرسالة' : 'View Message'; ?></span>
                        </button>

                        <form action="messages.php" method="POST" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle_read">
                            <input type="hidden" name="id" value="<?php echo $msg_id; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="height:38px; padding: 0 10px;" title="<?php echo $is_read ? ($lang === 'ar' ? 'تعيين كغير مقروءة' : 'Unread') : ($lang === 'ar' ? 'تعيين كمقروءة' : 'Mark Read'); ?>">
                                <i data-lucide="<?php echo $is_read ? 'eye-off' : 'eye'; ?>" style="width:14px;"></i>
                            </button>
                        </form>

                        <?php if (!$is_cs): ?>
                        <form action="messages.php" method="POST" style="margin:0;" onsubmit="return confirmAction(event, '<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف هذه الرسالة نهائياً؟' : 'Are you sure you want to delete this message?'; ?>');">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $msg_id; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="height:38px; color: var(--admin-danger); border-color: var(--admin-danger-light); padding: 0 10px;">
                                <i data-lucide="trash-2" style="width:15px;"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Strip -->
        <?php render_admin_pagination($page, $total_pages, $total_records, count($messages), $lang === 'ar' ? 'رسالة' : 'messages', 'messages.php', ['search' => $search, 'status' => $status_filter]); ?>

    <?php else: ?>
        <!-- Empty State -->
        <?php 
        $emptyTitle = !empty($search) || $status_filter !== 'all' ? ($lang === 'ar' ? 'لم يتم العثور على رسائل مطابقة' : 'No matching messages found') : ($lang === 'ar' ? 'لا توجد رسائل تواصل حتى الآن' : 'No contact messages yet');
        $emptyDesc = !empty($search) || $status_filter !== 'all' ? ($lang === 'ar' ? 'جرب تغيير كلمة البحث أو حالة الفلتر' : 'Try adjusting search keywords or filter status') : ($lang === 'ar' ? 'ستظهر الرسائل التي يرسلها زوار الموقع هنا فور إرسالها' : 'Inquiries submitted through the website contact form will appear here');
        render_admin_empty_state('mail-x', $emptyTitle, $emptyDesc, !empty($search) || $status_filter !== 'all' ? ($lang === 'ar' ? 'إعادة ضبط التصفية' : 'Reset Filters') : '', 'messages.php', true);
        ?>
    <?php endif; ?>
</main>

<!-- View Message Modal Dialog -->
<div id="viewMessageModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: var(--admin-bg-card); width: 100%; max-width: 580px; border-radius: var(--radius-lg); border: 1px solid var(--admin-border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
        <!-- Modal Header -->
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--admin-border-color); display: flex; align-items: center; justify-content: space-between; background: var(--admin-bg-subtle);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--admin-primary-light); color: var(--admin-primary); display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="mail" style="width: 18px; height: 18px;"></i>
                </div>
                <div>
                    <h3 id="modalMsgTitle" style="font-size: 16px; font-weight: 700; margin: 0; color: var(--admin-text-main);"></h3>
                    <span id="modalMsgDate" style="font-size: 12px; color: var(--admin-text-muted);"></span>
                </div>
            </div>
            <button type="button" onclick="closeMessageModal()" style="background: none; border: none; cursor: pointer; color: var(--admin-text-muted); padding: 4px; border-radius: 4px;">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px;">
            <!-- Sender Details Strip -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; background: var(--admin-bg-subtle); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--admin-border-color);">
                <div>
                    <span style="font-size: 11.5px; font-weight: 600; color: var(--admin-text-muted); display: block; margin-bottom: 2px;"><?php echo __('admin_msg_name'); ?>:</span>
                    <strong id="modalSenderName" style="font-size: 14px; color: var(--admin-text-main);"></strong>
                </div>
                <div>
                    <span style="font-size: 11.5px; font-weight: 600; color: var(--admin-text-muted); display: block; margin-bottom: 2px;"><?php echo __('admin_msg_contact'); ?>:</span>
                    <a id="modalSenderEmail" href="#" style="font-size: 13px; font-weight: 600; color: var(--admin-primary); text-decoration: none;"></a>
                </div>
                <div id="modalPhoneContainer">
                    <span style="font-size: 11.5px; font-weight: 600; color: var(--admin-text-muted); display: block; margin-bottom: 2px;"><?php echo __('admin_phone_col'); ?>:</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <a id="modalSenderPhone" href="#" style="font-size: 13px; font-weight: 600; color: var(--admin-text-main); text-decoration: none; dir: ltr;"></a>
                        <a id="modalWhatsappLink" href="#" target="_blank" class="btn btn-sm" style="background: #25d366; color: #fff; border: none; padding: 2px 8px; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                            <i data-lucide="message-circle" style="width: 12px; height: 12px;"></i>
                            <span>واتساب</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Full Message Body -->
            <div>
                <label style="font-size: 12.5px; font-weight: 700; color: var(--admin-text-main); display: block; margin-bottom: 6px;"><?php echo __('admin_msg_content'); ?>:</label>
                <div id="modalMsgContent" style="background: var(--admin-bg-card); border: 1px solid var(--admin-border-color); padding: 14px 16px; border-radius: var(--radius-md); font-size: 13.5px; line-height: 1.6; color: var(--admin-text-main); white-space: pre-wrap; word-break: break-word;"></div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="padding: 14px 20px; border-top: 1px solid var(--admin-border-color); display: flex; justify-content: flex-end; gap: 10px; background: var(--admin-bg-subtle);">
            <a id="modalReplyBtn" href="#" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i data-lucide="mail" style="width: 14px; height: 14px;"></i>
                <span><?php echo $lang === 'ar' ? 'رد عبر البريد الإلكتروني' : 'Reply Email'; ?></span>
            </a>
            <button type="button" onclick="closeMessageModal()" class="btn btn-secondary btn-sm" style="padding: 6px 14px;">
                <?php echo $lang === 'ar' ? 'إغلاق' : 'Close'; ?>
            </button>
        </div>
    </div>
</div>

<script>
function openMessageModal(msgData) {
    document.getElementById('modalMsgTitle').textContent = '<?php echo $lang === 'ar' ? 'تفاصيل الرسالة #' : 'Message Details #'; ?>' + msgData.id;
    document.getElementById('modalMsgDate').textContent = msgData.created_at || '';
    document.getElementById('modalSenderName').textContent = msgData.name || '';
    
    const emailElem = document.getElementById('modalSenderEmail');
    emailElem.textContent = msgData.email || '';
    emailElem.href = 'mailto:' + (msgData.email || '');
    
    const replyBtn = document.getElementById('modalReplyBtn');
    replyBtn.href = 'mailto:' + (msgData.email || '');

    const phoneContainer = document.getElementById('modalPhoneContainer');
    if (msgData.phone) {
        phoneContainer.style.display = 'block';
        const phoneElem = document.getElementById('modalSenderPhone');
        phoneElem.textContent = msgData.phone;
        phoneElem.href = 'tel:' + msgData.phone;

        const cleanPhone = msgData.phone.replace(/[^0-9]/g, '');
        const waLink = document.getElementById('modalWhatsappLink');
        waLink.href = 'https://wa.me/' + cleanPhone;
    } else {
        phoneContainer.style.display = 'none';
    }

    document.getElementById('modalMsgContent').textContent = msgData.message || '';

    const modal = document.getElementById('viewMessageModal');
    modal.style.display = 'flex';
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function closeMessageModal() {
    document.getElementById('viewMessageModal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMessageModal();
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
