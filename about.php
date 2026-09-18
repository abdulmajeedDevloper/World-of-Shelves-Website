<?php
// about.php
require_once __DIR__ . '/config/init.php';

$lang = get_current_lang();

// 1. Resolve custom SEO settings with improved fallback chain
$seo_title = get_setting('seo_about_title_' . $lang);
if (empty($seo_title)) {
    $seo_title = get_setting('about_us_title_' . $lang);
}
if (empty($seo_title)) {
    $seo_title = ($lang === 'ar') ? 'من نحن | عالم الرفوف' : 'About Us | World of Shelves';
}

$seo_desc = get_setting('seo_about_desc_' . $lang);
if (empty($seo_desc)) {
    $seo_desc = get_setting('about_story_desc_' . $lang);
}
if (empty($seo_desc)) {
    $seo_desc = ($lang === 'ar') 
        ? 'تعرف على شركة عالم الرفوف ورؤيتنا وخبرتنا المتميزة في تصميم وتركيب أرفف التخزين والمستودعات بالرياض والسعودية.' 
        : 'Learn about World of Shelves, our mission, vision, and expertise in warehouse racking and shelving systems in Saudi Arabia.';
}

$seo_og_image = get_setting('seo_about_og_image');

$seo = [
    'title_override' => $seo_title,
    'desc_raw' => $seo_desc,
    'type' => 'website'
];
if (!empty($seo_og_image)) {
    $seo['image'] = $seo_og_image;
}

include_once __DIR__ . '/includes/header.php';

$whatsapp_number = get_setting('whatsapp_number', '966500000000');
$wa_msg = rawurlencode($lang === 'ar' 
    ? "السلام عليكم، أود التواصل معكم للاستفسار عن خدمات الأرفف الحديدية." 
    : "Hello, I would like to contact you about iron shelves services.");
$wa_link = "https://wa.me/{$whatsapp_number}?text={$wa_msg}";

// 2. Define the 7 sections of the About Us page
$about_sections = [
    'banner'    => [
        'show'  => get_setting('about_show_banner', '1') === '1',
        'order' => (int)get_setting('about_order_banner', '10')
    ],
    'story'     => [
        'show'  => get_setting('about_show_story', '1') === '1',
        'order' => (int)get_setting('about_order_story', '20')
    ],
    'stats'     => [
        'show'  => get_setting('about_show_stats', '1') === '1',
        'order' => (int)get_setting('about_order_stats', '30')
    ],
    'expertise' => [
        'show'  => get_setting('about_show_expertise', '1') === '1',
        'order' => (int)get_setting('about_order_expertise', '40')
    ],
    'values'    => [
        'show'  => get_setting('about_show_values', '1') === '1',
        'order' => (int)get_setting('about_order_values', '50')
    ],
    'timeline'  => [
        'show'  => get_setting('about_show_timeline', '1') === '1',
        'order' => (int)get_setting('about_order_timeline', '60')
    ],
    'cta'       => [
        'show'  => get_setting('about_show_cta', '1') === '1',
        'order' => (int)get_setting('about_order_cta', '70')
    ]
];

// Sort sections by order weight ascending
uasort($about_sections, function($a, $b) {
    return $a['order'] <=> $b['order'];
});
?>

