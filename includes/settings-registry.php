<?php
// includes/settings-registry.php
// Centralized settings definition registry for World of Shelves CMS.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

/**
 * Returns the array of all supported CMS settings, organized by group.
 */
function get_settings_registry() {
    return [
        // -------------------------------------------------------------
        // 1. GENERAL GROUP
        // -------------------------------------------------------------
        'site_name_ar' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_site_name_ar',
            'default' => 'عالم الرفوف',
            'required' => true,
            'max_length' => 150
        ],
        'site_name_en' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_site_name_en',
            'default' => 'World of Shelves',
            'required' => true,
            'max_length' => 150
        ],
        'tagline_ar' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_tagline_ar',
            'default' => 'منصتك المتكاملة لتصميم وتوصيل وتركيب أرقى أنواع الرفوف',
            'required' => false,
            'max_length' => 255
        ],
        'tagline_en' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_tagline_en',
            'default' => 'Your all-in-one platform to design, deliver, and install premium shelves',
            'required' => false,
            'max_length' => 255
        ],
        'default_language' => [
            'group' => 'general',
            'type' => 'select',
            'label_key' => 'settings_default_language',
            'default' => 'ar',
            'options' => ['ar' => 'العربية', 'en' => 'English'],
            'required' => true
        ],
        'business_name_ar' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_business_name_ar',
            'default' => 'مؤسسة عالم الرفوف التجارية',
            'required' => false,
            'max_length' => 150
        ],
        'business_name_en' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_business_name_en',
            'default' => 'World of Shelves Trading Est.',
            'required' => false,
            'max_length' => 150
        ],
        'copyright_text_ar' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_copyright_text_ar',
            'default' => 'جميع الحقوق محفوظة.',
            'required' => false,
            'max_length' => 255
        ],
        'copyright_text_en' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_copyright_text_en',
            'default' => 'All Rights Reserved.',
            'required' => false,
            'max_length' => 255
        ],
        // Navbar Links (for backward compatibility)
        'nav_home_ar' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_nav_home_ar',
            'default' => 'الرئيسية',
            'required' => true,
            'max_length' => 50
        ],
        'nav_home_en' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_nav_home_en',
            'default' => 'Home',
            'required' => true,
            'max_length' => 50
        ],
        'nav_products_ar' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_nav_products_ar',
            'default' => 'المنتجات',
            'required' => true,
            'max_length' => 50
        ],
        'nav_products_en' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_nav_products_en',
            'default' => 'Products',
            'required' => true,
            'max_length' => 50
        ],
        'nav_about_ar' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_nav_about_ar',
            'default' => 'من نحن',
            'required' => true,
            'max_length' => 50
        ],
        'nav_about_en' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_nav_about_en',
            'default' => 'About Us',
            'required' => true,
            'max_length' => 50
        ],
        'nav_admin_ar' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_nav_admin_ar',
            'default' => 'لوحة الإدارة',
            'required' => true,
            'max_length' => 50
        ],
        'nav_admin_en' => [
            'group' => 'general',
            'type' => 'text',
            'label_key' => 'settings_nav_admin_en',
            'default' => 'Admin Panel',
            'required' => true,
            'max_length' => 50
        ],

        // -------------------------------------------------------------
        // 2. BRANDING GROUP
        // -------------------------------------------------------------
        'site_logo' => [
            'group' => 'branding',
            'type' => 'file',
            'label_key' => 'settings_site_logo',
            'default' => '',
            'prefix' => 'site-logo',
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'site_logo_dark' => [
            'group' => 'branding',
            'type' => 'file',
            'label_key' => 'settings_site_logo_dark',
            'default' => '',
            'prefix' => 'site-logo-dark',
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'site_favicon' => [
            'group' => 'branding',
            'type' => 'file',
            'label_key' => 'settings_site_favicon',
            'default' => '',
            'prefix' => 'favicon',
            'allowed_types' => ['image/x-icon', 'image/png', 'image/vnd.microsoft.icon']
        ],
        'default_og_image' => [
            'group' => 'branding',
            'type' => 'file',
            'label_key' => 'settings_default_og_image',
            'default' => 'assets/images/shelf1.png',
            'prefix' => 'og-image',
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'logo_alt_ar' => [
            'group' => 'branding',
            'type' => 'text',
            'label_key' => 'settings_logo_alt_ar',
            'default' => 'شعار عالم الرفوف',
            'required' => false,
            'max_length' => 150
        ],
        'logo_alt_en' => [
            'group' => 'branding',
            'type' => 'text',
            'label_key' => 'settings_logo_alt_en',
            'default' => 'World of Shelves Logo',
            'required' => false,
            'max_length' => 150
        ],
        'site_logo_text_ar' => [
            'group' => 'branding',
            'type' => 'text',
            'label_key' => 'settings_site_logo_text_ar',
            'default' => 'عالم الرفوف',
            'required' => false,
            'max_length' => 150
        ],
        'site_logo_text_en' => [
            'group' => 'branding',
            'type' => 'text',
            'label_key' => 'settings_site_logo_text_en',
            'default' => 'World of Shelves',
            'required' => false,
            'max_length' => 150
        ],

        // -------------------------------------------------------------
        // 3. CONTACT GROUP
        // -------------------------------------------------------------
        'contact_phone' => [
            'group' => 'contact',
            'type' => 'text',
            'label_key' => 'settings_contact_phone',
            'default' => '+966 500 000 000',
            'required' => false,
            'max_length' => 30
        ],
        'contact_phone_sec' => [
            'group' => 'contact',
            'type' => 'text',
            'label_key' => 'settings_contact_phone_sec',
            'default' => '',
            'required' => false,
            'max_length' => 30
        ],
        'whatsapp_number' => [
            'group' => 'contact',
            'type' => 'text',
            'label_key' => 'settings_whatsapp_number',
            'default' => '966500000000',
            'required' => true,
            'max_length' => 20
        ],
        'contact_email' => [
            'group' => 'contact',
            'type' => 'email',
            'label_key' => 'settings_contact_email',
            'default' => 'info@worldofshelves.com',
            'required' => false,
            'max_length' => 150
        ],
        'contact_address_ar' => [
            'group' => 'contact',
            'type' => 'text',
            'label_key' => 'settings_contact_address_ar',
            'default' => 'المملكة العربية السعودية، الرياض',
            'required' => false,
            'max_length' => 255
        ],
        'contact_address_en' => [
            'group' => 'contact',
            'type' => 'text',
            'label_key' => 'settings_contact_address_en',
            'default' => 'Riyadh, Saudi Arabia',
            'required' => false,
            'max_length' => 255
        ],
        'contact_map_iframe' => [
            'group' => 'contact',
            'type' => 'map_iframe',
            'label_key' => 'settings_contact_map_iframe',
            'default' => '',
            'required' => false
        ],
        'working_hours_ar' => [
            'group' => 'contact',
            'type' => 'text',
            'label_key' => 'settings_working_hours_ar',
            'default' => 'السبت - الخميس: 9:00 ص - 10:00 م',
            'required' => false,
            'max_length' => 150
        ],
        'working_hours_en' => [
            'group' => 'contact',
            'type' => 'text',
            'label_key' => 'settings_working_hours_en',
            'default' => 'Saturday - Thursday: 9:00 AM - 10:00 PM',
            'required' => false,
            'max_length' => 150
        ],

        // -------------------------------------------------------------
        // 4. SOCIAL MEDIA GROUP
        // -------------------------------------------------------------
        'social_facebook' => [
            'group' => 'social_media',
            'type' => 'url',
            'label_key' => 'settings_social_facebook',
            'default' => '',
            'required' => false,
            'max_length' => 255
        ],
        'social_instagram' => [
            'group' => 'social_media',
            'type' => 'url',
            'label_key' => 'settings_social_instagram',
            'default' => '',
            'required' => false,
            'max_length' => 255
        ],
        'social_twitter' => [
            'group' => 'social_media',
            'type' => 'url',
            'label_key' => 'settings_social_twitter',
            'default' => '',
            'required' => false,
            'max_length' => 255
        ],
        'social_linkedin' => [
            'group' => 'social_media',
            'type' => 'url',
            'label_key' => 'settings_social_linkedin',
            'default' => '',
            'required' => false,
            'max_length' => 255
        ],
        'social_youtube' => [
            'group' => 'social_media',
            'type' => 'url',
            'label_key' => 'settings_social_youtube',
            'default' => '',
            'required' => false,
            'max_length' => 255
        ],
        'social_tiktok' => [
            'group' => 'social_media',
            'type' => 'url',
            'label_key' => 'settings_social_tiktok',
            'default' => '',
            'required' => false,
            'max_length' => 255
        ],
        'social_snapchat' => [
            'group' => 'social_media',
            'type' => 'url',
            'label_key' => 'settings_social_snapchat',
            'default' => '',
            'required' => false,
            'max_length' => 255
        ],

        // -------------------------------------------------------------
        // 5. HOMEPAGE GROUP
        // -------------------------------------------------------------
        'hero_title_ar' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_hero_title_ar',
            'default' => 'اجعل مساحتك أكثر تنظيماً وجمالاً',
            'required' => false,
            'max_length' => 255
        ],
        'hero_title_en' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_hero_title_en',
            'default' => 'Make Your Space Organized & Beautiful',
            'required' => false,
            'max_length' => 255
        ],
        'hero_subtitle_ar' => [
            'group' => 'homepage',
            'type' => 'textarea',
            'label_key' => 'settings_hero_subtitle_ar',
            'default' => 'نوفر لك تشكيلة واسعة من الرفوف الجدارية، القائمة، والديكورية بأعلى جودة مع خدمة التوصيل والتركيب الاحترافية.',
            'required' => false,
            'max_length' => 500
        ],
        'hero_subtitle_en' => [
            'group' => 'homepage',
            'type' => 'textarea',
            'label_key' => 'settings_hero_subtitle_en',
            'default' => 'We offer a wide collection of wall, standing, and decorative shelves crafted with premium quality, complete with professional delivery and installation.',
            'required' => false,
            'max_length' => 500
        ],
        'hero_cta_primary_label_ar' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_hero_cta_primary_label_ar',
            'default' => 'استعرض المنتجات',
            'required' => false,
            'max_length' => 50
        ],
        'hero_cta_primary_label_en' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_hero_cta_primary_label_en',
            'default' => 'Browse Products',
            'required' => false,
            'max_length' => 50
        ],
        'hero_cta_secondary_label_ar' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_hero_cta_secondary_label_ar',
            'default' => 'تواصل عبر واتساب',
            'required' => false,
            'max_length' => 50
        ],
        'hero_cta_secondary_label_en' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_hero_cta_secondary_label_en',
            'default' => 'Contact via WhatsApp',
            'required' => false,
            'max_length' => 50
        ],
        'hero_image' => [
            'group' => 'homepage',
            'type' => 'file',
            'label_key' => 'settings_hero_image',
            'default' => 'assets/images/shelf1.png',
            'prefix' => 'hero-image',
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'seo_home_title_ar' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_seo_home_title_ar',
            'default' => 'عالم الرفوف - الرئيسية',
            'required' => false,
            'max_length' => 150
        ],
        'seo_home_title_en' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_seo_home_title_en',
            'default' => 'World of Shelves - Home',
            'required' => false,
            'max_length' => 150
        ],
        'seo_home_desc_ar' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_seo_home_desc_ar',
            'default' => 'نوفر لك تشكيلة واسعة من الرفوف بأعلى جودة.',
            'required' => false,
            'max_length' => 255
        ],
        'seo_home_desc_en' => [
            'group' => 'homepage',
            'type' => 'text',
            'label_key' => 'settings_seo_home_desc_en',
            'default' => 'We offer a wide collection of shelves crafted with premium quality.',
            'required' => false,
            'max_length' => 255
        ],

        // --- Sprint 3 Section Visibility & Sorting ---
        'homepage_show_hero' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_hero', 'default' => '1', 'required' => false
        ],
        'homepage_order_hero' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_hero', 'default' => '10', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_trust_strip' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_trust_strip', 'default' => '1', 'required' => false
        ],
        'homepage_order_trust_strip' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_trust_strip', 'default' => '20', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_trust_badges' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_trust_badges', 'default' => '1', 'required' => false
        ],
        'homepage_order_trust_badges' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_trust_badges', 'default' => '30', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_services' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_services', 'default' => '1', 'required' => false
        ],
        'homepage_order_services' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_services', 'default' => '40', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_areas' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_areas', 'default' => '1', 'required' => false
        ],
        'homepage_order_areas' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_areas', 'default' => '50', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_categories' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_categories', 'default' => '1', 'required' => false
        ],
        'homepage_order_categories' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_categories', 'default' => '60', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_products' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_products', 'default' => '1', 'required' => false
        ],
        'homepage_order_products' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_products', 'default' => '70', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_before_after' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_before_after', 'default' => '1', 'required' => false
        ],
        'homepage_order_before_after' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_before_after', 'default' => '80', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_projects' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_projects', 'default' => '1', 'required' => false
        ],
        'homepage_order_projects' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_projects', 'default' => '90', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_stats' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_stats', 'default' => '1', 'required' => false
        ],
        'homepage_order_stats' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_stats', 'default' => '100', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_testimonials' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_testimonials', 'default' => '1', 'required' => false
        ],
        'homepage_order_testimonials' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_testimonials', 'default' => '110', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_clients' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_clients', 'default' => '1', 'required' => false
        ],
        'homepage_order_clients' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_clients', 'default' => '120', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_how_we_work' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_how_we_work', 'default' => '1', 'required' => false
        ],
        'homepage_order_how_we_work' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_how_we_work', 'default' => '130', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_why_choose_us' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_why_choose_us', 'default' => '1', 'required' => false
        ],
        'homepage_order_why_choose_us' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_why_choose_us', 'default' => '140', 'required' => true, 'max_length' => 3
        ],
        'homepage_show_cta' => [
            'group' => 'homepage', 'type' => 'toggle', 'label_key' => 'settings_show_cta', 'default' => '1', 'required' => false
        ],
        'homepage_order_cta' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_order_cta', 'default' => '150', 'required' => true, 'max_length' => 3
        ],

        // --- Hero CTA Action Selectors ---
        'hero_cta_primary_action' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_hero_cta_primary_action', 'default' => 'products.php', 'options' => ['whatsapp' => 'واتساب / WhatsApp', 'products.php' => 'المنتجات / Products', 'services.php' => 'الخدمات / Services', 'projects.php' => 'المشاريع / Projects', 'contact.php' => 'الاتصال / Contact', 'about.php' => 'من نحن / About'], 'required' => true
        ],
        'hero_cta_secondary_action' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_hero_cta_secondary_action', 'default' => 'whatsapp', 'options' => ['whatsapp' => 'واتساب / WhatsApp', 'products.php' => 'المنتجات / Products', 'services.php' => 'الخدمات / Services', 'projects.php' => 'المشاريع / Projects', 'contact.php' => 'الاتصال / Contact', 'about.php' => 'من نحن / About'], 'required' => true
        ],

        // --- Section Headings (Bilingual) ---
        'homepage_services_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_services_title_ar', 'default' => 'خدماتنا', 'required' => false, 'max_length' => 255
        ],
        'homepage_services_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_services_title_en', 'default' => 'Our Services', 'required' => false, 'max_length' => 255
        ],
        'homepage_services_subtitle_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_services_subtitle_ar', 'default' => 'نوفر لك حلولاً متكاملة لتجهيز وتصميم مساحات التخزين', 'required' => false, 'max_length' => 255
        ],
        'homepage_services_subtitle_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_services_subtitle_en', 'default' => 'Complete shelving and storage planning solutions', 'required' => false, 'max_length' => 255
        ],

        'homepage_areas_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_areas_title_ar', 'default' => 'تطبيقات الأرفف والخدمات', 'required' => false, 'max_length' => 255
        ],
        'homepage_areas_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_areas_title_en', 'default' => 'Shelving Applications & Areas', 'required' => false, 'max_length' => 255
        ],
        'homepage_areas_subtitle_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_areas_subtitle_ar', 'default' => 'نصمم ونركب أنظمة الأرفف لمختلف المساحات والمنشآت', 'required' => false, 'max_length' => 255
        ],
        'homepage_areas_subtitle_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_areas_subtitle_en', 'default' => 'We design and install shelving systems for diverse facilities', 'required' => false, 'max_length' => 255
        ],

        'homepage_products_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_products_title_ar', 'default' => 'المنتجات المميزة', 'required' => false, 'max_length' => 255
        ],
        'homepage_products_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_products_title_en', 'default' => 'Featured Products', 'required' => false, 'max_length' => 255
        ],

        'homepage_before_after_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_before_after_title_ar', 'default' => 'قبل وبعد التركيب', 'required' => false, 'max_length' => 255
        ],
        'homepage_before_after_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_before_after_title_en', 'default' => 'Before & After Installation', 'required' => false, 'max_length' => 255
        ],

        'homepage_projects_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_projects_title_ar', 'default' => 'أحدث مشاريعنا', 'required' => false, 'max_length' => 255
        ],
        'homepage_projects_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_projects_title_en', 'default' => 'Our Featured Projects', 'required' => false, 'max_length' => 255
        ],
        'homepage_projects_subtitle_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_projects_subtitle_ar', 'default' => 'استعرض بعض أعمالنا الناجحة في تركيب وتثبيت الأرفف', 'required' => false, 'max_length' => 255
        ],
        'homepage_projects_subtitle_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_projects_subtitle_en', 'default' => 'Take a look at our shelving projects and client success stories', 'required' => false, 'max_length' => 255
        ],

        'homepage_testimonials_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_testimonials_title_ar', 'default' => 'آراء عملائنا', 'required' => false, 'max_length' => 255
        ],
        'homepage_testimonials_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_testimonials_title_en', 'default' => 'Client Testimonials', 'required' => false, 'max_length' => 255
        ],
        'homepage_testimonials_subtitle_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_testimonials_subtitle_ar', 'default' => 'ثقة عملائنا هي سر نجاحنا واستمرارنا في تقديم الأفضل', 'required' => false, 'max_length' => 255
        ],
        'homepage_testimonials_subtitle_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_testimonials_subtitle_en', 'default' => 'Our clients trust is the secret behind our continuous success', 'required' => false, 'max_length' => 255
        ],

        'homepage_clients_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_clients_title_ar', 'default' => 'شركاء النجاح', 'required' => false, 'max_length' => 255
        ],
        'homepage_clients_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_clients_title_en', 'default' => 'Success Partners', 'required' => false, 'max_length' => 255
        ],

        'homepage_how_we_work_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_how_we_work_title_ar', 'default' => 'خطوات العمل', 'required' => false, 'max_length' => 255
        ],
        'homepage_how_we_work_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_how_we_work_title_en', 'default' => 'How We Work', 'required' => false, 'max_length' => 255
        ],

        'homepage_why_us_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_why_us_title_ar', 'default' => 'لماذا تختارنا؟', 'required' => false, 'max_length' => 255
        ],
        'homepage_why_us_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_why_us_title_en', 'default' => 'Why Choose Us?', 'required' => false, 'max_length' => 255
        ],

        // --- Module Integrations (Limits & Sources) ---
        'homepage_products_limit' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_products_limit', 'default' => '3', 'required' => true, 'max_length' => 2
        ],
        'homepage_products_source' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_products_source', 'default' => 'featured', 'options' => ['featured' => 'المنتجات المميزة / Featured', 'new' => 'المنتجات الجديدة / New', 'requested' => 'الأكثر طلباً / Most Requested', 'latest' => 'أحدث المنتجات المضافة / Latest'], 'required' => true
        ],
        'homepage_projects_limit' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_projects_limit', 'default' => '3', 'required' => true, 'max_length' => 2
        ],
        'homepage_projects_source' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_projects_source', 'default' => 'featured', 'options' => ['featured' => 'المشاريع المميزة / Featured', 'latest' => 'أحدث المشاريع / Latest', 'featured_latest' => 'المميزة أولاً ثم الأحدث / Featured then Latest'], 'required' => true
        ],
        'homepage_testimonials_limit' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_testimonials_limit', 'default' => '6', 'required' => true, 'max_length' => 2
        ],
        'homepage_testimonials_source' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_testimonials_source', 'default' => 'featured', 'options' => ['featured' => 'المميزة فقط / Featured', 'active' => 'جميع الآراء النشطة / Active', 'latest_active' => 'أحدث الآراء النشطة / Latest Active'], 'required' => true
        ],

        // --- 4 Statistics Cards (Homepage Prefixed) ---
        // Stat 1
        'homepage_stat_1_value' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat1_value', 'default' => '450', 'required' => true, 'max_length' => 10
        ],
        'homepage_stat_1_suffix' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat1_suffix', 'default' => '+', 'required' => false, 'max_length' => 10
        ],
        'homepage_stat_1_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat1_title_ar', 'default' => 'المشاريع المنجزة', 'required' => true, 'max_length' => 150
        ],
        'homepage_stat_1_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat1_title_en', 'default' => 'Completed Projects', 'required' => true, 'max_length' => 150
        ],
        'homepage_stat_1_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat1_desc_ar', 'default' => 'مستودعات ومحلات تجارية تم تجهيزها بالكامل', 'required' => false, 'max_length' => 255
        ],
        'homepage_stat_1_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat1_desc_en', 'default' => 'Fully equipped warehouses and commercial shops', 'required' => false, 'max_length' => 255
        ],
        // Stat 2
        'homepage_stat_2_value' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat2_value', 'default' => '12000', 'required' => true, 'max_length' => 10
        ],
        'homepage_stat_2_suffix' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat2_suffix', 'default' => '+', 'required' => false, 'max_length' => 10
        ],
        'homepage_stat_2_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat2_title_ar', 'default' => 'أرفف تم تركيبها', 'required' => true, 'max_length' => 150
        ],
        'homepage_stat_2_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat2_title_en', 'default' => 'Shelves Installed', 'required' => true, 'max_length' => 150
        ],
        'homepage_stat_2_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat2_desc_ar', 'default' => 'بأعلى معايير الدقة والأمان وتثبيت مضاد للسقوط', 'required' => false, 'max_length' => 255
        ],
        'homepage_stat_2_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat2_desc_en', 'default' => 'With the highest standards of safety and anti-fall anchoring', 'required' => false, 'max_length' => 255
        ],
        // Stat 3
        'homepage_stat_3_value' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat3_value', 'default' => '380', 'required' => true, 'max_length' => 10
        ],
        'homepage_stat_3_suffix' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat3_suffix', 'default' => '+', 'required' => false, 'max_length' => 10
        ],
        'homepage_stat_3_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat3_title_ar', 'default' => 'عملائنا السعداء', 'required' => true, 'max_length' => 150
        ],
        'homepage_stat_3_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat3_title_en', 'default' => 'Happy Clients', 'required' => true, 'max_length' => 150
        ],
        'homepage_stat_3_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat3_desc_ar', 'default' => 'من الشركات والمؤسسات والأفراد في مختلف المناطق', 'required' => false, 'max_length' => 255
        ],
        'homepage_stat_3_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat3_desc_en', 'default' => 'From corporate groups, local businesses, and homeowners', 'required' => false, 'max_length' => 255
        ],
        // Stat 4
        'homepage_stat_4_value' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat4_value', 'default' => '10', 'required' => true, 'max_length' => 10
        ],
        'homepage_stat_4_suffix' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat4_suffix', 'default' => '+', 'required' => false, 'max_length' => 10
        ],
        'homepage_stat_4_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat4_title_ar', 'default' => 'سنوات الخبرة', 'required' => true, 'max_length' => 150
        ],
        'homepage_stat_4_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat4_title_en', 'default' => 'Years of Experience', 'required' => true, 'max_length' => 150
        ],
        'homepage_stat_4_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat4_desc_ar', 'default' => 'في تقديم وتصميم وتركيب وحلول مساحات التخزين', 'required' => false, 'max_length' => 255
        ],
        'homepage_stat_4_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_stat4_desc_en', 'default' => 'In delivering, designing, and professional shelving services', 'required' => false, 'max_length' => 255
        ],

        // --- 6 Areas We Serve Cards (Homepage Prefixed) ---
        // Area 1
        'homepage_area_1_icon' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_area1_icon', 'default' => 'warehouse', 'options' => ['warehouse' => 'مستودع / Warehouse', 'shopping-bag' => 'حقيبة تسوق / Shopping Bag', 'archive' => 'أرشيف / Archive', 'factory' => 'مصنع / Factory', 'cog' => 'ترس / Workshop', 'home' => 'منزل / Home', 'layers' => 'طبقات / Layers', 'package' => 'صندوق / Package', 'wrench' => 'مفتاح / Wrench'], 'required' => true
        ],
        'homepage_area_1_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area1_title_ar', 'default' => 'مستودعات تخزين', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_1_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area1_title_en', 'default' => 'Warehouse Storage', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_1_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area1_desc_ar', 'default' => 'تجهيز كامل للمستودعات الكبيرة والمتوسطة بأرفف تتحمل الأوزان الثقيلة.', 'required' => false, 'max_length' => 255
        ],
        'homepage_area_1_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area1_desc_en', 'default' => 'Equipping large & medium warehouses with heavy-duty racking systems.', 'required' => false, 'max_length' => 255
        ],
        // Area 2
        'homepage_area_2_icon' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_area2_icon', 'default' => 'shopping-bag', 'options' => ['warehouse' => 'مستودع / Warehouse', 'shopping-bag' => 'حقيبة تسوق / Shopping Bag', 'archive' => 'أرشيف / Archive', 'factory' => 'مصنع / Factory', 'cog' => 'ترس / Workshop', 'home' => 'منزل / Home', 'layers' => 'طبقات / Layers', 'package' => 'صندوق / Package', 'wrench' => 'مفتاح / Wrench'], 'required' => true
        ],
        'homepage_area_2_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area2_title_ar', 'default' => 'المحلات التجارية', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_2_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area2_title_en', 'default' => 'Retail Stores', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_2_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area2_desc_ar', 'default' => 'أرفف عرض عصرية لتقديم المنتجات والسلع للمستهلكين بأسلوب جذاب.', 'required' => false, 'max_length' => 255
        ],
        'homepage_area_2_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area2_desc_en', 'default' => 'Modern display shelving to showcase goods and attract customers.', 'required' => false, 'max_length' => 255
        ],
        // Area 3
        'homepage_area_3_icon' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_area3_icon', 'default' => 'archive', 'options' => ['warehouse' => 'مستودع / Warehouse', 'shopping-bag' => 'حقيبة تسوق / Shopping Bag', 'archive' => 'أرشيف / Archive', 'factory' => 'مصنع / Factory', 'cog' => 'ترس / Workshop', 'home' => 'منزل / Home', 'layers' => 'طبقات / Layers', 'package' => 'صندوق / Package', 'wrench' => 'مفتاح / Wrench'], 'required' => true
        ],
        'homepage_area_3_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area3_title_ar', 'default' => 'أرشيف الشركات', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_3_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area3_title_en', 'default' => 'Corporate Archives', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_3_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area3_desc_ar', 'default' => 'تنظيم وحفظ المستندات والملفات الإدارية بأرفف متينة تسهل الوصول إليها.', 'required' => false, 'max_length' => 255
        ],
        'homepage_area_3_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area3_desc_en', 'default' => 'Organizing documents and files with durable, accessible shelving.', 'required' => false, 'max_length' => 255
        ],
        // Area 4
        'homepage_area_4_icon' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_area4_icon', 'default' => 'factory', 'options' => ['warehouse' => 'مستودع / Warehouse', 'shopping-bag' => 'حقيبة تسوق / Shopping Bag', 'archive' => 'أرشيف / Archive', 'factory' => 'مصنع / Factory', 'cog' => 'ترس / Workshop', 'home' => 'منزل / Home', 'layers' => 'طبقات / Layers', 'package' => 'صندوق / Package', 'wrench' => 'مفتاح / Wrench'], 'required' => true
        ],
        'homepage_area_4_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area4_title_ar', 'default' => 'المنشآت الصناعية', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_4_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area4_title_en', 'default' => 'Industrial Facilities', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_4_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area4_desc_ar', 'default' => 'أرفف تخزين المواد الخام وقطع الغيار والمنتجات النهائية المصنوعة.', 'required' => false, 'max_length' => 255
        ],
        'homepage_area_4_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area4_desc_en', 'default' => 'Storage for raw materials, heavy spare parts, and finished products.', 'required' => false, 'max_length' => 255
        ],
        // Area 5
        'homepage_area_5_icon' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_area5_icon', 'default' => 'cog', 'options' => ['warehouse' => 'مستودع / Warehouse', 'shopping-bag' => 'حقيبة تسوق / Shopping Bag', 'archive' => 'أرشيف / Archive', 'factory' => 'مصنع / Factory', 'cog' => 'ترس / Workshop', 'home' => 'منزل / Home', 'layers' => 'طبقات / Layers', 'package' => 'صندوق / Package', 'wrench' => 'مفتاح / Wrench'], 'required' => true
        ],
        'homepage_area_5_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area5_title_ar', 'default' => 'ورش العمل والمهن', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_5_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area5_title_en', 'default' => 'Workshops & Trades', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_5_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area5_desc_ar', 'default' => 'تخزين وتنظيم المعدات والأدوات اليدوية والكهربائية بشكل يضمن سلامتها.', 'required' => false, 'max_length' => 255
        ],
        'homepage_area_5_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area5_desc_en', 'default' => 'Safe, organized shelving systems for machinery, tools, and hardware.', 'required' => false, 'max_length' => 255
        ],
        // Area 6
        'homepage_area_6_icon' => [
            'group' => 'homepage', 'type' => 'select', 'label_key' => 'settings_area6_icon', 'default' => 'home', 'options' => ['warehouse' => 'مستودع / Warehouse', 'shopping-bag' => 'حقيبة تسوق / Shopping Bag', 'archive' => 'أرشيف / Archive', 'factory' => 'مصنع / Factory', 'cog' => 'ترس / Workshop', 'home' => 'منزل / Home', 'layers' => 'طبقات / Layers', 'package' => 'صندوق / Package', 'wrench' => 'مفتاح / Wrench'], 'required' => true
        ],
        'homepage_area_6_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area6_title_ar', 'default' => 'التخزين المنزلي/المستودع', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_6_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area6_title_en', 'default' => 'Home & Pantry Storage', 'required' => true, 'max_length' => 150
        ],
        'homepage_area_6_desc_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area6_desc_ar', 'default' => 'ترتيب المؤونة والأغراض المنزلية الزائدة بأرفف خفيفة وأنيقة وسهلة التنظيف.', 'required' => false, 'max_length' => 255
        ],
        'homepage_area_6_desc_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_area6_desc_en', 'default' => 'Pantry organizers and light residential shelves for optimal space optimization.', 'required' => false, 'max_length' => 255
        ],

        // --- Homepage CTA Settings ---
        'cta_home_title_ar' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_cta_home_title_ar', 'default' => 'هل أنت جاهز لتنظيم مستودعك أو محلك التجاري؟', 'required' => false, 'max_length' => 255
        ],
        'cta_home_title_en' => [
            'group' => 'homepage', 'type' => 'text', 'label_key' => 'settings_cta_home_title_en', 'default' => 'Ready to Organize Your Warehouse or Store?', 'required' => false, 'max_length' => 255
        ],
        'cta_home_desc_ar' => [
            'group' => 'homepage', 'type' => 'textarea', 'label_key' => 'settings_cta_home_desc_ar', 'default' => 'تواصل معنا الآن للحصول على معاينة مجانية للموقع وتخطيط كروكي دقيق للمساحة مع عرض سعر منافس لتوريد وتركيب الأرفف الحديدية.', 'required' => false, 'max_length' => 1000
        ],
        'cta_home_desc_en' => [
            'group' => 'homepage', 'type' => 'textarea', 'label_key' => 'settings_cta_home_desc_en', 'default' => 'Contact us today for a free site inspection and space planning design, complete with a competitive quote for our iron racking systems.', 'required' => false, 'max_length' => 1000
        ],
        'cta_home_bg_image' => [
            'group' => 'homepage', 'type' => 'file', 'label_key' => 'settings_cta_home_bg_image', 'default' => '', 'prefix' => 'cta-home-bg', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        'about_us_title_ar' => [
            'group' => 'about_us',
            'type' => 'text',
            'label_key' => 'settings_about_title_ar',
            'default' => 'من نحن',
            'required' => true,
            'max_length' => 150
        ],
        'about_us_title_en' => [
            'group' => 'about_us',
            'type' => 'text',
            'label_key' => 'settings_about_title_en',
            'default' => 'About Us',
            'required' => true,
            'max_length' => 150
        ],
        'about_us_desc_ar' => [
            'group' => 'about_us',
            'type' => 'textarea',
            'label_key' => 'settings_about_desc_ar',
            'default' => '',
            'required' => false,
            'max_length' => 3000
        ],
        'about_us_desc_en' => [
            'group' => 'about_us',
            'type' => 'textarea',
            'label_key' => 'settings_about_desc_en',
            'default' => '',
            'required' => false,
            'max_length' => 3000
        ],
        'about_mission_title_ar' => [
            'group' => 'about_us',
            'type' => 'text',
            'label_key' => 'settings_mission_title_ar',
            'default' => 'رسالتنا',
            'required' => false,
            'max_length' => 150
        ],
        'about_mission_title_en' => [
            'group' => 'about_us',
            'type' => 'text',
            'label_key' => 'settings_mission_title_en',
            'default' => 'Our Mission',
            'required' => false,
            'max_length' => 150
        ],
        'about_mission_desc_ar' => [
            'group' => 'about_us',
            'type' => 'textarea',
            'label_key' => 'settings_mission_desc_ar',
            'default' => 'تقديم حلول تخزين وعرض مبتكرة تجمع بين القوة والجمال البصري.',
            'required' => false,
            'max_length' => 500
        ],
        'about_mission_desc_en' => [
            'group' => 'about_us',
            'type' => 'textarea',
            'label_key' => 'settings_mission_desc_en',
            'default' => 'To deliver innovative storage and display solutions that combine durability and visual appeal.',
            'required' => false,
            'max_length' => 500
        ],
        'about_vision_title_ar' => [
            'group' => 'about_us',
            'type' => 'text',
            'label_key' => 'settings_vision_title_ar',
            'default' => 'رؤيتنا',
            'required' => false,
            'max_length' => 150
        ],
        'about_vision_title_en' => [
            'group' => 'about_us',
            'type' => 'text',
            'label_key' => 'settings_vision_title_en',
            'default' => 'Our Vision',
            'required' => false,
            'max_length' => 150
        ],
        'about_vision_desc_ar' => [
            'group' => 'about_us',
            'type' => 'textarea',
            'label_key' => 'settings_vision_desc_ar',
            'default' => 'أن نكون الوجهة الأولى في المملكة لتوريد وتركيب الرفوف المنزلية والصناعية.',
            'required' => false,
            'max_length' => 500
        ],
        'about_vision_desc_en' => [
            'group' => 'about_us',
            'type' => 'textarea',
            'label_key' => 'settings_vision_desc_en',
            'default' => 'To be the premier destination in the Kingdom for supplying and installing residential and industrial shelving.',
            'required' => false,
            'max_length' => 500
        ],
        'about_image' => [
            'group' => 'about_us',
            'type' => 'file',
            'label_key' => 'settings_about_image',
            'default' => 'assets/images/shelf4.png',
            'prefix' => 'about-image',
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        // --- Sprint 5: About Section Visibility & Ordering ---
        'about_show_banner' => [
            'group' => 'about_us', 'type' => 'toggle', 'label_key' => 'settings_about_show_banner', 'default' => '1', 'required' => false
        ],
        'about_order_banner' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_order_banner', 'default' => '10', 'required' => true, 'max_length' => 3
        ],
        'about_show_story' => [
            'group' => 'about_us', 'type' => 'toggle', 'label_key' => 'settings_about_show_story', 'default' => '1', 'required' => false
        ],
        'about_order_story' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_order_story', 'default' => '20', 'required' => true, 'max_length' => 3
        ],
        'about_show_stats' => [
            'group' => 'about_us', 'type' => 'toggle', 'label_key' => 'settings_about_show_stats', 'default' => '1', 'required' => false
        ],
        'about_order_stats' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_order_stats', 'default' => '30', 'required' => true, 'max_length' => 3
        ],
        'about_show_expertise' => [
            'group' => 'about_us', 'type' => 'toggle', 'label_key' => 'settings_about_show_expertise', 'default' => '1', 'required' => false
        ],
        'about_order_expertise' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_order_expertise', 'default' => '40', 'required' => true, 'max_length' => 3
        ],
        'about_show_values' => [
            'group' => 'about_us', 'type' => 'toggle', 'label_key' => 'settings_about_show_values', 'default' => '1', 'required' => false
        ],
        'about_order_values' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_order_values', 'default' => '50', 'required' => true, 'max_length' => 3
        ],
        'about_show_timeline' => [
            'group' => 'about_us', 'type' => 'toggle', 'label_key' => 'settings_about_show_timeline', 'default' => '1', 'required' => false
        ],
        'about_order_timeline' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_order_timeline', 'default' => '60', 'required' => true, 'max_length' => 3
        ],
        'about_show_cta' => [
            'group' => 'about_us', 'type' => 'toggle', 'label_key' => 'settings_about_show_cta', 'default' => '1', 'required' => false
        ],
        'about_order_cta' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_order_cta', 'default' => '70', 'required' => true, 'max_length' => 3
        ],

        // --- Sprint 5: About Banner ---
        'about_banner_subtitle_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_banner_subtitle_ar', 'default' => 'منصتك المتكاملة لتصميم وتوصيل وتركيب أرقى أنواع الرفوف', 'required' => false, 'max_length' => 255
        ],
        'about_banner_subtitle_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_banner_subtitle_en', 'default' => 'Your all-in-one platform to design, deliver, and install premium shelves', 'required' => false, 'max_length' => 255
        ],

        // --- Sprint 5: About Story ---
        'about_story_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_story_title_ar', 'default' => 'قصة عالم الرفوف', 'required' => false, 'max_length' => 255
        ],
        'about_story_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_story_title_en', 'default' => 'The World of Shelves Story', 'required' => false, 'max_length' => 255
        ],
        'about_story_desc_ar' => [
            'group' => 'about_us', 'type' => 'textarea', 'label_key' => 'settings_about_story_desc_ar', 'default' => 'بدأنا رحلتنا من شغف توفير مساحات تخزين آمنة وفعالة للشركات والمستودعات والمحلات. على مدار السنوات، قمنا بتنفيذ مئات المشاريع وتوريد وتركيب أرفف حديدية تتميز بالصلابة والتحمل. نحن لا نبيع الأرفف فحسب، بل نصمم حلول تخزين ذكية تعيد إحياء المساحات الضائعة.', 'required' => false, 'max_length' => 3000
        ],
        'about_story_desc_en' => [
            'group' => 'about_us', 'type' => 'textarea', 'label_key' => 'settings_about_story_desc_en', 'default' => 'We started from a passion for providing safe and efficient storage spaces for companies, warehouses, and shops. Over the years, we have executed hundreds of projects supplying and installing iron shelves known for their strength and durability. We don\'t just sell shelves — we design smart storage solutions that revive wasted spaces.', 'required' => false, 'max_length' => 3000
        ],

        // --- Sprint 5: About Expertise ---
        'about_exp_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_exp_title_ar', 'default' => 'خبرتنا المتخصصة', 'required' => false, 'max_length' => 255
        ],
        'about_exp_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_exp_title_en', 'default' => 'Our Specialized Expertise', 'required' => false, 'max_length' => 255
        ],
        'about_exp_desc_ar' => [
            'group' => 'about_us', 'type' => 'textarea', 'label_key' => 'settings_about_exp_desc_ar', 'default' => 'نتميز بتقديم خدمات شاملة تغطي: بيع الأرفف الحديدية الجديدة بمختلف مقاساتها، تصميم وتوريد حلول التخزين للمستودعات الكبرى، تفكيك ونقل وإعادة تركيب الأرفف عند الانتقال، وصيانة وتعديل الأرفف القائمة، بالإضافة لشراء الأرفف الحديدية المستعملة بتقديرات مالية عادلة مع التكفل بالفك والنقل الفوري.', 'required' => false, 'max_length' => 3000
        ],
        'about_exp_desc_en' => [
            'group' => 'about_us', 'type' => 'textarea', 'label_key' => 'settings_about_exp_desc_en', 'default' => 'We specialize in comprehensive services covering: selling new iron shelves of all sizes, designing and supplying storage solutions for large warehouses, dismantling and relocating shelves, maintenance and modification of existing shelving, and purchasing used iron shelves with fair financial assessments including free dismantling and transport.', 'required' => false, 'max_length' => 3000
        ],

        // --- Sprint 5: About Values (3 fixed cards, no icon settings) ---
        'about_values_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_values_title_ar', 'default' => 'قيمنا', 'required' => false, 'max_length' => 255
        ],
        'about_values_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_values_title_en', 'default' => 'Our Values', 'required' => false, 'max_length' => 255
        ],
        'about_value_1_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value1_title_ar', 'default' => 'الصلابة والأمان', 'required' => false, 'max_length' => 255
        ],
        'about_value_1_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value1_title_en', 'default' => 'Strength & Safety', 'required' => false, 'max_length' => 255
        ],
        'about_value_1_desc_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value1_desc_ar', 'default' => 'نضمن جودة الحديد المستخدم وقدرته على تحمل الأوزان الثقيلة بأمان مطلق.', 'required' => false, 'max_length' => 500
        ],
        'about_value_1_desc_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value1_desc_en', 'default' => 'We guarantee the quality of steel used and its ability to safely support heavy loads.', 'required' => false, 'max_length' => 500
        ],
        'about_value_2_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value2_title_ar', 'default' => 'السرعة والدقة', 'required' => false, 'max_length' => 255
        ],
        'about_value_2_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value2_title_en', 'default' => 'Speed & Precision', 'required' => false, 'max_length' => 255
        ],
        'about_value_2_desc_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value2_desc_ar', 'default' => 'فريقنا ملتزم بإنهاء المعاينة والتركيب في أوقات قياسية وبدقة متناهية.', 'required' => false, 'max_length' => 500
        ],
        'about_value_2_desc_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value2_desc_en', 'default' => 'Our team is committed to completing inspections and installations in record time with extreme precision.', 'required' => false, 'max_length' => 500
        ],
        'about_value_3_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value3_title_ar', 'default' => 'المرونة والمصداقية', 'required' => false, 'max_length' => 255
        ],
        'about_value_3_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value3_title_en', 'default' => 'Flexibility & Credibility', 'required' => false, 'max_length' => 255
        ],
        'about_value_3_desc_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value3_desc_ar', 'default' => 'نقدم أسعاراً تنافسية ونشتري الأرفف المستعملة بأسعار عادلة ومقنعة للجميع.', 'required' => false, 'max_length' => 500
        ],
        'about_value_3_desc_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_value3_desc_en', 'default' => 'We offer competitive prices and buy used shelves at fair and convincing prices for everyone.', 'required' => false, 'max_length' => 500
        ],

        // --- Sprint 5: About Timeline (4 fixed milestones, no step fields) ---
        'about_timeline_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_timeline_title_ar', 'default' => 'مسيرة نمو نموذجية', 'required' => false, 'max_length' => 255
        ],
        'about_timeline_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_timeline_title_en', 'default' => 'A Model Growth Journey', 'required' => false, 'max_length' => 255
        ],
        'about_timeline_subtitle_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_timeline_subtitle_ar', 'default' => 'محطات ومراحل بارزة شكلت ريادتنا في تقديم حلول التخزين.', 'required' => false, 'max_length' => 500
        ],
        'about_timeline_subtitle_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_timeline_subtitle_en', 'default' => 'Key milestones that shaped our leadership in providing storage solutions.', 'required' => false, 'max_length' => 500
        ],
        'about_milestone_1_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms1_title_ar', 'default' => 'الانطلاق والبداية', 'required' => false, 'max_length' => 255
        ],
        'about_milestone_1_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms1_title_en', 'default' => 'Launch & Foundation', 'required' => false, 'max_length' => 255
        ],
        'about_milestone_1_desc_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms1_desc_ar', 'default' => 'تأسيس نواة الشركة وتوريد الرفوف الخفيفة.', 'required' => false, 'max_length' => 500
        ],
        'about_milestone_1_desc_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms1_desc_en', 'default' => 'Establishing the company core and supplying light-duty shelves.', 'required' => false, 'max_length' => 500
        ],
        'about_milestone_2_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms2_title_ar', 'default' => 'التحول للمستودعات الكبيرة', 'required' => false, 'max_length' => 255
        ],
        'about_milestone_2_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms2_title_en', 'default' => 'Shifting to Large Warehouses', 'required' => false, 'max_length' => 255
        ],
        'about_milestone_2_desc_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms2_desc_ar', 'default' => 'دخول مجال أرفف المستودعات وتخزين المنصات الثقيلة.', 'required' => false, 'max_length' => 500
        ],
        'about_milestone_2_desc_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms2_desc_en', 'default' => 'Entering warehouse racking and heavy pallet storage.', 'required' => false, 'max_length' => 500
        ],
        'about_milestone_3_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms3_title_ar', 'default' => 'تغطية المدن الرئيسية', 'required' => false, 'max_length' => 255
        ],
        'about_milestone_3_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms3_title_en', 'default' => 'Covering Major Cities', 'required' => false, 'max_length' => 255
        ],
        'about_milestone_3_desc_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms3_desc_ar', 'default' => 'توسيع خدمات فك ونقل أرفف المحلات والمخازن.', 'required' => false, 'max_length' => 500
        ],
        'about_milestone_3_desc_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms3_desc_en', 'default' => 'Expanding dismantling and relocation services across stores and warehouses.', 'required' => false, 'max_length' => 500
        ],
        'about_milestone_4_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms4_title_ar', 'default' => 'الحلول الذكية الحديثة', 'required' => false, 'max_length' => 255
        ],
        'about_milestone_4_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms4_title_en', 'default' => 'Modern Smart Solutions', 'required' => false, 'max_length' => 255
        ],
        'about_milestone_4_desc_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms4_desc_ar', 'default' => 'إطلاق استشارات التخطيط والمساحات مجاناً وتصفية المستعمل.', 'required' => false, 'max_length' => 500
        ],
        'about_milestone_4_desc_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_ms4_desc_en', 'default' => 'Launching free space planning consultations and used shelves clearance.', 'required' => false, 'max_length' => 500
        ],

        // --- Sprint 5: About CTA ---
        'about_cta_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_cta_title_ar', 'default' => 'ثق بشريك التخزين الأول في المملكة العربية السعودية', 'required' => false, 'max_length' => 255
        ],
        'about_cta_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_about_cta_title_en', 'default' => 'Trust the Leading Storage Partner in Saudi Arabia', 'required' => false, 'max_length' => 255
        ],
        'about_cta_desc_ar' => [
            'group' => 'about_us', 'type' => 'textarea', 'label_key' => 'settings_about_cta_desc_ar', 'default' => 'نجمع بين جودة التصنيع واحترافية الخدمة لنقدم لك أرففاً حديدية تعيش طويلاً وتدعم نمو أعمالك.', 'required' => false, 'max_length' => 1000
        ],
        'about_cta_desc_en' => [
            'group' => 'about_us', 'type' => 'textarea', 'label_key' => 'settings_about_cta_desc_en', 'default' => 'We combine manufacturing quality with service professionalism to deliver iron shelves that last and support your business growth.', 'required' => false, 'max_length' => 1000
        ],
        'about_cta_bg_image' => [
            'group' => 'about_us', 'type' => 'file', 'label_key' => 'settings_about_cta_bg_image', 'default' => '', 'prefix' => 'about-cta-bg', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'about_cta_action' => [
            'group' => 'about_us', 'type' => 'select', 'label_key' => 'settings_about_cta_action', 'default' => 'whatsapp', 'options' => ['whatsapp' => 'واتساب / WhatsApp', 'products' => 'المنتجات / Products', 'services' => 'الخدمات / Services', 'projects' => 'المشاريع / Projects', 'contact' => 'الاتصال / Contact', 'none' => 'بلا / None'], 'required' => true
        ],

        // --- Sprint 5: About SEO ---
        'seo_about_title_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_seo_about_title_ar', 'default' => '', 'required' => false, 'max_length' => 150
        ],
        'seo_about_title_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_seo_about_title_en', 'default' => '', 'required' => false, 'max_length' => 150
        ],
        'seo_about_desc_ar' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_seo_about_desc_ar', 'default' => '', 'required' => false, 'max_length' => 255
        ],
        'seo_about_desc_en' => [
            'group' => 'about_us', 'type' => 'text', 'label_key' => 'settings_seo_about_desc_en', 'default' => '', 'required' => false, 'max_length' => 255
        ],
        'seo_about_og_image' => [
            'group' => 'about_us', 'type' => 'file', 'label_key' => 'settings_seo_about_og_image', 'default' => '', 'prefix' => 'seo-about-og', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        // -------------------------------------------------------------
        // 7. SEO GROUP
        // -------------------------------------------------------------
        'seo_title_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_title_ar',
            'default' => 'عالم الرفوف',
            'required' => false,
            'max_length' => 150
        ],
        'seo_title_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_title_en',
            'default' => 'World of Shelves',
            'required' => false,
            'max_length' => 150
        ],
        'seo_description_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_desc_ar',
            'default' => 'منصتك المتكاملة لتصميم وتوصيل وتركيب أرقى أنواع الرفوف',
            'required' => false,
            'max_length' => 255
        ],
        'seo_description_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_desc_en',
            'default' => 'Your all-in-one platform to design, deliver, and install premium shelves',
            'required' => false,
            'max_length' => 255
        ],
        'seo_keywords' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_keywords',
            'default' => 'رفوف, رف جداري, خزانة كتب, رفوف مستودعات, shelves, bookshelves, warehouse racks',
            'required' => false,
            'max_length' => 500
        ],
        'site_url' => [
            'group' => 'seo',
            'type' => 'url',
            'label_key' => 'settings_site_url',
            'default' => 'http://localhost/World_of_Shelves_websit',
            'required' => true,
            'max_length' => 255
        ],
        'seo_google_console' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_google_console',
            'default' => '',
            'required' => false,
            'max_length' => 255
        ],
        'seo_home_title_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_home_title_ar',
            'default' => 'عالم الرفوف | تصميم وتوصيل وتركيب رفوف التخزين والمستودعات',
            'required' => false,
            'max_length' => 255
        ],
        'seo_home_title_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_home_title_en',
            'default' => 'World of Shelves | Storage & Warehouse Racking Solutions',
            'required' => false,
            'max_length' => 255
        ],
        'seo_home_desc_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_home_desc_ar',
            'default' => 'مؤسسة عالم الرفوف التجارية لبيع وتركيب أرفف المستودعات، الأرفف الحديدية، أرفف المحلات ورفوف التخزين المنزلي في السعودية.',
            'required' => false,
            'max_length' => 500
        ],
        'seo_home_desc_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_home_desc_en',
            'default' => 'World of Shelves Trading Est. offers high-quality warehouse racking, industrial shelving, shop shelving, and home storage racks in Saudi Arabia.',
            'required' => false,
            'max_length' => 500
        ],
        'seo_products_title_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_products_title_ar',
            'default' => 'منتجاتنا من أنظمة الرفوف وحلول التخزين | عالم الرفوف',
            'required' => false,
            'max_length' => 255
        ],
        'seo_products_title_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_products_title_en',
            'default' => 'Our Shelving Products & Storage Systems | World of Shelves',
            'required' => false,
            'max_length' => 255
        ],
        'seo_products_desc_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_products_desc_ar',
            'default' => 'تصفح تشكيلة واسعة من أرفف المستودعات، أرفف التخزين الحديدية، أرفف السوبرماركت والمحلات والرفوف المنزلية بأفضل الأسعار.',
            'required' => false,
            'max_length' => 500
        ],
        'seo_products_desc_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_products_desc_en',
            'default' => 'Browse a wide range of warehouse racking, industrial storage shelving, supermarket display racks, and home shelving at best prices.',
            'required' => false,
            'max_length' => 500
        ],
        'seo_services_title_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_services_title_ar',
            'default' => 'خدمات تصميم وتوصيل وتركيب الرفوف | عالم الرفوف',
            'required' => false,
            'max_length' => 255
        ],
        'seo_services_title_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_services_title_en',
            'default' => 'Shelving Design, Delivery & Installation Services | World of Shelves',
            'required' => false,
            'max_length' => 255
        ],
        'seo_services_desc_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_services_desc_ar',
            'default' => 'نقدم خدمات متكاملة تشمل التصميم الثلاثي الأبعاد، توصيل، تركيب وفك أنظمة الرفوف والمستودعات بأيدي مهندسين وفنيين مختصين.',
            'required' => false,
            'max_length' => 500
        ],
        'seo_services_desc_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_services_desc_en',
            'default' => 'We provide professional services including 3D design layout, delivery, installation, and relocation of warehouse shelving systems.',
            'required' => false,
            'max_length' => 500
        ],
        'seo_projects_title_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_projects_title_ar',
            'default' => 'معرض المشاريع والأعمال المنفذة | عالم الرفوف',
            'required' => false,
            'max_length' => 255
        ],
        'seo_projects_title_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_projects_title_en',
            'default' => 'Completed Storage Projects Portfolio | World of Shelves',
            'required' => false,
            'max_length' => 255
        ],
        'seo_projects_desc_ar' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_projects_desc_ar',
            'default' => 'استعرض أبرز مشاريع تركيب أرفف المستودعات والتخزين التي قمنا بتنفيذها لكبرى الشركات في السعودية.',
            'required' => false,
            'max_length' => 500
        ],
        'seo_projects_desc_en' => [
            'group' => 'seo',
            'type' => 'text',
            'label_key' => 'settings_seo_projects_desc_en',
            'default' => 'Explore our portfolio of completed warehouse racking and storage installation projects across Saudi Arabia.',
            'required' => false,
            'max_length' => 500
        ],

        // -------------------------------------------------------------
        // 8. ANALYTICS GROUP
        // -------------------------------------------------------------
        'analytics_google_ga4' => [
            'group' => 'analytics',
            'type' => 'text',
            'label_key' => 'settings_analytics_ga4',
            'default' => '',
            'required' => false,
            'regex' => '/^G-[A-Z0-9]{10}$/',
            'max_length' => 20
        ],
        'analytics_google_gtm' => [
            'group' => 'analytics',
            'type' => 'text',
            'label_key' => 'settings_analytics_gtm',
            'default' => '',
            'required' => false,
            'regex' => '/^GTM-[A-Z0-9]+$/',
            'max_length' => 20
        ],
        'analytics_meta_pixel' => [
            'group' => 'analytics',
            'type' => 'text',
            'label_key' => 'settings_analytics_pixel',
            'default' => '',
            'required' => false,
            'regex' => '/^[0-9]+$/',
            'max_length' => 30
        ],

        // -------------------------------------------------------------
        // 9. ADVANCED GROUP
        // -------------------------------------------------------------
        'maintenance_mode' => [
            'group' => 'advanced',
            'type' => 'toggle',
            'label_key' => 'settings_maintenance_mode',
            'default' => '0',
            'required' => false
        ],
        'maintenance_message_ar' => [
            'group' => 'advanced',
            'type' => 'textarea',
            'label_key' => 'settings_maintenance_msg_ar',
            'default' => 'الموقع تحت الصيانة حالياً. سنعود قريباً!',
            'required' => false,
            'max_length' => 500
        ],
        'maintenance_message_en' => [
            'group' => 'advanced',
            'type' => 'textarea',
            'label_key' => 'settings_maintenance_msg_en',
            'default' => 'Our site is currently undergoing maintenance. We will be back soon!',
            'required' => false,
            'max_length' => 500
        ],
        'enable_dark_mode' => [
            'group' => 'advanced',
            'type' => 'toggle',
            'label_key' => 'settings_enable_dark_mode',
            'default' => '1',
            'required' => false
        ],

        // -------------------------------------------------------------
        // 10. GLOBAL UI GROUP (Sprint 6)
        // -------------------------------------------------------------
        'nav_show_lang_switcher' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_nav_show_lang_switcher', 'default' => '1', 'required' => false
        ],
        'nav_show_dark_mode' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_nav_show_dark_mode', 'default' => '1', 'required' => false
        ],
        'nav_show_home' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_nav_show_home', 'default' => '1', 'required' => false
        ],
        'nav_show_products' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_nav_show_products', 'default' => '1', 'required' => false
        ],
        'nav_show_services' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_nav_show_services', 'default' => '1', 'required' => false
        ],
        'nav_show_projects' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_nav_show_projects', 'default' => '1', 'required' => false
        ],
        'nav_show_about' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_nav_show_about', 'default' => '1', 'required' => false
        ],
        'nav_show_contact' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_nav_show_contact', 'default' => '1', 'required' => false
        ],
        'nav_order_home' => [
            'group' => 'global_ui', 'type' => 'integer', 'label_key' => 'settings_nav_order_home', 'default' => '10', 'required' => false
        ],
        'nav_order_products' => [
            'group' => 'global_ui', 'type' => 'integer', 'label_key' => 'settings_nav_order_products', 'default' => '20', 'required' => false
        ],
        'nav_order_services' => [
            'group' => 'global_ui', 'type' => 'integer', 'label_key' => 'settings_nav_order_services', 'default' => '30', 'required' => false
        ],
        'nav_order_projects' => [
            'group' => 'global_ui', 'type' => 'integer', 'label_key' => 'settings_nav_order_projects', 'default' => '40', 'required' => false
        ],
        'nav_order_about' => [
            'group' => 'global_ui', 'type' => 'integer', 'label_key' => 'settings_nav_order_about', 'default' => '50', 'required' => false
        ],
        'nav_order_contact' => [
            'group' => 'global_ui', 'type' => 'integer', 'label_key' => 'settings_nav_order_contact', 'default' => '60', 'required' => false
        ],
        'nav_services_ar' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_nav_services_ar', 'default' => 'الخدمات', 'required' => false, 'max_length' => 50
        ],
        'nav_services_en' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_nav_services_en', 'default' => 'Services', 'required' => false, 'max_length' => 50
        ],
        'nav_projects_ar' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_nav_projects_ar', 'default' => 'المشاريع', 'required' => false, 'max_length' => 50
        ],
        'nav_projects_en' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_nav_projects_en', 'default' => 'Portfolio', 'required' => false, 'max_length' => 50
        ],
        'nav_contact_ar' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_nav_contact_ar', 'default' => 'اتصل بنا', 'required' => false, 'max_length' => 50
        ],
        'nav_contact_en' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_nav_contact_en', 'default' => 'Contact', 'required' => false, 'max_length' => 50
        ],
        'footer_show_identity' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_footer_show_identity', 'default' => '1', 'required' => false
        ],
        'footer_logo' => [
            'group' => 'global_ui', 'type' => 'file', 'label_key' => 'settings_footer_logo', 'default' => '', 'prefix' => 'footer-logo', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'footer_description_ar' => [
            'group' => 'global_ui', 'type' => 'textarea', 'label_key' => 'settings_footer_desc_ar', 'default' => '', 'required' => false, 'max_length' => 500
        ],
        'footer_description_en' => [
            'group' => 'global_ui', 'type' => 'textarea', 'label_key' => 'settings_footer_desc_en', 'default' => '', 'required' => false, 'max_length' => 500
        ],
        'footer_show_services' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_footer_show_services', 'default' => '1', 'required' => false
        ],
        'footer_services_title_ar' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_footer_services_title_ar', 'default' => 'الخدمات', 'required' => false, 'max_length' => 100
        ],
        'footer_services_title_en' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_footer_services_title_en', 'default' => 'Services', 'required' => false, 'max_length' => 100
        ],
        'footer_services_limit' => [
            'group' => 'global_ui', 'type' => 'integer', 'label_key' => 'settings_footer_services_limit', 'default' => '6', 'required' => false
        ],
        'footer_show_categories' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_footer_show_categories', 'default' => '1', 'required' => false
        ],
        'footer_categories_title_ar' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_footer_categories_title_ar', 'default' => 'المنتجات', 'required' => false, 'max_length' => 100
        ],
        'footer_categories_title_en' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_footer_categories_title_en', 'default' => 'Products', 'required' => false, 'max_length' => 100
        ],
        'show_floating_whatsapp' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_show_floating_whatsapp', 'default' => '1', 'required' => false
        ],
        'show_floating_phone' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_show_floating_phone', 'default' => '1', 'required' => false
        ],
        'show_floating_visit' => [
            'group' => 'global_ui', 'type' => 'toggle', 'label_key' => 'settings_show_floating_visit', 'default' => '1', 'required' => false
        ],
        'floating_whatsapp_msg_ar' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_floating_whatsapp_msg_ar', 'default' => 'السلام عليكم، أود طلب استشارة أو عرض سعر للأرفف الحديدية.', 'required' => false, 'max_length' => 500
        ],
        'floating_whatsapp_msg_en' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_floating_whatsapp_msg_en', 'default' => 'Hello, I would like to request a quotation or advice for steel shelves.', 'required' => false, 'max_length' => 500
        ],
        'floating_visit_msg_ar' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_floating_visit_msg_ar', 'default' => 'السلام عليكم، أود طلب حجز موعد معاينة مجانية للموقع لتخطيط وتركيب الأرفف.', 'required' => false, 'max_length' => 500
        ],
        'floating_visit_msg_en' => [
            'group' => 'global_ui', 'type' => 'text', 'label_key' => 'settings_floating_visit_msg_en', 'default' => 'Hello, I want to book a free site visit/inspection for steel racking setup planning.', 'required' => false, 'max_length' => 500
        ],

        // -------------------------------------------------------------
        // 11. CONTACT PAGE GROUP (Sprint 7)
        // -------------------------------------------------------------
        'seo_contact_title_ar' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_seo_contact_title_ar', 'default' => '', 'required' => false, 'max_length' => 150
        ],
        'seo_contact_title_en' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_seo_contact_title_en', 'default' => '', 'required' => false, 'max_length' => 150
        ],
        'seo_contact_desc_ar' => [
            'group' => 'contact_page', 'type' => 'textarea', 'label_key' => 'settings_seo_contact_desc_ar', 'default' => '', 'required' => false, 'max_length' => 255
        ],
        'seo_contact_desc_en' => [
            'group' => 'contact_page', 'type' => 'textarea', 'label_key' => 'settings_seo_contact_desc_en', 'default' => '', 'required' => false, 'max_length' => 255
        ],
        'seo_contact_og_image' => [
            'group' => 'contact_page', 'type' => 'file', 'label_key' => 'settings_seo_contact_og_image', 'default' => '', 'prefix' => 'seo-contact-og', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'contact_show_hero' => [
            'group' => 'contact_page', 'type' => 'toggle', 'label_key' => 'settings_contact_show_hero', 'default' => '1', 'required' => false
        ],
        'contact_show_form' => [
            'group' => 'contact_page', 'type' => 'toggle', 'label_key' => 'settings_contact_show_form', 'default' => '1', 'required' => false
        ],
        'contact_show_info' => [
            'group' => 'contact_page', 'type' => 'toggle', 'label_key' => 'settings_contact_show_info', 'default' => '1', 'required' => false
        ],
        'contact_show_map' => [
            'group' => 'contact_page', 'type' => 'toggle', 'label_key' => 'settings_contact_show_map', 'default' => '1', 'required' => false
        ],
        'contact_show_whatsapp_button' => [
            'group' => 'contact_page', 'type' => 'toggle', 'label_key' => 'settings_contact_show_whatsapp_button', 'default' => '1', 'required' => false
        ],
        'contact_hero_title_ar' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_hero_title_ar', 'default' => 'اتصل بنا', 'required' => false, 'max_length' => 150
        ],
        'contact_hero_title_en' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_hero_title_en', 'default' => 'Contact Us', 'required' => false, 'max_length' => 150
        ],
        'contact_hero_desc_ar' => [
            'group' => 'contact_page', 'type' => 'textarea', 'label_key' => 'settings_contact_hero_desc_ar', 'default' => 'يسعدنا تواصلك معنا للإجابة على استفساراتك وتقديم عروض الأسعار والخدمات الاستشارية مجاناً.', 'required' => false, 'max_length' => 500
        ],
        'contact_hero_desc_en' => [
            'group' => 'contact_page', 'type' => 'textarea', 'label_key' => 'settings_contact_hero_desc_en', 'default' => 'We are glad to hear from you. Reach out to get free consultation, answers, and quotation offers.', 'required' => false, 'max_length' => 500
        ],
        'contact_form_title_ar' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_form_title_ar', 'default' => 'أرسل لنا رسالة', 'required' => false, 'max_length' => 150
        ],
        'contact_form_title_en' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_form_title_en', 'default' => 'Send us a message', 'required' => false, 'max_length' => 150
        ],
        'contact_form_submit_ar' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_form_submit_ar', 'default' => 'إرسال الرسالة', 'required' => false, 'max_length' => 100
        ],
        'contact_form_submit_en' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_form_submit_en', 'default' => 'Send Message', 'required' => false, 'max_length' => 100
        ],
        'contact_info_title_ar' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_info_title_ar', 'default' => 'معلومات الاتصال', 'required' => false, 'max_length' => 150
        ],
        'contact_info_title_en' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_info_title_en', 'default' => 'Contact Info', 'required' => false, 'max_length' => 150
        ],
        'contact_location_title_ar' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_location_title_ar', 'default' => 'موقعنا', 'required' => false, 'max_length' => 150
        ],
        'contact_location_title_en' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_location_title_en', 'default' => 'Our Location', 'required' => false, 'max_length' => 150
        ],
        'contact_whatsapp_btn_ar' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_whatsapp_btn_ar', 'default' => 'تواصل معنا عبر واتساب', 'required' => false, 'max_length' => 100
        ],
        'contact_whatsapp_btn_en' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_whatsapp_btn_en', 'default' => 'Contact via WhatsApp', 'required' => false, 'max_length' => 100
        ],
        'contact_whatsapp_template_en' => [
            'group' => 'contact_page', 'type' => 'text', 'label_key' => 'settings_contact_whatsapp_template_en', 'default' => 'Hello, I have an inquiry about the shelves.', 'required' => false, 'max_length' => 500
        ],

        // -------------------------------------------------------------
        // 12. USED SHELVES PAGE GROUP (Sprint 8)
        // -------------------------------------------------------------
        'used_show_hero' => [
            'group' => 'used_shelves', 'type' => 'toggle', 'label_key' => 'settings_used_show_hero', 'default' => '1', 'required' => false
        ],
        'used_order_hero' => [
            'group' => 'used_shelves', 'type' => 'integer', 'label_key' => 'settings_used_order_hero', 'default' => '10', 'required' => true
        ],
        'used_show_buy' => [
            'group' => 'used_shelves', 'type' => 'toggle', 'label_key' => 'settings_used_show_buy', 'default' => '1', 'required' => false
        ],
        'used_order_buy' => [
            'group' => 'used_shelves', 'type' => 'integer', 'label_key' => 'settings_used_order_buy', 'default' => '20', 'required' => true
        ],
        'used_show_process' => [
            'group' => 'used_shelves', 'type' => 'toggle', 'label_key' => 'settings_used_show_process', 'default' => '1', 'required' => false
        ],
        'used_order_process' => [
            'group' => 'used_shelves', 'type' => 'integer', 'label_key' => 'settings_used_order_process', 'default' => '30', 'required' => true
        ],
        'used_show_factors' => [
            'group' => 'used_shelves', 'type' => 'toggle', 'label_key' => 'settings_used_show_factors', 'default' => '1', 'required' => false
        ],
        'used_order_factors' => [
            'group' => 'used_shelves', 'type' => 'integer', 'label_key' => 'settings_used_order_factors', 'default' => '40', 'required' => true
        ],
        'used_show_faq' => [
            'group' => 'used_shelves', 'type' => 'toggle', 'label_key' => 'settings_used_show_faq', 'default' => '1', 'required' => false
        ],
        'used_order_faq' => [
            'group' => 'used_shelves', 'type' => 'integer', 'label_key' => 'settings_used_order_faq', 'default' => '50', 'required' => true
        ],
        'used_show_cta' => [
            'group' => 'used_shelves', 'type' => 'toggle', 'label_key' => 'settings_used_show_cta', 'default' => '1', 'required' => false
        ],
        'used_order_cta' => [
            'group' => 'used_shelves', 'type' => 'integer', 'label_key' => 'settings_used_order_cta', 'default' => '60', 'required' => true
        ],

        // Hero Section Content
        'used_hero_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_hero_title_ar', 'default' => 'نشتري الأرفف الحديدية المستعملة بأفضل الأسعار', 'required' => false, 'max_length' => 255
        ],
        'used_hero_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_hero_title_en', 'default' => 'We Buy Used Iron Shelves at Top Rates', 'required' => false, 'max_length' => 255
        ],
        'used_hero_subtitle_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_hero_subtitle_ar', 'default' => 'نشتري أرفف المستودعات، المحلات، والشركات مع تكفلنا الكامل والتام بكافة أعمال الفك والتحميل والنقل الفوري السريع.', 'required' => false, 'max_length' => 1000
        ],
        'used_hero_subtitle_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_hero_subtitle_en', 'default' => 'We buy warehouse and retail shelving from businesses and warehouses, covering all dismantling, loading, and instant transport costs.', 'required' => false, 'max_length' => 1000
        ],
        'used_hero_cta_label_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_hero_cta_label_ar', 'default' => 'أرسل صور الأرفف عبر واتساب', 'required' => false, 'max_length' => 150
        ],
        'used_hero_cta_label_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_hero_cta_label_en', 'default' => 'Send Shelf Photos via WhatsApp', 'required' => false, 'max_length' => 150
        ],
        'used_hero_secondary_label_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_hero_secondary_label_ar', 'default' => 'الخدمات', 'required' => false, 'max_length' => 150
        ],
        'used_hero_secondary_label_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_hero_secondary_label_en', 'default' => 'Services', 'required' => false, 'max_length' => 150
        ],
        'used_hero_wa_msg_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_hero_wa_msg_ar', 'default' => 'السلام عليكم، أرغب في بيع أرفف حديدية مستعملة لدينا وأود الحصول على تسعير مبدئي، وسأقوم أرسل صور الأرفف والكميات الآن.', 'required' => false, 'max_length' => 500
        ],
        'used_hero_wa_msg_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_hero_wa_msg_en', 'default' => 'Hello, I want to sell used iron shelves and would like a preliminary offer. I will send photos and quantities now.', 'required' => false, 'max_length' => 500
        ],

        // What We Buy Content
        'used_buy_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_title_ar', 'default' => 'ما هي الأرفف التي نشتريها؟', 'required' => false, 'max_length' => 255
        ],
        'used_buy_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_title_en', 'default' => 'What Used Shelves Do We Buy?', 'required' => false, 'max_length' => 255
        ],
        'used_buy_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_buy_desc_ar', 'default' => 'نشتري جميع أنواع الأرفف الحديدية المستعملة بشرط سلامتها وقابليتها للاستخدام الآمن.', 'required' => false, 'max_length' => 1000
        ],
        'used_buy_desc_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_buy_desc_en', 'default' => 'We purchase all types of used steel racking as long as it is structurally safe and reusable.', 'required' => false, 'max_length' => 1000
        ],
        'used_buy_1_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_1_title_ar', 'default' => 'أرفف مستودعات ومخازن', 'required' => false, 'max_length' => 255
        ],
        'used_buy_1_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_1_title_en', 'default' => 'Warehouse & Industrial Racks', 'required' => false, 'max_length' => 255
        ],
        'used_buy_1_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_1_desc_ar', 'default' => 'نشتري الأرفف الثقيلة والمتوسطة المخصصة لتخزين البضائع في المستودعات اللوجستية الكبرى.', 'required' => false, 'max_length' => 500
        ],
        'used_buy_1_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_1_desc_en', 'default' => 'We buy heavy and medium-duty pallet racking systems designed for major warehouses.', 'required' => false, 'max_length' => 500
        ],
        'used_buy_2_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_2_title_ar', 'default' => 'أرفف محلات وسوبرماركت', 'required' => false, 'max_length' => 255
        ],
        'used_buy_2_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_2_title_en', 'default' => 'Retail & Grocery Gondola Shelves', 'required' => false, 'max_length' => 255
        ],
        'used_buy_2_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_2_desc_ar', 'default' => 'نشتري أرفف العرض المخصصة للمتاجر الكبرى والبقالات ومحلات التجزئة بمختلف أحجامها.', 'required' => false, 'max_length' => 500
        ],
        'used_buy_2_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_2_desc_en', 'default' => 'We buy display shelving from grocery stores, supermarkets, and boutique shops of all sizes.', 'required' => false, 'max_length' => 500
        ],
        'used_buy_3_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_3_title_ar', 'default' => 'أرفف حديدية ثقيلة (Pallet Racks)', 'required' => false, 'max_length' => 255
        ],
        'used_buy_3_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_3_title_en', 'default' => 'Heavy-Duty Cantilevers & Pallets', 'required' => false, 'max_length' => 255
        ],
        'used_buy_3_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_3_desc_ar', 'default' => 'نشتري الأرفف المخصصة للأوزان العالية والمنصات وحوامل المصانع الثقيلة.', 'required' => false, 'max_length' => 500
        ],
        'used_buy_3_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_3_desc_en', 'default' => 'We buy racking designed for heavy weights, raw materials, steel coils, and factory shelves.', 'required' => false, 'max_length' => 500
        ],
        'used_buy_4_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_4_title_ar', 'default' => 'أنظمة تخزين متكاملة وسقائف', 'required' => false, 'max_length' => 255
        ],
        'used_buy_4_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_4_title_en', 'default' => 'Complete Racking Structures & Mezzanines', 'required' => false, 'max_length' => 255
        ],
        'used_buy_4_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_4_desc_ar', 'default' => 'نشتري صفوف المستودعات الكاملة، السقائف الحديدية، وتوابعها التخزينية المتنوعة.', 'required' => false, 'max_length' => 500
        ],
        'used_buy_4_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_buy_4_desc_en', 'default' => 'We buy full warehouse layout structures, platforms, and their storage accessories.', 'required' => false, 'max_length' => 500
        ],

        // Process Section Content
        'used_process_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_process_title_ar', 'default' => 'كيف تتم عملية الشراء والتقييم؟', 'required' => false, 'max_length' => 255
        ],
        'used_process_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_process_title_en', 'default' => 'How Purchase & Valuation Work', 'required' => false, 'max_length' => 255
        ],
        'used_process_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_process_desc_ar', 'default' => 'نعتمد آلية سريعة ومبسطة لتقييم أرففك ودفع قيمتها نقداً أو بتحويل فوري بدون تعقيدات.', 'required' => false, 'max_length' => 1000
        ],
        'used_process_desc_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_process_desc_en', 'default' => 'We follow a simple, rapid procedure to evaluate your racking and pay cash or instant transfer.', 'required' => false, 'max_length' => 1000
        ],
        'used_step_1_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_1_title_ar', 'default' => 'إرسال صور ومقاسات', 'required' => false, 'max_length' => 255
        ],
        'used_step_1_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_1_title_en', 'default' => 'Send Photos & Sizes', 'required' => false, 'max_length' => 255
        ],
        'used_step_1_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_1_desc_ar', 'default' => 'أرسل صوراً واضحة للأرفف مع تفاصيل مقاساتها والكمية التقريبية المتاحة لديك.', 'required' => false, 'max_length' => 500
        ],
        'used_step_1_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_1_desc_en', 'default' => 'Send us clear photos of the shelving along with dimensions and quantity details.', 'required' => false, 'max_length' => 500
        ],
        'used_step_2_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_2_title_ar', 'default' => 'تحديد الموقع والفك', 'required' => false, 'max_length' => 255
        ],
        'used_step_2_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_2_title_en', 'default' => 'Location & Dismantling Details', 'required' => false, 'max_length' => 255
        ],
        'used_step_2_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_2_desc_ar', 'default' => 'حدد لنا مدينتك وهل تحتاج فريقنا للقيام بالفك والتحميل أم أنها جاهزة للنقل.', 'required' => false, 'max_length' => 500
        ],
        'used_step_2_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_2_desc_en', 'default' => 'Let us know your city and if shelves need dismantling or are ready to load.', 'required' => false, 'max_length' => 500
        ],
        'used_step_3_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_3_title_ar', 'default' => 'تسعير عادل وسريع', 'required' => false, 'max_length' => 255
        ],
        'used_step_3_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_3_title_en', 'default' => 'Fair Market Pricing', 'required' => false, 'max_length' => 255
        ],
        'used_step_3_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_3_desc_ar', 'default' => 'نقوم بدراسة طلبك وتقديم سعر شراء عادل ومناسب مبني على أسعار السوق وحالة الحديد.', 'required' => false, 'max_length' => 500
        ],
        'used_step_3_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_3_desc_en', 'default' => 'We study your details and send a fair offer based on steel condition and market value.', 'required' => false, 'max_length' => 500
        ],
        'used_step_4_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_4_title_ar', 'default' => 'الفك والتحميل الفوري', 'required' => false, 'max_length' => 255
        ],
        'used_step_4_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_4_title_en', 'default' => 'Dismantling & Quick Load', 'required' => false, 'max_length' => 255
        ],
        'used_step_4_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_4_desc_ar', 'default' => 'بعد الاتفاق، نرسل شاحناتنا وفريقنا الفني للقيام بكل أعمال الفك والنقل والدفع الفوري.', 'required' => false, 'max_length' => 500
        ],
        'used_step_4_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_step_4_desc_en', 'default' => 'Upon agreement, we dispatch our crews to handle all dismantling, loading, transport, and payment.', 'required' => false, 'max_length' => 500
        ],

        // Evaluation Factors Content
        'used_factors_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factors_title_ar', 'default' => 'العوامل التي تحدد قيمة الأرفف', 'required' => false, 'max_length' => 255
        ],
        'used_factors_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factors_title_en', 'default' => 'Factors Determining Used Racking Value', 'required' => false, 'max_length' => 255
        ],
        'used_factor_1_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_1_title_ar', 'default' => 'الحالة الفنية للحديد', 'required' => false, 'max_length' => 255
        ],
        'used_factor_1_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_1_title_en', 'default' => 'Steel & Structural Integrity', 'required' => false, 'max_length' => 255
        ],
        'used_factor_1_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_1_desc_ar', 'default' => 'سلامة الأرفف من الانحناءات أو الصدأ الشديد أو الكسور والعيوب الجوهرية.', 'required' => false, 'max_length' => 500
        ],
        'used_factor_1_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_1_desc_en', 'default' => 'Shelving condition (absence of severe rust, major dents, cracks, or modifications).', 'required' => false, 'max_length' => 500
        ],
        'used_factor_2_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_2_title_ar', 'default' => 'سماكة ونوع الأرفف', 'required' => false, 'max_length' => 255
        ],
        'used_factor_2_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_2_title_en', 'default' => 'Iron Gauge & System Class', 'required' => false, 'max_length' => 255
        ],
        'used_factor_2_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_2_desc_ar', 'default' => 'الأرفف الثقيلة المخصصة للأوزان العالية وذات السماكات المرتفعة تحظى بتقييم أعلى.', 'required' => false, 'max_length' => 500
        ],
        'used_factor_2_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_2_desc_en', 'default' => 'Heavy industrial racks with higher load capacities carry higher trade-in value.', 'required' => false, 'max_length' => 500
        ],
        'used_factor_3_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_3_title_ar', 'default' => 'الكمية الكلية للرفوف', 'required' => false, 'max_length' => 255
        ],
        'used_factor_3_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_3_title_en', 'default' => 'Racking Lot Volume', 'required' => false, 'max_length' => 255
        ],
        'used_factor_3_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_3_desc_ar', 'default' => 'الكميات الكبيرة وتجهيزات المستودعات الكاملة تتيح لنا تقديم أسعار تنافسية وممتازة.', 'required' => false, 'max_length' => 500
        ],
        'used_factor_3_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_3_desc_en', 'default' => 'Bulk racking lots and entire warehouse liquidations allow us to offer premium rates.', 'required' => false, 'max_length' => 500
        ],
        'used_factor_4_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_4_title_ar', 'default' => 'موقع الأرفف وتسهيلات النقل', 'required' => false, 'max_length' => 255
        ],
        'used_factor_4_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_4_title_en', 'default' => 'Location & Dismantling Work', 'required' => false, 'max_length' => 255
        ],
        'used_factor_4_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_4_desc_ar', 'default' => 'هل هي مفككة وجاهزة للنقل في شاحناتنا أم تتطلب عملاً شاقاً لفريقنا للفك والتحميل.', 'required' => false, 'max_length' => 500
        ],
        'used_factor_4_desc_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_factor_4_desc_en', 'default' => 'Whether shelves are already uninstalled and palletized or require full dismantling work.', 'required' => false, 'max_length' => 500
        ],

        // FAQ Section Content
        'used_faq_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_title_ar', 'default' => 'أسئلة شائعة حول شراء الأرفف المستعملة', 'required' => false, 'max_length' => 255
        ],
        'used_faq_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_title_en', 'default' => 'Used Shelving Purchase FAQs', 'required' => false, 'max_length' => 255
        ],
        'used_faq_1_q_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_1_q_ar', 'default' => 'هل تشترون كميات قليلة من الأرفف المستعملة؟', 'required' => false, 'max_length' => 255
        ],
        'used_faq_1_q_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_1_q_en', 'default' => 'Do you buy small lots of used shelves?', 'required' => false, 'max_length' => 255
        ],
        'used_faq_1_a_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_1_a_ar', 'default' => 'نعم، نشتري جميع الكميات سواء كانت صغيرة للمحلات الفردية أو كميات كبرى للمستودعات اللوجستية والشركات.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_1_a_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_1_a_en', 'default' => 'Yes, we buy all volumes, from single shop shelving rows to massive logistics warehouse liquidations.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_2_q_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_2_q_ar', 'default' => 'هل تتحملون تكاليف الفك والنقل؟', 'required' => false, 'max_length' => 255
        ],
        'used_faq_2_q_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_2_q_en', 'default' => 'Do you cover dismantling and shipping expenses?', 'required' => false, 'max_length' => 255
        ],
        'used_faq_2_a_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_2_a_ar', 'default' => 'نعم، نتكفل بإرسال فنيين متخصصين للفك الفوري وشاحنات لنقل الأرفف دون تحميل العميل أي تكاليف إضافية.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_2_a_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_2_a_en', 'default' => 'Yes, we dispatch our own professional technicians to dismantle and trucks to transport at no cost to you.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_3_q_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_3_q_ar', 'default' => 'كيف يتم تقييم السعر المالي للأرفف؟', 'required' => false, 'max_length' => 255
        ],
        'used_faq_3_q_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_3_q_en', 'default' => 'How is the purchase offer calculated?', 'required' => false, 'max_length' => 255
        ],
        'used_faq_3_a_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_3_a_ar', 'default' => 'يعتمد التقييم على: حالة الحديد وسلامته من الصدأ، مقاسات الرف وسماكته، والكمية الإجمالية، وموقع العميل الجغرافي.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_3_a_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_3_a_en', 'default' => 'We evaluate used racks based on condition, thickness/load class, lot size, and client location.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_4_q_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_4_q_ar', 'default' => 'هل يمكنني إرسال الصور مباشرة عبر واتساب؟', 'required' => false, 'max_length' => 255
        ],
        'used_faq_4_q_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_4_q_en', 'default' => 'Can I send photos directly on WhatsApp?', 'required' => false, 'max_length' => 255
        ],
        'used_faq_4_a_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_4_a_ar', 'default' => 'نعم، تواصل معنا عبر واتساب وأرسل الصور والمقاسات للحصول على تسعير مبدئي وسريع جداً.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_4_a_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_4_a_en', 'default' => 'Yes, message us on WhatsApp with photos and sizing for an immediate preliminary valuation.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_5_q_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_5_q_ar', 'default' => 'هل تشترون الأرفف التالفة أو المكسورة؟', 'required' => false, 'max_length' => 255
        ],
        'used_faq_5_q_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_used_faq_5_q_en', 'default' => 'Do you buy damaged or heavily rusted racks?', 'required' => false, 'max_length' => 255
        ],
        'used_faq_5_a_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_5_a_ar', 'default' => 'نشتري الأرفف الصالحة للاستخدام الآمن فقط. الأرفف التي بها صدأ شديد أو التواءات حرجة قد لا نقبل شراءها حرصاً على السلامة والأمان.', 'required' => false, 'max_length' => 1000
        ],
        'used_faq_5_a_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_used_faq_5_a_en', 'default' => 'We only buy racks fit for safe re-use. Highly rusted, bent, or broken racks are rejected for safety reasons.', 'required' => false, 'max_length' => 1000
        ],

        // CTA Section Content
        'cta_used_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_cta_used_title_ar', 'default' => 'لديك أرفف مستعملة وتريد التخلص منها بأفضل سعر؟', 'required' => false, 'max_length' => 255
        ],
        'cta_used_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_cta_used_title_en', 'default' => 'Have old racking lots you want to liquidate for top dollar?', 'required' => false, 'max_length' => 255
        ],
        'cta_used_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_cta_used_desc_ar', 'default' => 'نشتري كافة كميات الأرفف المستعملة وندفع لك فوراً، مع تحملنا لكامل تكاليف الفك والتحميل والنقل.', 'required' => false, 'max_length' => 1000
        ],
        'cta_used_desc_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_cta_used_desc_en', 'default' => 'We purchase all used metal shelving and pay you instantly, covering all dismantling and logistics costs ourselves.', 'required' => false, 'max_length' => 1000
        ],
        'cta_used_whatsapp_msg_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_cta_used_whatsapp_msg_ar', 'default' => 'السلام عليكم، أرغب في بيع أرفف مستعملة وأود التنسيق معكم.', 'required' => false, 'max_length' => 500
        ],
        'cta_used_whatsapp_msg_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_cta_used_whatsapp_msg_en', 'default' => 'Hello, I have used shelves to liquidate and would like to coordinate.', 'required' => false, 'max_length' => 500
        ],

        // SEO Overrides
        'seo_used_title_ar' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_seo_used_title_ar', 'default' => '', 'required' => false, 'max_length' => 150
        ],
        'seo_used_title_en' => [
            'group' => 'used_shelves', 'type' => 'text', 'label_key' => 'settings_seo_used_title_en', 'default' => '', 'required' => false, 'max_length' => 150
        ],
        'seo_used_desc_ar' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_seo_used_desc_ar', 'default' => '', 'required' => false, 'max_length' => 255
        ],
        'seo_used_desc_en' => [
            'group' => 'used_shelves', 'type' => 'textarea', 'label_key' => 'settings_seo_used_desc_en', 'default' => '', 'required' => false, 'max_length' => 255
        ],
        'seo_used_og_image' => [
            'group' => 'used_shelves', 'type' => 'file', 'label_key' => 'settings_seo_used_og_image', 'default' => '', 'prefix' => 'seo-used-og', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        // -------------------------------------------------------------
        // 13. INSTALLATION PAGE GROUP (Sprint 9)
        // -------------------------------------------------------------
        'install_show_hero' => [
            'group' => 'installation', 'type' => 'toggle', 'label_key' => 'settings_install_show_hero', 'default' => '1', 'required' => false
        ],
        'install_order_hero' => [
            'group' => 'installation', 'type' => 'integer', 'label_key' => 'settings_install_order_hero', 'default' => '10', 'required' => true
        ],
        'install_show_where' => [
            'group' => 'installation', 'type' => 'toggle', 'label_key' => 'settings_install_show_where', 'default' => '1', 'required' => false
        ],
        'install_order_where' => [
            'group' => 'installation', 'type' => 'integer', 'label_key' => 'settings_install_order_where', 'default' => '20', 'required' => true
        ],
        'install_show_process' => [
            'group' => 'installation', 'type' => 'toggle', 'label_key' => 'settings_install_show_process', 'default' => '1', 'required' => false
        ],
        'install_order_process' => [
            'group' => 'installation', 'type' => 'integer', 'label_key' => 'settings_install_order_process', 'default' => '30', 'required' => true
        ],
        'install_show_why' => [
            'group' => 'installation', 'type' => 'toggle', 'label_key' => 'settings_install_show_why', 'default' => '1', 'required' => false
        ],
        'install_order_why' => [
            'group' => 'installation', 'type' => 'integer', 'label_key' => 'settings_install_order_why', 'default' => '40', 'required' => true
        ],
        'install_show_cta' => [
            'group' => 'installation', 'type' => 'toggle', 'label_key' => 'settings_install_show_cta', 'default' => '1', 'required' => false
        ],
        'install_order_cta' => [
            'group' => 'installation', 'type' => 'integer', 'label_key' => 'settings_install_order_cta', 'default' => '50', 'required' => true
        ],

        // Hero Section Content
        'install_hero_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_hero_title_ar', 'default' => 'تركيب الأرفف الحديدية باحترافية وأمان مطلق', 'required' => true, 'max_length' => 255
        ],
        'install_hero_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_hero_title_en', 'default' => 'Professional & Anchor-Safe Racking Installation', 'required' => true, 'max_length' => 255
        ],
        'install_hero_subtitle_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_hero_subtitle_ar', 'default' => 'فريق متخصص في تركيب وتثبيت الأرفف الحديدية للمستودعات الكبرى، المحلات، غرف التخزين، والمنازل بأعلى معايير السلامة وثبات التحمل.', 'required' => true, 'max_length' => 1000
        ],
        'install_hero_subtitle_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_hero_subtitle_en', 'default' => 'Specialized team installing and anchoring iron shelves for industrial warehouses, cold stores, retail shops, and archives under strict safety codes.', 'required' => true, 'max_length' => 1000
        ],
        'install_hero_cta_label_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_hero_cta_label_ar', 'default' => 'احجز موعد معاينة تركيب عبر واتساب', 'required' => true, 'max_length' => 150
        ],
        'install_hero_cta_label_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_hero_cta_label_en', 'default' => 'Book Installation Inspection on WhatsApp', 'required' => true, 'max_length' => 150
        ],
        'install_hero_secondary_label_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_hero_secondary_label_ar', 'default' => 'الخدمات', 'required' => true, 'max_length' => 150
        ],
        'install_hero_secondary_label_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_hero_secondary_label_en', 'default' => 'Services', 'required' => true, 'max_length' => 150
        ],
        'install_hero_wa_msg_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_hero_wa_msg_ar', 'default' => 'السلام عليكم، أود حجز موعد معاينة تركيب أرفف حديدية، وسأقوم أرسل المقاسات وصور الموقع الآن.', 'required' => true, 'max_length' => 500
        ],
        'install_hero_wa_msg_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_hero_wa_msg_en', 'default' => 'Hello, I would like to book a site inspection for racking installation. I will send space dimensions and photos now.', 'required' => true, 'max_length' => 500
        ],

        // Where We Install Section
        'install_where_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_where_title_ar', 'default' => 'أين نقوم بتركيب وتثبيت الأرفف؟', 'required' => true, 'max_length' => 255
        ],
        'install_where_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_where_title_en', 'default' => 'Where Do We Install Shelves?', 'required' => true, 'max_length' => 255
        ],
        'install_where_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_where_desc_ar', 'default' => 'نوفر خدمات التركيب لمختلف المساحات والأنشطة التجارية والسكنية بأعلى دقة.', 'required' => true, 'max_length' => 1000
        ],
        'install_where_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_where_desc_en', 'default' => 'We provide accurate racking assembly and anchoring for various storage environments.', 'required' => true, 'max_length' => 1000
        ],

        // Location Cards (1-4)
        'install_loc_1_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_loc_1_title_ar', 'default' => 'المستودعات والمراكز اللوجستية الكبرى', 'required' => true, 'max_length' => 255
        ],
        'install_loc_1_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_loc_1_title_en', 'default' => 'Logistics Warehouses & Depots', 'required' => true, 'max_length' => 255
        ],
        'install_loc_1_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_loc_1_desc_ar', 'default' => 'تركيب وتأمين الأرفف الصناعية الضخمة وتثبيتها بالأرديات لضمان عدم السقوط أثناء التحميل.', 'required' => true, 'max_length' => 1000
        ],
        'install_loc_1_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_loc_1_desc_en', 'default' => 'Assembling and floor-anchoring large industrial racking to prevent collapses during forklift loading.', 'required' => true, 'max_length' => 1000
        ],

        'install_loc_2_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_loc_2_title_ar', 'default' => 'غرف التبريد ومخازن الأغذية', 'required' => true, 'max_length' => 255
        ],
        'install_loc_2_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_loc_2_title_en', 'default' => 'Cold Storages & Food Facilities', 'required' => true, 'max_length' => 255
        ],
        'install_loc_2_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_loc_2_desc_ar', 'default' => 'تركيب أرفف مقاومة للرطوبة والصدأ في مستودعات الأغذية والأدوية والمطاعم للامتثال للشروط الصحية.', 'required' => true, 'max_length' => 1000
        ],
        'install_loc_2_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_loc_2_desc_en', 'default' => 'Installing rust-proof and hygienic racking systems for restaurants, pharma, and food cold rooms.', 'required' => true, 'max_length' => 1000
        ],

        'install_loc_3_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_loc_3_title_ar', 'default' => 'المحلات والمعارض التجارية', 'required' => true, 'max_length' => 255
        ],
        'install_loc_3_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_loc_3_title_en', 'default' => 'Retail Stores & Commercial Showrooms', 'required' => true, 'max_length' => 255
        ],
        'install_loc_3_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_loc_3_desc_ar', 'default' => 'تركيب أرفف العرض الجدارية والاستعراضية لضمان ترتيب وتنسيق رائع يسهل وصول العملاء للمنتجات.', 'required' => true, 'max_length' => 1000
        ],
        'install_loc_3_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_loc_3_desc_en', 'default' => 'Setting up display racks, wall shelving, and checkout counters for clear product showcasing.', 'required' => true, 'max_length' => 1000
        ],

        'install_loc_4_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_loc_4_title_ar', 'default' => 'غرف التخزين المنزلية والمكاتب', 'required' => true, 'max_length' => 255
        ],
        'install_loc_4_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_loc_4_title_en', 'default' => 'Office Archives & Home Storages', 'required' => true, 'max_length' => 255
        ],
        'install_loc_4_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_loc_4_desc_ar', 'default' => 'أرفف خفيفة ومتوسطة لتنظيم الأغراض المنزلية والملفات المكتبية واستغلال المساحة المتاحة بشكل أمثل.', 'required' => true, 'max_length' => 1000
        ],
        'install_loc_4_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_loc_4_desc_en', 'default' => 'Medium and light-duty shelving layouts to neatly organize archive files and home goods.', 'required' => true, 'max_length' => 1000
        ],

        // Installation Steps Section
        'install_phases_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_phases_title_ar', 'default' => 'مراحل وخطوات التركيب الاحترافي للأرفف', 'required' => true, 'max_length' => 255
        ],
        'install_phases_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_phases_title_en', 'default' => 'Steps of Professional Racking Installation', 'required' => true, 'max_length' => 255
        ],
        'install_phases_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_phases_desc_ar', 'default' => 'نلتزم بخطة عمل دقيقة تضمن السرعة والالتزام بأعلى معايير الجودة والأمان.', 'required' => true, 'max_length' => 1000
        ],
        'install_phases_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_phases_desc_en', 'default' => 'We commit to a rigid workflow that guarantees speed, accuracy, and absolute safety.', 'required' => true, 'max_length' => 1000
        ],

        // Step Cards (1-4)
        'install_step_1_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_step_1_title_ar', 'default' => 'معاينة وتخطيط المساحة', 'required' => true, 'max_length' => 255
        ],
        'install_step_1_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_step_1_title_en', 'default' => 'Inspection & Space Layout Design', 'required' => true, 'max_length' => 255
        ],
        'install_step_1_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_step_1_desc_ar', 'default' => 'يقوم خبراؤنا بزيارة الموقع مجاناً لمعاينة المكان وتحديد المقاسات وتخطيط توزيع الأوزان.', 'required' => true, 'max_length' => 1000
        ],
        'install_step_1_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_step_1_desc_en', 'default' => 'Our site inspectors visit your facility for free to measure space, plan aisles, and verify floor level.', 'required' => true, 'max_length' => 1000
        ],

        'install_step_2_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_step_2_title_ar', 'default' => 'اختيار نوع وسماكة الرف', 'required' => true, 'max_length' => 255
        ],
        'install_step_2_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_step_2_title_en', 'default' => 'Selecting Racking Specs', 'required' => true, 'max_length' => 255
        ],
        'install_step_2_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_step_2_desc_ar', 'default' => 'نساعدك في اختيار الأرفف المناسبة لنوع البضائع وحجم الحمولة المتوقعة (خفيف، متوسط، ثقيل).', 'required' => true, 'max_length' => 1000
        ],
        'install_step_2_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_step_2_desc_en', 'default' => 'We assist in selecting appropriate beam and upright load classes (light, medium, or heavy-duty).', 'required' => true, 'max_length' => 1000
        ],

        'install_step_3_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_step_3_title_ar', 'default' => 'التركيب الفعلي والتثبيت', 'required' => true, 'max_length' => 255
        ],
        'install_step_3_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_step_3_title_en', 'default' => 'Assembly & Anchor Bolt Securing', 'required' => true, 'max_length' => 255
        ],
        'install_step_3_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_step_3_desc_ar', 'default' => 'يقوم فنيونا بتركيب وتجميع أجزاء الأرفف وتثبيتها بشكل آمن ومحكم في الأرضيات والجدران.', 'required' => true, 'max_length' => 1000
        ],
        'install_step_3_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_step_3_desc_en', 'default' => 'Our crew carries out assembly, securing frame bracing and anchoring posts firmly to concrete.', 'required' => true, 'max_length' => 1000
        ],

        'install_step_4_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_step_4_title_ar', 'default' => 'اختبار الثبات والتحمل', 'required' => true, 'max_length' => 255
        ],
        'install_step_4_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_step_4_title_en', 'default' => 'Balance & Capacity Verification', 'required' => true, 'max_length' => 255
        ],
        'install_step_4_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_step_4_desc_ar', 'default' => 'نتأكد من استقامة الأرفف وتوازنها ونقوم باختبار قدرتها على تحمل البضائع بأمان تام.', 'required' => true, 'max_length' => 1000
        ],
        'install_step_4_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_step_4_desc_en', 'default' => 'We inspect alignment, leveling, locking pins, and verify safe loading capacity before handover.', 'required' => true, 'max_length' => 1000
        ],

        // Why Professional Installation Section
        'install_why_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_why_title_ar', 'default' => 'لماذا يجب الاستعانة بفريق تركيب متخصص؟', 'required' => true, 'max_length' => 255
        ],
        'install_why_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_why_title_en', 'default' => 'Why Hire Professional Installers?', 'required' => true, 'max_length' => 255
        ],

        // Benefit Cards (1-3)
        'install_why_1_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_why_1_title_ar', 'default' => 'أمان مطلق وحماية من الانهيار', 'required' => true, 'max_length' => 255
        ],
        'install_why_1_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_why_1_title_en', 'default' => 'Absolute Safety & Prevent Collapses', 'required' => true, 'max_length' => 255
        ],
        'install_why_1_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_why_1_desc_ar', 'default' => 'التركيب الخاطئ للأرفف الثقيلة يشكل خطراً كبيراً على الأرواح والمنتجات، فريقنا يضمن تثبيتها وتأمينها.', 'required' => true, 'max_length' => 1000
        ],
        'install_why_1_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_why_1_desc_en', 'default' => 'Improper rack assembly poses severe risks to staff and goods. Professional anchoring prevents falls.', 'required' => true, 'max_length' => 1000
        ],

        'install_why_2_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_why_2_title_ar', 'default' => 'توزيع مثالي ومدروس للأوزان', 'required' => true, 'max_length' => 255
        ],
        'install_why_2_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_why_2_title_en', 'default' => 'Optimized Weight & Load Distribution', 'required' => true, 'max_length' => 255
        ],
        'install_why_2_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_why_2_desc_ar', 'default' => 'نضمن توزيع الأحمال بالتساوي على القوائم والعوارض لتفادي انحناء أو التواء الحديد على المدى الطويل.', 'required' => true, 'max_length' => 1000
        ],
        'install_why_2_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_why_2_desc_en', 'default' => 'We ensure loads are evenly distributed across beams and uprights to avoid steel fatigue or bending.', 'required' => true, 'max_length' => 1000
        ],

        'install_why_3_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_why_3_title_ar', 'default' => 'استغلال ذكي لكامل المساحة المتاحة', 'required' => true, 'max_length' => 255
        ],
        'install_why_3_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_install_why_3_title_en', 'default' => 'Smart Storage Space Optimization', 'required' => true, 'max_length' => 255
        ],
        'install_why_3_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_why_3_desc_ar', 'default' => 'خبرتنا تتيح لنا ترتيب الممرات وارتفاعات الأرفف لاستغلال كل متر مربع بشكل يزيد الكفاءة.', 'required' => true, 'max_length' => 1000
        ],
        'install_why_3_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_install_why_3_desc_en', 'default' => 'We layout storage systems to minimize aisle waste, maximizing pallet capacity per square meter.', 'required' => true, 'max_length' => 1000
        ],

        // Call to Action
        'cta_install_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_cta_install_title_ar', 'default' => 'أرسل مقاسات مستودعك أو صور المكان الآن، واحجز موعد معاينة تركيب مجاني!', 'required' => true, 'max_length' => 255
        ],
        'cta_install_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_cta_install_title_en', 'default' => 'Send your warehouse dimensions or site photos now, and book a free inspection!', 'required' => true, 'max_length' => 255
        ],
        'cta_install_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_cta_install_desc_ar', 'default' => 'يتكفل فريقنا الفني بفحص الموقع وتخطيط المساحة وتثبيت الأرفف بالخرسانة لضمان استقرارها الكامل.', 'required' => true, 'max_length' => 1000
        ],
        'cta_install_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_cta_install_desc_en', 'default' => 'Our crew surveys your space, plans optimal aisle widths, and anchors upright frames to concrete.', 'required' => true, 'max_length' => 1000
        ],
        'cta_install_whatsapp_msg_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_cta_install_whatsapp_msg_ar', 'default' => 'السلام عليكم، أرغب في حجز موعد معاينة لتركيب أرفف حديدية.', 'required' => true, 'max_length' => 500
        ],
        'cta_install_whatsapp_msg_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_cta_install_whatsapp_msg_en', 'default' => 'Hello, I want to book a site inspection for shelving installation.', 'required' => true, 'max_length' => 500
        ],

        // SEO Overrides
        'seo_install_title_ar' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_seo_install_title_ar', 'default' => '', 'required' => false, 'max_length' => 150
        ],
        'seo_install_title_en' => [
            'group' => 'installation', 'type' => 'text', 'label_key' => 'settings_seo_install_title_en', 'default' => '', 'required' => false, 'max_length' => 150
        ],
        'seo_install_desc_ar' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_seo_install_desc_ar', 'default' => '', 'required' => false, 'max_length' => 255
        ],
        'seo_install_desc_en' => [
            'group' => 'installation', 'type' => 'textarea', 'label_key' => 'settings_seo_install_desc_en', 'default' => '', 'required' => false, 'max_length' => 255
        ],
        'seo_install_og_image' => [
            'group' => 'installation', 'type' => 'file', 'label_key' => 'settings_seo_install_og_image', 'default' => '', 'prefix' => 'seo-install-og', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        
        // Sprint: Why Choose Services Cards
        'services_why_1_title_ar' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_1_title_ar', 'default' => 'معاينة مجانية ودقيقة', 'required' => true, 'max_length' => 255
        ],
        'services_why_1_title_en' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_1_title_en', 'default' => 'Free & Accurate Site Inspection', 'required' => true, 'max_length' => 255
        ],
        'services_why_1_desc_ar' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_1_desc_ar', 'default' => 'نقدم خدمة فحص الموقع وأخذ المقاسات بدقة متناهية مجاناً لضمان التصميم والتركيب الأمثل.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_1_desc_en' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_1_desc_en', 'default' => 'We provide space survey and dimensions check with extreme precision for free to guarantee the best fit.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_1_icon' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_1_icon', 'default' => 'scan', 'required' => true, 'max_length' => 100
        ],
        'services_why_1_image' => [
            'group' => 'services_page', 'type' => 'file', 'label_key' => 'settings_services_why_1_image', 'default' => '', 'prefix' => 'why-serv-1', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        'services_why_2_title_ar' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_2_title_ar', 'default' => 'فريق فني محترف', 'required' => true, 'max_length' => 255
        ],
        'services_why_2_title_en' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_2_title_en', 'default' => 'Professional Technical Crew', 'required' => true, 'max_length' => 255
        ],
        'services_why_2_desc_ar' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_2_desc_ar', 'default' => 'فريقنا مجهز ومدرب على أعلى المستويات لضمان تثبيت الأرفف بأمان تام ومطابقة المعايير الهندسية.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_2_desc_en' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_2_desc_en', 'default' => 'Our crew is highly trained and equipped to ensure racking anchoring with maximum safety and standards compliance.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_2_icon' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_2_icon', 'default' => 'users', 'required' => true, 'max_length' => 100
        ],
        'services_why_2_image' => [
            'group' => 'services_page', 'type' => 'file', 'label_key' => 'settings_services_why_2_image', 'default' => '', 'prefix' => 'why-serv-2', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        'services_why_3_title_ar' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_3_title_ar', 'default' => 'أسعار منافسة وعادلة', 'required' => true, 'max_length' => 255
        ],
        'services_why_3_title_en' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_3_title_en', 'default' => 'Competitive & Fair Pricing', 'required' => true, 'max_length' => 255
        ],
        'services_why_3_desc_ar' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_3_desc_ar', 'default' => 'نقدم تسعيراً شفافاً وحلولاً تناسب ميزانيتك دون أي تكاليف خفية، مع الحفاظ على أعلى مستويات الجودة.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_3_desc_en' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_3_desc_en', 'default' => 'We offer transparent pricing and custom solutions that fit your budget with zero hidden fees while retaining peak quality.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_3_icon' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_3_icon', 'default' => 'badge-dollar-sign', 'required' => true, 'max_length' => 100
        ],
        'services_why_3_image' => [
            'group' => 'services_page', 'type' => 'file', 'label_key' => 'settings_services_why_3_image', 'default' => '', 'prefix' => 'why-serv-3', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        'services_why_4_title_ar' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_4_title_ar', 'default' => 'التزام تام بالمواعيد', 'required' => true, 'max_length' => 255
        ],
        'services_why_4_title_en' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_4_title_en', 'default' => 'Total Commitment to Timelines', 'required' => true, 'max_length' => 255
        ],
        'services_why_4_desc_ar' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_4_desc_ar', 'default' => 'نحترم وقت عملائنا ونلتزم بجدول زمني محدد لعمليات التوريد والتركيب والفك والنقل دون أي تأخير.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_4_desc_en' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_4_desc_en', 'default' => 'We respect our client\'s time and stick to a strict timeline for delivery, setup, and teardown with no delays.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_4_icon' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_4_icon', 'default' => 'timer', 'required' => true, 'max_length' => 100
        ],
        'services_why_4_image' => [
            'group' => 'services_page', 'type' => 'file', 'label_key' => 'settings_services_why_4_image', 'default' => '', 'prefix' => 'why-serv-4', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        'services_why_5_title_ar' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_5_title_ar', 'default' => 'خيارات وحلول متنوعة', 'required' => true, 'max_length' => 255
        ],
        'services_why_5_title_en' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_5_title_en', 'default' => 'Diverse Options & Solutions', 'required' => true, 'max_length' => 255
        ],
        'services_why_5_desc_ar' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_5_desc_ar', 'default' => 'سواء كنت بحاجة لرفوف جدارية أنيقة للمنزل أو أنظمة تخزين ثقيلة لمستودعك، فلدينا الحل المناسب.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_5_desc_en' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_5_desc_en', 'default' => 'Whether you need elegant floating shelves for home or heavy pallet racking for your warehouse, we have the answer.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_5_icon' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_5_icon', 'default' => 'boxes', 'required' => true, 'max_length' => 100
        ],
        'services_why_5_image' => [
            'group' => 'services_page', 'type' => 'file', 'label_key' => 'settings_services_why_5_image', 'default' => '', 'prefix' => 'why-serv-5', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],

        'services_why_6_title_ar' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_6_title_ar', 'default' => 'ضمان ودعم مستمر', 'required' => true, 'max_length' => 255
        ],
        'services_why_6_title_en' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_6_title_en', 'default' => 'Warranty & Continuous Support', 'required' => true, 'max_length' => 255
        ],
        'services_why_6_desc_ar' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_6_desc_ar', 'default' => 'نوفر ضماناً حقيقياً على جودة المواد والتركيب، مع دعم فني متواصل للإجابة على كافة استفساراتكم.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_6_desc_en' => [
            'group' => 'services_page', 'type' => 'textarea', 'label_key' => 'settings_services_why_6_desc_en', 'default' => 'We provide structural warranty on materials and assembly, coupled with dedicated post-service customer support.', 'required' => true, 'max_length' => 1000
        ],
        'services_why_6_icon' => [
            'group' => 'services_page', 'type' => 'text', 'label_key' => 'settings_services_why_6_icon', 'default' => 'handshake', 'required' => true, 'max_length' => 100
        ],
        'services_why_6_image' => [
            'group' => 'services_page', 'type' => 'file', 'label_key' => 'settings_services_why_6_image', 'default' => '', 'prefix' => 'why-serv-6', 'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ]
    ];
}
