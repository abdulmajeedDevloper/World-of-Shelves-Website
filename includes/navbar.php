<?php
// includes/navbar.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/lang_helper.php';
require_once __DIR__ . '/navigation-helper.php';

$current_lang = get_current_lang();
$target_lang = ($current_lang === 'ar') ? 'en' : 'ar';
$target_lang_label = ($current_lang === 'ar') ? 'English' : 'العربية';

// Preserve current query parameters (e.g. product id) on toggle
$query_params = $_GET;
$query_params['lang'] = $target_lang;
$lang_toggle_url = '?' . http_build_query($query_params);

$nav_menu_items = get_public_navigation_menu();

$show_lang_switcher = get_setting('nav_show_lang_switcher', '1') === '1';
// If global dark mode is disabled, force toggle to be hidden
$global_dark_mode = get_setting('enable_dark_mode', '1') === '1';
$show_dark_mode = $global_dark_mode && (get_setting('nav_show_dark_mode', '1') === '1');
?>
<header class="main-header" id="mainHeader">
    <div class="container navbar">
        <a href="<?php echo get_site_url() . '/'; ?>" class="logo logo-brand">
            <?php 
            $logo_path = get_setting('site_logo'); 
            $logo_dark_path = get_setting('site_logo_dark');
            $logo_text = get_setting('site_logo_text_' . $current_lang);
            if (empty($logo_text)) {
                $logo_text = ($current_lang === 'ar') ? 'عالم الرفوف' : 'World of Shelves';
            }
            
            if (!empty($logo_path)):
                // Prevent duplicate output if dark logo matches or is empty
                if (empty($logo_dark_path) || $logo_dark_path === $logo_path):
            ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($logo_text); ?>" class="nav-logo-img" height="40">
            <?php else: ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($logo_text); ?>" class="nav-logo-img logo-light-mode" height="40">
                    <img src="<?php echo htmlspecialchars($logo_dark_path); ?>" alt="" class="nav-logo-img logo-dark-mode" aria-hidden="true" height="40">
            <?php 
                endif;
            ?>
                <span class="logo-brand-text"><?php echo htmlspecialchars($logo_text); ?></span>
            <?php
            else: 
            ?>
                <i data-lucide="layout-grid"></i>
                <span><?php echo htmlspecialchars($logo_text); ?></span>
            <?php endif; ?>
        </a>
        
        <ul class="nav-links" id="navLinks">
            <?php foreach ($nav_menu_items as $item): ?>
                <li><a href="<?php echo htmlspecialchars($item['url']); ?>" class="nav-link <?php echo $item['is_active'] ? 'active' : ''; ?>"><?php echo htmlspecialchars($item['label']); ?></a></li>
            <?php endforeach; ?>
            <!-- Mobile Menu Settings Panel -->
            <li class="mobile-nav-settings">
                <div class="mobile-settings-divider"></div>
                <div class="mobile-settings-title"><?php echo get_current_lang() === 'ar' ? 'إعدادات الموقع' : 'Site Settings'; ?></div>
                
                <!-- Language Row -->
                <div class="mobile-setting-section-group">
                    <span class="mobile-setting-group-label"><?php echo get_current_lang() === 'ar' ? 'اللغة' : 'Language'; ?></span>
                    <?php if ($show_lang_switcher): ?>
                        <a href="<?php echo htmlspecialchars($lang_toggle_url); ?>" class="mobile-setting-row lang-toggle">
                            <span class="mobile-setting-info">
                                <i data-lucide="globe" class="mobile-setting-icon"></i>
                                <span class="mobile-setting-label"><?php echo get_current_lang() === 'ar' ? 'تغيير اللغة' : 'Change Language'; ?></span>
                            </span>
                            <span class="mobile-setting-value"><?php echo $target_lang_label; ?></span>
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Dark Mode Row -->
                <div class="mobile-setting-section-group">
                    <span class="mobile-setting-group-label"><?php echo get_current_lang() === 'ar' ? 'الوضع الداكن' : 'Dark Mode'; ?></span>
                    <div class="mobile-setting-center-container">
                        <?php if ($show_dark_mode): ?>
                            <button class="mobile-theme-pill-btn theme-toggle" aria-label="Toggle Theme" type="button">
                                <i data-lucide="moon" class="themeIcon mobile-setting-icon"></i>
                                <span class="mobile-setting-label"><?php echo get_current_lang() === 'ar' ? 'الوضع الداكن' : 'Dark Mode'; ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        </ul>
        
        <div class="nav-actions">
            <?php if ($show_lang_switcher): ?>
                <!-- Language Toggle Button -->
                <a href="<?php echo htmlspecialchars($lang_toggle_url); ?>" class="lang-toggle header-lang-toggle">
                    <i data-lucide="globe"></i>
                    <span><?php echo htmlspecialchars($target_lang_label); ?></span>
                </a>
            <?php endif; ?>

            <?php if ($show_dark_mode): ?>
                <!-- Theme Toggle Button -->
                <button class="icon-btn theme-toggle header-theme-toggle" id="themeToggle" aria-label="Toggle Theme">
                    <i data-lucide="moon" class="themeIcon"></i>
                </button>
            <?php endif; ?>
            
            <!-- Mobile Menu Toggle -->
            <button class="icon-btn menu-toggle" id="menuToggle" aria-label="<?php echo get_current_lang() === 'ar' ? 'تبديل القائمة' : 'Toggle Menu'; ?>">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </div>
</header>
