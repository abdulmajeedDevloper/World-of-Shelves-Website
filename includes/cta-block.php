<?php
// includes/cta-block.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/lang_helper.php';

$whatsapp_number = get_setting('whatsapp_number', '966500000000');
$lang = get_current_lang();

// Default page if not specified
if (!isset($cta_page)) {
    $cta_page = 'home';
}

// Contextual details mapping (fallbacks)
$cta_config = [
    'home' => [
        'title_key' => 'cta_home_title',
        'desc_key' => 'cta_home_desc',
        'wa_msg' => ($lang === 'ar') 
            ? "السلام عليكم، أود الحصول على معاينة مجانية وتصميم كروكي لأرفف مستودعنا/محلنا." 
            : "Hello, I would like to book a free site inspection and space planning layout for our racking."
    ],
    'services' => [
        'title_key' => 'cta_services_title',
        'desc_key' => 'cta_services_desc',
        'wa_msg' => ($lang === 'ar') 
            ? "السلام عليكم، أود حجز موعد لخدمات فك ونقل وصيانة الأرفف الحديدية." 
            : "Hello, I want to book a dismantling, relocation, or maintenance service for our steel shelves."
    ],
    'installation' => [
        'title_key' => 'cta_install_title',
        'desc_key' => 'cta_install_desc',
        'wa_msg' => ($lang === 'ar') 
            ? "السلام عليكم، أود طلب خدمة تركيب وتثبيت أرفف حديدية لمستودعنا." 
            : "Hello, I want to request professional installation and anchoring services for our warehouse shelves."
    ],
    'used' => [
        'title_key' => 'cta_used_title',
        'desc_key' => 'cta_used_desc',
        'wa_msg' => ($lang === 'ar') 
            ? "السلام عليكم، أرغب في بيع أرفف مستعملة لدينا وأود الحصول على تسعير مبدئي." 
            : "Hello, I want to sell used steel shelves and would like a preliminary offer."
    ],
    'about' => [
        'title_key' => 'cta_about_title',
        'desc_key' => 'cta_about_desc',
        'wa_msg' => ($lang === 'ar') 
            ? "السلام عليكم، أود الحصول على استشارة وعرض أسعار لحلول الأرفف الحديدية." 
            : "Hello, I would like a consultation and quotation offer for steel shelving solutions."
    ]
];

$current_cta = isset($cta_config[$cta_page]) ? $cta_config[$cta_page] : $cta_config['home'];

$cta_title = isset($cta_title) && !empty($cta_title) ? $cta_title : '';
$cta_desc = isset($cta_desc) && !empty($cta_desc) ? $cta_desc : '';
$cta_bg_image = isset($cta_bg_image) && !empty($cta_bg_image) ? $cta_bg_image : '';
$cta_wa_msg = isset($cta_wa_msg) && !empty($cta_wa_msg) ? $cta_wa_msg : '';

if (empty($cta_title)) {
    if ($cta_page === 'home') {
        $cta_title = get_setting('cta_home_title_' . $lang, '');
    } elseif ($cta_page === 'about') {
        $cta_title = get_setting('about_cta_title_' . $lang, '');
    }
}

if (empty($cta_desc)) {
    if ($cta_page === 'home') {
        $cta_desc = get_setting('cta_home_desc_' . $lang, '');
    } elseif ($cta_page === 'about') {
        $cta_desc = get_setting('about_cta_desc_' . $lang, '');
    }
}

if (empty($cta_bg_image)) {
    if ($cta_page === 'home') {
        $cta_bg_image = get_setting('cta_home_bg_image', '');
    } elseif ($cta_page === 'about') {
        $cta_bg_image = get_setting('about_cta_bg_image', '');
    }
}

if (empty($cta_title)) {
    $cta_title = __($current_cta['title_key']);
}
if (empty($cta_desc)) {
    $cta_desc = __($current_cta['desc_key']);
}

$final_wa_msg = !empty($cta_wa_msg) ? $cta_wa_msg : $current_cta['wa_msg'];
$wa_link = "https://wa.me/{$whatsapp_number}?text=" . rawurlencode($final_wa_msg);
?>

<div class="premium-cta-block reveal-on-scroll" <?php if (!empty($cta_bg_image)): ?> style="background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?php echo htmlspecialchars($cta_bg_image); ?>'); background-size: cover; background-position: center; color: #ffffff;"<?php endif; ?>>
    <div class="container premium-cta-inner">
        <h2 <?php if (!empty($cta_bg_image)): ?> style="color: #ffffff;"<?php endif; ?>><?php echo htmlspecialchars($cta_title); ?></h2>
        <p <?php if (!empty($cta_bg_image)): ?> style="color: rgba(255,255,255,0.9);"<?php endif; ?>><?php echo htmlspecialchars($cta_desc); ?></p>
        
        <div class="premium-cta-buttons">
            <?php 
            $cta_action = 'whatsapp';
            if ($cta_page === 'about') {
                $cta_action = get_setting('about_cta_action', 'whatsapp');
            }

            if ($cta_action !== 'none'): 
                if ($cta_action === 'whatsapp'):
            ?>
                <a href="<?php echo $wa_link; ?>" target="_blank" class="btn-whatsapp-quote">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                      <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                    </svg>
                    <span><?php echo __('request_quote_label'); ?></span>
                </a>
                <?php 
                else: 
                    $btn_href = get_site_url() . '/';
                    $btn_label = '';
                    if ($cta_action === 'products') {
                        $btn_href = 'products';
                        $btn_label = __('nav_products');
                    } elseif ($cta_action === 'services') {
                        $btn_href = 'services';
                        $btn_label = __('breadcrumb_services');
                    } elseif ($cta_action === 'projects') {
                        $btn_href = 'projects';
                        $btn_label = __('nav_portfolio');
                    } elseif ($cta_action === 'contact') {
                        $btn_href = 'contact';
                        $btn_label = __('nav_contact');
                    }
                ?>
                    <a href="<?php echo htmlspecialchars($btn_href); ?>" class="btn btn-primary">
                        <span><?php echo htmlspecialchars($btn_label); ?></span>
                    </a>
                <?php 
                endif;
            endif; 
            ?>
            
            <?php 
            // Render secondary button if not none and not products page itself (to avoid duplicates)
            if ($cta_action !== 'products'):
            ?>
                <a href="products" class="btn btn-secondary-cta">
                    <span><?php echo __('nav_products'); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

