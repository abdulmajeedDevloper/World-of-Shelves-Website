<?php
// projects.php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/portfolio-helper.php';

// Category legacy redirect to clean URL
if (isset($_GET['category'])) {
    $cat_val = trim($_GET['category']);
    try {
        $stmt = $pdo->prepare("SELECT slug FROM project_categories WHERE category_key = :cat OR id = :cat_id OR slug = :cat_slug LIMIT 1");
        $stmt->execute([':cat' => $cat_val, ':cat_id' => is_numeric($cat_val) ? (int)$cat_val : 0, ':cat_slug' => $cat_val]);
        $cat_slug = $stmt->fetchColumn();
        if ($cat_slug) {
            $lang = get_current_lang();
            $redirect_url = get_site_url() . '/projects/category/' . $cat_slug;
            if ($lang === 'en') {
                $redirect_url .= '?lang=en';
            }
            header("Location: " . $redirect_url, true, 301);
            exit;
        }
    } catch (PDOException $e) {}
}

$pr_lang = get_current_lang();
$lang = $pr_lang;

// Global page variables:
$cms_projects_title = get_setting($lang === 'ar' ? 'seo_projects_title_ar' : 'seo_projects_title_en');
$cms_projects_desc = get_setting($lang === 'ar' ? 'seo_projects_desc_ar' : 'seo_projects_desc_en');

$default_projects_title = ($lang === 'ar')
    ? 'معرض المشاريع والأعمال المنفذة | عالم الرفوف'
    : 'Completed Storage Projects Portfolio | World of Shelves';
$default_projects_desc = ($lang === 'ar')
    ? 'استعرض أبرز مشاريع تركيب أرفف المستودعات والتخزين التي قمنا بتنفيذها لكبرى الشركات في السعودية.'
    : 'Explore our portfolio of completed warehouse racking and storage installation projects across Saudi Arabia.';

$site_wide_title = get_setting($lang === 'ar' ? 'seo_title_ar' : 'seo_title_en');
$site_wide_desc = get_setting($lang === 'ar' ? 'seo_desc_ar' : 'seo_desc_en');

$proj_title = !empty($cms_projects_title) ? $cms_projects_title : (!empty($default_projects_title) ? $default_projects_title : $site_wide_title);
$proj_desc = !empty($cms_projects_desc) ? $cms_projects_desc : (!empty($default_projects_desc) ? $default_projects_desc : $site_wide_desc);

$category_slug = isset($_GET['category_slug']) ? trim($_GET['category_slug']) : '';
$cat_details = null;
if ($category_slug !== '') {
    try {
        $c_stmt = $pdo->prepare("SELECT * FROM project_categories WHERE slug = :slug LIMIT 1");
        $c_stmt->execute([':slug' => $category_slug]);
        $cat_details = $c_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cat_details) {
            // Category slug not found -> 404
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
    } catch (PDOException $e) {
        $cat_details = null;
    }
}

$projects = get_portfolio_projects();
if ($cat_details) {
    $projects = array_filter($projects, function($p) use ($cat_details) {
        return (int)($p['category_id'] ?? 0) === (int)$cat_details['id'] || (string)($p['category'] ?? '') === (string)$cat_details['category_key'];
    });
}

$db_categories = get_project_categories(true);

