<?php
// products.php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/product-helper.php';

// Category legacy redirect to clean URL
if (isset($_GET['category']) && !isset($_GET['search']) && !isset($_GET['page'])) {
    $allowed_keys = ['category', 'lang'];
    $unexpected = false;
    foreach ($_GET as $key => $val) {
        if (!in_array($key, $allowed_keys, true)) {
            $unexpected = true;
            break;
        }
    }
    
    if (!$unexpected) {
        $cat_val = trim($_GET['category']);
        try {
            $stmt = $pdo->prepare("SELECT slug FROM categories WHERE code = :cat OR id = :cat_id OR slug = :cat_slug LIMIT 1");
            $stmt->execute([':cat' => $cat_val, ':cat_id' => is_numeric($cat_val) ? (int)$cat_val : 0, ':cat_slug' => $cat_val]);
            $cat_slug = $stmt->fetchColumn();
            if ($cat_slug) {
                $lang = get_current_lang();
                $redirect_url = get_site_url() . '/products/category/' . $cat_slug;
                if ($lang === 'en') {
                    $redirect_url .= '?lang=en';
                }
                header("Location: " . $redirect_url, true, 301);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error in category legacy redirect: " . $e->getMessage());
        }
    }
}

// inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_slug = isset($_GET['category_slug']) ? trim($_GET['category_slug']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;

$lang = get_current_lang();

// 1. Count Total Matching Products
$count_query = "SELECT COUNT(*) FROM products WHERE is_active = 1";
$count_params = [];

if (!empty($search)) {
    $count_query .= " AND (name_ar LIKE :search OR name_en LIKE :search OR description_ar LIKE :search OR description_en LIKE :search)";
    $count_params[':search'] = '%' . $search . '%';
}

if (!empty($category)) {
    $count_query .= " AND category = :category";
    $count_params[':category'] = $category;
}

try {
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_products = (int)$count_stmt->fetchColumn();
} catch (PDOException $e) {
    $total_products = 0;
}

// 2. Fetch Category details if specified via slug or code
$cat_details = null;
if ($category_slug !== '') {
    try {
        $c_stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = :slug LIMIT 1");
        $c_stmt->execute([':slug' => $category_slug]);
        $cat_details = $c_stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat_details) {
            $category = $cat_details['code'];
        } else {
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
} elseif ($category !== '') {
    try {
        $c_stmt = $pdo->prepare("SELECT * FROM categories WHERE code = :cat OR id = :cat_id OR slug = :cat_slug LIMIT 1");
        $c_stmt->execute([':cat' => $category, ':cat_id' => is_numeric($category) ? (int)$category : 0, ':cat_slug' => $category]);
        $cat_details = $c_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cat_details = null;
    }
}

// 3. Resolve Dynamic SEO Metadata
if (!empty($cat_details)) {
    $cat_name = ($lang === 'ar') ? ($cat_details['name_ar'] ?? '') : ($cat_details['name_en'] ?? '');
    
    // Title Fallback: 
    // 1. localized category SEO field
    // 2. localized category name (resolved to "Category Name | Site Name")
    // 3. safe global products fallback
    $cat_seo_title = ($lang === 'ar') ? ($cat_details['seo_title_ar'] ?? '') : ($cat_details['seo_title_en'] ?? '');
    
    $global_title = get_setting($lang === 'ar' ? 'seo_products_title_ar' : 'seo_products_title_en');
    if (empty($global_title)) {
        $global_title = ($lang === 'ar') 
            ? 'أنظمة وأرفف التخزين للمستودعات والمحلات | عالم الرفوف'
            : 'Warehouse Racking & Shelving Systems | World of Shelves';
    }
    
    if (!empty($cat_seo_title)) {
        $prod_title = $cat_seo_title;
    } elseif (!empty($cat_name)) {
        $prod_title = ($lang === 'ar') ? ($cat_name . ' | عالم الرفوف') : ($cat_name . ' | World of Shelves');
    } else {
        $prod_title = $global_title;
    }

    // Description Fallback:
    // 1. localized category SEO field
    // 2. localized category description
    // 3. localized category name fallback string
    // 4. safe global products fallback
    $cat_meta_desc = ($lang === 'ar') ? ($cat_details['meta_desc_ar'] ?? '') : ($cat_details['meta_desc_en'] ?? '');
    $cat_intro_desc = ($lang === 'ar') ? ($cat_details['description_ar'] ?? '') : ($cat_details['description_en'] ?? '');
    
    $global_desc = get_setting($lang === 'ar' ? 'seo_products_desc_ar' : 'seo_products_desc_en');
    if (empty($global_desc)) {
        $global_desc = ($lang === 'ar')
            ? 'تصفح كتالوج الأرفف الحديدية وأنظمة التخزين للمستودعات والمحلات والمصانع من عالم الرفوف بأسعار ممتازة.'
            : 'Browse industrial steel racking, warehouse storage systems, and retail shelving catalogue from World of Shelves.';
    }

    if (!empty($cat_meta_desc)) {
        $prod_desc = $cat_meta_desc;
    } elseif (!empty($cat_intro_desc)) {
        $prod_desc = $cat_intro_desc;
    } elseif (!empty($cat_name)) {
        $prod_desc = ($lang === 'ar')
            ? ('تصفح أنظمة وأرفف ' . $cat_name . ' والمواصفات الكاملة من عالم الرفوف في السعودية.')
            : ('Explore ' . $cat_name . ' storage racking and shelving systems from World of Shelves Saudi Arabia.');
    } else {
        $prod_desc = $global_desc;
    }

    $cat_canonical_base = get_site_url() . '/products/category/' . $cat_details['slug'];
    $cat_canonical_params = [];
    if ($page > 1) {
        $cat_canonical_params['page'] = $page;
    }
    if ($lang === 'en') {
        $cat_canonical_params['lang'] = 'en';
    }
    $cat_canonical_url = $cat_canonical_base;
    if (count($cat_canonical_params) > 0) {
        $cat_canonical_url .= '?' . http_build_query($cat_canonical_params);
    }

    $seo = [
        'title_override' => $prod_title,
        'desc_raw' => $prod_desc,
        'type' => 'website',
        'canonical_override' => $cat_canonical_url,
        'canonical_params' => []
    ];
} else {
    // Global page:
    // CMS localized SEO value
    // → current existing localized hardcoded/default value
    // → safe site-wide fallback
    $cms_products_title = get_setting($lang === 'ar' ? 'seo_products_title_ar' : 'seo_products_title_en');
    $cms_products_desc = get_setting($lang === 'ar' ? 'seo_products_desc_ar' : 'seo_products_desc_en');

    $default_products_title = ($lang === 'ar') 
        ? 'أنظمة وأرفف التخزين للمستودعات والمحلات | عالم الرفوف'
        : 'Warehouse Racking & Shelving Systems | World of Shelves';
    $default_products_desc = ($lang === 'ar')
        ? 'تصفح كتالوج الأرفف الحديدية وأنظمة التخزين للمستودعات والمحلات والمصانع من عالم الرفوف بأسعار ممتازة.'
        : 'Browse industrial steel racking, warehouse storage systems, and retail shelving catalogue from World of Shelves.';

    $site_wide_title = get_setting($lang === 'ar' ? 'seo_title_ar' : 'seo_title_en');
    $site_wide_desc = get_setting($lang === 'ar' ? 'seo_desc_ar' : 'seo_desc_en');

    $prod_title = !empty($cms_products_title) ? $cms_products_title : (!empty($default_products_title) ? $default_products_title : $site_wide_title);
    $prod_desc = !empty($cms_products_desc) ? $cms_products_desc : (!empty($default_products_desc) ? $default_products_desc : $site_wide_desc);

    $seo = [
        'title_override' => $prod_title,
        'desc_raw' => $prod_desc,
        'type' => 'website',
        'canonical_params' => ['page']
    ];
}

// If searching or filtering an invalid/empty category, set noindex to protect search quality
if ($total_products === 0 || ($category !== '' && empty($cat_details)) || !empty($search)) {
    $seo['noindex'] = true;
}

$total_pages = max(1, (int)ceil($total_products / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

// 2. Fetch Paginated Products
$query = "SELECT * FROM products WHERE is_active = 1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name_ar LIKE :search OR name_en LIKE :search OR description_ar LIKE :search OR description_en LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($category)) {
    $query .= " AND category = :category";
    $params[':category'] = $category;
}

$query .= " ORDER BY sort_order ASC, id DESC LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// Populate normalized collection products for search crawler CollectionPage schema
$seo_products = [];
$site_url = get_site_url();
foreach ($products as $p) {
    $p_name = (get_current_lang() === 'ar') ? $p['name_ar'] : $p['name_en'];
    $seo_products[] = [
        'name' => $p_name,
        'url' => get_product_details_url($p, $lang, true),
        'image' => $site_url . '/' . ltrim($p['image_url'], '/')
    ];
}
$seo['collection_products'] = $seo_products;

// Define breadcrumbs BEFORE header load so they are consumed by seo-helper.php JSON-LD
$lang_suffix = ($lang === 'en') ? '?lang=en' : '';
if (!empty($cat_details)) {
    $breadcrumbs = [
        __('nav_products') => 'products' . $lang_suffix,
        (($lang === 'ar') ? $cat_details['name_ar'] : $cat_details['name_en']) => 'products/category/' . $cat_details['slug'] . $lang_suffix
    ];
} else {
    $breadcrumbs = [
        __('nav_products') => 'products' . $lang_suffix
    ];
}

include_once __DIR__ . '/includes/header.php';



// Fetch categories dynamically
$categories = [];
try {
    $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $categories_list = $cat_stmt->fetchAll();
    foreach ($categories_list as $c) {
        $categories[$c['code']] = (get_current_lang() == 'ar') ? $c['name_ar'] : $c['name_en'];
    }
} catch (PDOException $e) {
    // fallback
}
?>

<main class="container">
<?php
// Render visible breadcrumbs
include __DIR__ . '/includes/breadcrumbs.php';
?>
    <div class="catalog-layout">
        <aside class="sidebar-filter">
            <div class="filter-section" id="category-filter-section">
                <!-- Desktop Header -->
                <h2 class="filter-title desktop-only-filter-title" style="font-size: 16px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                    <i data-lucide="filter" class="filter-header-icon" aria-hidden="true"></i> 
                    <?php echo __('filter_category'); ?>
                </h2>

                <!-- Mobile Header -->
                <h2 class="filter-title-mobile">
                    <span><?php echo __('filter_category'); ?></span>
                    <i data-lucide="filter" class="filter-header-icon" aria-hidden="true" style="width: 16px; height: 16px; color: var(--accent-primary);"></i>
                </h2>

                <!-- Mobile Trigger Button -->
                <?php 
                $active_cat_label = !empty($category) && isset($categories[$category]) ? $categories[$category] : __('filter_all_categories');
                ?>
                <button type="button" 
                        id="mobile-filter-btn" 
                        class="mobile-filter-trigger" 
                        aria-expanded="false" 
                        aria-controls="mobile-filter-list"
                        aria-label="<?php echo htmlspecialchars(__('filter_category'), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mobile-filter-heading">
                        <i data-lucide="filter" class="mobile-filter-heading-icon" aria-hidden="true"></i>
                        <span><?php echo __('filter_category'); ?></span>
                    </div>
                    <div class="mobile-filter-selection">
                        <span class="selected-cat-name"><?php echo htmlspecialchars($active_cat_label); ?></span>
                        <i data-lucide="chevron-down" class="chevron-arrow" aria-hidden="true"></i>
                    </div>
                </button>

                <!-- Shared Category List -->
                <ul class="filter-list" id="mobile-filter-list">
                    <?php 
                    $all_url = 'products';
                    $all_params = [];
                    if ($search !== '') {
                        $all_params['search'] = $search;
                    }
                    if ($lang === 'en') {
                        $all_params['lang'] = 'en';
                    }
                    if (!empty($all_params)) {
                        $all_url .= '?' . http_build_query($all_params);
                    }
                    ?>
                    <li class="filter-item <?php echo empty($category) ? 'active' : ''; ?>">
                        <a href="<?php echo htmlspecialchars($all_url); ?>" <?php echo empty($category) ? 'aria-current="page"' : ''; ?>>
                            <span><?php echo __('filter_all_categories'); ?></span>
                            <?php if (empty($category)): ?>
                                <i data-lucide="check" class="active-check-icon" aria-hidden="true">✓</i>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if (isset($categories_list) && is_array($categories_list)): ?>
                        <?php foreach ($categories_list as $cat_row): ?>
                            <?php 
                            $cat_key = $cat_row['code'];
                            $cat_label = ($lang === 'ar') ? $cat_row['name_ar'] : $cat_row['name_en'];
                            $cat_slug = !empty($cat_row['slug']) ? $cat_row['slug'] : $cat_row['code'];
                            
                            $cat_url = 'products/category/' . $cat_slug;
                            $cat_params = [];
                            if ($search !== '') {
                                $cat_params['search'] = $search;
                            }
                            if ($lang === 'en') {
                                $cat_params['lang'] = 'en';
                            }
                            if (!empty($cat_params)) {
                                $cat_url .= '?' . http_build_query($cat_params);
                            }
                            ?>
                            <li class="filter-item <?php echo $category === $cat_key ? 'active' : ''; ?>">
                                <a href="<?php echo htmlspecialchars($cat_url); ?>" <?php echo $category === $cat_key ? 'aria-current="page"' : ''; ?>>
                                    <span><?php echo htmlspecialchars($cat_label); ?></span>
                                    <?php if ($category === $cat_key): ?>
                                        <i data-lucide="check" class="active-check-icon" aria-hidden="true">✓</i>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="catalog-main-content">
            <?php if ($cat_details): ?>
                <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 8px; color: var(--text-primary); text-align: start;"><?php echo htmlspecialchars($lang === 'ar' ? $cat_details['name_ar'] : $cat_details['name_en'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php 
                $intro_desc = ($lang === 'ar') ? ($cat_details['description_ar'] ?? '') : ($cat_details['description_en'] ?? '');
                if (!empty($intro_desc)):
                ?>
                    <p style="font-size: 15px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 24px; text-align: start;"><?php echo htmlspecialchars($intro_desc, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php else: ?>
                    <div style="margin-bottom: 24px;"></div>
                <?php endif; ?>
            <?php else: ?>
                <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 24px; color: var(--text-primary); text-align: start;"><?php echo __('nav_products'); ?></h1>
            <?php endif; ?>
            <!-- Search Bar -->
            <form action="<?php echo $cat_details ? 'products/category/' . $cat_details['slug'] : 'products'; ?>" method="GET" class="catalog-search-bar">
                <?php if ($lang === 'en'): ?>
                    <input type="hidden" name="lang" value="en">
                <?php endif; ?>
                <div class="search-input-wrapper">
                    <i data-lucide="search" aria-hidden="true"></i>
                    <input type="text" name="search" class="search-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo __('search_placeholder'); ?>">
                </div>
                <button type="submit" class="btn btn-primary search-submit-btn" aria-label="<?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i data-lucide="search" aria-hidden="true"></i>
                </button>
            </form>

            <!-- Products Listing Grid -->
            <?php if (count($products) > 0): ?>
                <div class="products-grid">
                    <?php foreach ($products as $prod): ?>
                        <?php 
                        $lang = get_current_lang();
                        $name = ($lang == 'ar') ? $prod['name_ar'] : $prod['name_en'];
                        $desc = ($lang == 'ar') ? $prod['description_ar'] : $prod['description_en'];
                        $alt  = ($lang == 'ar') ? (!empty($prod['alt_text_ar']) ? $prod['alt_text_ar'] : $prod['name_ar']) : (!empty($prod['alt_text_en']) ? $prod['alt_text_en'] : $prod['name_en']);
                        $category_label = isset($categories[$prod['category']]) ? $categories[$prod['category']] : $prod['category'];

                        // Determine badges based on database flags
                        $badges = [];
                        $prod_stock = isset($prod['stock']) ? intval($prod['stock']) : 0;
                        if ($prod_stock <= 0) {
                            $badges[] = ['key' => 'out_of_stock_badge', 'class' => 'status-out-of-stock'];
                        }
                        if (!empty($prod['is_most_requested'])) {
                            $badges[] = ['key' => 'most_requested', 'class' => 'status-most-requested'];
                        }
                        if (!empty($prod['is_new'])) {
                            $badges[] = ['key' => 'new_product', 'class' => 'status-new'];
                        }
                        // Preserve non-marketing status badge (Used) for decorative category
                        if ($prod['category'] === 'decorative') {
                            $badges[] = ['key' => 'status_used', 'class' => 'status-used'];
                        }

                        $whatsapp_number = get_setting('whatsapp_number', '966500000000');
                        $whatsapp_msg = rawurlencode(($lang === 'ar') 
                            ? "السلام عليكم، أود طلب عرض سعر وتفاصيل لمنتج: " . $name 
                            : "Hello, I would like to request a quote and details for product: " . $name);
                        $whatsapp_link = "https://wa.me/{$whatsapp_number}?text={$whatsapp_msg}";
                        ?>
                        <div class="product-card">
                            <div class="product-card-media">
                                <a href="<?php echo get_product_details_url($prod, $lang); ?>" class="product-card-img-link" aria-label="<?php echo htmlspecialchars($name); ?>">
                                    <img src="<?php echo htmlspecialchars($prod['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($alt); ?>" 
                                         class="product-card-img"
                                         width="300"
                                         height="225"
                                         loading="lazy"
                                         decoding="async">
                                </a>
                                
                                <?php if (!empty($badges)): ?>
                                    <div class="product-card-badges">
                                        <?php foreach ($badges as $badge): ?>
                                            <span class="product-badge <?php echo $badge['class']; ?>"><?php echo htmlspecialchars(__($badge['key'])); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-card-content">
                                <span class="product-card-category"><?php echo htmlspecialchars($category_label); ?></span>
                                <h2 class="product-card-title">
                                    <a href="<?php echo get_product_details_url($prod, $lang); ?>"><?php echo htmlspecialchars($name); ?></a>
                                </h2>
                                <p class="product-card-desc"><?php echo htmlspecialchars($desc); ?></p>
                                
                                <div class="product-card-actions">
                                    <a href="<?php echo get_product_details_url($prod, $lang); ?>" class="btn-product-details">
                                        <span><?php echo __('view_details_btn'); ?></span>
                                        <i data-lucide="<?php echo get_lang_direction() === 'rtl' ? 'arrow-left' : 'arrow-right'; ?>" class="btn-arrow-icon" aria-hidden="true"></i>
                                    </a>
                                    <a href="<?php echo $whatsapp_link; ?>" target="_blank" rel="noopener noreferrer" class="btn-product-whatsapp" aria-label="<?php echo htmlspecialchars(__('request_quote_wa_aria') . ' ' . $name); ?>" title="<?php echo htmlspecialchars(__('request_quote_wa')); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                                           <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Premium Redesigned Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination-container" aria-label="<?php echo get_current_lang() === 'ar' ? 'التنقل بين الصفحات' : 'Pagination'; ?>">
                        <?php
                        $build_page_url = function($p_num) use ($category, $search) {
                            $q = [];
                            if (!empty($category)) $q['category'] = $category;
                            if (!empty($search)) $q['search'] = $search;
                            if ($p_num > 1) $q['page'] = $p_num;
                            if (isset($_GET['lang']) && $_GET['lang'] !== 'ar') $q['lang'] = $_GET['lang'];
                            return 'products' . (!empty($q) ? '?' . http_build_query($q) : '');
                        };
                        
                        $is_ar  = (get_current_lang() === 'ar');
                        $is_rtl = (get_lang_direction() === 'rtl');

                        // Dynamic Ellipsis Page Window Calculation
                        $pagination_items = [];
                        $window = 1;

                        if ($total_pages <= 7) {
                            for ($i = 1; $i <= $total_pages; $i++) {
                                $pagination_items[] = $i;
                            }
                        } else {
                            $pagination_items[] = 1;
                            $start = max(2, $page - $window);
                            $end   = min($total_pages - 1, $page + $window);

                            if ($start > 2) {
                                $pagination_items[] = '...';
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                $pagination_items[] = $i;
                            }

                            if ($end < $total_pages - 1) {
                                $pagination_items[] = '...';
                            }

                            $pagination_items[] = $total_pages;
                        }

                        $prev_icon = $is_rtl ? 'chevron-right' : 'chevron-left';
                        $next_icon = $is_rtl ? 'chevron-left' : 'chevron-right';
                        ?>

                        <ul class="pagination-list">
                            <!-- Previous Page Button -->
                            <li class="pagination-item">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo htmlspecialchars($build_page_url($page - 1)); ?>" class="pagination-btn nav-btn" aria-label="<?php echo $is_ar ? 'الصفحة السابقة' : 'Previous page'; ?>">
                                        <i data-lucide="<?php echo $prev_icon; ?>" aria-hidden="true"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-btn nav-btn disabled" aria-disabled="true">
                                        <i data-lucide="<?php echo $prev_icon; ?>" aria-hidden="true"></i>
                                    </span>
                                <?php endif; ?>
                            </li>

                            <!-- Page Numbers & Ellipses -->
                            <?php foreach ($pagination_items as $p_item): ?>
                                <li class="pagination-item">
                                    <?php if ($p_item === '...'): ?>
                                        <span class="pagination-ellipsis" aria-hidden="true">&hellip;</span>
                                    <?php elseif ($p_item == $page): ?>
                                        <span class="pagination-btn page-num active" aria-current="page"><?php echo $p_item; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($build_page_url($p_item)); ?>" class="pagination-btn page-num"><?php echo $p_item; ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>

                            <!-- Next Page Button -->
                            <li class="pagination-item">
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo htmlspecialchars($build_page_url($page + 1)); ?>" class="pagination-btn nav-btn" aria-label="<?php echo $is_ar ? 'الصفحة التالية' : 'Next page'; ?>">
                                        <i data-lucide="<?php echo $next_icon; ?>" aria-hidden="true"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-btn nav-btn disabled" aria-disabled="true">
                                        <i data-lucide="<?php echo $next_icon; ?>" aria-hidden="true"></i>
                                    </span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-cart-view">
                    <i data-lucide="package-x" aria-hidden="true"></i>
                    <h2><?php echo __('no_products_found'); ?></h2>
                    <p class="empty-state-desc">
                        <?php echo get_current_lang() === 'ar' ? 'جرّب البحث بكلمات أخرى أو تحقق من تهجئة الحروف.' : 'Try searching with different keywords or check your spelling.'; ?>
                    </p>
                    <a href="products" class="btn btn-primary" style="margin-top: 16px;"><?php echo __('continue_shopping'); ?></a>
                </div>
            <?php endif; ?>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchForm = document.querySelector('.catalog-search-bar');
                if (searchForm) {
                    searchForm.addEventListener('submit', function() {
                        const submitBtn = this.querySelector('.search-submit-btn');
                        if (submitBtn) {
                            const icon = submitBtn.querySelector('i, svg');
                            if (icon) {
                                icon.setAttribute('data-lucide', 'loader-2');
                                icon.classList.add('spinning');
                                if (window.lucide) {
                                    window.lucide.createIcons();
                                }
                            }
                        }
                    });
                }

                // Mobile Collapsible Category Filter logic
                const filterBtn = document.getElementById('mobile-filter-btn');
                const filterSection = document.getElementById('category-filter-section');
                
                if (filterBtn && filterSection) {
                    filterBtn.addEventListener('click', function() {
                        const isExpanded = this.getAttribute('aria-expanded') === 'true';
                        this.setAttribute('aria-expanded', !isExpanded);
                        filterSection.classList.toggle('open');
                    });

                    // Explicit close handler when any category link is clicked (e.g. for future AJAX loading)
                    const categoryLinks = filterSection.querySelectorAll('.filter-list a');
                    categoryLinks.forEach(link => {
                        link.addEventListener('click', function() {
                            filterBtn.setAttribute('aria-expanded', 'false');
                            filterSection.classList.remove('open');
                        });
                    });

                    // Close dropdown on Escape key press
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && filterSection.classList.contains('open')) {
                            filterBtn.setAttribute('aria-expanded', 'false');
                            filterSection.classList.remove('open');
                            filterBtn.focus();
                        }
                    });

                    // Close dropdown when clicking outside
                    document.addEventListener('click', function(e) {
                        if (filterSection.classList.contains('open') && !filterSection.contains(e.target)) {
                            filterBtn.setAttribute('aria-expanded', 'false');
                            filterSection.classList.remove('open');
                        }
                    });

                    // Reset dropdown state when resizing from mobile to desktop
                    window.addEventListener('resize', function() {
                        if (window.innerWidth > 768 && filterSection.classList.contains('open')) {
                            filterBtn.setAttribute('aria-expanded', 'false');
                            filterSection.classList.remove('open');
                        }
                    });
                }
            });
            </script>
        </div>
    </div>
</main>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
