<?php
// 404.php
http_response_code(404);

if (!defined('HEADER_LOADED')) {
    $seo = [
        'title_key' => 'page_not_found_title',
        'desc_key' => 'page_not_found_desc',
        'type' => 'website',
        'noindex' => true,
        'canonical' => false
    ];
    include_once __DIR__ . '/includes/header.php';
}
?>

<div class="container about-page-container" style="text-align: center; padding: 100px 24px; min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div style="max-width: 500px; margin: 0 auto;">
        <div style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); width: 88px; height: 88px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
            <i data-lucide="compass" style="width: 44px; height: 44px; color: var(--primary-color);"></i>
        </div>
        <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 16px; color: var(--text-primary); line-height: 1.3;">
            <?php echo __('page_not_found_title'); ?>
        </h1>
        <p style="font-size: 16px; color: var(--text-secondary); margin-bottom: 36px; line-height: 1.7;">
            <?php echo __('page_not_found_desc'); ?>
        </p>
        <a href="<?php echo get_site_url() . '/'; ?>" class="btn btn-primary-cta" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
            <i data-lucide="home" style="width: 18px; height: 18px;"></i>
            <span><?php echo __('back_to_home'); ?></span>
        </a>
    </div>
</div>

<?php
include_once __DIR__ . '/includes/footer.php';
exit;