if ($cat_details) {
    $cat_name = ($lang === 'ar') ? ($cat_details['name_ar'] ?? '') : ($cat_details['name_en'] ?? '');
    
    // Title Fallback:
    // 1. localized category SEO field
    // 2. localized category name (resolved to "Category Name Projects | World of Shelves")
    // 3. safe global projects fallback
    $cat_seo_title = ($lang === 'ar') ? ($cat_details['seo_title_ar'] ?? '') : ($cat_details['seo_title_en'] ?? '');
    
    if (!empty($cat_seo_title)) {
        $seo_title = $cat_seo_title;
    } elseif (!empty($cat_name)) {
        $seo_title = ($lang === 'ar') ? ($cat_name . ' | مشاريع عالم الرفوف') : ($cat_name . ' Projects | World of Shelves');
    } else {
        $seo_title = $proj_title;
    }

    // Description Fallback:
    // 1. localized category SEO field
    // 2. localized category description
    // 3. localized category name fallback string
    // 4. safe global projects fallback
    $cat_meta_desc = ($lang === 'ar') ? ($cat_details['meta_desc_ar'] ?? '') : ($cat_details['meta_desc_en'] ?? '');
    $cat_desc = ($lang === 'ar') ? ($cat_details['description_ar'] ?? '') : ($cat_details['description_en'] ?? '');

    if (!empty($cat_meta_desc)) {
        $seo_desc = $cat_meta_desc;
    } elseif (!empty($cat_desc)) {
        $seo_desc = mb_substr(strip_tags($cat_desc), 0, 155);
    } elseif (!empty($cat_name)) {
        $seo_desc = ($lang === 'ar')
            ? ('استعرض مشاريع ' . $cat_name . ' المنفذة من عالم الرفوف في السعودية.')
            : ('Explore completed ' . $cat_name . ' projects by World of Shelves in Saudi Arabia.');
    } else {
        $seo_desc = $proj_desc;
    }

    $seo = [
        'title_override' => $seo_title,
        'desc_raw' => $seo_desc,
        'type' => 'website',
        'canonical_override' => get_site_url() . '/projects/category/' . $cat_details['slug'] . ($lang === 'en' ? '?lang=en' : '')
    ];
} else {
    $seo = [
        'title_override' => $proj_title,
        'desc_raw' => $proj_desc,
        'type' => 'website',
        'canonical_override' => get_site_url() . '/projects' . ($lang === 'en' ? '?lang=en' : '')
    ];
}

// Populate collection for structured data
$seo_projects = [];
$site_url = get_site_url();
foreach ($projects as $p) {
    $p_title = ($lang === 'ar') ? $p['title_ar'] : $p['title_en'];
    $seo_projects[] = [
        'name' => $p_title,
        'url' => get_project_details_url($p['slug'], $lang, true),
        'image' => $site_url . '/' . ltrim($p['main_image'], '/')
    ];
}
$seo['collection_projects'] = $seo_projects;

// Define breadcrumbs BEFORE header load so they are consumed by seo-helper.php JSON-LD
$lang_suffix = ($lang === 'en') ? '?lang=en' : '';
if ($cat_details) {
    $breadcrumbs = [
        __('nav_portfolio') => 'projects' . $lang_suffix,
        (($lang === 'ar') ? $cat_details['name_ar'] : $cat_details['name_en']) => 'projects/category/' . $cat_details['slug'] . $lang_suffix
    ];
} else {
    $breadcrumbs = [
        __('nav_portfolio') => 'projects' . $lang_suffix
    ];
}

include_once __DIR__ . '/includes/header.php';
?>
<main>
<?php
$page_title_cms = get_setting('projects_page_title_' . $lang, __('portfolio_title'));
$page_subtitle_cms = get_setting('projects_page_subtitle_' . $lang, __('portfolio_subtitle'));

// Render visible breadcrumbs
include __DIR__ . '/includes/breadcrumbs.php';
?>

