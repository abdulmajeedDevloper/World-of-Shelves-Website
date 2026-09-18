<?php
// admin/profile.php
require_once dirname(__DIR__) . '/config/admin_init.php';

$error_message = '';
$success_message = '';

// Get current admin user
$username_session = $_SESSION['admin_username'];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username_session]);
    $admin = $stmt->fetch();
    if (!$admin) {
        admin_destroy_session('session_invalid');
    }
} catch (PDOException $e) {
    error_log('[WorldOfShelves] Profile load error: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = "رمز الحماية (CSRF Token) غير صالح.";
    } else {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        
        if (empty($username) || empty($email)) {
            $error_message = get_current_lang() === 'ar' ? 'اسم المستخدم والبريد الإلكتروني حقول مطلوبة.' : 'Username and email are required.';
        } else {
            try {
                // Check if username is taken by another user
                $check_user = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1");
                $check_user->execute([':username' => $username, ':id' => $admin['id']]);
                if ($check_user->fetch()) {
                    $error_message = get_current_lang() === 'ar' ? 'اسم المستخدم مستخدم بالفعل.' : 'Username is already taken.';
                }
                
                // Check if email is taken
                if (empty($error_message)) {
                    $check_email = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
                    $check_email->execute([':email' => $email, ':id' => $admin['id']]);
                    if ($check_email->fetch()) {
                        $error_message = get_current_lang() === 'ar' ? 'البريد الإلكتروني مستخدم بالفعل.' : 'Email is already taken.';
                    }
                }
                
                if (empty($error_message)) {
                    // Handle image upload
                    $image_path = $admin['image'];
                    if (isset($_FILES['admin_image']) && $_FILES['admin_image']['error'] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['admin_image']['tmp_name'];
                        $file_name = basename($_FILES['admin_image']['name']);
                        $file_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $file_name);
                        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
                        if (in_array($ext, $allowed_exts)) {
                            $dest_dir = dirname(__DIR__) . '/assets/images/';
                            if (!is_dir($dest_dir)) {
                                mkdir($dest_dir, 0755, true);
                            }
                            $new_filename = 'admin_avatar_' . time() . '.' . $ext;
                            $dest_path = $dest_dir . $new_filename;
                            
                            if (move_uploaded_file($file_tmp, $dest_path)) {
                                $image_path = 'assets/images/' . $new_filename;
                            }
                        } else {
                            $error_message = get_current_lang() === 'ar' ? 'نوع الملف غير مسموح به. امتدادات الصور المدعومة: png, jpg, jpeg, gif, svg, webp' : 'Invalid file type. Supported extensions: png, jpg, jpeg, gif, svg, webp';
                        }
                    }
                }
                
                if (empty($error_message)) {
                    // Update database
                    if (!empty($password)) {
                        // Password change
                        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                        $update_stmt = $pdo->prepare("UPDATE users SET username = :username, full_name = :full_name, email = :email, password = :password, image = :image WHERE id = :id");
                        $update_stmt->execute([
                            ':username' => $username,
                            ':full_name' => $full_name,
                            ':email' => $email,
                            ':password' => $hashed_pass,
                            ':image' => $image_path,
                            ':id' => $admin['id']
                        ]);
                    } else {
                        // Keep password unchanged
                        $update_stmt = $pdo->prepare("UPDATE users SET username = :username, full_name = :full_name, email = :email, image = :image WHERE id = :id");
                        $update_stmt->execute([
                            ':username' => $username,
                            ':full_name' => $full_name,
                            ':email' => $email,
                            ':image' => $image_path,
                            ':id' => $admin['id']
                        ]);
                    }
                    
                    // Update session variables
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_email'] = $email;
                    
                    // Reload admin data
                    $admin['username'] = $username;
                    $admin['full_name'] = $full_name;
                    $admin['email'] = $email;
                    $admin['image'] = $image_path;
                    
                    $success_message = get_current_lang() === 'ar' ? 'تم تحديث الملف الشخصي بنجاح.' : 'Profile updated successfully.';
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}

$page_title = get_current_lang() === 'ar' ? 'حساب المسؤول' : 'Admin Profile';
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

    <!-- Admin Content Area -->
    <main class="admin-content">
        <header class="admin-header">
            <div>
                <h1 style="font-size: 28px;"><?php echo get_current_lang() === 'ar' ? 'الملف الشخصي للمسؤول' : 'Admin Profile'; ?></h1>
                <p><?php echo get_current_lang() === 'ar' ? 'تعديل الاسم البرمجي وكلمة المرور وصورة الحساب الشخصية' : 'Manage your admin credentials, name, and profile avatar'; ?></p>
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

        <!-- Profile Form Card -->
        <section class="admin-table-card" style="padding: 30px;">
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="update_profile" value="1">
                
                <!-- Profile Avatar Preview Section -->
                <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 30px; flex-wrap: wrap;">
                    <div style="position: relative; width: 100px; height: 100px;">
                        <?php if (!empty($admin['image']) && file_exists(dirname(__DIR__) . '/' . $admin['image'])): ?>
                            <img src="../<?php echo htmlspecialchars($admin['image']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-primary);">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; border-radius: 50%; background-color: var(--accent-light); color: var(--accent-primary); display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: bold; border: 3px solid var(--accent-primary);">
                                <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 style="font-size: 16px; font-weight: bold; margin-bottom: 6px;"><?php echo htmlspecialchars($admin['full_name'] ?: $admin['username']); ?></h3>
                        <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 12px;"><?php echo htmlspecialchars($admin['email']); ?></p>
                        <div style="position: relative;">
                            <input type="file" name="admin_image" id="admin_image" accept="image/*" style="display: none;" onchange="document.getElementById('fileName').textContent = this.files[0] ? this.files[0].name : '';">
                            <label for="admin_image" class="btn btn-secondary btn-sm" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <i data-lucide="upload" style="width: 14px;"></i>
                                <span><?php echo get_current_lang() === 'ar' ? 'رفع صورة جديدة' : 'Upload New Photo'; ?></span>
                            </label>
                            <span id="fileName" style="font-size: 12px; margin-inline-start: 10px; color: var(--text-muted);"></span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username"><?php echo get_current_lang() === 'ar' ? 'اسم المستخدم' : 'Username'; ?> *</label>
                        <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="full_name"><?php echo get_current_lang() === 'ar' ? 'الاسم بالكامل' : 'Full Name'; ?></label>
                        <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email"><?php echo get_current_lang() === 'ar' ? 'البريد الإلكتروني' : 'Email Address'; ?> *</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password"><?php echo get_current_lang() === 'ar' ? 'كلمة المرور الجديدة' : 'New Password'; ?></label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="<?php echo get_current_lang() === 'ar' ? 'اتركه فارغاً للإبقاء على الحالية' : 'Leave blank to keep current'; ?>">
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px; display: inline-flex; align-items: center; gap: 8px;">
                        <i data-lucide="save"></i>
                        <span><?php echo __('admin_save'); ?></span>
                    </button>
                </div>
            </form>
        </section>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
