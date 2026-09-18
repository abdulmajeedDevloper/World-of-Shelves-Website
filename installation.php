<?php
// installation.php
require_once __DIR__ . '/config/init.php';

$lang = get_current_lang();

// 1. Resolve OpenGraph Image Fallback Chain using shared validation helper
$seo_og_image = get_setting('seo_install_og_image');
$default_og_image = get_setting('default_og_image');
$site_logo = get_setting('site_logo');

$final_og = '';
if (is_valid_public_image_path($seo_og_image)) {
    $final_og = $seo_og_image;
} elseif (is_valid_public_image_path($default_og_image)) {
    $final_og = $default_og_image;
} elseif (is_valid_public_image_path($site_logo)) {
    $final_og = $site_logo;
}

// 2. Prepare SEO Title and Description Overrides
$seo_title_override = get_setting('seo_install_title_' . $lang);
$seo_desc_override = get_setting('seo_install_desc_' . $lang);

$install_title = !empty($seo_title_override)
    ? $seo_title_override
    : (($lang === 'ar') ? 'خدمات تركيب وفك أرفف المستودعات | عالم الرفوف' : 'Warehouse Racking Installation & Assembly Services | World of Shelves');

$install_desc = !empty($seo_desc_override)
    ? $seo_desc_override
    : get_setting('install_hero_subtitle_' . $lang, __('install_hero_subtitle'));

$seo = [
    'title_override' => $install_title,
    'desc_raw' => $install_desc,
    'type' => 'website',
    'image' => $final_og,
    'service' => [
        'name' => $install_title,
        'description' => $install_desc
    ]
];

include_once __DIR__ . '/includes/header.php';
?>
<main>
<?php
// Official WhatsApp Link Configuration
$whatsapp_number = get_setting('whatsapp_number', '');
$wa_install_msg = '';
$wa_install_link = '';

if (!empty($whatsapp_number)) {
    $wa_install_msg = rawurlencode(get_setting('install_hero_wa_msg_' . $lang, __('install_hero_wa_msg')));
    $wa_install_link = "https://wa.me/{$whatsapp_number}?text={$wa_install_msg}";
}

