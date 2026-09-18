<?php
// index.php
require_once __DIR__ . '/config/init.php';
$h_lang = get_current_lang();
$cms_home_title = get_setting($h_lang === 'ar' ? 'seo_home_title_ar' : 'seo_home_title_en');
$cms_home_desc = get_setting($h_lang === 'ar' ? 'seo_home_desc_ar' : 'seo_home_desc_en');

$default_home_title = ($h_lang === 'ar') 
    ? 'عالم الرفوف | أرفف مستودعات وحلول تخزين في السعودية' 
    : 'World of Shelves | Warehouse Racking & Storage Solutions Saudi Arabia';

$default_home_desc = ($h_lang === 'ar') 
    ? 'مصنع ومورد متقدم لأنظمة الأرفف الحديدية وحلول التخزين للمستودعات والمحلات والمصانع في المملكة العربية السعودية.' 
    : 'Leading manufacturer and supplier of industrial steel racking, warehouse storage systems, and display shelving in Saudi Arabia.';

$site_wide_title = get_setting($h_lang === 'ar' ? 'seo_title_ar' : 'seo_title_en');
$site_wide_desc = get_setting($h_lang === 'ar' ? 'seo_desc_ar' : 'seo_desc_en');

$seo_home_title = !empty($cms_home_title) ? $cms_home_title : (!empty($default_home_title) ? $default_home_title : $site_wide_title);
$seo_home_desc = !empty($cms_home_desc) ? $cms_home_desc : (!empty($default_home_desc) ? $default_home_desc : $site_wide_desc);

$seo = [
    'title_override' => $seo_home_title,
    'desc_raw' => $seo_home_desc,
    'type' => 'website'
];
// Resolve Hero LCP image URL once for both header preloading and section rendering
$preloadImage = get_setting('hero_image', 'assets/images/shelf1.png');
include_once __DIR__ . '/includes/header.php';

// Fetch global required data once
require_once __DIR__ . '/includes/hero-slider-helper.php';
require_once __DIR__ . '/includes/category-helper.php';

try {
    // Fetch active categories (cached)
    $categories = get_all_categories($pdo);
    
    // Fetch active features
    $feat_stmt = $pdo->query("SELECT * FROM features ORDER BY id ASC");
    $features = $feat_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $features = [];
}

$h_lang = get_current_lang();

// Authoritative Homepage Section Allowlist
$sections = [
    'hero' => [
        'key' => 'hero',
        'default_order' => 10
    ],
    'trust_strip' => [
        'key' => 'trust_strip',
        'default_order' => 20
    ],
    'trust_badges' => [
        'key' => 'trust_badges',
        'default_order' => 30
    ],
    'services' => [
        'key' => 'services',
        'default_order' => 40
    ],
    'areas' => [
        'key' => 'areas',
        'default_order' => 50
    ],
    'categories' => [
        'key' => 'categories',
        'default_order' => 60
    ],
    'products' => [
        'key' => 'products',
        'default_order' => 70
    ],
    'before_after' => [
        'key' => 'before_after',
        'default_order' => 80
    ],
    'projects' => [
        'key' => 'projects',
        'default_order' => 90
    ],
    'stats' => [
        'key' => 'stats',
        'default_order' => 100
    ],
    'testimonials' => [
        'key' => 'testimonials',
        'default_order' => 110
    ],
    'clients' => [
        'key' => 'clients',
        'default_order' => 120
    ],
    'how_we_work' => [
        'key' => 'how_we_work',
        'default_order' => 130
    ],
    'why_choose_us' => [
        'key' => 'why_choose_us',
        'default_order' => 140
    ],
    'cta' => [
        'key' => 'cta',
        'default_order' => 150
    ]
];

// Read visibility and order settings dynamically
$active_sections = [];
foreach ($sections as $key => $sec) {
    $show = get_setting('homepage_show_' . $key, '1');
    if ($show === '1') {
        $order_val = get_setting('homepage_order_' . $key, (string)$sec['default_order']);
        $sec['order'] = is_numeric($order_val) ? intval($order_val) : $sec['default_order'];
        $active_sections[] = $sec;
    }
}

// Stable deterministic sorting
uasort($active_sections, function($a, $b) {
    if ($a['order'] !== $b['order']) {
        return $a['order'] <=> $b['order'];
    }
    if ($a['default_order'] !== $b['default_order']) {
        return $a['default_order'] <=> $b['default_order'];
    }
    return strcmp($a['key'], $b['key']);
});
?>

