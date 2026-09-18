<?php
// includes/footer.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/lang_helper.php';

$lang = get_current_lang();

// 1. Column Identity
$show_identity = get_setting('footer_show_identity', '1') === '1';
$footer_logo_path = get_setting('footer_logo');
if (empty($footer_logo_path)) {
    $footer_logo_path = get_setting('site_logo');
}

$footer_desc = get_setting('footer_description_' . $lang);
if (empty($footer_desc)) {
    $footer_desc = get_setting('tagline_' . $lang);
}

$business_name = get_setting('business_name_' . $lang);
if (empty($business_name)) {
    $business_name = get_setting('site_name_' . $lang, __('brand_name'));
}

// 2. Column Services
$show_footer_services = get_setting('footer_show_services', '1') === '1';
$footer_services_title = get_setting('footer_services_title_' . $lang);
if (empty($footer_services_title)) {
    $footer_services_title = __('services_title');
}
$services_limit = (int)get_setting('footer_services_limit', '6');
if ($services_limit <= 0 || $services_limit > 10) {
    $services_limit = 6;
}

// Fetch active services dynamically using cached helper
$footer_services = [];
if ($show_footer_services) {
    require_once INCLUDES_PATH . '/services-helper.php';
    $all_active_services = get_active_services($pdo);
    $footer_services = array_slice($all_active_services, 0, $services_limit);
}