<div class="container about-page-container">
    <!-- Header Section -->
    <div class="about-header-banner reveal-on-scroll">
        <h1><?php echo htmlspecialchars($cat_details ? (($lang === 'ar') ? $cat_details['name_ar'] : $cat_details['name_en']) : $page_title_cms, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>
            <?php 
            if ($cat_details) {
                echo htmlspecialchars(($lang === 'ar') ? ($cat_details['description_ar'] ?? '') : ($cat_details['description_en'] ?? ''), ENT_QUOTES, 'UTF-8');
            } else {
                echo htmlspecialchars($page_subtitle_cms, ENT_QUOTES, 'UTF-8');
            }
            ?>
        </p>
        <div class="about-header-divider"></div>
    </div>

    <!-- Portfolio Filter Bar -->
    <div class="portfolio-filter-bar reveal-on-scroll">
        <a href="<?php echo get_site_url() . '/projects' . ($lang === 'en' ? '?lang=en' : ''); ?>" class="filter-btn <?php echo !$cat_details ? 'active' : ''; ?>">
            <?php echo __('filter_all'); ?>
        </a>
        <?php foreach ($db_categories as $cat): ?>
            <?php 
            $cat_name = ($lang === 'ar') ? $cat['name_ar'] : $cat['name_en'];
            $is_cat_active = $cat_details && (int)$cat_details['id'] === (int)$cat['id'];
            $cat_url = get_site_url() . '/projects/category/' . $cat['slug'] . ($lang === 'en' ? '?lang=en' : '');
            ?>
            <a href="<?php echo htmlspecialchars($cat_url); ?>" class="filter-btn <?php echo $is_cat_active ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Projects Grid -->
    <?php if (count($projects) === 0): ?>
        <div style="text-align: center; padding: 60px 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); margin-top: 30px; min-height: 250px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div style="background-color: var(--border-color); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <i data-lucide="folder-open" style="width: 32px; height: 32px; color: var(--text-secondary);"></i>
            </div>
            <p style="font-size: 16px; font-weight: 600; color: var(--text-secondary); margin: 0;"><?php echo __('empty_portfolio_message'); ?></p>
        </div>
    <?php else: ?>
        <div class="projects-grid reveal-on-scroll">
            <?php foreach ($projects as $p): 
                $p_title_ar = (string)($p['title_ar'] ?? '');
                $p_title_en = (string)($p['title_en'] ?? '');
                $p_location_ar = (string)($p['location_ar'] ?? '');
                $p_location_en = (string)($p['location_en'] ?? '');
                $p_category = (string)($p['category'] ?? '');
                $p_desc_ar = (string)($p['desc_ar'] ?? '');
                $p_desc_en = (string)($p['desc_en'] ?? '');
                $p_slug = (string)($p['slug'] ?? '');
                $p_main_image = (string)($p['main_image'] ?? '');
                $p_date = (string)($p['date'] ?? '');

                $title = ($lang === 'ar') ? $p_title_ar : $p_title_en;
                $location = ($lang === 'ar') ? $p_location_ar : $p_location_en;
                $category_label = get_project_category_label($p_category, $lang);
                $desc = ($lang === 'ar') ? $p_desc_ar : $p_desc_en;
                $detail_link = get_project_details_url($p_slug, $lang);
            ?>
                <div class="project-card project-card-item" data-category="<?php echo htmlspecialchars($p_category, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="project-image-wrapper">
                        <?php if (!empty($category_label)): ?>
                            <span class="project-badge"><?php echo htmlspecialchars($category_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars($p_main_image, ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" 
                             class="project-img" 
                             width="640"
                             height="400"
                             loading="lazy" 
                             decoding="async" />
                    </div>
                    <div class="project-card-content">
                        <h2><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <div class="project-meta-info">
                            <?php if (!empty($location)): ?>
                                <span><i data-lucide="map-pin"></i> <?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($p_date)): ?>
                                <span><i data-lucide="calendar"></i> <?php echo htmlspecialchars($p_date, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($desc)): ?>
                            <p><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars($detail_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary-cta">
                            <span><?php echo __('view_project'); ?></span>
                            <i data-lucide="<?php echo $lang === 'ar' ? 'arrow-left' : 'arrow-right'; ?>" class="arrow-icon"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- CTA Block -->
<?php
$cta_page = 'home';
include __DIR__ . '/includes/cta-block.php';
?>

</main>
<?php
include_once __DIR__ . '/includes/footer.php';
?>
