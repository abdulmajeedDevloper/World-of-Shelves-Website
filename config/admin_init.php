<?php
// config/admin_init.php
// Admin bootstrap: inherits init.php, adds flash helpers, session security, and auth enforcement.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

// Load main initialization (handles DB, session, environment, security headers)
require_once __DIR__ . '/init.php';
require_once dirname(__DIR__) . '/includes/admin-components.php';

// Set timezone for admin area
date_default_timezone_set('Asia/Riyadh');

// ============================================================
//  Centralized Admin Session Destroy Helper
// ============================================================
if (!function_exists('admin_destroy_session')) {
    /**
     * Completely destroys the admin session, deletes the session cookie,
     * and redirects to admin/login.php with an optional reason code.
     *
     * @param string $reason  Query parameter appended to login.php URL (e.g. 'idle', 'absolute', 'session_invalid')
     * @return void (exits)
     */
    function admin_destroy_session($reason = '') {
        // Clear all session data
        $_SESSION = [];

        // Delete the session cookie from the browser
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy the session file on the server
        session_destroy();

        // Build redirect URL
        $redirect = 'login.php';
        if ($reason !== '') {
            $redirect .= '?reason=' . urlencode($reason);
        }

        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================
//  Flash message helpers (session-based, one-time read)
// ============================================================
if (!function_exists('set_flash')) {
    function set_flash($type, $message) {
        $_SESSION['flash_' . $type] = $message;
    }
}
if (!function_exists('get_flash')) {
    function get_flash($type) {
        if (isset($_SESSION['flash_' . $type])) {
            $msg = $_SESSION['flash_' . $type];
            unset($_SESSION['flash_' . $type]);
            return $msg;
        }
        return '';
    }
}

// ============================================================
//  Centralized Role-Based Access Control (RBAC) Helpers
// ============================================================
if (!function_exists('current_admin_role')) {
    function current_admin_role() {
        return $_SESSION['admin_role'] ?? 'admin';
    }
}

if (!function_exists('is_full_admin')) {
    function is_full_admin() {
        return current_admin_role() === 'admin';
    }
}

if (!function_exists('require_admin_role')) {
    function require_admin_role($allowed_roles) {
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        $role = current_admin_role();
        if (!in_array($role, $allowed_roles, true)) {
            http_response_code(403);
            exit('Access denied. Insufficient permissions.');
        }
    }
}

// ============================================================
//  Auth enforcement (unless explicitly bypassed, e.g. login.php)
// ============================================================
if (!defined('BYPASS_ADMIN_AUTH') || BYPASS_ADMIN_AUTH !== true) {

    // --- Basic login check ---
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: login.php");
        exit;
    }

    // --- Idle Timeout (30 minutes of inactivity) ---
    if (isset($_SESSION['admin_last_activity'])) {
        $idle_duration = time() - $_SESSION['admin_last_activity'];
        if ($idle_duration > ADMIN_IDLE_TIMEOUT_SECONDS) {
            admin_destroy_session('idle');
        }
    }

    // --- Absolute Timeout (8 hours maximum session lifetime) ---
    if (isset($_SESSION['admin_login_time'])) {
        $session_age = time() - $_SESSION['admin_login_time'];
        if ($session_age > ADMIN_ABSOLUTE_TIMEOUT_SECONDS) {
            admin_destroy_session('absolute');
        }
    }

    // --- Update last activity timestamp (must be AFTER timeout checks) ---
    $_SESSION['admin_last_activity'] = time();

    // --- Periodic Session ID Regeneration (every 15 minutes) ---
    $now = time();
    if (!isset($_SESSION['admin_session_regenerated_at'])) {
        $_SESSION['admin_session_regenerated_at'] = $now;
    } elseif (($now - $_SESSION['admin_session_regenerated_at']) > ADMIN_SESSION_REGENERATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['admin_session_regenerated_at'] = $now;
    }

    // --- Re-validate session against database (on every request) ---
    if (isset($_SESSION['admin_username']) && isset($pdo)) {
        try {
            $auth_stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE username = :u LIMIT 1");
            $auth_stmt->execute([':u' => $_SESSION['admin_username']]);
            $auth_row = $auth_stmt->fetch();
            if (!$auth_row) {
                // User deleted
                admin_destroy_session('session_invalid');
            }
            
            // Verify role is still valid
            $db_role = $auth_row['role'] ?? 'admin';
            if ($db_role !== 'admin' && $db_role !== 'customer_service') {
                admin_destroy_session('session_invalid');
            }
            
            // Verify role in session matches role in DB (prevents role hijacking)
            if (($_SESSION['admin_role'] ?? '') !== $db_role) {
                admin_destroy_session('session_invalid');
            }
            
            // Verify account status is active
            $db_status = $auth_row['status'] ?? 'active';
            if ($db_status !== 'active') {
                admin_destroy_session('session_invalid');
            }
        } catch (PDOException $e) {
            error_log('[WorldOfShelves] Admin auth re-validation error: ' . $e->getMessage());
        }
    }

    // --- Centralized File-level RBAC Page Protection ---
    $current_page = basename($_SERVER['SCRIPT_FILENAME']);
    $allowed_cs_pages = ['index.php', 'orders.php', 'messages.php', 'global-ui.php', 'logout.php'];
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'customer_service') {
        if (!in_array($current_page, $allowed_cs_pages, true)) {
            http_response_code(403);
            exit('Access denied. Insufficient permissions.');
        }
    }

    // --- No-cache headers for all authenticated admin pages ---
    // Prevents the browser Back button from showing cached protected content after logout.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

}