// 3. Column Categories
$show_categories = get_setting('footer_show_categories', '1') === '1';
$footer_categories_title = get_setting('footer_categories_title_' . $lang);
if (empty($footer_categories_title)) {
    $footer_categories_title = __('nav_products');
}
?>
<footer class="main-footer">
    <div class="container footer-grid">
        <?php if ($show_identity): ?>
            <div class="footer-brand">
                <?php 
                $logo_text = get_setting('site_logo_text_' . $lang);
                if (empty($logo_text)) {
                    $logo_text = ($lang === 'ar') ? 'عالم الرفوف' : 'World of Shelves';
                }
                if (!empty($footer_logo_path)): 
                ?>
                    <div class="footer-logo-container footer-logo-brand">
                        <img src="<?php echo htmlspecialchars($footer_logo_path); ?>" alt="<?php echo htmlspecialchars($logo_text); ?>" class="footer-logo-img" width="160" height="44" loading="lazy" decoding="async">
                        <span class="footer-logo-text"><?php echo htmlspecialchars($logo_text); ?></span>
                    </div>
                <?php else: ?>
                    <h3 class="footer-logo-text"><?php echo htmlspecialchars($logo_text); ?></h3>
                <?php endif; ?>
                
                <p class="footer-brand-desc"><?php echo htmlspecialchars($footer_desc); ?></p>
                
                <div class="footer-meta-list">
                    <div class="footer-meta-item">
                        <div class="footer-icon-badge"><i data-lucide="map-pin"></i></div>
                        <span><?php echo htmlspecialchars(get_setting('contact_address_' . $lang, ($lang === 'ar' ? 'المملكة العربية السعودية، الرياض' : 'Riyadh, Saudi Arabia'))); ?></span>
                    </div>
                    <?php 
                    $phone_raw = get_setting('contact_phone', '+966 500 000 000');
                    $phone_clean = preg_replace('/[^0-9+]/', '', $phone_raw);
                    ?>
                    <div class="footer-meta-item">
                        <div class="footer-icon-badge"><i data-lucide="phone"></i></div>
                        <a href="tel:<?php echo htmlspecialchars($phone_clean); ?>" dir="ltr"><?php echo htmlspecialchars($phone_raw); ?></a>
                    </div>
                    <div class="footer-meta-item">
                        <div class="footer-icon-badge"><i data-lucide="clock"></i></div>
                        <span><?php echo htmlspecialchars(get_setting('working_hours_' . $lang, __('working_hours'))); ?></span>
                    </div>
                </div>
                
                <!-- CMS Dynamic Social Media Links -->
                <?php
                if (!function_exists('is_valid_non_placeholder_social')) {
                    function is_valid_non_placeholder_social($url) {
                        if (empty($url)) return false;
                        $lower = strtolower($url);
                        if (strpos($lower, 'yourpage') !== false || strpos($lower, 'yourhandle') !== false || strpos($lower, 'username') !== false || strpos($lower, 'example') !== false) {
                            return false;
                        }
                        return true;
                    }
                }
                
                $social_channels = [
                    'social_facebook'  => [
                        'svg' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>',
                        'label_ar' => 'فيسبوك',
                        'label_en' => 'Facebook'
                    ],
                    'social_instagram' => [
                        'svg' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line></svg>',
                        'label_ar' => 'إنستغرام',
                        'label_en' => 'Instagram'
                    ],
                    'social_twitter'   => [
                        'svg' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg>',
                        'label_ar' => 'إكس / تويتر',
                        'label_en' => 'X / Twitter'
                    ],
                    'social_linkedin'  => [
                        'svg' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect width="4" height="12" x="2" y="9"></rect><circle cx="4" cy="4" r="2"></circle></svg>',
                        'label_ar' => 'لينكد إن',
                        'label_en' => 'LinkedIn'
                    ],
                    'social_youtube'   => [
                        'svg' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17z"></path><polygon points="10 15 15 12 10 9"></polygon></svg>',
                        'label_ar' => 'يوتيوب',
                        'label_en' => 'YouTube'
                    ],
                    'social_tiktok'    => [
                        'svg' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path></svg>',
                        'label_ar' => 'تيك توك',
                        'label_en' => 'TikTok'
                    ],
                    'social_snapchat'  => [
                        'svg' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0-6 6c0 3.3 1.5 5 3 6.5h6c1.5-1.5 3-3.2 3-6.5a6 6 0 0 0-6-6Z"></path><path d="M6.5 15.5c-1 0-1.5-.5-1.5-1s.5-1.5 1.5-1.5H9"></path><path d="M17.5 15.5c1 0 1.5-.5 1.5-1s-.5-1.5-1.5-1.5H15"></path><path d="M9 16c0 1.5 1 2.5 3 2.5s3-1 3-2.5"></path></svg>',
                        'label_ar' => 'سناب شات',
                        'label_en' => 'Snapchat'
                    ]
                ];
                
                $active_socials = [];
                foreach ($social_channels as $key => $data) {
                    $url = get_setting($key);
                    if (!empty($url) && is_valid_non_placeholder_social($url)) {
                        $active_socials[$key] = [
                            'url'   => $url,
                            'svg'   => $data['svg'],
                            'label' => ($lang === 'ar') ? $data['label_ar'] : $data['label_en']
                        ];
                    }
                }
                
                if (count($active_socials) > 0):
                ?>
                <div class="footer-social-links" style="display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap;">
                    <?php foreach ($active_socials as $key => $social): ?>
                        <a href="<?php echo htmlspecialchars($social['url']); ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="footer-social-icon" 
                           aria-label="<?php echo htmlspecialchars($social['label']); ?>"
                           style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-card, rgba(255,255,255,0.05)); color: var(--text-muted, #94a3b8); transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1);"
                           onmouseover="this.style.color='var(--color-primary, #f59e0b)'; this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='var(--color-primary, #f59e0b)';"
                           onmouseout="this.style.color='var(--text-muted, #94a3b8)'; this.style.background='var(--bg-card, rgba(255,255,255,0.05))'; this.style.borderColor='rgba(255,255,255,0.1)';"
                        >
                            <?php echo $social['svg']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($show_footer_services && count($footer_services) > 0): ?>
            <div class="footer-links">
                <h4><?php echo htmlspecialchars($footer_services_title); ?></h4>
                <ul>
                    <?php foreach ($footer_services as $srv): 
                        $srv_title = isset($srv['title_' . $lang]) ? $srv['title_' . $lang] : (($lang === 'ar') ? $srv['title_ar'] : $srv['title_en']);
                        $srv_action = isset($srv['custom_action']) ? $srv['custom_action'] : '';
                        
                        $srv_href = 'services';
                        if ($srv_action === 'products') {
                            $srv_href = 'products';
                        } elseif ($srv_action === 'installation') {
                            $srv_href = 'installation';
                        } elseif ($srv_action === 'used_shelves') {
                            $srv_href = 'used-shelves';
                        } elseif ($srv_action === 'contact') {
                            $srv_href = 'contact';
                        } else if (!empty($srv['slug'])) {
                            $srv_href = 'services?service=' . urlencode($srv['slug']);
                        }
                    ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($srv_href); ?>">
                                <i data-lucide="chevron-left" class="footer-link-icon"></i>
                                <span><?php echo htmlspecialchars($srv_title); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($show_categories): ?>
            <div class="footer-links">
                <h4><?php echo htmlspecialchars($footer_categories_title); ?></h4>
                <ul>
                    <?php
                    $footer_categories = get_all_categories($pdo);
                    if (count($footer_categories) > 0):
                        foreach ($footer_categories as $cat):
                            $cat_name = ($lang == 'ar') ? $cat['name_ar'] : $cat['name_en'];
                            ?>
                            <li>
                                <a href="products?category=<?php echo htmlspecialchars($cat['code']); ?>">
                                    <i data-lucide="chevron-left" class="footer-link-icon"></i>
                                    <span><?php echo htmlspecialchars($cat_name); ?></span>
                                </a>
                            </li>
                            <?php
                        endforeach;
                    else:
                        ?>
                        <li><a href="products?category=wall"><i data-lucide="chevron-left" class="footer-link-icon"></i><span><?php echo __('category_wall'); ?></span></a></li>
                        <li><a href="products?category=standing"><i data-lucide="chevron-left" class="footer-link-icon"></i><span><?php echo __('category_standing'); ?></span></a></li>
                        <li><a href="products?category=industrial"><i data-lucide="chevron-left" class="footer-link-icon"></i><span><?php echo __('category_industrial'); ?></span></a></li>
                        <li><a href="products?category=decorative"><i data-lucide="chevron-left" class="footer-link-icon"></i><span><?php echo __('category_decorative'); ?></span></a></li>
                        <?php
                    endif;
                    ?>
                </ul>
            </div>
        <?php endif; ?>

    </div>
    
    <div class="footer-bottom-wrapper">
        <div class="container footer-bottom-content">
            <?php
            $copyright_tpl = get_setting('copyright_text_' . $lang);
            if (empty($copyright_tpl)) {
                $copyright_tpl = ($lang === 'ar') 
                    ? '© {year} {business_name}. جميع الحقوق محفوظة.' 
                    : '© {year} {business_name}. All Rights Reserved.';
            }
            
            $copyright_business = get_setting('business_name_' . $lang);
            if (empty($copyright_business)) {
                $copyright_business = get_setting('site_name_' . $lang, __('brand_name'));
            }
            
            $current_year = date('Y');
            $copyright_final = str_replace(
                ['{year}', '{business_name}'],
                [$current_year, $copyright_business],
                $copyright_tpl
            );
            ?>
            <p class="footer-copyright"><?php echo htmlspecialchars($copyright_final); ?></p>

        </div>
    </div>

    <!-- Floating Expandable Contact FAB Widget -->
    <?php include_once __DIR__ . '/floating-bar.php'; ?>
</footer>

<!-- Scripts -->
<script src="assets/js/main.js?v=<?php echo file_exists(dirname(__DIR__) . '/assets/js/main.js') ? filemtime(dirname(__DIR__) . '/assets/js/main.js') : '2.6'; ?>"></script>
<script>
    (function() {
        function initLucide() {
            if (window.lucide) {
                lucide.createIcons();
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initLucide);
        } else {
            initLucide();
        }
    })();
</script>
</body>
</html>
