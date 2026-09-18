<?php
// config/init.php

// 0. Production Error Settings
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 1. Core Constants
require_once __DIR__ . '/constants.php';

// 2. Session Initialization (secure, idempotent)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 3. Set Default Timezone
date_default_timezone_set('Asia/Riyadh');

// 4. Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: https:; frame-src 'self' https://www.google.com https://*.google.com https://www.googletagmanager.com; connect-src 'self' https://*.google-analytics.com https://www.facebook.com;");

// 5. Dependencies
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/lang_helper.php';
require_once CONFIG_PATH . '/db.php';
