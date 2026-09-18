<?php
// product-details.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/lang_helper.php';
require_once __DIR__ . '/includes/product-helper.php';
require_once __DIR__ . '/includes/portfolio-helper.php';

// Fetch product details
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = null;

if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT p.*, c.slug AS category_slug, c.name_ar AS category_name_ar, c.name_en AS category_name_en 
                           FROM products p 
                           LEFT JOIN categories c ON p.category = c.code 
                           WHERE p.slug = :slug");
    $stmt->execute([':slug' => $slug]);
    $product = $stmt->fetch();
} elseif ($id > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.slug AS category_slug, c.name_ar AS category_name_ar, c.name_en AS category_name_en 
                           FROM products p 
                           LEFT JOIN categories c ON p.category = c.code 
                           WHERE p.id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
}

if (!$product || (isset($product['is_active']) && $product['is_active'] == 0)) {
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

// 301 Permanent Redirect for legacy product URLs (?id=N)
if (empty($_GET['slug']) && !empty($product['slug'])) {
    $lang = get_current_lang();
    $redirect_url = get_product_details_url($product, $lang, true);
    header("Location: " . $redirect_url, true, 301);
    exit;
}

// Fetch Gallery, Colors, and Models Payload
$lang = get_current_lang();
$gallery_payload = get_product_gallery_payload($pdo, $product, $lang);
$gallery_images  = $gallery_payload['images'];
$product_colors  = $gallery_payload['colors'];
$product_models  = $gallery_payload['models'];

$categories = [
    'warehouse' => __('category_industrial'),
    'supermarket' => __('category_standing'),
    'store-accessories' => __('category_decorative'),
    'shelf-accessories' => __('category_wall')
];

// Resolve OG Image path from Media Library if og_media_id is set
$og_image_url = '';
if (!empty($product['og_media_id'])) {
    try {
        $og_stmt = $pdo->prepare("SELECT file_path FROM media_library WHERE id = :id LIMIT 1");
        $og_stmt->execute([':id' => (int)$product['og_media_id']]);
        $og_image_url = $og_stmt->fetchColumn() ?: '';
    } catch (PDOException $e) {
        $og_image_url = '';
    }
}
if (empty($og_image_url)) {
    $og_image_url = !empty($gallery_images[0]['url']) ? $gallery_images[0]['url'] : $product['image_url'];
}

// Define SEO metadata dynamically using product data & stored SEO overrides
$p_name = ($lang === 'ar') ? $product['name_ar'] : $product['name_en'];
$p_desc = ($lang === 'ar') ? $product['description_ar'] : $product['description_en'];
$p_seo_title = ($lang === 'ar') ? ($product['seo_title_ar'] ?? '') : ($product['seo_title_en'] ?? '');
$p_meta_desc = ($lang === 'ar') ? ($product['meta_description_ar'] ?? '') : ($product['meta_description_en'] ?? '');
$p_alt_text  = ($lang === 'ar') ? ($product['alt_text_ar'] ?? '') : ($product['alt_text_en'] ?? '');
if (empty($p_alt_text)) {
    $p_alt_text = $p_name;
}

$seo_title_final = !empty($p_seo_title) ? $p_seo_title : null;
$seo_desc_final  = !empty($p_meta_desc) ? $p_meta_desc : (mb_strlen(strip_tags($p_desc)) > 155 ? mb_substr(strip_tags($p_desc), 0, 155) . '...' : strip_tags($p_desc));

// Resolve localized human-readable category name for schema builder
// Resolve localized human-readable category name for schema builder (already resolved in single JOIN query)
$product_cat_slug = !empty($product['category_slug']) ? $product['category_slug'] : '';
$product_cat_name = '';
if (!empty($product_cat_slug)) {
    $product_cat_name = ($lang === 'ar') ? $product['category_name_ar'] : $product['category_name_en'];
}
$resolved_category_name = !empty($product_cat_name) ? $product_cat_name : $product['category'];

$lang_suffix = ($lang === 'en') ? '?lang=en' : '';
$breadcrumbs = [
    __('nav_products') => 'products' . $lang_suffix
];
if (!empty($product_cat_name) && !empty($product_cat_slug)) {
    $breadcrumbs[$product_cat_name] = 'products/category/' . $product_cat_slug . $lang_suffix;
}
$breadcrumbs[$p_name] = get_product_details_url($product, $lang);

$seo = [
    'title_raw' => $p_name,
    'title_override' => $seo_title_final,
    'desc_raw' => $seo_desc_final,
    'type' => 'product',
    'image' => $og_image_url,
    'canonical_override' => !empty($product['canonical_url']) ? $product['canonical_url'] : get_product_details_url($product, $lang, true),
    'canonical_params' => [],
    'product' => array_merge($product, ['resolved_category_name' => $resolved_category_name]) // Consumed by JSON-LD generator in seo-helper.php
];

include_once __DIR__ . '/includes/header.php';
?>
<main>
<?php

$name = ($lang == 'ar') ? $product['name_ar'] : $product['name_en'];
$desc = ($lang == 'ar') ? $product['description_ar'] : $product['description_en'];
$materials = ($lang == 'ar') ? $product['materials_ar'] : $product['materials_en'];

// Dynamic Product Condition
if ($product['category'] == 'decorative') {
    $condition_key = 'status_used';
} else {
    $condition_key = 'status_new';
}

// Official WhatsApp Link Configuration (Clean without quantity)
$whatsapp_number = get_setting('whatsapp_number', '966500000000');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$product_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$whatsapp_text = rawurlencode(($lang === 'ar') 
    ? "السلام عليكم، أود طلب عرض سعر وتفاصيل لمنتج: " . $name . "\nرابط المنتج: " . $product_url
    : "Hello, I would like to request a quote and details for: " . $name . "\nProduct link: " . $product_url);
$whatsapp_link = "https://wa.me/{$whatsapp_number}?text={$whatsapp_text}";

// Fetch Dynamic Suitable For and Why Product Detail Cards, Tech Specs & Related Content
$suitable_cards   = get_product_detail_cards($pdo, $id, 'suitable_for');
$why_cards        = get_product_detail_cards($pdo, $id, 'why_product');
$tech_specs       = get_product_tech_specs($product);
$related_products = get_related_products($pdo, $product, 4);

$short_intro       = ($lang === 'ar') ? ($product['short_intro_ar'] ?? '') : ($product['short_intro_en'] ?? '');
$selling_points    = get_product_json_field($product, 'selling_points');
$benefits          = get_product_json_field($product, 'benefits');
$industries_served = get_product_json_field($product, 'industries_served');
$cta_settings      = get_product_json_field($product, 'cta_settings');
$related_projects  = get_related_projects($pdo, $product, 3);
$related_services  = get_related_services($pdo, $product, 3);
?>

<div class="container product-details-container">
    <!-- Breadcrumbs -->
    <?php include __DIR__ . '/includes/breadcrumbs.php'; ?>

    <!-- Live Region for Screen Readers -->
    <div id="gallery-live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>

    <div class="detail-grid">
        <!-- Product Image & Gallery Container -->
        <div class="detail-img-card-wrapper">
            <div class="detail-img-card">
                <?php 
                $primary_img_url = !empty($gallery_images[0]['url']) ? $gallery_images[0]['url'] : $product['image_url'];
                $primary_img_alt = !empty($gallery_images[0]['alt']) ? $gallery_images[0]['alt'] : $p_alt_text;
                ?>
                <img src="<?php echo htmlspecialchars($primary_img_url); ?>" 
                     alt="<?php echo htmlspecialchars($primary_img_alt); ?>" 
                     class="detail-img" 
                     id="mainDetailsImage"
                     width="540"
                     height="400"
                     fetchpriority="high"
                     loading="eager"
                     decoding="sync">

                <!-- Lightbox Trigger Overlay Button -->
                <button type="button" id="mainImageLightboxBtn" class="lightbox-trigger-btn" aria-label="<?php echo htmlspecialchars(__('gallery_open_lightbox')); ?>" title="<?php echo htmlspecialchars(__('gallery_open_lightbox')); ?>">
                    <i data-lucide="maximize-2" aria-hidden="true"></i>
                </button>
            </div>

            <!-- Dynamic Thumbnail Gallery -->
            <?php if (count($gallery_images) > 1): ?>
                <div class="details-thumbnail-gallery" role="tablist" aria-label="<?php echo htmlspecialchars(__('tab_gallery')); ?>">
                    <?php foreach ($gallery_images as $idx => $gimg): ?>
                        <button type="button" 
                                class="thumbnail-item <?php echo $idx === 0 ? 'active' : ''; ?>" 
                                role="tab"
                                aria-selected="<?php echo $idx === 0 ? 'true' : 'false'; ?>"
                                data-src="<?php echo htmlspecialchars($gimg['url']); ?>"
                                data-alt="<?php echo htmlspecialchars($gimg['alt']); ?>">
                            <img src="<?php echo htmlspecialchars($gimg['url']); ?>" alt="<?php echo htmlspecialchars($gimg['alt']); ?>" width="80" height="60" loading="lazy" decoding="async">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Product Info & Inquiries Box -->
        <div class="detail-content">
            <?php
            $category_label = !empty($product_cat_name) ? $product_cat_name : $product['category'];
            ?>
            <span class="detail-category"><?php echo htmlspecialchars($category_label); ?></span>
            <h1 class="detail-title"><?php echo htmlspecialchars($name); ?></h1>

            <?php if (!empty($short_intro)): ?>
                <p class="detail-short-intro" style="font-size: 16px; font-weight: 600; color: var(--accent-primary); line-height: 1.6; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($short_intro); ?>
                </p>
            <?php endif; ?>

            <p class="detail-desc"><?php echo htmlspecialchars($desc); ?></p>

            <!-- Meta Specs Info Cards Grid -->
            <div class="meta-info-grid">
                <?php 
                $resolved_dimensions = '';
                if ($lang === 'ar') {
                    if (!empty($product['dimensions_ar'])) {
                        $resolved_dimensions = $product['dimensions_ar'];
                    } elseif (!empty($product['dimensions'])) {
                        $resolved_dimensions = $product['dimensions'];
                    }
                } else {
                    // English
                    if (!empty($product['dimensions_en'])) {
                        $resolved_dimensions = $product['dimensions_en'];
                    }
                }
                if (!empty($resolved_dimensions)): ?>
                    <div class="meta-item">
                        <div class="meta-item-header">
                            <i data-lucide="ruler" class="meta-icon" aria-hidden="true"></i>
                            <span class="meta-label"><?php echo __('dimensions'); ?></span>
                        </div>
                        <span class="meta-val"><?php echo htmlspecialchars($resolved_dimensions); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($materials)): ?>
                    <div class="meta-item">
                        <div class="meta-item-header">
                            <i data-lucide="layers" class="meta-icon" aria-hidden="true"></i>
                            <span class="meta-label"><?php echo __('materials'); ?></span>
                        </div>
                        <span class="meta-val"><?php echo htmlspecialchars($materials); ?></span>
                    </div>
                <?php endif; ?>

                <div class="meta-item">
                    <div class="meta-item-header">
                        <i data-lucide="shield-check" class="meta-icon" aria-hidden="true"></i>
                        <span class="meta-label"><?php echo __('condition'); ?></span>
                    </div>
                    <span class="meta-val condition-val"><?php echo __($condition_key); ?></span>
                </div>

                <div class="meta-item">
                    <div class="meta-item-header">
                        <i data-lucide="package-check" class="meta-icon" aria-hidden="true"></i>
                        <span class="meta-label"><?php echo __('availability'); ?></span>
                    </div>
                    <span class="meta-val">
                        <?php if ($product['stock'] > 0): ?>
                            <span class="stock-status-badge in-stock"><?php echo __('in_stock'); ?></span>
                        <?php else: ?>
                            <span class="stock-status-badge out-of-stock"><?php echo __('out_of_stock'); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <!-- Primary WhatsApp & CTA Box -->
            <div class="details-cta-box">
                <?php if ($product['stock'] > 0): ?>
                    <div class="details-buttons-row" style="display: flex; flex-direction: column; gap: 10px;">
                        <?php if (!isset($cta_settings['whatsapp']) || $cta_settings['whatsapp']): ?>
                            <a href="<?php echo $whatsapp_link; ?>" id="waQuoteBtn" target="_blank" rel="noopener noreferrer" class="btn-whatsapp-quote btn-full" style="width: 100%; height: 52px; font-size: 16px; font-weight: 700;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                                  <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                                </svg>
                                <span><?php echo __('request_quote_wa'); ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($product['catalog_pdf_url']) && (!isset($cta_settings['download_catalog']) || $cta_settings['download_catalog'])): ?>
                            <a href="<?php echo htmlspecialchars($product['catalog_pdf_url']); ?>" target="_blank" download class="btn btn-secondary" style="width: 100%; height: 44px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                                <i data-lucide="file-text" style="width: 18px;"></i>
                                <span><?php echo $lang === 'ar' ? 'تحميل الكتالوج والمواصفات (PDF)' : 'Download Catalog & Specs (PDF)'; ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Section 3: Customer Benefits -->
    <?php if (!empty($benefits)): ?>
        <section class="benefits-section" style="margin-top: 40px; margin-bottom: 40px;">
            <h2 class="details-section-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="sparkles" style="color: var(--accent-primary);"></i>
                <span><?php echo $lang === 'ar' ? 'فوائد المنتج للعميل' : 'Customer Benefits'; ?></span>
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
                <?php foreach ($benefits as $b): 
                    $b_txt = ($lang === 'ar') ? ($b['ar'] ?? '') : ($b['en'] ?? '');
                    if (empty($b_txt)) continue;
                ?>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; display: flex; align-items: flex-start; gap: 12px; box-shadow: var(--shadow-sm);">
                        <i data-lucide="check-circle-2" style="color: var(--accent-primary); width: 22px; height: 22px; flex-shrink: 0; margin-top: 2px;"></i>
                        <span style="font-size: 14.5px; font-weight: 600; color: var(--text-primary); line-height: 1.5;"><?php echo htmlspecialchars($b_txt); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Section 4: Main Selling Points -->
    <?php if (!empty($selling_points)): ?>
        <section class="selling-points-section" style="margin-top: 40px; margin-bottom: 40px;">
            <h2 class="details-section-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="check-square" style="color: var(--accent-primary);"></i>
                <span><?php echo $lang === 'ar' ? 'أبرز مميزات ونقاط القوة' : 'Main Selling Points & Features'; ?></span>
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
                <?php foreach ($selling_points as $sp): 
                    $sp_txt = ($lang === 'ar') ? ($sp['ar'] ?? '') : ($sp['en'] ?? '');
                    if (empty($sp_txt)) continue;
                ?>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; display: flex; align-items: flex-start; gap: 12px; box-shadow: var(--shadow-sm);">
                        <i data-lucide="zap" style="color: #f59e0b; width: 22px; height: 22px; flex-shrink: 0; margin-top: 2px;"></i>
                        <span style="font-size: 14.5px; font-weight: 600; color: var(--text-primary); line-height: 1.5;"><?php echo htmlspecialchars($sp_txt); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Section 5: Suitable For -->
    <?php if (!empty($suitable_cards)): ?>
        <section class="suitable-section">
            <h2 class="details-section-title"><?php echo __('suitable_for'); ?></h2>
            <div class="suitable-grid">
                <?php foreach ($suitable_cards as $scard): 
                    $sc_title = ($lang === 'ar') ? $scard['title_ar'] : $scard['title_en'];
                    $sc_desc  = ($lang === 'ar') ? $scard['description_ar'] : $scard['description_en'];
                    $sc_icon  = validate_icon_name($scard['icon_name']);
                ?>
                    <div class="suitable-card">
                        <i data-lucide="<?php echo htmlspecialchars($sc_icon); ?>"></i>
                        <h3><?php echo htmlspecialchars($sc_title); ?></h3>
                        <?php if (!empty($sc_desc)): ?>
                            <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px; line-height: 1.4;"><?php echo htmlspecialchars($sc_desc); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Section 6: Why This Product? -->
    <?php if (!empty($why_cards)): ?>
        <section class="why-shelf-section">
            <h2 class="details-section-title"><?php echo __('why_this_shelf'); ?></h2>
            <div class="why-shelf-grid">
                <?php foreach ($why_cards as $wcard): 
                    $wc_title = ($lang === 'ar') ? $wcard['title_ar'] : $wcard['title_en'];
                    $wc_desc  = ($lang === 'ar') ? $wcard['description_ar'] : $wcard['description_en'];
                    $wc_icon  = validate_icon_name($wcard['icon_name']);
                ?>
                    <div class="why-shelf-card">
                        <div class="why-shelf-icon"><i data-lucide="<?php echo htmlspecialchars($wc_icon); ?>"></i></div>
                        <h3><?php echo htmlspecialchars($wc_title); ?></h3>
                        <?php if (!empty($wc_desc)): ?>
                            <p><?php echo htmlspecialchars($wc_desc); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Section 7: Technical Specifications -->
    <?php if (!empty($tech_specs)): ?>
        <section class="tech-specs-section" style="margin-top: 40px; margin-bottom: 40px;">
            <h2 class="details-section-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="file-text" style="color: var(--accent-primary);"></i>
                <span><?php echo __('technical_specs'); ?></span>
            </h2>
            <div class="tech-specs-table-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-sm);">
                <table class="tech-specs-table" style="width: 100%; border-collapse: collapse;">
                    <tbody>
                        <?php foreach ($tech_specs as $sidx => $spec): 
                            $s_key = ($lang === 'ar') ? (!empty($spec['key_ar']) ? $spec['key_ar'] : $spec['key_en']) : (!empty($spec['key_en']) ? $spec['key_en'] : $spec['key_ar']);
                            $s_val = ($lang === 'ar') ? (!empty($spec['value_ar']) ? $spec['value_ar'] : $spec['value_en']) : (!empty($spec['value_en']) ? $spec['value_en'] : $spec['value_ar']);
                            $bg_row = ($sidx % 2 === 0) ? 'var(--bg-primary)' : 'var(--bg-card)';
                        ?>
                            <tr style="border-bottom: 1px solid var(--border-color); background: <?php echo $bg_row; ?>;">
                                <th style="text-align: <?php echo get_lang_direction() === 'rtl' ? 'right' : 'left'; ?>; padding: 14px 18px; width: 35%; font-weight: 700; color: var(--text-primary); border-inline-end: 1px solid var(--border-color); font-size: 14px;">
                                    <?php echo htmlspecialchars($s_key); ?>
                                </th>
                                <td style="padding: 14px 18px; color: var(--text-secondary); font-size: 14px; font-weight: 500;">
                                    <?php echo htmlspecialchars($s_val); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- Section 8: Industries Served -->
    <?php if (!empty($industries_served)): ?>
        <section class="industries-section" style="margin-top: 40px; margin-bottom: 40px;">
            <h2 class="details-section-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="building-2" style="color: var(--accent-primary);"></i>
                <span><?php echo $lang === 'ar' ? 'القطاعات والصناعات المستهدفة' : 'Industries & Applications Served'; ?></span>
            </h2>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <?php foreach ($industries_served as $ind): 
                    $ind_txt = ($lang === 'ar') ? ($ind['ar'] ?? '') : ($ind['en'] ?? '');
                    if (empty($ind_txt)) continue;
                ?>
                    <span style="background: var(--bg-card); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 13.5px; color: var(--text-primary); display: inline-flex; align-items: center; gap: 6px; box-shadow: var(--shadow-sm);">
                        <i data-lucide="check" style="width: 14px; color: var(--accent-primary);"></i>
                        <span><?php echo htmlspecialchars($ind_txt); ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Section 9: Related Projects & Services -->
    <?php if (!empty($related_projects) || !empty($related_services)): ?>
        <section class="related-portfolio-section" style="margin-top: 40px; margin-bottom: 40px;">
            <?php if (!empty($related_projects)): ?>
                <h2 class="details-section-title" style="display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="briefcase" style="color: var(--accent-primary);"></i>
                    <span><?php echo $lang === 'ar' ? 'مشاريع منفذة ذات صلة' : 'Related Executed Projects'; ?></span>
                </h2>
                <div class="related-projects-grid-details">
                    <?php foreach ($related_projects as $pitem): 
                        $p_t = ($lang === 'ar') ? $pitem['title_ar'] : $pitem['title_en'];
                        $p_i = !empty($pitem['main_image']) ? $pitem['main_image'] : 'assets/images/shelf1.png';
                        $p_url = get_project_details_url($pitem['slug'], $lang);
                    ?>
                        <a href="<?php echo htmlspecialchars($p_url); ?>" style="text-decoration: none; color: inherit; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; display: block; box-shadow: var(--shadow-sm);">
                            <img src="<?php echo htmlspecialchars($p_i); ?>" alt="<?php echo htmlspecialchars($p_t); ?>">
                            <div style="padding: 12px;">
                                <h3 style="font-size: 14.5px; font-weight: 700; margin: 0; color: var(--text-primary);"><?php echo htmlspecialchars($p_t); ?></h3>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($related_services)): ?>
                <h2 class="details-section-title" style="display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="wrench" style="color: var(--accent-primary);"></i>
                    <span><?php echo $lang === 'ar' ? 'خدمات ذات صلة' : 'Related Storage Services'; ?></span>
                </h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px;">
                    <?php foreach ($related_services as $sitem): 
                        $s_t = ($lang === 'ar') ? $sitem['title_ar'] : $sitem['title_en'];
                    ?>
                        <a href="services" style="text-decoration: none; color: inherit; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-sm);">
                            <i data-lucide="arrow-left-circle" style="color: var(--accent-primary); width: 20px; flex-shrink: 0;"></i>
                            <span style="font-size: 14px; font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($s_t); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Section 10: Related Products -->
    <?php if (!empty($related_products)): ?>
        <section class="related-products-section" style="margin-top: 48px; margin-bottom: 48px;">
            <h2 class="details-section-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="grid" style="color: var(--accent-primary);"></i>
                <span><?php echo __('related_products'); ?></span>
            </h2>
            <div class="products-grid">
                <?php foreach ($related_products as $rprod): 
                    $rp_name = ($lang === 'ar') ? $rprod['name_ar'] : $rprod['name_en'];
                    $rp_desc = ($lang === 'ar') ? $rprod['description_ar'] : $rprod['description_en'];
                    $rp_alt  = ($lang === 'ar') ? (!empty($rprod['alt_text_ar']) ? $rprod['alt_text_ar'] : $rprod['name_ar']) : (!empty($rprod['alt_text_en']) ? $rprod['alt_text_en'] : $rprod['name_en']);
                    $rp_img  = !empty($rprod['image_url']) ? $rprod['image_url'] : 'assets/images/shelf1.png';
                    $rp_cat_label = isset($categories[$rprod['category']]) ? $categories[$rprod['category']] : $rprod['category'];

                    $whatsapp_number = get_setting('whatsapp_number', '966500000000');
                    $whatsapp_msg = rawurlencode(($lang === 'ar') 
                        ? "السلام عليكم، أود طلب عرض سعر وتفاصيل لمنتج: " . $rp_name 
                        : "Hello, I would like to request a quote and details for product: " . $rp_name);
                    $whatsapp_link = "https://wa.me/{$whatsapp_number}?text={$whatsapp_msg}";
                ?>
                    <div class="product-card">
                        <div class="product-card-media">
                            <a href="product-details?id=<?php echo $rprod['id']; ?>" class="product-card-img-link" aria-label="<?php echo htmlspecialchars($rp_name); ?>">
                                <img src="<?php echo htmlspecialchars($rp_img); ?>" 
                                     alt="<?php echo htmlspecialchars($rp_alt); ?>" 
                                     class="product-card-img"
                                     width="300"
                                     height="225"
                                     loading="lazy"
                                     decoding="async">
                            </a>
                            <?php 
                            $rprod_stock = isset($rprod['stock']) ? intval($rprod['stock']) : 0;
                            if ($rprod_stock <= 0): 
                            ?>
                                <div class="product-card-badges">
                                    <span class="product-badge status-out-of-stock"><?php echo htmlspecialchars(__('out_of_stock_badge')); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="product-card-content">
                            <span class="product-card-category"><?php echo htmlspecialchars($rp_cat_label); ?></span>
                            <h3 class="product-card-title">
                                <a href="product-details?id=<?php echo $rprod['id']; ?>"><?php echo htmlspecialchars($rp_name); ?></a>
                            </h3>
                            <p class="product-card-desc"><?php echo htmlspecialchars(mb_substr(strip_tags($rp_desc), 0, 90)) . '...'; ?></p>

                            <div class="product-card-actions">
                                <a href="product-details?id=<?php echo $rprod['id']; ?>" class="btn-product-details">
                                    <span><?php echo __('view_details_btn'); ?></span>
                                    <i data-lucide="<?php echo get_lang_direction() === 'rtl' ? 'arrow-left' : 'arrow-right'; ?>" class="btn-arrow-icon" aria-hidden="true"></i>
                                </a>
                                <a href="<?php echo $whatsapp_link; ?>" target="_blank" rel="noopener noreferrer" class="btn-product-whatsapp" aria-label="<?php echo htmlspecialchars(__('request_quote_wa_aria') . ' ' . $rp_name); ?>" title="<?php echo htmlspecialchars(__('request_quote_wa')); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                                       <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<!-- Accessible Lightbox Modal -->
