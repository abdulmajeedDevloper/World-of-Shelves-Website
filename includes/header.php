<?php
// includes/header.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

define('HEADER_LOADED', true);

// Bootstrap: use init.php if not already loaded via config; otherwise db.php is already loaded.
// require_once is idempotent — safe to call regardless of load order.
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config/constants.php';
}
require_once INCLUDES_PATH . '/lang_helper.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/seo-helper.php';
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>" dir="<?php echo get_lang_direction(); ?>">
<head>
    <base href="<?php echo get_site_url(); ?>/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Favicon & Manifest -->
    <?php 
    require_once __DIR__ . '/favicon_helper.php';
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

    <!-- Local Fonts & Font Preloading -->
    <link rel="stylesheet" href="assets/css/local_fonts.css?v=<?php echo file_exists(dirname(__DIR__) . '/assets/css/local_fonts.css') ? filemtime(dirname(__DIR__) . '/assets/css/local_fonts.css') : '1.0'; ?>">
    <?php if (get_current_lang() === 'ar'): ?>
        <link rel="preload" href="assets/fonts/cairo-400-normal-93eb798906f254b70b8d347485f5780f.woff2" as="font" type="font/woff2" crossorigin>
    <?php else: ?>
        <link rel="preload" href="assets/fonts/outfit-400-normal-357083765ab387df4e1e14decb5328af.woff2" as="font" type="font/woff2" crossorigin>
    <?php endif; ?>

    <!-- Preload Hero LCP Image if defined by controller -->
    <?php if (!empty($preloadImage)): ?>
        <link rel="preload" href="<?php echo htmlspecialchars($preloadImage, ENT_QUOTES, 'UTF-8'); ?>" as="image" fetchpriority="high">
    <?php endif; ?>

    <!-- Deferred Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@0.344.0" defer></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(dirname(__DIR__) . '/assets/css/style.css') ? filemtime(dirname(__DIR__) . '/assets/css/style.css') : '3.0'; ?>">

    <!-- Dark Mode FOUC Prevention -->
    <script>
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
</head>
<body>
<?php
// Inject Analytics Noscript Fallbacks immediately after body start
$gtm_id = get_setting('analytics_google_gtm');
$is_gtm_valid = !empty($gtm_id) && preg_match('/^GTM-[A-Z0-9]+$/', $gtm_id);
if ($is_gtm_valid): ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo htmlspecialchars($gtm_id, ENT_QUOTES, 'UTF-8'); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php endif; ?>

<?php
$pixel_id = get_setting('analytics_meta_pixel');
$is_pixel_valid = !empty($pixel_id) && preg_match('/^[0-9]+$/', $pixel_id);
if ($is_pixel_valid): ?>
<!-- Meta Pixel noscript fallback -->
<noscript><img height="1" width="1" style="display:none" 
src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($pixel_id, ENT_QUOTES, 'UTF-8'); ?>&ev=PageView&noscript=1" 
alt="Meta Pixel Tracking" /></noscript>
<!-- End Meta Pixel noscript fallback -->
<?php endif; ?>

<?php include __DIR__ . '/navbar.php'; ?>
