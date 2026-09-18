<?php
// includes/before-after.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
if (!isset($before_img) || !isset($after_img)) {
    return;
}
?>
<?php
$ba_project_title = '';
if (!empty($title)) {
    $ba_project_title = $title;
} elseif (!empty($project_title_caption)) {
    $ba_project_title = $project_title_caption;
}

$ba_lang = function_exists('get_current_lang') ? get_current_lang() : 'ar';
if ($ba_lang === 'ar') {
    $before_alt = !empty($ba_project_title) ? "قبل تنفيذ مشروع " . $ba_project_title : "قبل تنفيذ المشروع";
    $after_alt = !empty($ba_project_title) ? "بعد تنفيذ مشروع " . $ba_project_title : "بعد تنفيذ المشروع";
} else {
    $before_alt = !empty($ba_project_title) ? "Before implementation of " . $ba_project_title : "Before implementation";
    $after_alt = !empty($ba_project_title) ? "After implementation of " . $ba_project_title : "After implementation";
}
?>
<div class="before-after-wrapper reveal-on-scroll">
    <div class="ba-slider-container" id="baSlider">
        <!-- Before Image (Base) -->
        <div class="ba-image-wrapper ba-before">
            <img src="<?php echo htmlspecialchars($before_img); ?>" alt="<?php echo htmlspecialchars($before_alt, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async" />
            <span class="ba-label ba-label-before"><?php echo __('before_label'); ?></span>
        </div>
        
        <!-- After Image (Overlay) -->
        <div class="ba-image-wrapper ba-after" id="baAfterWrapper">
            <img src="<?php echo htmlspecialchars($after_img); ?>" alt="<?php echo htmlspecialchars($after_alt, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async" />
            <span class="ba-label ba-label-after"><?php echo __('after_label'); ?></span>
        </div>
        
        <!-- Slider Handle -->
        <div class="ba-handle" id="baHandle">
            <div class="ba-handle-line"></div>
            <div class="ba-handle-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m18 8 4 4-4 4M6 8l-4 4 4 4" />
                </svg>
            </div>
        </div>
    </div>
</div>
