<?php
// config/constants.php

// Define application root path (absolute path to the project root)
define('BASE_PATH', dirname(__DIR__));

// Define common directory paths
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('LANG_PATH', BASE_PATH . '/lang');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Upload path for user-generated media
define('UPLOADS_PATH', ASSETS_PATH . '/images');

// Application meta
define('APP_NAME', 'World of Shelves');
define('APP_VERSION', '1.0.0');

// Admin Session Security Policy
define('ADMIN_IDLE_TIMEOUT_SECONDS', 1800);        // 30 minutes of inactivity
define('ADMIN_ABSOLUTE_TIMEOUT_SECONDS', 28800);    // 8 hours absolute maximum
define('ADMIN_SESSION_REGENERATE_SECONDS', 900);     // Regenerate session ID every 15 minutes
