<?php
// admin/users.php
// User Management Panel for Customer Service Accounts (Administrator Only)

require_once dirname(__DIR__) . '/config/admin_init.php';

// Enforce full admin access only
if (!is_full_admin()) {
    http_response_code(403);
    exit('Access denied. Only full Administrators can access this page.');
}

$error_message = '';
$success_message = '';
$lang = get_current_lang();

$action = $_GET['action'] ?? '';
$user_id = (int)($_GET['id'] ?? 0);
$user_to_edit = null;

if ($action === 'edit' && $user_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'customer_service' LIMIT 1");
        $stmt->execute([':id' => $user_id]);
        $user_to_edit = $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Handle Create / Update / Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = $lang === 'ar' ? 'رمز الحماية غير صالح.' : 'Invalid security token.';
    } else {
        $post_action = $_POST['action'] ?? '';
        
        if ($post_action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $status = $_POST['status'] ?? 'active';
            
            if (empty($username) || empty($email) || empty($password)) {
                $error_message = $lang === 'ar' ? 'يرجى ملء جميع الحقول المطلوبة.' : 'Please fill all required fields.';
            } else {
                try {
                    // Check username uniqueness
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
                    $stmt->execute([':u' => $username]);
                    if ($stmt->fetch()) {
                        $error_message = $lang === 'ar' ? 'اسم المستخدم مستخدم بالفعل.' : 'Username is already taken.';
                    } else {
                        // Check email uniqueness
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
                        $stmt->execute([':e' => $email]);
                        if ($stmt->fetch()) {
                            $error_message = $lang === 'ar' ? 'البريد الإلكتروني مستخدم بالفعل.' : 'Email is already taken.';
                        } else {
                            // Insert new user (hardcoded role: customer_service)
                            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                            $insert_stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'customer_service', ?)");
                            if ($insert_stmt->execute([$username, $full_name, $email, $hashed_pass, $status])) {
                                $success_message = $lang === 'ar' ? 'تم إنشاء الحساب بنجاح!' : 'Account created successfully!';
                                $action = ''; // Close modal
                            } else {
                                $error_message = $lang === 'ar' ? 'حدث خطأ أثناء إنشاء الحساب.' : 'An error occurred while creating the account.';
                            }
                        }
                    }
                } catch (PDOException $e) {
                    error_log('[WorldOfShelves] Admin create user error: ' . $e->getMessage());
                    $error_message = $lang === 'ar' ? 'حدث خطأ في قاعدة البيانات.' : 'A database error occurred.';
                }
            }
        }
        
        elseif ($post_action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $password = trim($_POST['password'] ?? '');
            
            if (empty($email) || $id <= 0) {
                $error_message = $lang === 'ar' ? 'البريد الإلكتروني حقل مطلوب.' : 'Email field is required.';
            } else {
                try {
                    // Fetch user and verify it is a customer_service role
                    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $id]);
                    $target_user = $stmt->fetch();
                    
                    if (!$target_user || $target_user['role'] !== 'customer_service') {
                        $error_message = $lang === 'ar' ? 'غير مصرح بتعديل هذا الحساب.' : 'Not authorized to update this account.';
                    } else {
                        // Check email uniqueness
                        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = :e AND id != :id LIMIT 1");
                        $check_stmt->execute([':e' => $email, ':id' => $id]);
                        if ($check_stmt->fetch()) {
                            $error_message = $lang === 'ar' ? 'البريد الإلكتروني مستخدم بالفعل.' : 'Email is already taken.';
                        } else {
                            if (!empty($password)) {
                                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                                $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, status = ?, password = ? WHERE id = ?");
                                $success = $update_stmt->execute([$full_name, $email, $status, $hashed_pass, $id]);
                            } else {
                                $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, status = ? WHERE id = ?");
                                $success = $update_stmt->execute([$full_name, $email, $status, $id]);
                            }
                            
                            if ($success) {
                                $success_message = $lang === 'ar' ? 'تم تحديث الحساب بنجاح!' : 'Account updated successfully!';
                                $action = ''; // Close modal
                            } else {
                                $error_message = $lang === 'ar' ? 'حدث خطأ أثناء التحديث.' : 'An error occurred during the update.';
                            }
                        }
                    }
                } catch (PDOException $e) {
                    error_log('[WorldOfShelves] Admin update user error: ' . $e->getMessage());
                    $error_message = $lang === 'ar' ? 'حدث خطأ في قاعدة البيانات.' : 'A database error occurred.';
                }
            }
        }
        
        elseif ($post_action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $error_message = $lang === 'ar' ? 'معرف المستخدم غير صالح.' : 'Invalid user ID.';
            } else {
                try {
                    // Verify it is a customer_service role
                    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $id]);
                    $target_user = $stmt->fetch();
                    
                    if (!$target_user || $target_user['role'] !== 'customer_service') {
                        $error_message = $lang === 'ar' ? 'غير مصرح بحذف هذا الحساب.' : 'Not authorized to delete this account.';
                    } else {
                        $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                        if ($delete_stmt->execute([':id' => $id])) {
                            $success_message = $lang === 'ar' ? 'تم حذف الحساب بنجاح!' : 'Account deleted successfully!';
                        } else {
                            $error_message = $lang === 'ar' ? 'حدث خطأ أثناء الحذف.' : 'An error occurred during deletion.';
                        }
                    }
                } catch (PDOException $e) {
                    error_log('[WorldOfShelves] Admin delete user error: ' . $e->getMessage());
                    $error_message = $lang === 'ar' ? 'حدث خطأ في قاعدة البيانات.' : 'A database error occurred.';
                }
            }
        }
    }
}

