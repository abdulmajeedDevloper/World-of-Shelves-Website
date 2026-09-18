<?php
// includes/seo-helper.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

/**
 * Helper to generate page metadata, OpenGraph tags, and JSON-LD structured data.
 */

// Initialize variables with defaults if not set page-side
if (!isset($seo) || !is_array($seo)) {
    $seo = [];
}

$lang = get_current_lang();
$site_name = ($lang === 'ar') ? get_setting('site_name_ar', 'عالم الرفوف') : get_setting('site_name_en', 'World of Shelves');
if (!function_exists('is_valid_non_placeholder_phone')) {
    function is_valid_non_placeholder_phone($phone) {
        if (empty($phone)) return false;
        $clean = preg_replace('/\D/', '', $phone);
        if (preg_match('/^(\d)\1+$/', $clean)) return false;
        if (preg_match('/000000+$/', $clean)) return false;
        if (strpos($clean, '123456') !== false) return false;
        return true;
    }
}

if (!function_exists('is_valid_non_placeholder_address')) {
    function is_valid_non_placeholder_address($address) {
        if (empty($address)) return false;
        $lower = strtolower($address);
        if (strpos($lower, 'placeholder') !== false || strpos($lower, 'example') !== false) {
            return false;
        }
        return true;
    }
}

if (!function_exists('is_valid_non_placeholder_email')) {
    function is_valid_non_placeholder_email($email) {
        if (empty($email)) return false;
        $lower = strtolower($email);
        if (strpos($lower, 'example.com') !== false || strpos($lower, 'placeholder.com') !== false) {
            return false;
        }
        return true;
    }
}

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

if (!function_exists('sanitize_prod_url')) {
    function sanitize_prod_url($url, $prod_url) {
        if (empty($url)) return $url;
        return preg_replace('~^https?://(localhost|127\.0\.0\.1)(:\d+)?~i', $prod_url, $url);
    }
}

$site_url = get_site_url();
$prod_site_url = defined('CANONICAL_PROD_URL') ? CANONICAL_PROD_URL : 'https://worldofshelves.com';

// 1. Dynamic Title and Description Calculation
$title_part = isset($seo['title_key']) ? __($seo['title_key']) : '';
if (empty($title_part) && isset($seo['title_raw'])) {
    $title_part = $seo['title_raw'];
}
if (!empty($seo['title_override'])) {
    $page_title = $seo['title_override'];
} else {
    $page_title = !empty($title_part) ? ($title_part . ' - ' . $site_name) : ($site_name . ' - ' . __('tagline'));
}

$page_desc = isset($seo['desc_key']) ? __($seo['desc_key']) : '';
if (empty($page_desc) && isset($seo['desc_raw'])) {
    $page_desc = $seo['desc_raw'];
}
if (empty($page_desc)) {
    $page_desc = ($lang === 'ar') ? get_setting('tagline_ar', '') : get_setting('tagline_en', '');
}

// 2. Canonical URL Strategy
if (!empty($seo['canonical_override'])) {
    $canonical_url = sanitize_prod_url($seo['canonical_override'], $prod_site_url);
} else {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $current_page = basename($script_name);
    $current_name = pathinfo($current_page, PATHINFO_FILENAME);

    if ($current_name === 'index') {
        $canonical_base = $prod_site_url;
    } else {
        $canonical_base = $prod_site_url . '/' . $current_name;
    }

    $canonical_params = [];
    if (isset($seo['canonical_params']) && is_array($seo['canonical_params'])) {
        foreach ($seo['canonical_params'] as $param) {
            if ($param === 'search') continue; // Always omit search from canonicals
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                $canonical_params[$param] = $_GET[$param];
            }
        }
    }
    // Only preserve language parameter if it is non-default (en)
    if (isset($_GET['lang']) && $_GET['lang'] !== 'ar') {
        $canonical_params['lang'] = $_GET['lang'];
    }

    $canonical_url = $canonical_base;
    if (count($canonical_params) > 0) {
        $canonical_url .= '?' . http_build_query($canonical_params);
    }
}

// 2b. hreflang Alternate Language Links (with strict identity allowlist parameter mapping)
$allowed_params = [];
$hreflang_overridden = false;

if ($current_page === 'product-details.php' && !empty($seo['product']['slug'])) {
    $slug_val = trim($seo['product']['slug']);
    $ar_url = $prod_site_url . '/products/' . $slug_val;
    $en_url = $ar_url . '?lang=en';
    $x_def_url = $ar_url;
    $hreflang_overridden = true;
} elseif ($current_page === 'products.php' && !empty($_GET['category_slug'])) {
    $slug_val = trim($_GET['category_slug']);
    $ar_url = $prod_site_url . '/products/category/' . $slug_val;
    $en_url = $ar_url . '?lang=en';
    $x_def_url = $ar_url;
    $hreflang_overridden = true;
} elseif ($current_page === 'projects.php' && !empty($_GET['category_slug'])) {
    $slug_val = trim($_GET['category_slug']);
    $ar_url = $prod_site_url . '/projects/category/' . $slug_val;
    $en_url = $ar_url . '?lang=en';
    $x_def_url = $ar_url;
    $hreflang_overridden = true;
}

