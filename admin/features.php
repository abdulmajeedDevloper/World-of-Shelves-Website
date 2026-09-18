<?php
// admin/features.php
require_once dirname(__DIR__) . '/config/admin_init.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

// Handle Delete Action
if ($action === 'delete' && $id > 0) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        $error_message = "رمز الحماية (CSRF Token) غير صالح.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM features WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $success_message = "تم حذف الميزة بنجاح.";
            header("Location: features.php");
            exit;
        } catch (PDOException $e) {
            $error_message = "خطأ أثناء حذف الميزة: " . $e->getMessage();
        }
    }
}

// Handle Add/Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_feature'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = "رمز الحماية (CSRF Token) غير صالح.";
    } else {
        $title_ar = trim($_POST['title_ar']);
        $title_en = trim($_POST['title_en']);
        $description_ar = trim($_POST['description_ar']);
        $description_en = trim($_POST['description_en']);
        $icon = trim($_POST['icon']);

        if (empty($title_ar) || empty($title_en)) {
            $error_message = "يرجى ملء العناوين الإلزامية.";
        } else {
            if (empty($icon)) {
                $icon = 'shield-check';
            }
            
            try {
                if ($id > 0) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE features SET title_ar = :title_ar, title_en = :title_en, description_ar = :description_ar, description_en = :description_en, icon = :icon WHERE id = :id");
                    $stmt->execute([
                        ':title_ar' => $title_ar,
                        ':title_en' => $title_en,
                        ':description_ar' => $description_ar,
                        ':description_en' => $description_en,
                        ':icon' => $icon,
                        ':id' => $id
                    ]);
                    $success_message = "تم تحديث الميزة بنجاح.";
                } else {
                    // Add
                    $stmt = $pdo->prepare("INSERT INTO features (title_ar, title_en, description_ar, description_en, icon) VALUES (:title_ar, :title_en, :description_ar, :description_en, :icon)");
                    $stmt->execute([
                        ':title_ar' => $title_ar,
                        ':title_en' => $title_en,
                        ':description_ar' => $description_ar,
                        ':description_en' => $description_en,
                        ':icon' => $icon
                    ]);
                    $success_message = "تم إضافة الميزة الجديدة بنجاح.";
                }
                header("Location: features.php");
                exit;
            } catch (PDOException $e) {
                $error_message = "خطأ في قاعدة البيانات: " . $e->getMessage();
            }
        }
    }
}

// Fetch Feature for Editing
$feature_to_edit = null;
if (($action === 'edit' || $action === 'add') && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM features WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $feature_to_edit = $stmt->fetch();
}

// Fetch all features for table
try {
    $feat_stmt = $pdo->query("SELECT * FROM features ORDER BY id DESC");
    $features = $feat_stmt->fetchAll();
} catch (PDOException $e) {
    $features = [];
}

