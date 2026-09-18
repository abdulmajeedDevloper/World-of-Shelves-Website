<?php
// includes/client-logos.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/portfolio-helper.php';
$logos = get_client_logos();
?>
<section class="client-logos-section reveal-on-scroll">
    <div class="container">
        <div class="section-header">
            <h2><?php echo __('clients_title'); ?></h2>
            <div class="section-divider"></div>
            <p><?php echo __('clients_subtitle'); ?></p>
        </div>
        
        <div class="logo-grid">
            <?php foreach ($logos as $l): ?>
                <div class="logo-item">
                    <img src="<?php echo htmlspecialchars($l['logo_url']); ?>" 
                         alt="<?php echo htmlspecialchars($l['name']); ?>" 
                         width="120"
                         height="40"
                         loading="lazy" 
                         decoding="async" />
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
