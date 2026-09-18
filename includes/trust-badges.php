<?php
// includes/trust-badges.php
require_once __DIR__ . '/lang_helper.php';
?>
<section class="trust-badges-section reveal-on-scroll">
    <div class="container">
        <div class="trust-badges-grid">
            <!-- Badge 1: Installation -->
            <div class="trust-badge-card">
                <div class="trust-badge-icon">
                    <i data-lucide="wrench" aria-hidden="true"></i>
                </div>
                <div class="trust-badge-text">
                    <h3><?php echo __('badge_trust_install_title'); ?></h3>
                    <p><?php echo __('badge_trust_install_desc'); ?></p>
                </div>
            </div>

            <!-- Badge 2: Quality -->
            <div class="trust-badge-card">
                <div class="trust-badge-icon">
                    <i data-lucide="shield-check" aria-hidden="true"></i>
                </div>
                <div class="trust-badge-text">
                    <h3><?php echo __('badge_trust_quality_title'); ?></h3>
                    <p><?php echo __('badge_trust_quality_desc'); ?></p>
                </div>
            </div>

            <!-- Badge 3: Visit -->
            <div class="trust-badge-card">
                <div class="trust-badge-icon">
                    <i data-lucide="map-pin-check" aria-hidden="true"></i>
                </div>
                <div class="trust-badge-text">
                    <h3><?php echo __('badge_trust_visit_title'); ?></h3>
                    <p><?php echo __('badge_trust_visit_desc'); ?></p>
                </div>
            </div>

            <!-- Badge 4: Response -->
            <div class="trust-badge-card">
                <div class="trust-badge-icon">
                    <i data-lucide="messages-square" aria-hidden="true"></i>
                </div>
                <div class="trust-badge-text">
                    <h3><?php echo __('badge_trust_response_title'); ?></h3>
                    <p><?php echo __('badge_trust_response_desc'); ?></p>
                </div>
            </div>

            <!-- Badge 5: Pricing -->
            <div class="trust-badge-card">
                <div class="trust-badge-icon">
                    <i data-lucide="badge-percent" aria-hidden="true"></i>
                </div>
                <div class="trust-badge-text">
                    <h3><?php echo __('badge_trust_pricing_title'); ?></h3>
                    <p><?php echo __('badge_trust_pricing_desc'); ?></p>
                </div>
            </div>

            <!-- Badge 6: Used -->
            <div class="trust-badge-card">
                <div class="trust-badge-icon">
                    <i data-lucide="recycle" aria-hidden="true"></i>
                </div>
                <div class="trust-badge-text">
                    <h3><?php echo __('badge_trust_used_title'); ?></h3>
                    <p><?php echo __('badge_trust_used_desc'); ?></p>
                </div>
            </div>

            <!-- Badge 7: Solutions -->
            <div class="trust-badge-card">
                <div class="trust-badge-icon">
                    <i data-lucide="layout-grid" aria-hidden="true"></i>
                </div>
                <div class="trust-badge-text">
                    <h3><?php echo __('badge_trust_solutions_title'); ?></h3>
                    <p><?php echo __('badge_trust_solutions_desc'); ?></p>
                </div>
            </div>

            <!-- Badge 8: Team -->
            <div class="trust-badge-card">
                <div class="trust-badge-icon">
                    <i data-lucide="users" aria-hidden="true"></i>
                </div>
                <div class="trust-badge-text">
                    <h3><?php echo __('badge_trust_team_title'); ?></h3>
                    <p><?php echo __('badge_trust_team_desc'); ?></p>
                </div>
            </div>
        </div>
    </div>
</section>
