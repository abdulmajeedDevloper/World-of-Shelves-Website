<?php
// includes/homepage/homepage-products.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

$lang = get_current_lang();
$whatsapp_number = get_setting('whatsapp_number', '966500000000');

$limit_val = intval(get_setting('homepage_products_limit', '3'));
if ($limit_val < 1 || $limit_val > 12) {
    $limit_val = 3;
}

$source_mode = get_setting('homepage_products_source', 'featured');

// Map sources to SQL where clauses
$sql_clauses = [
    'featured'  => 'is_active = 1 AND is_featured = 1 ORDER BY sort_order ASC, id DESC',
    'new'       => 'is_active = 1 AND is_new = 1 ORDER BY sort_order ASC, id DESC',
    'requested' => 'is_active = 1 AND is_most_requested = 1 ORDER BY sort_order ASC, id DESC',
    'latest'    => 'is_active = 1 ORDER BY id DESC'
];

$order_sql = isset($sql_clauses[$source_mode]) ? $sql_clauses[$source_mode] : $sql_clauses['featured'];

$prods = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE $order_sql LIMIT :limit");
    $stmt->bindValue(':limit', $limit_val, PDO::PARAM_INT);
    $stmt->execute();
    $prods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Homepage Products Query failed: " . $e->getMessage());
}

$sec_title = get_setting('homepage_products_title_' . $lang, __('featured_products'));

// Build in-memory category lookup map from the categories already loaded by index.php
$categories_map = [];
if (isset($categories) && is_array($categories)) {
    foreach ($categories as $cat) {
        $categories_map[$cat['code']] = ($lang === 'ar') ? $cat['name_ar'] : $cat['name_en'];
    }
} else {
    // If rendered independently and the lookup is unavailable, perform one categories query before entering the loop
    try {
        $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
        $categories_list = $cat_stmt->fetchAll();
        foreach ($categories_list as $cat) {
            $categories_map[$cat['code']] = ($lang === 'ar') ? $cat['name_ar'] : $cat['name_en'];
        }
    } catch (PDOException $e) {
        // Fallback remains empty
    }
}
?>
<?php if (count($prods) > 0): ?>
<section class="products-section reveal-on-scroll">
    <div class="container">
        <div class="section-header">
            <h2><?php echo htmlspecialchars($sec_title); ?></h2>
            <div class="section-divider"></div>
        </div>
        
        <div class="products-grid">
            <?php foreach ($prods as $prod): ?>
                <?php 
                $name = ($lang === 'ar') ? $prod['name_ar'] : $prod['name_en'];
                $desc = ($lang === 'ar') ? $prod['description_ar'] : $prod['description_en'];
                
                // Get Category Display Label from in-memory map lookup (No N+1 queries)
                $category_label = isset($categories_map[$prod['category']]) ? $categories_map[$prod['category']] : $prod['category'];

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
                if ($prod['category'] === 'decorative') {
                    $badges[] = ['key' => 'status_used', 'class' => 'status-used'];
                }
                ?>
                <div class="product-card">
                    <div class="product-image-container" style="position: relative;">
                        <img src="<?php echo htmlspecialchars($prod['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($name); ?>" 
                             class="product-card-img"
                             width="300"
                             height="220"
                             loading="lazy"
                             decoding="async">
                        
                        <div style="position: absolute; top: 12px; <?php echo get_lang_direction() === 'rtl' ? 'right: 12px;' : 'left: 12px;'; ?>; display: flex; flex-direction: column; gap: 6px;">
                            <?php foreach ($badges as $badge): ?>
                                <span class="product-badge <?php echo $badge['class']; ?>" style="position: static; margin: 0;"><?php echo htmlspecialchars(__($badge['key'])); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="product-info">
                        <span class="product-cat"><?php echo htmlspecialchars($category_label); ?></span>
                        <h3 class="product-title">
                            <a href="<?php echo get_product_details_url($prod, $lang); ?>"><?php echo htmlspecialchars($name); ?></a>
                        </h3>
                        <p class="product-description-snippet"><?php echo htmlspecialchars($desc); ?></p>
                        
                        <?php
                        $whatsapp_msg = rawurlencode(($lang === 'ar') 
                            ? "السلام عليكم، أود طلب عرض سعر وتفاصيل لمنتج: " . $name 
                            : "Hello, I would like to request a quote and details for product: " . $name);
                        $whatsapp_link = "https://wa.me/{$whatsapp_number}?text={$whatsapp_msg}";
                        ?>
                        <div class="product-price-row">
                            <a href="<?php echo get_product_details_url($prod, $lang); ?>" class="btn btn-secondary btn-sm flex-details-btn">
                                <span><?php echo $lang === 'ar' ? 'عرض التفاصيل' : 'View Details'; ?></span>
                            </a>
                            <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="btn-whatsapp-quote flex-whatsapp-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                                  <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                                </svg>
                                <span><?php echo __('hero_cta_quote'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
