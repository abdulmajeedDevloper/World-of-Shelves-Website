<?php
// includes/stats-section.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/lang_helper.php';
$lang = get_current_lang();
?>
<section class="stats-section reveal-on-scroll">
    <div class="container">
        <div class="stats-grid">
            <?php for ($i = 1; $i <= 4; $i++): 
                $val = get_setting("homepage_stat_{$i}_value", '');
                $suffix = get_setting("homepage_stat_{$i}_suffix", '+');
                $title = get_setting("homepage_stat_{$i}_title_" . $lang, '');
                $desc = get_setting("homepage_stat_{$i}_desc_" . $lang, '');
                
                // Skip rendering if value or title is missing
                if (empty($val) || empty($title)) {
                    continue;
                }
            ?>
                <!-- Stat <?php echo $i; ?> -->
                <div class="stat-card">
                    <div class="stat-number-wrapper">
                        <span class="stat-count" data-target="<?php echo htmlspecialchars($val); ?>">0</span><span class="stat-suffix"><?php echo htmlspecialchars($suffix); ?></span>
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
