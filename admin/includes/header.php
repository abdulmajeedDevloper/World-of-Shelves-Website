<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>" dir="<?php echo get_lang_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : __('admin_dashboard'); ?> - <?php echo __('brand_name'); ?></title>
    
    <?php 
    require_once dirname(dirname(__DIR__)) . '/includes/favicon_helper.php';
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
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(dirname(dirname(__DIR__)) . '/assets/css/style.css') ? filemtime(dirname(dirname(__DIR__)) . '/assets/css/style.css') : '3.0'; ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo file_exists(dirname(dirname(__DIR__)) . '/assets/css/admin.css') ? filemtime(dirname(dirname(__DIR__)) . '/assets/css/admin.css') : '2.8'; ?>">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo file_exists(dirname(dirname(__DIR__)) . '/assets/css/admin-enterprise.css') ? filemtime(dirname(dirname(__DIR__)) . '/assets/css/admin-enterprise.css') : '1.0'; ?>">
</head>
<body>

<div class="admin-layout">
    <!-- Mobile Navigation Top Bar -->
    <div class="admin-mobile-nav">
        <button id="adminSidebarToggle" class="mobile-menu-toggle" aria-controls="adminSidebar" aria-expanded="false" aria-label="<?php echo get_current_lang() === 'ar' ? 'تبويب القائمة' : 'Toggle navigation menu'; ?>">
            <i data-lucide="menu"></i>
        </button>
        <span class="admin-mobile-logo-text"><?php echo htmlspecialchars(__('brand_name')); ?></span>
        <div style="width: 24px;"></div>
    </div>
    
    <!-- Sidebar Overlay backdrop -->
    <div id="adminSidebarOverlay" class="admin-sidebar-overlay"></div>

    <!-- Admin Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Admin Content Area -->
    <main class="admin-content">