if (!$hreflang_overridden) {
    if ($current_page === 'product-details.php') {
        if (isset($_GET['id']) && $_GET['id'] !== '') {
            $allowed_params['id'] = $_GET['id'];
        }
    } elseif ($current_page === 'project-details.php') {
        if (isset($_GET['project']) && $_GET['project'] !== '') {
            $allowed_params['project'] = $_GET['project'];
        }
    }

    // Build alternate link URLs
    if ($current_name === 'index') {
        $hreflang_base = $prod_site_url;
    } else {
        $hreflang_base = $prod_site_url . '/' . $current_name;
    }

    // Arabic alternate (points to the default clean URL without lang=ar)
    $ar_url = $hreflang_base;
    if (count($allowed_params) > 0) {
        $ar_url .= '?' . http_build_query($allowed_params);
    }

    // English alternate
    $en_params = array_merge($allowed_params, ['lang' => 'en']);
    $en_url = $hreflang_base . '?' . http_build_query($en_params);

    // x-default (points to default URL without lang query, which defaults to ar language)
    $x_def_url = $ar_url;
}

// 3. OpenGraph Image Fallback
$og_image = isset($seo['image']) ? $seo['image'] : get_setting('default_og_image', 'assets/images/shelf1.png');
if (!empty($og_image)) {
    $og_image = sanitize_prod_url($og_image, $prod_site_url);
    if (!preg_match('~^(https?:)?//~i', $og_image)) {
        $og_image = $prod_site_url . '/' . ltrim($og_image, '/');
    }
} else {
    $og_image = '';
}

$og_type = isset($seo['type']) ? $seo['type'] : 'website';
$og_locale = ($lang === 'ar') ? 'ar_AR' : 'en_US';
$og_locale_alt = ($lang === 'ar') ? 'en_US' : 'ar_AR';

// Output Meta Elements
?>
<meta name="description" content="<?php echo htmlspecialchars($page_desc, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (isset($seo['noindex']) && $seo['noindex'] === true): ?>
    <?php if (!empty($_GET['search'])): ?>
        <meta name="robots" content="noindex, follow">
    <?php else: ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
<?php endif; ?>
<?php if (!isset($seo['canonical']) || $seo['canonical'] !== false): ?>
<link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>

<link rel="alternate" hreflang="ar" href="<?php echo htmlspecialchars($ar_url, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($en_url, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($x_def_url, ENT_QUOTES, 'UTF-8'); ?>">

