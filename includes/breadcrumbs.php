<?php
// includes/breadcrumbs.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

if (!isset($breadcrumbs) || !is_array($breadcrumbs)) {
    $breadcrumbs = [];
}
?>
<nav class="breadcrumbs-nav" aria-label="<?php echo get_current_lang() === 'ar' ? 'مسار التنقل' : 'Breadcrumb'; ?>">
    <div class="container">
        <ol class="breadcrumbs-list">
            <li class="breadcrumb-item">
                <a href="<?php echo get_site_url() . '/'; ?>">
                    <span><?php echo __('breadcrumb_home'); ?></span>
                </a>
            </li>
            <?php 
            $i = 2;
            $total = count($breadcrumbs);
            foreach ($breadcrumbs as $name => $url): 
                $is_last = ($i === $total + 1);
            ?>
                <li class="breadcrumb-item <?php echo $is_last ? 'active' : ''; ?>">
                    <span class="breadcrumb-separator">/</span>
                    <?php if ($url && !$is_last): ?>
                        <a href="<?php echo htmlspecialchars($url); ?>">
                            <span><?php echo htmlspecialchars($name); ?></span>
                        </a>
                    <?php else: ?>
                        <span aria-current="page"><?php echo htmlspecialchars($name); ?></span>
                    <?php endif; ?>
                </li>
            <?php 
                $i++;
            endforeach; 
            ?>
        </ol>
    </div>
</nav>