<div id="productLightboxModal" class="lightbox-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php echo htmlspecialchars(__('gallery_open_lightbox')); ?>" style="display: none;">
    <button type="button" id="lightboxCloseBtn" class="lightbox-close-btn" aria-label="<?php echo htmlspecialchars(__('gallery_close_lightbox')); ?>" title="<?php echo htmlspecialchars(__('gallery_close_lightbox')); ?>">
        <i data-lucide="x" aria-hidden="true"></i>
    </button>
    <button type="button" id="lightboxPrevBtn" class="lightbox-nav-btn prev" aria-label="<?php echo htmlspecialchars(__('gallery_prev_img')); ?>">
        <i data-lucide="<?php echo get_lang_direction() === 'rtl' ? 'chevron-right' : 'chevron-left'; ?>" aria-hidden="true"></i>
    </button>
    <div class="lightbox-content">
        <img id="lightboxMainImg" src="" alt="" class="lightbox-img">
    </div>
    <button type="button" id="lightboxNextBtn" class="lightbox-nav-btn next" aria-label="<?php echo htmlspecialchars(__('gallery_next_img')); ?>">
        <i data-lucide="<?php echo get_lang_direction() === 'rtl' ? 'chevron-left' : 'chevron-right'; ?>" aria-hidden="true"></i>
    </button>
</div>

<!-- Gallery Data JSON Script Tag for JS Hydration -->
<script id="product-gallery-data" type="application/json">
<?php echo json_encode($gallery_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
</script>
<script src="assets/js/product-gallery.js"></script>

</main>
<?php
include_once __DIR__ . '/includes/footer.php';
?>