<?php
// 3b. Google Search Console & Analytics Injection (Head tags)
$gsc_id = get_setting('seo_google_console');
if (!empty($gsc_id) && preg_match('/^[a-zA-Z0-9_-]+$/', $gsc_id)) {
    echo '<meta name="google-site-verification" content="' . htmlspecialchars($gsc_id, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

$gtm_id = get_setting('analytics_google_gtm');
$is_gtm_valid = !empty($gtm_id) && preg_match('/^GTM-[A-Z0-9]+$/', $gtm_id);

if ($is_gtm_valid) {
    ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo htmlspecialchars($gtm_id, ENT_QUOTES, 'UTF-8'); ?>');</script>
<!-- End Google Tag Manager -->
    <?php
}

$ga4_id = get_setting('analytics_google_ga4');
$is_ga4_valid = !empty($ga4_id) && preg_match('/^G-[A-Z0-9]+$/', $ga4_id);

if ($is_ga4_valid && !$is_gtm_valid) {
    ?>
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($ga4_id, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?php echo htmlspecialchars($ga4_id, ENT_QUOTES, 'UTF-8'); ?>');
</script>
    <?php
}

$pixel_id = get_setting('analytics_meta_pixel');
$is_pixel_valid = !empty($pixel_id) && preg_match('/^[0-9]+$/', $pixel_id);

if ($is_pixel_valid) {
    ?>
<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window,document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo htmlspecialchars($pixel_id, ENT_QUOTES, 'UTF-8'); ?>');
fbq('track', 'PageView');
</script>
<!-- End Facebook Pixel Code -->
    <?php
}
?>

<!-- OpenGraph Metadata -->
<meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($page_desc); ?>">
<meta property="og:type" content="<?php echo htmlspecialchars($og_type); ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($canonical_url); ?>">
<?php if (!empty($og_image)): ?>
<meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?php echo htmlspecialchars($site_name); ?>">
<meta property="og:locale" content="<?php echo htmlspecialchars($og_locale); ?>">
<meta property="og:locale:alternate" content="<?php echo htmlspecialchars($og_locale_alt); ?>">

<!-- Twitter Card Metadata -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($page_desc); ?>">
<?php if (!empty($og_image)): ?>
<meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
<?php endif; ?>

<?php
// 4. Schema.org JSON-LD structured data builder
$schemas = [];

// Logo and Social sameAs profiles resolve dynamically
$resolved_logo = '';
$site_logo_path = get_setting('site_logo');
if (!empty($site_logo_path)) {
    $site_logo_path = sanitize_prod_url($site_logo_path, $prod_site_url);
    if (!preg_match('~^(https?:)?//~i', $site_logo_path)) {
        $resolved_logo = $prod_site_url . '/' . ltrim($site_logo_path, '/');
    } else {
        $resolved_logo = $site_logo_path;
    }
}

$social_keys = [
    'social_facebook',
    'social_instagram',
    'social_twitter',
    'social_linkedin',
    'social_youtube',
    'social_tiktok',
    'social_snapchat'
];
$same_as = [];
foreach ($social_keys as $skey) {
    $sval = get_setting($skey);
    if (!empty($sval) && is_valid_non_placeholder_social($sval)) {
        $same_as[] = sanitize_prod_url($sval, $prod_site_url);
    }
}

// Authoritative Organization/LocalBusiness Schema
$contact_phone = get_setting('contact_phone');
if (!is_valid_non_placeholder_phone($contact_phone)) {
    $contact_phone = '';
}
$contact_address = get_setting('contact_address_' . $lang);
if (!is_valid_non_placeholder_address($contact_address)) {
    $contact_address = '';
}
$gps_coordinates = get_setting('gps_coordinates');
$contact_email = get_setting('contact_email');
if (!is_valid_non_placeholder_email($contact_email)) {
    $contact_email = '';
}

$biz_type = (!empty($contact_phone) && !empty($contact_address)) ? 'LocalBusiness' : 'Organization';
$biz_schema = [
    '@context' => 'https://schema.org',
    '@type' => $biz_type,
    '@id' => $prod_site_url . '/#organization',
    'name' => $site_name,
    'url' => $prod_site_url
];

if (!empty($resolved_logo)) {
    $biz_schema['logo'] = $resolved_logo;
}
if (!empty($contact_phone)) {
    $biz_schema['telephone'] = $contact_phone;
}
if (!empty($contact_email)) {
    $biz_schema['email'] = $contact_email;
}
if (!empty($contact_address)) {
    $biz_schema['address'] = [
        '@type' => 'PostalAddress',
        'streetAddress' => $contact_address,
        'addressLocality' => ($lang === 'ar') ? 'الرياض' : 'Riyadh',
        'addressCountry' => 'SA'
    ];
}
if (!empty($gps_coordinates)) {
    $coords = explode(',', $gps_coordinates);
    if (count($coords) >= 2) {
        $biz_schema['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => floatval(trim($coords[0])),
            'longitude' => floatval(trim($coords[1]))
        ];
    }
}
if (count($same_as) > 0) {
    $biz_schema['sameAs'] = $same_as;
}
$schemas[] = $biz_schema;

// WebSite (only on homepage index.php)
if ($current_page === 'index.php') {
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $site_name,
        'url' => $prod_site_url,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => $prod_site_url . '/products?search={search_term_string}',
            'query-input' => 'required name=search_term_string'
        ]
    ];
}

// WebPage
if ($current_page === 'index.php') {
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $canonical_url . '#webpage',
        'url' => $canonical_url,
        'name' => $page_title,
        'description' => $page_desc,
        'about' => [
            '@id' => $prod_site_url . '/#organization'
        ]
    ];
} elseif ($current_page === 'product-details.php' && isset($seo['product'])) {
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $canonical_url . '#webpage',
        'url' => $canonical_url,
        'name' => $page_title,
        'description' => $page_desc,
        'mainEntity' => [
            '@id' => $canonical_url . '#product'
        ]
    ];
} elseif ($current_page === 'project-details.php' && isset($seo['creative_work'])) {
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $canonical_url . '#webpage',
        'url' => $canonical_url,
        'name' => $page_title,
        'description' => $page_desc,
        'mainEntity' => [
            '@id' => $canonical_url . '#project'
        ]
    ];
}

