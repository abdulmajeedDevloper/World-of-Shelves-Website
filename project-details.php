<?php
// project-details.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/lang_helper.php';
require_once __DIR__ . '/includes/portfolio-helper.php';
require_once __DIR__ . '/includes/product-helper.php';

// 4. Validate the Public Project Slug
$slug = isset($_GET['project']) ? trim($_GET['project']) : '';
if ($slug === '' || strlen($slug) > 255 || !preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
    http_response_code(404);
    $seo = [
        'title_key' => 'page_not_found_title',
        'desc_key' => 'page_not_found_desc',
        'type' => 'website',
        'noindex' => true,
        'canonical' => false
    ];
    include_once __DIR__ . '/includes/header.php';
    include __DIR__ . '/404.php';
    exit;
}

$project = get_project_by_slug($slug);

if (!$project || (isset($project['is_active']) && $project['is_active'] == 0)) {
    http_response_code(404);
    $seo = [
        'title_key' => 'page_not_found_title',
        'desc_key' => 'page_not_found_desc',
        'type' => 'website',
        'noindex' => true,
        'canonical' => false
    ];
    include_once __DIR__ . '/includes/header.php';
    include __DIR__ . '/404.php';
    exit;
}

$lang = get_current_lang();

// Normalize Nullable Database Fields
$p_title_ar = (string)($project['title_ar'] ?? '');
$p_title_en = (string)($project['title_en'] ?? '');
$p_desc_ar = (string)($project['desc_ar'] ?? '');
$p_desc_en = (string)($project['desc_en'] ?? '');
$p_location_ar = (string)($project['location_ar'] ?? '');
$p_location_en = (string)($project['location_en'] ?? '');
$p_client_ar = (string)($project['client_ar'] ?? '');
$p_client_en = (string)($project['client_en'] ?? '');
$p_service_ar = (string)($project['service_ar'] ?? '');
$p_service_en = (string)($project['service_en'] ?? '');
$p_duration_ar = (string)($project['duration_ar'] ?? '');
$p_duration_en = (string)($project['duration_en'] ?? '');
$p_category = (string)($project['category'] ?? '');
$p_long_desc_ar = (string)($project['long_desc_ar'] ?? '');
$p_long_desc_en = (string)($project['long_desc_en'] ?? '');
$p_main_image = (string)($project['main_image'] ?? '');
$p_before_image = (string)($project['before_image'] ?? '');
$p_after_image = (string)($project['after_image'] ?? '');
$p_date = (string)($project['date'] ?? '');
$p_slug = (string)($project['slug'] ?? '');

$title = ($lang === 'ar') ? $p_title_ar : $p_title_en;
$desc = ($lang === 'ar') ? $p_desc_ar : $p_desc_en;
$location = ($lang === 'ar') ? $p_location_ar : $p_location_en;
$client = ($lang === 'ar') ? $p_client_ar : $p_client_en;
$service = ($lang === 'ar') ? $p_service_ar : $p_service_en;
$duration = ($lang === 'ar') ? $p_duration_ar : $p_duration_en;
$category_label = get_project_category_label(!empty($project['category_id']) ? $project['category_id'] : $p_category, $lang);
$long_desc = ($lang === 'ar') ? $p_long_desc_ar : $p_long_desc_en;

// Dynamic database SEO tags with trimmed fallbacks
$db_seo_title = ($lang === 'ar') ? (string)($project['seo_title_ar'] ?? '') : (string)($project['seo_title_en'] ?? '');
$db_seo_desc = ($lang === 'ar') ? (string)($project['seo_description_ar'] ?? '') : (string)($project['seo_description_en'] ?? '');

$seo_title = (trim($db_seo_title) !== '') ? trim($db_seo_title) : $title;
$seo_desc = (trim($db_seo_desc) !== '') ? trim($db_seo_desc) : $desc;

// Define SEO metadata dynamically using project data
$site_url = get_site_url();
$project_url = get_project_details_url($p_slug, null, true);
$absolute_image = $p_main_image;
if (!empty($absolute_image) && !preg_match('~^(https?:)?//~i', $absolute_image)) {
    $absolute_image = $site_url . '/' . ltrim($absolute_image, '/');
}

// Resolve category details from the single main query JOIN
$project_cat_slug = !empty($project['category_slug']) ? $project['category_slug'] : '';
$project_cat_name = '';
if (!empty($project_cat_slug)) {
    $project_cat_name = ($lang === 'ar') ? $project['category_name_ar'] : $project['category_name_en'];
}

