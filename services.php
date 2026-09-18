<?php
// services.php
require_once __DIR__ . '/config/init.php';
$s_lang = get_current_lang();

$cms_services_title = get_setting($s_lang === 'ar' ? 'seo_services_title_ar' : 'seo_services_title_en');
$cms_services_desc = get_setting($s_lang === 'ar' ? 'seo_services_desc_ar' : 'seo_services_desc_en');

$default_services_title = ($s_lang === 'ar')
    ? 'خدمات الأرفف وحلول التخزين والتركيب | عالم الرفوف'
    : 'Storage Racking Installation & Maintenance Services | World of Shelves';
$default_services_desc = ($s_lang === 'ar')
    ? 'خدمات فك ونقل وتركيب وصيانة أرفف المستودعات وتخطيط المساحات في جميع أنحاء المملكة العربية السعودية.'
    : 'Professional dismantling, relocation, installation, and maintenance services for warehouse racking systems in Saudi Arabia.';

$site_wide_title = get_setting($s_lang === 'ar' ? 'seo_title_ar' : 'seo_title_en');
$site_wide_desc = get_setting($s_lang === 'ar' ? 'seo_desc_ar' : 'seo_desc_en');

$srv_title = !empty($cms_services_title) ? $cms_services_title : (!empty($default_services_title) ? $default_services_title : $site_wide_title);
$srv_desc = !empty($cms_services_desc) ? $cms_services_desc : (!empty($default_services_desc) ? $default_services_desc : $site_wide_desc);

$lang_suffix = ($s_lang === 'en') ? '?lang=en' : '';
$breadcrumbs = [
    __('breadcrumb_services') => 'services' . $lang_suffix
];

$seo = [
    'title_override' => $srv_title,
    'desc_raw' => $srv_desc,
    'type' => 'website',
    'service' => [
        'name' => ($s_lang === 'ar') ? 'خدمات تركيب وصيانة أرفف المستودعات' : 'Steel Shelving & Storage Racking Services',
        'description' => $srv_desc
    ]
];
include_once __DIR__ . '/includes/header.php';
?>
<main>
<?php
// Render visible breadcrumbs
include __DIR__ . '/includes/breadcrumbs.php';

// Official WhatsApp Link Configuration
$whatsapp_number = get_setting('whatsapp_number', '966500000000');
$lang = get_current_lang();

// Custom WhatsApp links for each service
$wa_inspect_msg = rawurlencode($lang === 'ar' 
    ? "السلام عليكم، أرغب في حجز موعد معاينة مجانية وتخطيط للمساحة لخدمات الأرفف." 
    : "Hello, I would like to book a free inspection and space planning appointment for racking.");
$wa_inspect_link = "https://wa.me/{$whatsapp_number}?text={$wa_inspect_msg}";

$wa_general_msg = rawurlencode($lang === 'ar' 
    ? "السلام عليكم، أريد الاستفسار عن خدمات الأرفف الحديدية وحلول التخزين." 
    : "Hello, I would like to inquire about iron racking services and storage solutions.");
$wa_general_link = "https://wa.me/{$whatsapp_number}?text={$wa_general_msg}";

// Service specific message templates
$service_msgs = [
    1 => ($lang === 'ar' ? "السلام عليكم، أريد الاستفسار عن بيع الأرفف الحديدية الجديدة." : "Hello, I would like to inquire about buying new iron shelves."),
    2 => ($lang === 'ar' ? "السلام عليكم، أريد طلب خدمة تركيب وتثبيت أرفف حديدية لمستودع/محل." : "Hello, I want to request installation and anchoring services for iron shelves."),
    3 => ($lang === 'ar' ? "السلام عليكم، أريد بيع أرفف حديدية مستعملة وأود الحصول على تقييم مبدئي." : "Hello, I want to sell used iron shelves and would like a preliminary valuation."),
    4 => ($lang === 'ar' ? "السلام عليكم، أود طلب خدمة فك ونقل أرفف حديدية لدينا." : "Hello, I would like to request dismantling and moving services for our iron shelves."),
    5 => ($lang === 'ar' ? "السلام عليكم، أود الحصول على حلول تخزين وتخطيط لمستودعنا/مخزننا." : "Hello, I would like storage planning and racking solutions for our warehouse."),
    6 => ($lang === 'ar' ? "السلام عليكم، أود طلب صيانة وتعديل أرفف حديدية لدينا." : "Hello, I want to request maintenance and modification of our iron shelves.")
];
?>