// CreativeWork (for portfolio projects details page)
if (isset($seo['creative_work']) && is_array($seo['creative_work'])) {
    $cw = $seo['creative_work'];
    $cw_schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CreativeWork',
        '@id' => $canonical_url . '#project',
        'name' => $cw['name'],
        'description' => $cw['description'],
        'image' => $cw['image'],
        'url' => $cw['url'],
        'provider' => [
            '@type' => $biz_type,
            '@id' => $prod_site_url . '/#organization'
        ]
    ];
    if (isset($cw['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cw['date'])) {
        $cw_schema['datePublished'] = $cw['date'];
    }
    $schemas[] = $cw_schema;
}

// Product Schema
if (isset($seo['product']) && is_array($seo['product'])) {
    $prod = $seo['product'];
    $prod_name = ($lang === 'ar') ? $prod['name_ar'] : $prod['name_en'];
    $prod_desc = ($lang === 'ar') ? $prod['description_ar'] : $prod['description_en'];
    $prod_material = ($lang === 'ar') ? (isset($prod['materials_ar']) ? $prod['materials_ar'] : '') : (isset($prod['materials_en']) ? $prod['materials_en'] : '');
    
    $prod_image = !empty($seo['image']) ? sanitize_prod_url($seo['image'], $prod_site_url) : ($prod_site_url . '/' . ltrim($prod['image_url'], '/'));

    $product_schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        '@id' => $canonical_url . '#product',
        'name' => $prod_name,
        'description' => $prod_desc,
        'image' => $prod_image,
        'category' => isset($prod['resolved_category_name']) ? $prod['resolved_category_name'] : $prod['category'],
        'url' => $canonical_url
    ];
    
    if (!empty($prod['sku'])) {
        $product_schema['sku'] = $prod['sku'];
    }

    if (!empty($prod['brand'])) {
        $product_schema['brand'] = [
            '@type' => 'Brand',
            'name' => $prod['brand']
        ];
    }

    if (!empty($prod['mpn'])) {
        $product_schema['mpn'] = $prod['mpn'];
    }

    if (!empty($prod['gtin'])) {
        $gtin_val = trim($prod['gtin']);
        if (preg_match('/^\d+$/', $gtin_val) && in_array(strlen($gtin_val), [8, 12, 13, 14], true)) {
            $product_schema['gtin'] = $gtin_val;
        }
    }

    if (!empty($prod_material)) {
        $product_schema['material'] = $prod_material;
    }
    
    $schemas[] = $product_schema;
}

// Service Schema
if (isset($seo['service']) && is_array($seo['service'])) {
    $srv = $seo['service'];
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'serviceType' => $srv['name'],
        'provider' => [
            '@type' => $biz_type,
            '@id' => $prod_site_url . '/#organization'
        ],
        'description' => $srv['description'],
        'areaServed' => 'SA'
    ];
}

// FAQPage Schema
if (isset($seo['faq']) && is_array($seo['faq'])) {
    $faq_entities = [];
    foreach ($seo['faq'] as $item) {
        $faq_entities[] = [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['answer']
            ]
        ];
    }
    if (count($faq_entities) > 0) {
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faq_entities
        ];
    }
}

// Breadcrumb Schema
if (isset($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 0) {
    $list_items = [];
    
    // Add Home first
    $list_items[] = [
        '@type' => 'ListItem',
        'position' => 1,
        'name' => __('breadcrumb_home'),
        'item' => $prod_site_url . '/'
    ];
    
    $pos = 2;
    foreach ($breadcrumbs as $crumb_name => $crumb_url) {
        $abs_url = $crumb_url;
        if (!empty($abs_url)) {
            if (!preg_match('~^(https?:)?//~i', $abs_url)) {
                $abs_url = $prod_site_url . '/' . ltrim($abs_url, '/');
            }
        } else {
            $abs_url = $canonical_url;
        }
        
        $list_items[] = [
            '@type' => 'ListItem',
            'position' => $pos,
            'name' => $crumb_name,
            'item' => $abs_url
        ];
        $pos++;
    }
    
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list_items
    ];
}

// CollectionPage and ItemList Schema (only on dynamic products list page)
if (isset($seo['collection_products']) && is_array($seo['collection_products'])) {
    $items = [];
    $pos = 1;
    foreach ($seo['collection_products'] as $p) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $pos,
            'url' => $p['url'],
            'name' => $p['name'],
            'image' => $p['image']
        ];
        $pos++;
    }
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $page_title,
        'description' => $page_desc,
        'url' => $canonical_url,
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => count($items),
            'itemListElement' => $items
        ]
    ];
}

// CollectionPage and ItemList Schema for Projects (only on dynamic projects list page)
if (isset($seo['collection_projects']) && is_array($seo['collection_projects'])) {
    $items = [];
    $pos = 1;
    foreach ($seo['collection_projects'] as $p) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $pos,
            'url' => $p['url'],
            'name' => $p['name'],
            'image' => $p['image']
        ];
        $pos++;
    }
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $page_title,
        'description' => $page_desc,
        'url' => $canonical_url,
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => count($items),
            'itemListElement' => $items
        ]
    ];
}

// Output Schemas in JSON-LD format
foreach ($schemas as $s) {
    echo "\n<script type=\"application/ld+json\">\n" . json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n</script>\n";
}