$lang_suffix = ($lang === 'en') ? '?lang=en' : '';
$breadcrumbs = [
    __('nav_portfolio') => 'projects' . $lang_suffix
];
if (!empty($project_cat_name) && !empty($project_cat_slug)) {
    $breadcrumbs[$project_cat_name] = 'projects/category/' . $project_cat_slug . $lang_suffix;
}
$breadcrumbs[$title] = get_project_details_url($p_slug, $lang);

$seo = [
    'title_raw' => $seo_title,
    'desc_raw' => $seo_desc,
    'type' => 'article',
    'image' => $p_main_image,
    'canonical_params' => ['project'],
    'creative_work' => [
        'name' => $title,
        'description' => $desc,
        'image' => $absolute_image,
        'url' => $project_url,
        'date' => $p_date
    ]
];

include_once __DIR__ . '/includes/header.php';
?>
<main>
<?php

// Render visible breadcrumbs
include __DIR__ . '/includes/breadcrumbs.php';

// Prepare WhatsApp link for project details CTA without localhost URLs
$whatsapp_number = get_setting('whatsapp_number', '966500000000');
$project_url = get_project_details_url($p_slug, null, true);

$wa_msg = rawurlencode($lang === 'ar' 
    ? "السلام عليكم، أود الاستفسار عن تفاصيل مشروع مماثل لـ: " . $title . "\n\nالرابط:\n" . $project_url 
    : "Hello, I would like to inquire about a project similar to: " . $title . "\n\nProject link:\n" . $project_url);
$wa_link = "https://wa.me/" . rawurlencode((string)($whatsapp_number ?? '')) . "?text=" . $wa_msg;
?>