// Fetch all customer service users
$cs_users = [];
try {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'customer_service' ORDER BY id DESC");
    $cs_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[WorldOfShelves] Fetch CS users error: ' . $e->getMessage());
}

$page_title = $lang === 'ar' ? 'إدارة مسؤولي التواصل والطلبات' : 'Customer Service Users';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h2>
            <i data-lucide="users" style="vertical-align: middle; margin-inline-end: 8px;"></i>
            <?php echo $lang === 'ar' ? 'إدارة حسابات مسؤول التواصل والطلبات' : 'Customer Service User Accounts'; ?>
        </h2>
        <a href="users.php?action=add" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <i data-lucide="user-plus"></i>
            <span><?php echo $lang === 'ar' ? 'إنشاء حساب جديد' : 'Create Account'; ?></span>
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- User Accounts Table List -->
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: start;"><?php echo $lang === 'ar' ? 'اسم المستخدم' : 'Username'; ?></th>
                        <th style="padding: 12px; text-align: start;"><?php echo $lang === 'ar' ? 'الاسم الكامل' : 'Full Name'; ?></th>
                        <th style="padding: 12px; text-align: start;"><?php echo $lang === 'ar' ? 'البريد الإلكتروني' : 'Email'; ?></th>
                        <th style="padding: 12px; text-align: start;"><?php echo $lang === 'ar' ? 'الحالة' : 'Status'; ?></th>
                        <th style="padding: 12px; text-align: center;"><?php echo $lang === 'ar' ? 'إجراءات' : 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($cs_users) > 0): ?>
                        <?php foreach ($cs_users as $user): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px; font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td style="padding: 12px;">
                                    <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                        <span class="badge badge-success" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px;">
                                            <?php echo $lang === 'ar' ? 'نشط' : 'Active'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px;">
                                            <?php echo $lang === 'ar' ? 'معطل' : 'Disabled'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <div style="display: inline-flex; gap: 8px;">
                                        <!-- Edit Link -->
                                        <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">
                                            <i data-lucide="edit-2"></i>
                                        </a>

                                        <!-- Delete Account Form -->
                                        <form method="POST" action="" onsubmit="return confirm('<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف هذا الحساب نهائياً؟' : 'Are you sure you want to permanently delete this account?'; ?>');" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; color: var(--danger); border-color: rgba(239, 68, 68, 0.2);">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 24px; text-align: center; color: var(--text-muted);">
                                <?php echo $lang === 'ar' ? 'لا يوجد حسابات لمسؤولي التواصل حالياً.' : 'No customer service accounts found.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Modal Overlay -->
<?php if ($action === 'add' || ($action === 'edit' && $user_to_edit)): ?>
    <div class="admin-modal" id="userModal" style="display: flex; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1100;">
        <div class="admin-modal-content" style="background: var(--bg-card, #fff); border-radius: var(--radius-lg); width: 100%; max-width: 500px; padding: 25px; box-shadow: var(--shadow-xl); position: relative; margin: 15px;">
            <div class="admin-modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px;">
                <h3 style="margin: 0;">
                    <?php echo $action === 'edit' ? ($lang === 'ar' ? 'تعديل حساب مسؤول التواصل' : 'Edit Account') : ($lang === 'ar' ? 'إنشاء حساب مسؤول تواصل جديد' : 'Create New Account'); ?>
                </h3>
                <a href="users.php" class="icon-btn" style="text-decoration: none; color: var(--text-muted); font-size: 20px;"><i data-lucide="x"></i></a>
            </div>
            
            <form method="POST" action="users.php" style="display: flex; flex-direction: column; gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="<?php echo $action === 'edit' ? 'update' : 'create'; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $user_to_edit['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label><?php echo $lang === 'ar' ? 'اسم المستخدم *' : 'Username *'; ?></label>
                    <input type="text" name="username" required class="form-control" value="<?php echo $user_to_edit ? htmlspecialchars($user_to_edit['username']) : ''; ?>" <?php echo $action === 'edit' ? 'readonly style="background: var(--bg-main);"' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label><?php echo $lang === 'ar' ? 'الاسم الكامل' : 'Full Name'; ?></label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo $user_to_edit ? htmlspecialchars($user_to_edit['full_name'] ?? '') : ''; ?>">
                </div>

                <div class="form-group">
                    <label><?php echo $lang === 'ar' ? 'البريد الإلكتروني *' : 'Email *'; ?></label>
                    <input type="email" name="email" required class="form-control" value="<?php echo $user_to_edit ? htmlspecialchars($user_to_edit['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>
                        <?php if ($action === 'edit'): ?>
                            <?php echo $lang === 'ar' ? 'كلمة المرور الجديدة (اختياري)' : 'New Password (Optional)'; ?>
                        <?php else: ?>
                            <?php echo $lang === 'ar' ? 'كلمة المرور *' : 'Password *'; ?>
                        <?php endif; ?>
                    </label>
                    <input type="password" name="password" class="form-control" <?php echo $action === 'edit' ? '' : 'required'; ?>>
                </div>

                <div class="form-group">
                    <label><?php echo $lang === 'ar' ? 'حالة الحساب' : 'Account Status'; ?></label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo ($user_to_edit && ($user_to_edit['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'نشط' : 'Active'; ?></option>
                        <option value="disabled" <?php echo ($user_to_edit && ($user_to_edit['status'] ?? 'active') === 'disabled') ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'معطل' : 'Disabled'; ?></option>
                    </select>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
                    <a href="users.php" class="btn btn-secondary"><?php echo $lang === 'ar' ? 'إلغاء' : 'Cancel'; ?></a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'edit' ? ($lang === 'ar' ? 'حفظ التعديلات' : 'Save Changes') : ($lang === 'ar' ? 'إنشاء الحساب' : 'Create Account'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
