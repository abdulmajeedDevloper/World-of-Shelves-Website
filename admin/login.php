<?php
// admin/login.php
define('BYPASS_ADMIN_AUTH', true);
require_once dirname(__DIR__) . '/config/admin_init.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// Rate limiting: max 5 failed attempts per 10-minute window (session-based)
$max_attempts  = 5;
$lockout_secs  = 600; // 10 minutes
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_lockout_until'])) {
    $_SESSION['login_lockout_until'] = 0;
}

$is_locked     = (time() < $_SESSION['login_lockout_until']);
$lockout_remaining = max(0, $_SESSION['login_lockout_until'] - time());

$error_message = '';

// Session-expiry reason feedback (localized)
if (isset($_GET['reason'])) {
    $reason = $_GET['reason'];
    $is_ar = (get_current_lang() === 'ar');
    if ($reason === 'idle') {
        $error_message = $is_ar
            ? 'انتهت جلستك بسبب عدم النشاط. يرجى تسجيل الدخول مرة أخرى.'
            : 'Your session expired due to inactivity. Please log in again.';
    } elseif ($reason === 'absolute') {
        $error_message = $is_ar
            ? 'انتهت مدة الجلسة القصوى. يرجى تسجيل الدخول مرة أخرى.'
            : 'Your session reached the maximum duration. Please log in again.';
    } elseif ($reason === 'session_invalid') {
        $error_message = $is_ar
            ? 'جلستك لم تعد صالحة. يرجى تسجيل الدخول مرة أخرى.'
            : 'Your session is no longer valid. Please log in again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_locked) {
        $mins = ceil($lockout_remaining / 60);
        $error_message = get_current_lang() === 'ar'
            ? "تم تجاوز الحد الأقصى لمحاولات الدخول. يرجى الانتظار {$mins} دقيقة."
            : "Too many failed attempts. Please wait {$mins} minute(s).";
    } elseif (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = get_current_lang() === 'ar' ? 'رمز الحماية غير صالح.' : 'Invalid security token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error_message = __('invalid_input');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $role = $user['role'] ?? 'admin';
                    $status = $user['status'] ?? 'active';
                    
                    if ($role !== 'admin' && $role !== 'customer_service') {
                        $error_message = get_current_lang() === 'ar' ? 'غير مصرح لك بدخول لوحة التحكم.' : 'You are not authorized to access the admin panel.';
                    } elseif ($status !== 'active') {
                        $error_message = get_current_lang() === 'ar' ? 'تم تعطيل هذا الحساب.' : 'This account has been disabled.';
                    } else {
                        // Success: regenerate session ID immediately to prevent fixation
                        session_regenerate_id(true);

                        // Clear rate-limiting state
                        $_SESSION['login_attempts']     = 0;
                        $_SESSION['login_lockout_until'] = 0;

                        // Set authentication flags
                        $_SESSION['admin_logged_in']    = true;
                        $_SESSION['admin_username']     = $user['username'];
                        $_SESSION['admin_email']        = $user['email'];
                        $_SESSION['admin_role']         = $role;

                        // Stamp session security timestamps
                        $now = time();
                        $_SESSION['admin_login_time']             = $now;
                        $_SESSION['admin_last_activity']          = $now;
                        $_SESSION['admin_session_regenerated_at'] = $now;

                        header("Location: index.php");
                        exit;
                    }
                } else {
                    $_SESSION['login_attempts']++;
                    if ($_SESSION['login_attempts'] >= $max_attempts) {
                        $_SESSION['login_lockout_until'] = time() + $lockout_secs;
                        $_SESSION['login_attempts']      = 0;
                    }
                    $error_message = __('admin_login_error');
                }
            } catch (PDOException $e) {
                error_log('[WorldOfShelves] Login error: ' . $e->getMessage());
                $error_message = get_current_lang() === 'ar' ? 'خطأ في الخادم. يرجى المحاولة لاحقاً.' : 'Server error. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>" dir="<?php echo get_lang_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('admin_login_title'); ?> - <?php echo __('brand_name'); ?></title>
    
    <?php 
    require_once dirname(__DIR__) . '/includes/favicon_helper.php';
    $favicon_val = get_setting('site_favicon'); 
    if (!empty($favicon_val)): 
        $favicon_ext = pathinfo($favicon_val, PATHINFO_EXTENSION);
        $favicon_type = ($favicon_ext === 'ico') ? 'image/x-icon' : 'image/png';
        $favicon_href = get_favicon_asset_url($favicon_val);
        $sizes_attr = ($favicon_ext === 'ico') ? ' sizes="any"' : '';
    ?>
        <link rel="shortcut icon" href="<?php echo $favicon_href; ?>">
        <link rel="icon" type="<?php echo $favicon_type; ?>"<?php echo $sizes_attr; ?> href="<?php echo $favicon_href; ?>">
        <link rel="apple-touch-icon" href="<?php echo $favicon_href; ?>">
    <?php else: ?>
        <link rel="shortcut icon" href="<?php echo get_favicon_asset_url('favicon.ico'); ?>">
        <link rel="icon" type="image/x-icon" sizes="any" href="<?php echo get_favicon_asset_url('favicon.ico'); ?>">
        <link rel="icon" type="image/png" sizes="16x16" href="<?php echo get_favicon_asset_url('favicon-16x16.png'); ?>">
        <link rel="icon" type="image/png" sizes="32x32" href="<?php echo get_favicon_asset_url('favicon-32x32.png'); ?>">
        <link rel="icon" type="image/png" sizes="48x48" href="<?php echo get_favicon_asset_url('favicon-48x48.png'); ?>">
        <link rel="icon" type="image/png" sizes="96x96" href="<?php echo get_favicon_asset_url('favicon-96x96.png'); ?>">
        <link rel="icon" type="image/png" sizes="192x192" href="<?php echo get_favicon_asset_url('android-chrome-192x192.png'); ?>">
        <link rel="icon" type="image/svg+xml" href="<?php echo get_favicon_asset_url('assets/images/favicon.svg'); ?>">
        <link rel="apple-touch-icon" sizes="180x180" href="<?php echo get_favicon_asset_url('apple-touch-icon.png'); ?>">
    <?php endif; ?>
    <link rel="manifest" href="<?php echo get_favicon_manifest_url(); ?>">

    <script src="https://unpkg.com/lucide@0.344.0"></script>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(dirname(__DIR__) . '/assets/css/style.css') ? filemtime(dirname(__DIR__) . '/assets/css/style.css') : '3.0'; ?>">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: var(--bg-primary);">

    <div class="checkout-card" style="width: 100%; max-width: 420px; box-shadow: var(--shadow-lg); border-radius: var(--radius-lg);">
        <div style="text-align: center; margin-bottom: 24px;">
            <a href="../index.php" class="logo" style="justify-content: center; font-size: 28px; margin-bottom: 12px;">
                <i data-lucide="layout-grid" style="stroke: var(--accent-primary);"></i>
                <span><?php echo __('brand_name'); ?></span>
            </a>
            <p><?php echo __('admin_login_title'); ?></p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div style="background-color: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-md); margin-bottom: 16px; font-size: 14px; font-weight: 600; text-align: center;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="form-group">
                <label for="username"><?php echo __('admin_username'); ?></label>
                <input type="text" name="username" id="username" class="form-control" placeholder="admin" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 30px;">
                <label for="password"><?php echo __('admin_password'); ?></label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                <span><?php echo __('admin_login_btn'); ?></span>
                <i data-lucide="log-in"></i>
            </button>
        </form>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