<div class="container about-page-container">
    <!-- Header Section -->
    <div class="about-header-banner reveal-on-scroll" style="margin-bottom: 40px;">
        <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 12px; color: var(--text-primary);"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p style="font-size: 15px; color: var(--text-secondary);"><?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($category_label, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="about-header-divider"></div>
    </div>

    <!-- Project Details Grid -->
    <div class="project-details-grid reveal-on-scroll">
        
        <!-- Left Column: Media & Description -->
        <div class="project-details-media">
            <!-- 4. Tabbed Image Gallery switcher (No Lightbox) -->
            <div class="project-gallery-container">
                <!-- Large Primary Image (LCP optimized) -->
                <div class="gallery-primary-img-card">
                    <img src="<?php echo htmlspecialchars($p_main_image, ENT_QUOTES, 'UTF-8'); ?>" 
                         id="primaryGalleryImg" 
                         class="gallery-primary-img" 
                         width="750"
                         height="480"
                         fetchpriority="high" 
                         loading="eager"
                         decoding="sync" 
                         alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                
                <!-- Thumbnails Row -->
                <?php if (isset($project['gallery']) && is_array($project['gallery']) && count($project['gallery']) > 0): ?>
                    <div class="gallery-thumbnails-row">
                        <!-- Main image thumb -->
                        <button class="gallery-thumb-btn active" data-large-src="<?php echo htmlspecialchars($p_main_image, ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="<?php echo htmlspecialchars($p_main_image, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail Primary" width="100" height="66" loading="lazy" decoding="async" />
                        </button>
                        
                        <!-- Extra gallery thumbs -->
                        <?php foreach ($project['gallery'] as $idx => $img): 
                            $img_path = (string)($img ?? '');
                        ?>
                            <button class="gallery-thumb-btn" data-large-src="<?php echo htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?php echo htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail <?php echo $idx + 1; ?>" width="100" height="66" loading="lazy" decoding="async" />
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="project-details-description" style="margin-bottom: 40px;">
                <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 16px; color: var(--text-primary);"><?php echo __('project_details'); ?></h2>
                <p style="font-size: 15.5px; line-height: 1.8; color: var(--text-secondary); margin-bottom: 20px; text-align: start;"><?php echo nl2br(htmlspecialchars($long_desc, ENT_QUOTES, 'UTF-8')); ?></p>
            </div>

            <!-- 5. Transformation Before / After Widget -->
            <?php 
            if ($p_before_image !== '' && $p_after_image !== '') {
                $before_img = $p_before_image;
                $after_img = $p_after_image;
                include __DIR__ . '/includes/before-after.php';
            }
            ?>
        </div>
        
        <!-- Right Column: Sidebar Info Card -->
        <div class="project-details-sidebar">
            <div class="project-info-premium" dir="<?php echo get_lang_direction(); ?>">
                <div class="project-info-premium__header">
                    <h2 class="project-info-premium__title">
                        <i data-lucide="info" class="project-info-premium__header-icon" aria-hidden="true"></i>
                        <span><?php echo __('project_info'); ?></span>
                    </h2>
                </div>
                
                <div class="project-info-premium__table">
                    <?php if (!empty($client)): ?>
                    <div class="project-info-premium__row">
                        <div class="project-info-premium__label-col">
                            <span class="project-info-premium__icon-wrapper">
                                <i data-lucide="building" class="project-info-premium__icon" aria-hidden="true"></i>
                            </span>
                            <span class="project-info-premium__label"><?php echo __('project_client_label'); ?></span>
                        </div>
                        <div class="project-info-premium__value-col">
                            <span class="project-info-premium__value"><?php echo htmlspecialchars($client, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($location)): ?>
                    <div class="project-info-premium__row">
                        <div class="project-info-premium__label-col">
                            <span class="project-info-premium__icon-wrapper">
                                <i data-lucide="map-pin" class="project-info-premium__icon" aria-hidden="true"></i>
                            </span>
                            <span class="project-info-premium__label"><?php echo __('project_location_label'); ?></span>
                        </div>
                        <div class="project-info-premium__value-col">
                            <span class="project-info-premium__value"><?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($p_date)): ?>
                    <div class="project-info-premium__row">
                        <div class="project-info-premium__label-col">
                            <span class="project-info-premium__icon-wrapper">
                                <i data-lucide="calendar" class="project-info-premium__icon" aria-hidden="true"></i>
                            </span>
                            <span class="project-info-premium__label"><?php echo __('project_completion_date'); ?></span>
                        </div>
                        <div class="project-info-premium__value-col">
                            <span class="project-info-premium__value"><?php echo htmlspecialchars($p_date, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($service)): ?>
                    <div class="project-info-premium__row">
                        <div class="project-info-premium__label-col">
                            <span class="project-info-premium__icon-wrapper">
                                <i data-lucide="layers" class="project-info-premium__icon" aria-hidden="true"></i>
                            </span>
                            <span class="project-info-premium__label"><?php echo __('project_service'); ?></span>
                        </div>
                        <div class="project-info-premium__value-col">
                            <span class="project-info-premium__value"><?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($duration)): ?>
                    <div class="project-info-premium__row">
                        <div class="project-info-premium__label-col">
                            <span class="project-info-premium__icon-wrapper">
                                <i data-lucide="clock" class="project-info-premium__icon" aria-hidden="true"></i>
                            </span>
                            <span class="project-info-premium__label"><?php echo __('project_duration'); ?></span>
                        </div>
                        <div class="project-info-premium__value-col">
                            <span class="project-info-premium__value"><?php echo htmlspecialchars($duration, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($category_label)): ?>
                    <div class="project-info-premium__row">
                        <div class="project-info-premium__label-col">
                            <span class="project-info-premium__icon-wrapper">
                                <i data-lucide="tag" class="project-info-premium__icon" aria-hidden="true"></i>
                            </span>
                            <span class="project-info-premium__label"><?php echo __('project_category'); ?></span>
                        </div>
                        <div class="project-info-premium__value-col">
                            <span class="project-info-premium__badge"><?php echo htmlspecialchars($category_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <a href="<?php echo htmlspecialchars($wa_link, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-whatsapp-cta btn-block" style="margin-top: 24px; padding: 14px 20px; border-radius: var(--radius-md); font-weight: 700; width: 100%;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                      <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                    </svg>
                    <span><?php echo __('whatsapp_project_quote'); ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Related Projects Row -->
    <!-- Related Products Section -->
    <?php 
    $related_products = get_project_related_products($pdo, $project['id']);
    if (count($related_products) > 0):
    ?>
        <div class="related-products-section reveal-on-scroll" style="margin-top: 60px; border-top: 1px solid var(--border-color); padding-top: 40px;">
            <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 24px; color: var(--text-primary); text-align: center;">
                <?php echo __('related_products'); ?>
            </h2>
            <div class="products-grid">
                <?php foreach ($related_products as $p): 
                    $p_name = ($lang === 'ar') ? $p['name_ar'] : $p['name_en'];
                    $p_desc = ($lang === 'ar') ? $p['short_intro_ar'] : $p['short_intro_en'];
                    if (empty($p_desc)) {
                        $p_desc = ($lang === 'ar') ? $p['description_ar'] : $p['description_en'];
                    }
                    if (mb_strlen($p_desc) > 120) {
                        $p_desc = mb_substr($p_desc, 0, 120) . '...';
                    }
                    $p_link = get_product_details_url($p, $lang);
                ?>
                    <div class="product-card">
                        <div class="product-image-container" style="position: relative;">
                            <img src="<?php echo htmlspecialchars($p['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($p_name); ?>" 
                                 class="product-card-img"
                                 width="300"
                                 height="220"
                                 loading="lazy"
                                 decoding="async">
                            <?php 
                            $p_stock = isset($p['stock']) ? intval($p['stock']) : 0;
                            if ($p_stock <= 0): 
                            ?>
                                <div class="product-card-badges">
                                    <span class="product-badge status-out-of-stock"><?php echo htmlspecialchars(__('out_of_stock_badge')); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-content">
                            <h3><?php echo htmlspecialchars($p_name); ?></h3>
                            <p><?php echo htmlspecialchars($p_desc); ?></p>
                            <div class="product-card-footer" style="margin-top: auto; display: flex; align-items: center; justify-content: center; width: 100%;">
                                <a href="<?php echo htmlspecialchars($p_link); ?>" class="btn btn-primary-cta" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin: 0 auto;">
                                    <span><?php echo __('view_details'); ?></span>
                                    <i data-lucide="arrow-right" class="arrow-icon"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php 
    $all_projects = get_portfolio_projects();
    $related = array_filter($all_projects, function($item) use ($p_category, $p_slug) {
        return (string)($item['category'] ?? '') === $p_category && (string)($item['slug'] ?? '') !== $p_slug;
    });
    // Limit to 3 projects max
    $related = array_slice($related, 0, 3);
    
    if (count($related) > 0):
    ?>
        <div class="related-projects-section reveal-on-scroll" style="margin-top: 60px; border-top: 1px solid var(--border-color); padding-top: 40px;">
            <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 24px; color: var(--text-primary); text-align: center;">
                <?php echo __('related_projects'); ?>
            </h2>
            <div class="projects-grid">
                <?php foreach ($related as $rp): 
                    $rp_title_ar = (string)($rp['title_ar'] ?? '');
                    $rp_title_en = (string)($rp['title_en'] ?? '');
                    $rp_location_ar = (string)($rp['location_ar'] ?? '');
                    $rp_location_en = (string)($rp['location_en'] ?? '');
                    $rp_category = (string)($rp['category'] ?? '');
                    $rp_desc_ar = (string)($rp['desc_ar'] ?? '');
                    $rp_desc_en = (string)($rp['desc_en'] ?? '');
                    $rp_slug = (string)($rp['slug'] ?? '');
                    $rp_main_image = (string)($rp['main_image'] ?? '');
                    $rp_date = (string)($rp['date'] ?? '');

                    $rp_title = ($lang === 'ar') ? $rp_title_ar : $rp_title_en;
                    $rp_location = ($lang === 'ar') ? $rp_location_ar : $rp_location_en;
                    $rp_category_label = ($rp_category !== '') ? __('filter_' . $rp_category) : '';
                    $rp_desc = ($lang === 'ar') ? $rp_desc_ar : $rp_desc_en;
                    $rp_link = get_project_details_url($rp_slug, $lang);
                ?>
                    <div class="project-card">
                        <div class="project-image-wrapper">
                            <span class="project-badge"><?php echo htmlspecialchars($rp_category_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <img src="<?php echo htmlspecialchars($rp_main_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($rp_title, ENT_QUOTES, 'UTF-8'); ?>" class="project-img" loading="lazy" decoding="async" />
                        </div>
                        <div class="project-card-content">
                            <h3><?php echo htmlspecialchars($rp_title, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="project-meta-info">
                                <span><i data-lucide="map-pin"></i> <?php echo htmlspecialchars($rp_location, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span><i data-lucide="calendar"></i> <?php echo htmlspecialchars($rp_date, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <p><?php echo htmlspecialchars($rp_desc, ENT_QUOTES, 'UTF-8'); ?></p>
                            <a href="<?php echo htmlspecialchars($rp_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary-cta">
                                <span><?php echo __('view_project'); ?></span>
                                <i data-lucide="arrow-right" class="arrow-icon"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- CTA Block -->
<?php
$cta_page = 'services'; // relevant to custom layouts/relocation service focus
include __DIR__ . '/includes/cta-block.php';
?>

</main>
<?php
include_once __DIR__ . '/includes/footer.php';
?>
