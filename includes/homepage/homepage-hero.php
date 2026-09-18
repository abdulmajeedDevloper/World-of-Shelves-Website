<?php
// includes/homepage/homepage-hero.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

$whatsapp_number = get_setting('whatsapp_number', '966500000000');
$lang = get_current_lang();

// Helper to resolve action target safely
function get_homepage_action_url($action_key, $whatsapp_number, $lang) {
    switch ($action_key) {
        case 'whatsapp':
            $msg = rawurlencode($lang === 'ar' 
                ? "السلام عليكم، أود الاستفسار عن تفاصيل وخدمات الأرفف الحديدية." 
                : "Hello, I would like to inquire about iron shelves services.");
            return "https://wa.me/{$whatsapp_number}?text={$msg}";
        case 'products.php':
        case 'products':
            return 'products';
        case 'services.php':
        case 'services':
            return 'services';
        case 'projects.php':
        case 'projects':
            return 'projects';
        case 'contact.php':
        case 'contact':
            return 'contact';
        case 'about.php':
        case 'about':
            return 'about';
        default:
            return 'products';
    }
}

$primary_action = get_setting('hero_cta_primary_action', 'products.php');
$secondary_action = get_setting('hero_cta_secondary_action', 'whatsapp');

$primary_url = get_homepage_action_url($primary_action, $whatsapp_number, $lang);
$secondary_url = get_homepage_action_url($secondary_action, $whatsapp_number, $lang);

$hero_title = get_setting('hero_title_' . $lang, __('hero_title'));
$hero_subtitle = get_setting('hero_subtitle_' . $lang, __('hero_subtitle'));

$primary_label = get_setting('hero_cta_primary_label_' . $lang, __('hero_cta_products'));
$secondary_label = get_setting('hero_cta_secondary_label_' . $lang, __('hero_cta_whatsapp'));
$hero_image = isset($preloadImage) && !empty($preloadImage) ? $preloadImage : get_setting('hero_image', 'assets/images/shelf1.png');
?>
<section class="hero-section hero-carousel" aria-label="<?php echo get_current_lang() === 'ar' ? 'واجهة العرض المتحركة' : 'Hero Image Carousel'; ?>">
    <!-- Background Slides Deck -->
    <div class="hero-carousel__slides">
        <?php foreach ($heroSlides as $idx => $slide): ?>
            <div class="hero-carousel__slide <?php echo $idx === 0 ? 'hero-carousel__slide--active' : ''; ?>" aria-hidden="<?php echo $idx === 0 ? 'false' : 'true'; ?>">
                <img src="<?php echo htmlspecialchars($slide['image_path']); ?>" 
                     alt="<?php echo htmlspecialchars(get_current_lang() === 'ar' ? $slide['alt_ar'] : $slide['alt_en']); ?>" 
                     class="hero-carousel__image"
                     width="1920"
                     height="800"
                     <?php echo $idx === 0 ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"'; ?>>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Dark Readability Overlay -->
    <div class="hero-carousel__overlay"></div>

    <!-- Centered Fixed Text Content Block -->
    <div class="hero-carousel__content container">
        <h1><?php echo htmlspecialchars($hero_title); ?></h1>
        <p><?php echo htmlspecialchars($hero_subtitle); ?></p>
        
        <div class="hero-carousel__actions">
            <!-- Primary Action Button -->
            <a href="<?php echo htmlspecialchars($primary_url); ?>" <?php echo ($primary_action === 'whatsapp') ? 'target="_blank"' : ''; ?> class="btn btn-primary-cta">
                <span><?php echo htmlspecialchars($primary_label); ?></span>
                <?php if ($primary_action === 'whatsapp'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                      <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                    </svg>
                <?php else: ?>
                    <i data-lucide="arrow-left" class="rtl-only" aria-hidden="true"></i>
                    <i data-lucide="arrow-right" class="ltr-only" aria-hidden="true"></i>
                <?php endif; ?>
            </a>
            
            <!-- Secondary Action Button -->
            <a href="<?php echo htmlspecialchars($secondary_url); ?>" <?php echo ($secondary_action === 'whatsapp') ? 'target="_blank"' : ''; ?> class="btn btn-secondary-cta">
                <?php if ($secondary_action === 'whatsapp'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="whatsapp-icon-svg">
                      <path d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.198 8.198 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.183 8.183 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24zm4.52-6.17c-.25-.12-1.47-.72-1.69-.8-.23-.08-.39-.12-.56.12-.17.25-.66.8-.81.97-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2.01-1.24-.74-.66-1.24-1.47-1.39-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.24-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/>
                    </svg>
                <?php else: ?>
                    <i data-lucide="message-circle" aria-hidden="true"></i>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($secondary_label); ?></span>
            </a>
        </div>
    </div>

    <!-- Carousel Controls -->
    <?php if (count($heroSlides) > 1): ?>
        <div class="hero-carousel__nav">
            <button class="hero-carousel__btn hero-carousel__btn--prev" aria-label="<?php echo get_current_lang() === 'ar' ? 'السابق' : 'Previous'; ?>">
                <i data-lucide="chevron-left" aria-hidden="true"></i>
            </button>
            <button class="hero-carousel__btn hero-carousel__btn--next" aria-label="<?php echo get_current_lang() === 'ar' ? 'التالي' : 'Next'; ?>">
                <i data-lucide="chevron-right" aria-hidden="true"></i>
            </button>
        </div>
        <div class="hero-carousel__dots" role="tablist">
            <?php foreach ($heroSlides as $idx => $slide): ?>
                <button class="hero-carousel__dot <?php echo $idx === 0 ? 'hero-carousel__dot--active' : ''; ?>" role="tab" aria-selected="<?php echo $idx === 0 ? 'true' : 'false'; ?>" aria-label="<?php echo (get_current_lang() === 'ar' ? 'الانتقال للشريحة ' : 'Go to slide ') . ($idx + 1); ?>" data-index="<?php echo $idx; ?>"></button>
            <?php endforeach; ?>
        </div>
        <script src="assets/js/hero-carousel.js" defer></script>
    <?php endif; ?>
</section>
