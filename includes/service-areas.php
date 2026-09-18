<?php
// includes/service-areas.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/lang_helper.php';
$lang = get_current_lang();

$sec_title = get_setting('homepage_areas_title_' . $lang, __('serve_areas_title'));
$sec_subtitle = get_setting('homepage_areas_subtitle_' . $lang, __('serve_areas_subtitle'));
?>
<section class="service-areas-section reveal-on-scroll">
    <div class="container">
        <div class="section-header">
            <h2><?php echo htmlspecialchars($sec_title); ?></h2>
            <?php if (!empty($sec_subtitle)): ?>
                <p class="section-subtitle"><?php echo htmlspecialchars($sec_subtitle); ?></p>
            <?php endif; ?>
            <div class="section-divider"></div>
        </div>
        
        <div class="service-areas-grid">
            <?php for ($i = 1; $i <= 6; $i++): 
                $icon = get_setting("homepage_area_{$i}_icon", '');
                $title = get_setting("homepage_area_{$i}_title_" . $lang, '');
                $desc = get_setting("homepage_area_{$i}_desc_" . $lang, '');
                
                // Skip if title is empty
                if (empty($title)) {
                    continue;
                }
            ?>
                <!-- Card <?php echo $i; ?> -->
                <div class="area-card">
                    <div class="area-icon-wrapper">
                        <i data-lucide="<?php echo htmlspecialchars(!empty($icon) ? $icon : 'warehouse'); ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($title); ?></h3>
                    <?php if (!empty($desc)): ?>
                        <p><?php echo htmlspecialchars($desc); ?></p>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>