// Layout sections array definition
$sections = [
    'hero' => [
        'key' => 'hero',
        'order' => (int)get_setting('install_order_hero', 10),
        'default_order' => 10,
        'show' => (bool)get_setting('install_show_hero', true),
        'render' => function() use ($lang, $whatsapp_number, $wa_install_link) {
            ?>
            <!-- Hero Section -->
            <div class="about-header-banner reveal-on-scroll">
                <h1><?php echo htmlspecialchars(get_setting('install_hero_title_' . $lang, __('install_hero_title'))); ?></h1>
                <p><?php echo nl2br(htmlspecialchars(get_setting('install_hero_subtitle_' . $lang, __('install_hero_subtitle')))); ?></p>
                <div class="about-header-divider"></div>
                <div style="margin-top: 30px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <?php if (!empty($wa_install_link)): ?>
                    <a href="<?php echo $wa_install_link; ?>" target="_blank" class="btn btn-whatsapp-cta">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                          <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                        </svg>
                        <span><?php echo htmlspecialchars(get_setting('install_hero_cta_label_' . $lang, __('install_hero_cta_label'))); ?></span>
                    </a>
                    <?php endif; ?>
                    <a href="services" class="btn btn-secondary-cta">
                        <span><?php echo htmlspecialchars(get_setting('install_hero_secondary_label_' . $lang, __('services_title'))); ?></span>
                    </a>
                </div>
            </div>
            <?php
        }
    ],
    'where' => [
        'key' => 'where',
        'order' => (int)get_setting('install_order_where', 20),
        'default_order' => 20,
        'show' => (bool)get_setting('install_show_where', true),
        'render' => function() use ($lang) {
            ?>
            <!-- Section 1: Where We Install -->
            <div class="reveal-on-scroll" style="margin-bottom: 80px;">
                <div style="text-align: center; margin-bottom: 40px;">
                    <h2 style="font-size: 28px; font-weight: 800; color: var(--text-primary);"><?php echo htmlspecialchars(get_setting('install_where_title_' . $lang, __('install_where_title'))); ?></h2>
                    <p style="font-size: 15px; color: var(--text-secondary); max-width: 600px; margin: 10px auto 0;"><?php echo nl2br(htmlspecialchars(get_setting('install_where_desc_' . $lang, __('install_where_desc')))); ?></p>
                </div>
                <div class="why-services-grid">
                    <div class="why-serv-card">
                        <div class="why-serv-icon"><i data-lucide="warehouse"></i></div>
                        <h3><?php echo htmlspecialchars(get_setting('install_loc_1_title_' . $lang, __('install_loc_1_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_loc_1_desc_' . $lang, __('install_loc_1_desc')))); ?></p>
                    </div>
                    <div class="why-serv-card">
                        <div class="why-serv-icon"><i data-lucide="snowflake"></i></div>
                        <h3><?php echo htmlspecialchars(get_setting('install_loc_2_title_' . $lang, __('install_loc_2_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_loc_2_desc_' . $lang, __('install_loc_2_desc')))); ?></p>
                    </div>
                    <div class="why-serv-card">
                        <div class="why-serv-icon"><i data-lucide="shopping-bag"></i></div>
                        <h3><?php echo htmlspecialchars(get_setting('install_loc_3_title_' . $lang, __('install_loc_3_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_loc_3_desc_' . $lang, __('install_loc_3_desc')))); ?></p>
                    </div>
                    <div class="why-serv-card">
                        <div class="why-serv-icon"><i data-lucide="home"></i></div>
                        <h3><?php echo htmlspecialchars(get_setting('install_loc_4_title_' . $lang, __('install_loc_4_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_loc_4_desc_' . $lang, __('install_loc_4_desc')))); ?></p>
                    </div>
                </div>
            </div>
            <?php
        }
    ],
    'process' => [
        'key' => 'process',
        'order' => (int)get_setting('install_order_process', 30),
        'default_order' => 30,
        'show' => (bool)get_setting('install_show_process', true),
        'render' => function() use ($lang) {
            ?>
            <!-- Section 2: Steps of Installation -->
            <div class="about-expertise-section reveal-on-scroll" style="margin-bottom: 80px;">
                <div style="text-align: center; margin-bottom: 40px;">
                    <h2 style="font-size: 28px; font-weight: 800; color: var(--text-primary);"><?php echo htmlspecialchars(get_setting('install_phases_title_' . $lang, __('install_phases_title'))); ?></h2>
                    <p style="font-size: 15px; color: var(--text-secondary); max-width: 600px; margin: 10px auto 0;"><?php echo nl2br(htmlspecialchars(get_setting('install_phases_desc_' . $lang, __('install_phases_desc')))); ?></p>
                </div>
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-icon-wrapper">
                            <div class="step-number">1</div>
                            <i data-lucide="pencil-ruler"></i>
                        </div>
                        <h3><?php echo htmlspecialchars(get_setting('install_step_1_title_' . $lang, __('install_step_1_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_step_1_desc_' . $lang, __('install_step_1_desc')))); ?></p>
                    </div>
                    <div class="step-card">
                        <div class="step-icon-wrapper">
                            <div class="step-number">2</div>
                            <i data-lucide="package-open"></i>
                        </div>
                        <h3><?php echo htmlspecialchars(get_setting('install_step_2_title_' . $lang, __('install_step_2_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_step_2_desc_' . $lang, __('install_step_2_desc')))); ?></p>
                    </div>
                    <div class="step-card">
                        <div class="step-icon-wrapper">
                            <div class="step-number">3</div>
                            <i data-lucide="wrench"></i>
                        </div>
                        <h3><?php echo htmlspecialchars(get_setting('install_step_3_title_' . $lang, __('install_step_3_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_step_3_desc_' . $lang, __('install_step_3_desc')))); ?></p>
                    </div>
                    <div class="step-card">
                        <div class="step-icon-wrapper">
                            <div class="step-number">4</div>
                            <i data-lucide="activity"></i>
                        </div>
                        <h3><?php echo htmlspecialchars(get_setting('install_step_4_title_' . $lang, __('install_step_4_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_step_4_desc_' . $lang, __('install_step_4_desc')))); ?></p>
                    </div>
                </div>
            </div>
            <?php
        }
    ],
    'why' => [
        'key' => 'why',
        'order' => (int)get_setting('install_order_why', 40),
        'default_order' => 40,
        'show' => (bool)get_setting('install_show_why', true),
        'render' => function() use ($lang) {
            ?>
            <!-- Section 3: Why Professional Installation -->
            <div class="reveal-on-scroll" style="margin-bottom: 80px;">
                <h2 style="font-size: 28px; font-weight: 800; text-align: center; margin-bottom: 40px; color: var(--text-primary);">
                    <?php echo htmlspecialchars(get_setting('install_why_title_' . $lang, __('install_why_title'))); ?>
                </h2>
                <div class="why-shelf-grid">
                    <div class="why-shelf-card">
                        <div class="why-shelf-icon"><i data-lucide="shield-alert"></i></div>
                        <h3><?php echo htmlspecialchars(get_setting('install_why_1_title_' . $lang, __('install_why_1_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_why_1_desc_' . $lang, __('install_why_1_desc')))); ?></p>
                    </div>
                    <div class="why-shelf-card">
                        <div class="why-shelf-icon"><i data-lucide="scale"></i></div>
                        <h3><?php echo htmlspecialchars(get_setting('install_why_2_title_' . $lang, __('install_why_2_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_why_2_desc_' . $lang, __('install_why_2_desc')))); ?></p>
                    </div>
                    <div class="why-shelf-card">
                        <div class="why-shelf-icon"><i data-lucide="layout"></i></div>
                        <h3><?php echo htmlspecialchars(get_setting('install_why_3_title_' . $lang, __('install_why_3_title'))); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('install_why_3_desc_' . $lang, __('install_why_3_desc')))); ?></p>
                    </div>
                </div>
            </div>
            <?php
        }
    ],
    'cta' => [
        'key' => 'cta',
        'order' => (int)get_setting('install_order_cta', 50),
        'default_order' => 50,
        'show' => (bool)get_setting('install_show_cta', true),
        'render' => function() use ($lang) {
            ?>
            <!-- Final Contextual CTA Section -->
            <?php
            $cta_page = 'installation';
            $cta_title = get_setting('cta_install_title_' . $lang, __('cta_install_title'));
            $cta_desc = get_setting('cta_install_desc_' . $lang, __('cta_install_desc'));
            $cta_wa_msg = get_setting('cta_install_whatsapp_msg_' . $lang);
            include __DIR__ . '/includes/cta-block.php';
            ?>
            <?php
        }
    ]
];

// Sort sections dynamically using 3-tier tie-breaking algorithm: order -> default_order -> section_key
uasort($sections, function($a, $b) {
    if ($a['order'] !== $b['order']) {
        return $a['order'] <=> $b['order'];
    }
    if ($a['default_order'] !== $b['default_order']) {
        return $a['default_order'] <=> $b['default_order'];
    }
    return strcmp($a['key'], $b['key']);
});
?>

<div class="container installation-page-container">
    <?php include_once __DIR__ . '/includes/trust-strip.php'; ?>
    
    <?php
    // Render sorted sections
    foreach ($sections as $section) {
        if ($section['show'] && is_callable($section['render'])) {
            $section['render']();
        }
    }
    ?>
</div>

</main>
<?php
include_once __DIR__ . '/includes/footer.php';
?>
