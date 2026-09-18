<?php
// includes/floating-bar.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/lang_helper.php';

$whatsapp_number = get_setting('whatsapp_number', '');
$contact_phone = get_setting('contact_phone', '');
$lang = get_current_lang();

// Visibility toggles
$show_wa = get_setting('show_floating_whatsapp', '1') === '1';
$show_phone = get_setting('show_floating_phone', '1') === '1' && !empty($contact_phone);
$show_visit = get_setting('show_floating_visit', '1') === '1';

// Custom WhatsApp message templates plain text
$wa_msg_text = get_setting('floating_whatsapp_msg_' . $lang);
if (empty($wa_msg_text)) {
    $wa_msg_text = ($lang === 'ar') 
        ? "السلام عليكم، أود طلب استشارة أو عرض سعر للأرفف الحديدية." 
        : "Hello, I would like to request a quotation or advice for steel shelves.";
}

$visit_msg_text = get_setting('floating_visit_msg_' . $lang);
if (empty($visit_msg_text)) {
    $visit_msg_text = ($lang === 'ar') 
        ? "السلام عليكم، أود طلب حجز موعد معاينة مجانية للموقع لتخطيط وتركيب الأرفف." 
        : "Hello, I want to book a free site visit/inspection for steel racking setup planning.";
}

// Generate links with fallback to contact.php if whatsapp_number is empty
if (!empty($whatsapp_number)) {
    $wa_fab_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $whatsapp_number) . "?text=" . rawurlencode($wa_msg_text);
    $visit_fab_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $whatsapp_number) . "?text=" . rawurlencode($visit_msg_text);
} else {
    $wa_fab_link = "contact";
    $visit_fab_link = "contact";
}

$call_fab_link = "tel:" . preg_replace('/[^0-9+]/', '', $contact_phone);
?>

<?php if ($show_wa || $show_phone || $show_visit): ?>
<!-- Reusable Expandable Floating Contact Widget -->
<div class="fab-widget-container" id="fabWidget">
    <!-- Desktop Layout (Compact Sidebar) -->
    <div class="fab-desktop-sidebar">
        <?php if ($show_wa): ?>
            <a href="<?php echo htmlspecialchars($wa_fab_link); ?>" <?php echo $wa_fab_link !== 'contact.php' ? 'target="_blank"' : ''; ?> class="desktop-fab-item fab-wa" title="<?php echo __('fab_whatsapp'); ?>">
                <i data-lucide="message-circle"></i>
                <span><?php echo __('fab_whatsapp'); ?></span>
            </a>
        <?php endif; ?>
        <?php if ($show_phone): ?>
            <a href="<?php echo htmlspecialchars($call_fab_link); ?>" class="desktop-fab-item fab-call" title="<?php echo __('fab_call'); ?>">
                <i data-lucide="phone"></i>
                <span><?php echo __('fab_call'); ?></span>
            </a>
        <?php endif; ?>
        <?php if ($show_visit): ?>
            <a href="<?php echo htmlspecialchars($visit_fab_link); ?>" <?php echo $visit_fab_link !== 'contact.php' ? 'target="_blank"' : ''; ?> class="desktop-fab-item fab-visit" title="<?php echo __('fab_visit'); ?>">
                <i data-lucide="calendar"></i>
                <span><?php echo __('fab_visit'); ?></span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Mobile Layout (Expandable FAB Button) -->
    <div class="fab-mobile-container" id="fabMobileContainer">
        <!-- Expanded Sub-buttons -->
        <div class="fab-mobile-menu">
            <?php if ($show_wa): ?>
                <a href="<?php echo htmlspecialchars($wa_fab_link); ?>" <?php echo $wa_fab_link !== 'contact.php' ? 'target="_blank"' : ''; ?> class="mobile-sub-fab fab-wa" aria-label="<?php echo __('fab_whatsapp'); ?>">
                    <i data-lucide="message-circle"></i>
                    <span class="sub-fab-label"><?php echo __('fab_whatsapp'); ?></span>
                </a>
            <?php endif; ?>
            <?php if ($show_phone): ?>
                <a href="<?php echo htmlspecialchars($call_fab_link); ?>" class="mobile-sub-fab fab-call" aria-label="<?php echo __('fab_call'); ?>">
                    <i data-lucide="phone"></i>
                    <span class="sub-fab-label"><?php echo __('fab_call'); ?></span>
                </a>
            <?php endif; ?>
            <?php if ($show_visit): ?>
                <a href="<?php echo htmlspecialchars($visit_fab_link); ?>" <?php echo $visit_fab_link !== 'contact.php' ? 'target="_blank"' : ''; ?> class="mobile-sub-fab fab-visit" aria-label="<?php echo __('fab_visit'); ?>">
                    <i data-lucide="calendar"></i>
                    <span class="sub-fab-label"><?php echo __('fab_visit'); ?></span>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Main Trigger Button -->
        <button class="fab-main-trigger" id="fabMainTrigger" aria-label="Toggle Contact Menu">
            <i data-lucide="message-circle" class="trigger-icon-chat"></i>
            <i data-lucide="x" class="trigger-icon-close" style="display: none;"></i>
        </button>
    </div>
</div>
<?php endif; ?>