$page_title = __('admin_manage_features');
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

    <!-- Admin Content Area -->
    <main class="admin-content">
        <header class="admin-header">
            <div>
                <h1 style="font-size: 28px;"><?php echo __('feat_title'); ?></h1>
                <p><?php echo __('feat_subtitle'); ?></p>
            </div>
            
            <a href="?lang=<?php echo get_current_lang() === 'ar' ? 'en' : 'ar'; ?>" class="lang-toggle">
                <i data-lucide="globe"></i>
                <span><?php echo get_current_lang() === 'ar' ? 'English' : 'العربية'; ?></span>
            </a>
        </header>

        <!-- Message Alerts -->
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

        <!-- Features Table Card -->
        <section class="admin-table-card">
            <div class="admin-table-card-header">
                <h3 style="font-size: 18px;"><?php echo __('feat_table_header'); ?></h3>
                <a href="features.php?action=add" class="btn btn-primary btn-sm">
                    <i data-lucide="plus" style="width:16px;"></i>
                    <span><?php echo __('feat_add_btn'); ?></span>
                </a>
            </div>
            
            <?php if (count($features) > 0): ?>
                <!-- Swipe Hint -->
                <div class="admin-table-scroll-hint">
                    <i data-lucide="info" style="width: 14px; height: 14px;"></i>
                    <span><?php echo __('admin_table_scroll_hint'); ?></span>
                </div>

                <div class="admin-table-wrapper">
                    <table class="admin-table admin-table--features">
                        <thead>
                            <tr>
                                <th><?php echo __('feat_col_icon'); ?></th>
                                <th><?php echo __('feat_col_title_ar'); ?></th>
                                <th><?php echo __('feat_col_title_en'); ?></th>
                                <th><?php echo __('feat_col_desc_ar'); ?></th>
                                <th><?php echo __('feat_col_actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($features as $feat): ?>
                                <tr>
                                    <td>
                                        <div style="background: var(--bg-main); width: 40px; height: 40px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--accent-secondary);">
                                            <i data-lucide="<?php echo htmlspecialchars($feat['icon']); ?>"></i>
                                        </div>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($feat['title_ar']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($feat['title_en']); ?></td>
                                    <td style="font-size: 13px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($feat['description_ar']); ?>
                                    </td>
                                    <td class="actions-cell">
                                        <div style="display: flex; gap: 8px;">
                                            <a href="features.php?action=edit&id=<?php echo $feat['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px;">
                                                <i data-lucide="edit-3" style="width: 14px;"></i>
                                            </a>
                                            <a href="features.php?action=delete&id=<?php echo $feat['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px; color: var(--danger); border-color: var(--danger-light);" onclick="return confirm('<?php echo addslashes(__('feat_delete_confirm')); ?>')">
                                                <i data-lucide="trash-2" style="width: 14px;"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="admin-empty-state">
                    <i data-lucide="sparkles" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 12px; display: inline-block;"></i>
                    <p style="font-size: 15px; color: var(--text-secondary); margin: 0;"><?php echo __('feat_no_records'); ?></p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Add / Edit Modal Overlay -->
        <?php if ($action === 'add' || ($action === 'edit' && $feature_to_edit)): ?>
            <div class="admin-modal" id="featureModal">
                <div class="admin-modal-content">
                    <div class="admin-modal-header">
                        <h2><?php echo $action === 'edit' ? __('feat_modal_edit') : __('feat_modal_add'); ?></h2>
                        <a href="features.php" class="icon-btn"><i data-lucide="x"></i></a>
                    </div>
                    
                    <form action="features.php<?php echo $action === 'edit' ? '?id='.$id : ''; ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="save_feature" value="1">
                        
                        <div class="admin-modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="title_ar"><?php echo __('feat_field_title_ar'); ?></label>
                                    <input type="text" name="title_ar" id="title_ar" class="form-control" dir="rtl" value="<?php echo $feature_to_edit ? htmlspecialchars($feature_to_edit['title_ar']) : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="title_en"><?php echo __('feat_field_title_en'); ?></label>
                                    <input type="text" name="title_en" id="title_en" class="form-control" dir="ltr" value="<?php echo $feature_to_edit ? htmlspecialchars($feature_to_edit['title_en']) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="icon"><?php echo __('feat_field_icon'); ?></label>
                                <input type="text" name="icon" id="icon" class="form-control" dir="ltr" value="<?php echo $feature_to_edit ? htmlspecialchars($feature_to_edit['icon']) : ''; ?>" placeholder="e.g. shield-check, wrench, truck, star, heart" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description_ar"><?php echo __('feat_field_desc_ar'); ?></label>
                                <textarea name="description_ar" id="description_ar" class="form-control" dir="rtl" rows="3"><?php echo $feature_to_edit ? htmlspecialchars($feature_to_edit['description_ar']) : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="description_en"><?php echo __('feat_field_desc_en'); ?></label>
                                <textarea name="description_en" id="description_en" class="form-control" dir="ltr" rows="3"><?php echo $feature_to_edit ? htmlspecialchars($feature_to_edit['description_en']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="admin-modal-footer">
                            <a href="features.php" class="btn btn-secondary"><?php echo __('admin_cancel'); ?></a>
                            <button type="submit" class="btn btn-primary"><?php echo __('admin_save'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
