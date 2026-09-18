<?php
// includes/testimonials.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/portfolio-helper.php';
$testimonials = get_testimonials();
?>
<?php if (!empty($testimonials)): ?>
<section class="testimonials-section reveal-on-scroll">
    <div class="container">
        <div class="section-header">
            <h2><?php echo __('testimonials_title'); ?></h2>
            <div class="section-divider"></div>
            <p><?php echo __('testimonials_subtitle'); ?></p>
        </div>
        
        <div class="testimonials-grid">
            <?php foreach ($testimonials as $t): 
                $name = (get_current_lang() === 'ar') ? $t['name_ar'] : $t['name_en'];
                $company = (get_current_lang() === 'ar') ? $t['company_ar'] : $t['company_en'];
                $review = (get_current_lang() === 'ar') ? $t['review_ar'] : $t['review_en'];
                $stars = intval($t['stars']);
            ?>
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i data-lucide="star" class="testimonial-star <?php echo ($s <= $stars) ? 'star-filled' : 'star-empty'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="testimonial-review">"<?php echo htmlspecialchars($review); ?>"</p>
                    <div class="testimonial-author">
                        <div class="author-info">
                            <h4><?php echo htmlspecialchars($name); ?></h4>
                            <span><?php echo htmlspecialchars($company); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