<main>
<?php
// Loop and render sections using fixed allowlist switch cases (no dynamic database-controlled file inclusion)
foreach ($active_sections as $sec) {
    switch ($sec['key']) {
        
        case 'hero':
            $heroSlides = hero_slide_find_active($pdo);
            // Fallback slide if zero slides are active
            if (empty($heroSlides)) {
                $fallback_image = get_setting('hero_image', 'assets/images/shelf1.png');
                if (empty($fallback_image) || !is_valid_public_image_path($fallback_image)) {
                    $fallback_image = 'assets/images/shelf1.png';
                }
                $heroSlides = [[
                    'image_path' => $fallback_image,
                    'alt_ar' => 'رفوف حديدية عالية الجودة لتنظيم المساحات',
                    'alt_en' => 'High quality iron shelves for organizing spaces'
                ]];
            }
            include __DIR__ . '/includes/homepage/homepage-hero.php';
            break;
            
        case 'trust_strip':
            include __DIR__ . '/includes/trust-strip.php';
            break;
            
        case 'trust_badges':
            include __DIR__ . '/includes/trust-badges.php';
            break;
            
        case 'services':
            $featured_services = get_active_featured_services($pdo);
            if (count($featured_services) === 0) {
                break;
            }
            $services_title = get_setting('homepage_services_title_' . $h_lang, __('services_title'));
            $services_subtitle = get_setting('homepage_services_subtitle_' . $h_lang, '');
            $services_cta_label = get_setting('homepage_services_cta_label_' . $h_lang, ($h_lang === 'ar' ? 'عرض تفاصيل كافة الخدمات' : 'View All Services Details'));
            $whatsapp_number = get_setting('whatsapp_number', '966500000000');
            ?>
            <section class="services-section reveal-on-scroll" id="services">
                <div class="container">
                    <div class="section-header">
                        <h2><?php echo htmlspecialchars($services_title); ?></h2>
                        <?php if (!empty($services_subtitle)): ?>
                            <p class="section-subtitle" style="text-align: center; margin-top: 10px; color: var(--text-muted);"><?php echo htmlspecialchars($services_subtitle); ?></p>
                        <?php endif; ?>
                        <div class="section-divider"></div>
                    </div>
                    
                    <div class="services-grid">
                        <?php foreach ($featured_services as $srv):
                            $title = htmlspecialchars($srv['title_' . $h_lang]);
                            $desc = htmlspecialchars($srv['short_desc_' . $h_lang]);
                            $link_url = map_action_to_url($srv['homepage_action'], $whatsapp_number);
                            $link_label = !empty($srv['homepage_action_label_' . $h_lang]) 
                                ? htmlspecialchars($srv['homepage_action_label_' . $h_lang]) 
                                : ($h_lang === 'ar' ? 'تفاصيل الخدمة' : 'Service Details');
                            ?>
                            <div class="service-card">
                                <?php if (!empty($srv['image_path'])): ?>
                                    <div class="service-card-image-wrapper">
                                        <img src="<?php echo htmlspecialchars($srv['image_path']); ?>" alt="<?php echo $title; ?>" loading="lazy">
                                    </div>
                                <?php else: ?>
                                    <div class="service-icon">
                                        <i data-lucide="<?php echo htmlspecialchars($srv['icon']); ?>"></i>
                                    </div>
                                <?php endif; ?>
                                <h3><?php echo $title; ?></h3>
                                <p><?php echo $desc; ?></p>
                                <?php if ($srv['homepage_action'] !== 'none' && !empty($link_url)): ?>
                                    <a href="<?php echo htmlspecialchars($link_url); ?>" class="service-link-btn">
                                        <span><?php echo $link_label; ?></span>
                                        <i data-lucide="<?php echo $h_lang === 'ar' ? 'arrow-left' : 'arrow-right'; ?>"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="text-align: center; margin-top: 40px;">
                        <a href="services" class="btn btn-secondary">
                            <span><?php echo htmlspecialchars($services_cta_label); ?></span>
                        </a>
                    </div>
                </div>
            </section>
            <?php
            break;
            
        case 'areas':
            include __DIR__ . '/includes/service-areas.php';
            break;
            
        case 'categories':
            ?>
            <section class="categories-section">
                <div class="container">
                    <div class="section-header">
                        <h2><?php echo __('filter_category'); ?></h2>
                        <div class="section-divider"></div>
                    </div>
                    
                    <div class="categories-grid">
                        <?php foreach ($categories as $cat): ?>
                            <?php
                            $cat_name = ($h_lang === 'ar') ? $cat['name_ar'] : $cat['name_en'];
                            ?>
                            <a href="products?category=<?php echo htmlspecialchars($cat['code']); ?>" class="category-card" style="padding: 0; display: flex; flex-direction: column; overflow: hidden;">
                                <div class="category-card__image-container">
                                    <img src="<?php echo htmlspecialchars(get_category_image_url($cat)); ?>" alt="<?php echo get_category_image_alt($cat, $h_lang); ?>" class="category-card__image" loading="lazy">
                                </div>
                                <h3 style="padding: 16px; margin: 0; text-align: center; font-size: 15px; font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($cat_name); ?></h3>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;
            
        case 'products':
            include __DIR__ . '/includes/homepage/homepage-products.php';
            break;
            
        case 'before_after':
            require_once __DIR__ . '/includes/portfolio-helper.php';
            $ba_project = get_homepage_before_after_project();
            
            // Default demo assets fallbacks
            $before_img = 'assets/images/shelf4.png';
            $after_img = 'assets/images/shelf2.png';
            $ba_title = get_setting('homepage_before_after_title_' . $h_lang, __('before_after_title'));
            $project_title_caption = '';
            $project_desc_caption = '';
            $project_detail_link = '';
            
            $has_valid_images = false;
            
            if ($ba_project) {
                // Validate both before_image and after_image paths before rendering to reject unsafe paths
                if (is_safe_image_path($ba_project['before_image']) && is_safe_image_path($ba_project['after_image'])) {
                    $before_img = $ba_project['before_image'];
                    $after_img = $ba_project['after_image'];
                    $project_title_caption = ($h_lang === 'ar') ? $ba_project['title_ar'] : $ba_project['title_en'];
                    $project_desc_caption = ($h_lang === 'ar') ? $ba_project['desc_ar'] : $ba_project['desc_en'];
                    $project_detail_link = get_project_details_url($ba_project['slug'], $h_lang);
                    $has_valid_images = true;
                }
            } else {
                // Use default demo assets which are inherently safe
                $has_valid_images = true;
            }
            
            // If no valid image exists (even fallbacks fail, e.g. files missing or deleted), hide the section gracefully
            if ($has_valid_images):
            ?>
            <section class="before-after-section reveal-on-scroll">
                <div class="container">
                    <div class="section-header">
                        <h2><?php echo htmlspecialchars($ba_title, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <?php if (!empty($project_title_caption)): ?>
                            <p style="font-size: 15px; color: var(--text-secondary); margin-top: 8px; text-align: center;">
                                <strong><?php echo htmlspecialchars($project_title_caption, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($project_desc_caption)): ?>
                                    - <?php echo htmlspecialchars($project_desc_caption, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <div class="section-divider"></div>
                    </div>
                    <div class="before-after-grid">
                        <div class="before-after-card">
                            <div class="before-after-img-wrapper">
                                <img src="<?php echo htmlspecialchars($before_img, ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?php echo htmlspecialchars($project_title_caption !== '' ? $project_title_caption . ' - ' . __('before') : __('before'), ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="before-after-img" 
                                     width="540" 
                                     height="360" 
                                     loading="lazy" 
                                     decoding="async">
                                <span class="before-after-badge before-badge"><?php echo __('before'); ?></span>
                            </div>
                            <div class="before-after-info">
                                <p><?php echo get_current_lang() === 'ar' ? 'قبل التنفيذ والتركيب' : 'Before Implementation'; ?></p>
                            </div>
                        </div>
                        <div class="before-after-card">
                            <div class="before-after-img-wrapper">
                                <img src="<?php echo htmlspecialchars($after_img, ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?php echo htmlspecialchars($project_title_caption !== '' ? $project_title_caption . ' - ' . __('after') : __('after'), ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="before-after-img" 
                                     width="540" 
                                     height="360" 
                                     loading="lazy" 
                                     decoding="async">
                                <span class="before-after-badge after-badge"><?php echo __('after'); ?></span>
                            </div>
                            <div class="before-after-info">
                                <p><?php echo get_current_lang() === 'ar' ? 'بعد التنفيذ والتركيب والترتيب' : 'After Implementation & Organization'; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($project_detail_link)): ?>
                        <div style="text-align: center; margin-top: 30px;">
                            <a href="<?php echo htmlspecialchars($project_detail_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">
                                <span><?php echo $h_lang === 'ar' ? 'عرض تفاصيل المشروع كاملة' : 'View Full Project Details'; ?></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php
            endif;
            break;
            
        case 'projects':
            include __DIR__ . '/includes/homepage/homepage-projects.php';
            break;
            
        case 'stats':
            include __DIR__ . '/includes/stats-section.php';
            break;
            
        case 'testimonials':
            include __DIR__ . '/includes/testimonials.php';
            break;
            
        case 'clients':
            $clients_title = get_setting('homepage_clients_title_' . $h_lang, __('clients_title'));
            ?>
            <section class="client-logos-section reveal-on-scroll">
                <div class="container">
                    <div class="section-header">
                        <h2><?php echo htmlspecialchars($clients_title); ?></h2>
                        <div class="section-divider"></div>
                    </div>
                    
                    <?php 
                    // Render client logos from helper
                    $logos = get_client_logos(); 
                    ?>
                    <div class="logo-grid">
                        <?php foreach ($logos as $l): ?>
                            <div class="logo-item">
                                <img src="<?php echo htmlspecialchars($l['logo_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($l['name']); ?>" 
                                     width="120"
                                     height="40"
                                     loading="lazy" 
                                     decoding="async" />
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;
            
        case 'how_we_work':
            $how_title = get_setting('homepage_how_we_work_title_' . $h_lang, __('how_we_work_title'));
            ?>
            <section class="how-we-work-section reveal-on-scroll">
                <div class="container">
                    <div class="section-header">
                        <h2><?php echo htmlspecialchars($how_title); ?></h2>
                        <div class="section-divider"></div>
                    </div>
                    <div class="steps-grid">
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">1</div>
                                <i data-lucide="message-square"></i>
                            </div>
                            <h3><?php echo __('step_1_title'); ?></h3>
                            <p><?php echo __('step_1_desc'); ?></p>
                        </div>
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">2</div>
                                <i data-lucide="camera"></i>
                            </div>
                            <h3><?php echo __('step_2_title'); ?></h3>
                            <p><?php echo __('step_2_desc'); ?></p>
                        </div>
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">3</div>
                                <i data-lucide="file-text"></i>
                            </div>
                            <h3><?php echo __('step_3_title'); ?></h3>
                            <p><?php echo __('step_3_desc'); ?></p>
                        </div>
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">4</div>
                                <i data-lucide="check-circle2"></i>
                            </div>
                            <h3><?php echo __('step_4_title'); ?></h3>
                            <p><?php echo __('step_4_desc'); ?></p>
                        </div>
                    </div>
                </div>
            </section>
            <?php
            break;
            
        case 'why_choose_us':
            $why_title = get_setting('homepage_why_us_title_' . $h_lang, __('why_us_title'));
            ?>
            <section class="features reveal-on-scroll">
                <div class="container">
                    <div class="section-header">
                        <h2><?php echo htmlspecialchars($why_title); ?></h2>
                        <div class="section-divider"></div>
                    </div>
                    
                    <div class="features-grid">
                        <?php foreach ($features as $feat): ?>
                            <?php
                            $feat_title = ($h_lang === 'ar') ? $feat['title_ar'] : $feat['title_en'];
                            $feat_desc = ($h_lang === 'ar') ? $feat['description_ar'] : $feat['description_en'];
                            ?>
                            <div class="feature-card-premium">
                                <div class="feature-icon-wrapper">
                                    <i data-lucide="<?php echo htmlspecialchars($feat['icon']); ?>"></i>
                                </div>
                                <div class="feature-card-content">
                                    <h3><?php echo htmlspecialchars($feat_title); ?></h3>
                                    <p><?php echo htmlspecialchars($feat_desc); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;
            
        case 'cta':
            $cta_page = 'home';
            include __DIR__ . '/includes/cta-block.php';
            break;
    }
}
?>
</main>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
