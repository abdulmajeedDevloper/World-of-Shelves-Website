<?php
// includes/admin-sidebar.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_cs = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'customer_service');
$lang = get_current_lang();
?>
<!-- Admin Sidebar -->
<aside id="adminSidebar" class="admin-sidebar">
    <div class="admin-sidebar-logo">
        <i data-lucide="layout-grid"></i>
        <span><?php echo htmlspecialchars(__('brand_name')); ?></span>
    </div>
    
    <ul class="admin-menu">
        <li class="admin-menu-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <a href="index.php">
                <i data-lucide="line-chart"></i>
                <span><?php echo htmlspecialchars(__('admin_dashboard')); ?></span>
            </a>
        </li>
        
        <?php if (!$is_cs): ?>
            <li class="admin-menu-item <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                <a href="products.php">
                    <i data-lucide="package"></i>
                    <span><?php echo htmlspecialchars(__('admin_manage_products')); ?></span>
                </a>
            </li>
        <?php endif; ?>

        <li class="admin-menu-item <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
            <a href="orders.php">
                <i data-lucide="shopping-bag"></i>
                <span><?php echo htmlspecialchars(__('admin_manage_orders')); ?></span>
            </a>
        </li>

        <li class="admin-menu-item <?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
            <a href="messages.php">
                <i data-lucide="mail"></i>
                <span><?php echo htmlspecialchars(__('admin_manage_messages')); ?></span>
            </a>
        </li>

        <?php if (!$is_cs): ?>
            <li class="admin-menu-item <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                <a href="categories.php">
                    <i data-lucide="folder"></i>
                    <span><?php echo htmlspecialchars(__('admin_manage_categories')); ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'hero-slider.php' ? 'active' : ''; ?>">
                <a href="hero-slider.php">
                    <i data-lucide="image"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'إدارة سلايدر الهيرو' : 'Hero Slider Carousel'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo ($current_page === 'projects.php' || $current_page === 'project-form.php') ? 'active' : ''; ?>">
                <a href="projects.php">
                    <i data-lucide="briefcase"></i>
                    <span><?php echo htmlspecialchars(__('admin_manage_projects')); ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo ($current_page === 'testimonials.php' || $current_page === 'testimonial-form.php') ? 'active' : ''; ?>">
                <a href="testimonials.php">
                    <i data-lucide="message-square"></i>
                    <span><?php echo htmlspecialchars(__('admin_manage_testimonials')); ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo ($current_page === 'services.php' || $current_page === 'service-form.php') ? 'active' : ''; ?>">
                <a href="services.php">
                    <i data-lucide="wrench"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'إدارة الخدمات' : 'Manage Services'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'features.php' ? 'active' : ''; ?>">
                <a href="features.php">
                    <i data-lucide="award"></i>
                    <span><?php echo htmlspecialchars(__('admin_manage_features')); ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php">
                    <i data-lucide="user"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'حساب المسؤول' : 'Admin Profile'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'homepage.php' ? 'active' : ''; ?>">
                <a href="homepage.php">
                    <i data-lucide="panels-top-left"></i>
                    <span><?php echo htmlspecialchars(__('admin_homepage_title')); ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'about.php' ? 'active' : ''; ?>">
                <a href="about.php">
                    <i data-lucide="info"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'إدارة من نحن' : 'Manage About Us'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'global-ui.php' && (!isset($_GET['tab']) || ($_GET['tab'] !== 'socials' && $_GET['tab'] !== 'contact')) ? 'active' : ''; ?>">
                <a href="global-ui.php">
                    <i data-lucide="globe"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'عناصر الموقع العامة' : 'Global Site Management'; ?></span>
                </a>
            </li>
        <?php else: ?>
            <!-- Customer Service Specific Sub-Settings Links -->
            <li class="admin-menu-item <?php echo ($current_page === 'global-ui.php' && isset($_GET['tab']) && $_GET['tab'] === 'socials') ? 'active' : ''; ?>">
                <a href="global-ui.php?tab=socials">
                    <i data-lucide="share-2"></i>
                    <span><?php echo $lang === 'ar' ? 'إعدادات شبكات التواصل' : 'Social Media Settings'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo ($current_page === 'global-ui.php' && isset($_GET['tab']) && $_GET['tab'] === 'contact') ? 'active' : ''; ?>">
                <a href="global-ui.php?tab=contact">
                    <i data-lucide="phone"></i>
                    <span><?php echo $lang === 'ar' ? 'بيانات الاتصال والعناوين' : 'Contact Information'; ?></span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (!$is_cs): ?>
            <li class="admin-menu-item <?php echo $current_page === 'contact.php' ? 'active' : ''; ?>">
                <a href="contact.php">
                    <i data-lucide="phone-call"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'إدارة صفحة التواصل' : 'Contact Page Management'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'used-shelves.php' ? 'active' : ''; ?>">
                <a href="used-shelves.php">
                    <i data-lucide="layers"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'إدارة الأرفف المستعملة' : 'Manage Used Shelves'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'installation.php' ? 'active' : ''; ?>">
                <a href="installation.php">
                    <i data-lucide="wrench"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'إدارة صفحة التركيب' : 'Manage Installation'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'media.php' ? 'active' : ''; ?>">
                <a href="media.php">
                    <i data-lucide="image"></i>
                    <span><?php echo htmlspecialchars(__('admin_media_library')); ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                <a href="users.php">
                    <i data-lucide="users"></i>
                    <span><?php echo get_current_lang() === 'ar' ? 'إدارة مسؤولي التواصل' : 'Users Management'; ?></span>
                </a>
            </li>
            <li class="admin-menu-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <a href="settings.php">
                    <i data-lucide="settings"></i>
                    <span><?php echo htmlspecialchars(__('admin_site_settings')); ?></span>
                </a>
            </li>
        <?php endif; ?>

        <li class="admin-menu-item" style="margin-top: 40px;">
            <a href="../" target="_blank">
                <i data-lucide="external-link"></i>
                <span><?php echo htmlspecialchars(__('admin_view_site')); ?></span>
            </a>
        </li>
        <li class="admin-menu-item" style="color: var(--danger);">
            <a href="logout.php">
                <i data-lucide="log-out"></i>
                <span><?php echo htmlspecialchars(__('admin_logout')); ?></span>
            </a>
        </li>
    </ul>
</aside>