<main>
<div class="container about-page-container">
    <?php
    foreach ($about_sections as $sec_key => $sec_info) {
        if (!$sec_info['show']) {
            continue;
        }
        
        switch ($sec_key) {
            case 'banner':
                ?>
                <!-- Header Banner -->
                <div class="about-header-banner" style="margin-bottom: 40px;">
                    <h1><?php echo htmlspecialchars(get_setting('about_us_title_' . $lang, __('about_us_title'))); ?></h1>
                    <p><?php echo htmlspecialchars(get_setting('about_banner_subtitle_' . $lang, __('tagline'))); ?></p>
                    <div class="about-header-divider"></div>
                </div>
                <?php
                break;
                
            case 'story':
                ?>
                <!-- Section 1: The Story & Image -->
                <div class="about-story-section reveal-on-scroll">
                    <div class="about-story-content">
                        <h2><?php echo htmlspecialchars(get_setting('about_story_title_' . $lang, __('about_story_title'))); ?></h2>
                        <p class="about-story-text"><?php echo nl2br(htmlspecialchars(get_setting('about_story_desc_' . $lang, __('about_story_desc')))); ?></p>
                        
                        <div class="about-vision-mission-grid">
                            <div class="vision-card">
                                <h3><i data-lucide="compass"></i> <?php echo htmlspecialchars(get_setting('about_mission_title_' . $lang, __('about_mission_title'))); ?></h3>
                                <p><?php echo htmlspecialchars(get_setting('about_mission_desc_' . $lang, __('about_mission_desc'))); ?></p>
                            </div>
                            <div class="mission-card">
                                <h3><i data-lucide="eye"></i> <?php echo htmlspecialchars(get_setting('about_vision_title_' . $lang, __('about_vision_title'))); ?></h3>
                                <p><?php echo htmlspecialchars(get_setting('about_vision_desc_' . $lang, __('about_vision_desc'))); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="about-story-image-wrapper">
                        <div class="about-image-card">
                            <img src="<?php echo htmlspecialchars(get_setting('about_image', 'assets/images/shelf4.png')); ?>" 
                                 alt="<?php echo htmlspecialchars(get_setting('about_story_title_' . $lang, __('about_story_title'))); ?>" 
                                 class="about-img"
                                 width="600"
                                 height="400"
                                 fetchpriority="high"
                                 loading="eager"
                                 decoding="sync">
                        </div>
                    </div>
                </div>
                <?php
                break;
                
            case 'stats':
                ?>
                <!-- Section 2: Reusable Premium Business Statistics -->
                <?php include __DIR__ . '/includes/stats-section.php'; ?>
                <?php
                break;
                
            case 'expertise':
                ?>
                <!-- Section 3: Specialized Expertise -->
                <div class="about-expertise-section reveal-on-scroll">
                    <div class="about-expertise-content">
                        <h2><?php echo htmlspecialchars(get_setting('about_exp_title_' . $lang, __('about_exp_title'))); ?></h2>
                        <p><?php echo nl2br(htmlspecialchars(get_setting('about_exp_desc_' . $lang, __('about_exp_desc')))); ?></p>
                    </div>
                </div>
                <?php
                break;
                
            case 'values':
                ?>
                <!-- Section 4: Our Values -->
                <div class="about-values-section reveal-on-scroll">
                    <h2 class="values-section-title"><?php echo htmlspecialchars(get_setting('about_values_title_' . $lang, __('about_values_title'))); ?></h2>
                    <div class="about-values-grid">
                        <div class="value-card">
                            <div class="value-icon"><i data-lucide="shield"></i></div>
                            <h3><?php echo htmlspecialchars(get_setting('about_value_1_title_' . $lang, __('about_value_1'))); ?></h3>
                            <p><?php echo htmlspecialchars(get_setting('about_value_1_desc_' . $lang, __('about_value_1_desc'))); ?></p>
                        </div>
                        <div class="value-card">
                            <div class="value-icon"><i data-lucide="zap"></i></div>
                            <h3><?php echo htmlspecialchars(get_setting('about_value_2_title_' . $lang, __('about_value_2'))); ?></h3>
                            <p><?php echo htmlspecialchars(get_setting('about_value_2_desc_' . $lang, __('about_value_2_desc'))); ?></p>
                        </div>
                        <div class="value-card">
                            <div class="value-icon"><i data-lucide="scale"></i></div>
                            <h3><?php echo htmlspecialchars(get_setting('about_value_3_title_' . $lang, __('about_value_3'))); ?></h3>
                            <p><?php echo htmlspecialchars(get_setting('about_value_3_desc_' . $lang, __('about_value_3_desc'))); ?></p>
                        </div>
                    </div>
                </div>
                <?php
                break;
                
            case 'timeline':
                require_once __DIR__ . '/includes/about-helper.php';
                $milestones = get_about_milestones();
                if (count($milestones) > 0):
                    ?>
                    <!-- Section 4.5: Company Milestones Timeline -->
                    <div class="timeline-section reveal-on-scroll" style="margin-bottom: 60px; border-radius: var(--radius-lg); overflow: hidden;">
                        <h2 class="values-section-title"><?php echo htmlspecialchars(get_setting('about_timeline_title_' . $lang, __('timeline_title'))); ?></h2>
                        <p style="text-align: center; color: var(--text-secondary); margin-top: -30px; margin-bottom: 40px; font-size: 15.5px;"><?php echo htmlspecialchars(get_setting('about_timeline_subtitle_' . $lang, __('timeline_subtitle'))); ?></p>
                        
                        <div class="timeline-container">
                            <?php 
                            $idx = 1;
                            foreach ($milestones as $m): 
                                $step_str = str_pad($idx, 2, '0', STR_PAD_LEFT);
                                $idx++;
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content-card">
                                        <span class="timeline-step-badge"><?php echo $step_str; ?></span>
                                        <h3><?php echo htmlspecialchars($m['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($m['desc']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php
                endif;
                break;
                
            case 'cta':
                ?>
                <!-- Section 5: Reusable Premium Contextual CTA Block -->
                <?php
                $cta_page = 'about';
                include __DIR__ . '/includes/cta-block.php';
                ?>
                <?php
                break;
        }
    }
    ?>
</div>
</main>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