<div class="container services-page-container">
    <?php include_once __DIR__ . '/includes/trust-strip.php'; ?>
    <!-- Hero Section -->
    <div class="about-header-banner reveal-on-scroll">
        <h1><?php echo __('services_hero_title'); ?></h1>
        <p><?php echo __('services_hero_subtitle'); ?></p>
        <div class="about-header-divider"></div>
        <div style="margin-top: 30px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo $wa_inspect_link; ?>" target="_blank" class="btn btn-whatsapp-cta">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                  <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                </svg>
                <span><?php echo __('services_cta_inspect'); ?></span>
            </a>
            <a href="products" class="btn btn-primary-cta">
                <span><?php echo __('nav_products'); ?></span>
            </a>
        </div>
    </div>

    <!-- Section 1: Services Grid -->
    <div class="services-grid-page" style="margin-bottom: 80px;">
        <?php
        $active_services = get_active_services($pdo);
        if (count($active_services) > 0):
            foreach ($active_services as $srv):
                $title = htmlspecialchars($srv['title_' . $lang]);
                $desc = htmlspecialchars($srv['short_desc_' . $lang]);
                $b1 = htmlspecialchars($srv['benefit_1_' . $lang]);
                $b2 = htmlspecialchars($srv['benefit_2_' . $lang]);
                $b3 = htmlspecialchars($srv['benefit_3_' . $lang]);
                
                // Secondary Button Target Action Mapping
                $sec_url = '';
                $sec_label = '';
                if ($srv['custom_action'] !== 'none') {
                    $sec_url = map_action_to_url($srv['custom_action'], $whatsapp_number);
                    $sec_label = !empty($srv['custom_action_label_' . $lang]) 
                        ? htmlspecialchars($srv['custom_action_label_' . $lang]) 
                        : '';
                }

                // Primary WhatsApp quotation action link
                $wa_msg_text = ($lang === 'ar'
                    ? "السلام عليكم، أريد الاستفسار عن خدمة: " . $srv['title_ar']
                    : "Hello, I would like to inquire about: " . $srv['title_en']
                );
                $wa_msg_encoded = rawurlencode($wa_msg_text);
                $wa_card_link = "https://wa.me/{$whatsapp_number}?text={$wa_msg_encoded}";
                ?>
                <div class="service-card-expanded reveal-on-scroll">
                    <?php if (!empty($srv['image_path'])): ?>
                        <div class="service-card-image-wrapper">
                            <img src="<?php echo htmlspecialchars($srv['image_path']); ?>" alt="<?php echo $title; ?>" width="350" height="180" loading="lazy" decoding="async">
                        </div>
                        <h2><?php echo $title; ?></h2>
                    <?php else: ?>
                        <div class="service-card-header">
                            <div class="service-card-icon-wrapper"><i data-lucide="<?php echo htmlspecialchars($srv['icon']); ?>"></i></div>
                            <h2><?php echo $title; ?></h2>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($desc)): ?>
                        <p class="service-card-desc"><?php echo $desc; ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($b1) || !empty($b2) || !empty($b3)): ?>
                        <div class="service-card-benefits">
                            <h3><?php echo __('service_benefits'); ?></h3>
                            <ul>
                                <?php if (!empty($b1)): ?>
                                    <li><i data-lucide="check"></i> <?php echo $b1; ?></li>
                                <?php endif; ?>
                                <?php if (!empty($b2)): ?>
                                    <li><i data-lucide="check"></i> <?php echo $b2; ?></li>
                                <?php endif; ?>
                                <?php if (!empty($b3)): ?>
                                    <li><i data-lucide="check"></i> <?php echo $b3; ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($srv['custom_action'] !== 'none' && !empty($sec_url) && !empty($sec_label)): ?>
                        <a href="<?php echo htmlspecialchars($sec_url); ?>" class="btn btn-secondary btn-full" style="margin-bottom: 8px;">
                            <span><?php echo $sec_label; ?></span>
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo htmlspecialchars($wa_card_link); ?>" target="_blank" class="service-whatsapp-btn btn-full">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                          <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                        </svg>
                        <span><?php echo __('service_cta_order'); ?></span>
                    </a>
                </div>
            <?php
            endforeach;
        else:
            ?>
            <div class="no-services-container" style="text-align: center; padding: 80px 20px; color: var(--text-muted); background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color); width: 100%;">
                <i data-lucide="wrench" style="width: 48px; height: 48px; stroke-width: 1.5; margin-bottom: 16px; color: var(--text-muted); display: inline-block;"></i>
                <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px;">
                    <?php echo $lang === 'ar' ? 'لا تتوفر خدمات حالياً' : 'No Services Available'; ?>
                </h3>
                <p style="font-size: 15px; max-width: 500px; margin: 0 auto;">
                    <?php echo $lang === 'ar' ? 'نحن نعمل على تحديث خدماتنا. يرجى مراجعة هذه الصفحة لاحقاً أو التواصل معنا مباشرة عبر الواتساب.' : 'We are updating our services. Please check back later or contact us directly via WhatsApp.'; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 2: Why choose us? -->
    <div class="about-expertise-section reveal-on-scroll" style="margin-bottom: 80px;">
        <h2 style="font-size: 28px; font-weight: 800; text-align: center; margin-bottom: 40px; color: var(--text-primary);">
            <?php echo __('why_choose_services'); ?>
        </h2>
        <div class="why-services-grid">
            <?php 
            // Explicit setting keys for search indexing: services_why_1_image, services_why_2_image, services_why_3_image, services_why_4_image, services_why_5_image, services_why_6_image
            for ($i = 1; $i <= 6; $i++): 
                $card_title = htmlspecialchars(get_setting("services_why_{$i}_title_{$lang}"));
                $card_desc = htmlspecialchars(get_setting("services_why_{$i}_desc_{$lang}"));
                $card_icon = htmlspecialchars(get_setting("services_why_{$i}_icon"));
                $card_image = get_setting("services_why_{$i}_image");
            ?>
                <!-- Item <?php echo $i; ?> -->
                <div class="why-serv-card">
                    <?php if (!empty($card_image)): ?>
                        <div class="why-serv-image-wrapper" style="width: 100%; height: 160px; border-radius: var(--radius-md); overflow: hidden; margin-bottom: 16px;">
                            <img src="<?php echo htmlspecialchars($card_image); ?>" alt="<?php echo $card_title; ?>" width="350" height="160" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    <?php else: ?>
                        <div class="why-serv-icon">
                            <i data-lucide="<?php echo $card_icon; ?>"></i>
                        </div>
                    <?php endif; ?>
                    <h3><?php echo $card_title; ?></h3>
                    <p><?php echo $card_desc; ?></p>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Section 3: Process Steps -->
    <div class="reveal-on-scroll" style="margin: 80px 0;">
        <h2 style="font-size: 28px; font-weight: 800; text-align: center; margin-bottom: 40px; color: var(--text-primary);">
            <?php echo __('steps_order_title'); ?>
        </h2>
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

    <!-- Final Contextual CTA Section -->
    <?php
    $cta_page = 'services';
    include_once __DIR__ . '/includes/cta-block.php';
    ?>
</div>

</main>
<?php
include_once __DIR__ . '/includes/footer.php';
?>
