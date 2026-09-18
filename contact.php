<?php
// contact.php
// Refactored CMS Contact page with dynamic visibilities, timing token lifecycle, anti-spam, and safe maps.

require_once __DIR__ . '/config/init.php';

$lang = get_current_lang();

// 1. Visibilities
$show_hero = get_setting('contact_show_hero', '1') === '1';
$show_form = get_setting('contact_show_form', '1') === '1';
$show_info = get_setting('contact_show_info', '1') === '1';
$show_map  = get_setting('contact_show_map', '1') === '1';
$show_whatsapp = get_setting('contact_show_whatsapp_button', '1') === '1';

// 2. SEO Fallback Chain
$seo_title = get_setting('seo_contact_title_' . $lang);
if (empty($seo_title)) {
    $seo_title = get_setting('contact_hero_title_' . $lang);
}
if (empty($seo_title)) {
    $seo_title = __('contact_title');
}

$seo_desc = get_setting('seo_contact_desc_' . $lang);
if (empty($seo_desc)) {
    $seo_desc = get_setting('contact_hero_desc_' . $lang);
}
if (empty($seo_desc)) {
    $seo_desc = __('contact_subtitle');
}

// OG Image Fallback: 1. seo_contact_og_image, 2. default_og_image, 3. site_logo, 4. omit
$seo_og_image = get_setting('seo_contact_og_image');
if (empty($seo_og_image)) {
    $seo_og_image = get_setting('default_og_image');
}
if (empty($seo_og_image)) {
    $logo_path = get_setting('site_logo');
    if (!empty($logo_path) && file_exists(__DIR__ . '/' . $logo_path)) {
        $seo_og_image = $logo_path;
    }
}

$seo = [
    'title_override' => ($seo_title !== __('contact_title')) ? $seo_title : (($lang === 'ar') ? 'تواصل معنا | عالم الرفوف' : 'Contact Us | World of Shelves'),
    'desc_raw'       => $seo_desc,
    'type'           => 'website',
];
if (!empty($seo_og_image)) {
    $seo['image'] = $seo_og_image;
}

$success_msg = '';
$error_msg   = '';

// Keep variables for preserving form values
$form_name    = '';
$form_email   = '';
$form_phone   = '';
$form_message = '';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timing Token Lifecycle helper: Prune old timing tokens to prevent unbounded session growth
if (isset($_SESSION['contact_timing_tokens']) && is_array($_SESSION['contact_timing_tokens'])) {
    $now = time();
    foreach ($_SESSION['contact_timing_tokens'] as $tok => $tstamp) {
        if ($now - $tstamp > 1800) { // 30 minutes expiration
            unset($_SESSION['contact_timing_tokens'][$tok]);
        }
    }
    // Limit to maximum 10 active tokens per session
    if (count($_SESSION['contact_timing_tokens']) > 10) {
        $_SESSION['contact_timing_tokens'] = array_slice($_SESSION['contact_timing_tokens'], -10, null, true);
    }
} else {
    $_SESSION['contact_timing_tokens'] = [];
}

// 3. Process POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    
    // Check if form is disabled
    if (!$show_form) {
        http_response_code(403);
        exit("Form submission is disabled.");
    }

    $form_name    = trim($_POST['name']    ?? '');
    $form_email   = trim($_POST['email']   ?? '');
    $form_phone   = trim($_POST['phone']   ?? '');
    $form_message = trim($_POST['message'] ?? '');

    // A. CSRF Validation
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_msg = __('contact_form_invalid');
    } 
    // B. Honeypot check (visual-hidden field)
    elseif (!empty($_POST['website_url'])) {
        // Silent block: do not write to DB, pretend success to trick bots
        $success_msg = __('contact_form_success');
        $form_name = $form_email = $form_phone = $form_message = '';
    } 
    // C. Form Timing check
    else {
        $submitted_token = $_POST['form_token'] ?? '';
        
        // Strict format check for token to prevent injection as session key
        if (empty($submitted_token) || !preg_match('/^[a-f0-9]{32}$/', $submitted_token)) {
            $error_msg = __('contact_form_invalid');
        } else {
            $token_key = $submitted_token;
            $load_time = isset($_SESSION['contact_timing_tokens'][$token_key]) ? $_SESSION['contact_timing_tokens'][$token_key] : 0;
            
            // Consume the token immediately to prevent replay attacks
            unset($_SESSION['contact_timing_tokens'][$token_key]);

            if ($load_time === 0 || (time() - $load_time) < 3) {
                // Silently block or return general error
                $error_msg = __('contact_form_invalid');
            } else {
                // D. Session-based Rate Limiter (Only for valid database-write attempts)
                $last_write = $_SESSION['last_contact_write_time'] ?? 0;
                if (time() - $last_write < 30) {
                    $error_msg = get_current_lang() === 'ar' 
                        ? 'لقد قمت بإرسال رسالة مؤخراً. يرجى الانتظار 30 ثانية قبل المحاولة مرة أخرى.' 
                        : 'You have submitted a message recently. Please wait 30 seconds before trying again.';
                } else {
                    // E. Multibyte Length and Format validations
                    $strlen_fn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

                    if (empty($form_name) || empty($form_email) || empty($form_message)) {
                        $error_msg = __('contact_form_invalid');
                    } elseif (empty($form_phone)) {
                        $error_msg = get_current_lang() === 'ar' 
                            ? 'يرجى إدخال رقم الجوال.' 
                            : 'Please enter your mobile number.';
                    } elseif ($strlen_fn($form_name) > 100 || $strlen_fn($form_email) > 150 || $strlen_fn($form_phone) > 30 || $strlen_fn($form_message) > 2000) {
                        $error_msg = get_current_lang() === 'ar' 
                            ? 'لقد تجاوزت الحقول الحد الأقصى للمحارف المسموح بها.' 
                            : 'Form inputs exceed the maximum allowed length.';
                    } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
                        $error_msg = get_current_lang() === 'ar' ? 'البريد الإلكتروني غير صالح.' : 'Invalid email address.';
                    } else {
                        // F. Insert to database
                        try {
                            $stmt = $pdo->prepare("INSERT INTO messages (name, email, phone, message, is_read) VALUES (?, ?, ?, ?, 0)");
                            if ($stmt->execute([$form_name, $form_email, $form_phone, $form_message])) {
                                $success_msg = __('contact_form_success');
                                // Save rate limit timestamp
                                $_SESSION['last_contact_write_time'] = time();
                                // Clear input variables
                                $form_name = $form_email = $form_phone = $form_message = '';
                            } else {
                                $error_msg = __('contact_form_error');
                            }
                        } catch (PDOException $e) {
                            error_log('[WorldOfShelves] Contact form db insert error: ' . $e->getMessage());
                            $error_msg = __('contact_form_error');
                        }
                    }
                }
            }
        }
    }
}

// 4. Generate fresh timing token for rendering the GET request or after validation fails
try {
    $new_token = bin2hex(random_bytes(16));
} catch (Exception $e) {
    $new_token = md5(uniqid(rand(), true));
}
$_SESSION['contact_timing_tokens'][$new_token] = time();

require_once __DIR__ . '/includes/header.php';

// Retrieve shared contact details
$contact_email = get_setting('contact_email', 'info@worldofshelves.com');
$contact_phone = get_setting('contact_phone', '+966 500 000 000');
$contact_address = get_setting('contact_address_' . $lang, 'Riyadh, Saudi Arabia');
$whatsapp_number = get_setting('whatsapp_number', '');

// Reconstruct safe iframe from URL-only storage
$map_setting = get_setting('contact_map_iframe', '');
$map_src = '';
if (!empty($map_setting)) {
    if (preg_match('/src="([^"]+)"/i', $map_setting, $matches)) {
        $map_src = $matches[1];
    } else {
        $map_src = $map_setting;
    }
}

// Strict google maps domains allowlist check
$map_iframe = '';
if (!empty($map_src)) {
    $parsed_url = parse_url($map_src);
    $host = isset($parsed_url['host']) ? strtolower($parsed_url['host']) : '';
    $scheme = isset($parsed_url['scheme']) ? strtolower($parsed_url['scheme']) : '';
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

    $allowed_hosts = ['www.google.com', 'google.com', 'maps.google.com'];

    if ($scheme === 'https' && in_array($host, $allowed_hosts, true) && strpos($path, '/maps/') === 0) {
        $map_iframe = '<iframe src="' . htmlspecialchars($map_src) . '" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
    }
}

// Fallback to default Riyadh Google Map iframe if map setting is empty or malicious
if (empty($map_iframe)) {
    $fallback_url = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d115857.08726588145!2d46.75893264673629!3d24.846549216091217!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3e2efd978a9c3ce1%3A0x6b44edef890f6b4!2sRiyadh%20Saudi%20Arabia!5e0!3m2!1sen!2s!4v1700000000000!5m2!1sen!2s";
    $map_iframe = '<iframe src="' . htmlspecialchars($fallback_url) . '" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
}

// Dynamic titles & subtitles
$hero_title = get_setting('contact_hero_title_' . $lang);
if (empty($hero_title)) {
    $hero_title = __('contact_title');
}
$hero_desc = get_setting('contact_hero_desc_' . $lang);
if (empty($hero_desc)) {
    $hero_desc = __('contact_subtitle');
}
$form_card_title = get_setting('contact_form_title_' . $lang);
if (empty($form_card_title)) {
    $form_card_title = __('contact_form_title');
}
$form_submit_label = get_setting('contact_form_submit_' . $lang);
if (empty($form_submit_label)) {
    $form_submit_label = __('contact_form_submit');
}
$info_card_title = get_setting('contact_info_title_' . $lang);
if (empty($info_card_title)) {
    $info_card_title = __('contact_info_title');
}
$map_card_title = get_setting('contact_location_title_' . $lang);
if (empty($map_card_title)) {
    $map_card_title = __('contact_location_title');
}
?>

<main class="page-main">
    <!-- Contact Hero Section -->
    <?php if ($show_hero): ?>
        <section class="section page-hero reveal-item" style="padding-top: 140px; padding-bottom: 60px; text-align: center;">
            <div class="container">
                <h1 style="font-size: 3rem; margin-bottom: 20px; color: var(--text-main); font-weight: 800;">
                    <?php echo htmlspecialchars($hero_title); ?>
                </h1>
                <p style="font-size: 1.1rem; color: var(--text-muted); max-width: 600px; margin: 0 auto; line-height: 1.6;">
                    <?php echo htmlspecialchars($hero_desc); ?>
                </p>
            </div>
        </section>
    <?php endif; ?>

    <!-- Contact Info, Form & Map Section -->
    <section class="section" style="padding-top: 20px; padding-bottom: 80px;">
        <div class="container">
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 30px; background: #d4edda; color: #155724; padding: 15px; border-radius: var(--radius-md); border-right: 4px solid #28a745;">
                    <i data-lucide="check-circle" style="display: inline-block; vertical-align: middle; margin-inline-end: 8px;"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger" style="margin-bottom: 30px; background: #f8d7da; color: #721c24; padding: 15px; border-radius: var(--radius-md); border-right: 4px solid #dc3545;">
                    <i data-lucide="alert-circle" style="display: inline-block; vertical-align: middle; margin-inline-end: 8px;"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <?php
            // Calculate column visibility combinations to keep responsive layout intact
            $grid_columns = '';
            if ($show_form && ($show_info || $show_map)) {
                // Two-column layout
                $grid_columns = 'grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));';
            } else {
                // Full width single column layout
                $grid_columns = 'grid-template-columns: 1fr;';
            }
            ?>

            <div class="contact-grid" style="display: grid; <?php echo $grid_columns; ?> gap: 40px; align-items: stretch;">
                
                <!-- Left Side: Form -->
                <?php if ($show_form): ?>
                    <div class="contact-form-wrapper reveal-item" style="background: var(--bg-main); padding: 40px; border-radius: var(--radius-lg); border: 1.5px solid var(--border-dark); box-shadow: var(--shadow-sm);">
                        <h2 style="font-size: 1.5rem; margin-bottom: 30px; color: var(--accent-primary); border-bottom: 2px solid var(--border-dark); padding-bottom: 10px;">
                            <i data-lucide="mail-open" style="display: inline-block; vertical-align: middle; margin-inline-end: 8px;"></i>
                            <?php echo htmlspecialchars($form_card_title); ?>
                        </h2>

                        <form method="POST" action="" style="display: flex; flex-direction: column; gap: 20px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($new_token); ?>">
                            
                            <!-- Honeypot anti-spam field -->
                            <div class="visual-hidden-field">
                                <label for="website_url">Website URL (Leave Empty)</label>
                                <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true">
                            </div>

                            <div class="form-group">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main);"><?php echo __('contact_form_name'); ?></label>
                                <input type="text" name="name" required class="contact-input" value="<?php echo htmlspecialchars($form_name); ?>">
                            </div>

                            <div class="form-group">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main);"><?php echo __('contact_form_email'); ?></label>
                                <input type="email" name="email" required dir="ltr" class="contact-input" value="<?php echo htmlspecialchars($form_email); ?>">
                            </div>

                            <div class="form-group">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main);"><?php echo __('contact_form_phone'); ?></label>
                                <input type="tel" name="phone" required dir="ltr" class="contact-input" value="<?php echo htmlspecialchars($form_phone); ?>">
                            </div>

                            <div class="form-group">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main);"><?php echo __('contact_form_message'); ?></label>
                                <textarea name="message" rows="5" required class="contact-input" style="resize: vertical;"><?php echo htmlspecialchars($form_message); ?></textarea>
                            </div>

                            <button type="submit" name="submit_contact" class="btn btn-primary btn-block btn-lg" style="margin-top: 10px;">
                                <i data-lucide="send"></i>
                                <?php echo htmlspecialchars($form_submit_label); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Right Side: Contact Details & Map -->
                <?php if ($show_info || $show_map): ?>
                    <div style="display: flex; flex-direction: column; gap: 40px;">
                        <!-- Contact Details -->
                        <?php if ($show_info): ?>
                            <div class="contact-details reveal-item" style="background: var(--bg-main); padding: 40px; border-radius: var(--radius-lg); border: 1.5px solid var(--border-dark); box-shadow: var(--shadow-sm);">
                                <h2 style="font-size: 1.5rem; margin-bottom: 30px; color: var(--accent-primary); border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
                                    <?php echo htmlspecialchars($info_card_title); ?>
                                </h2>
                                
                                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 24px;">
                                    <li style="display: flex; align-items: flex-start; gap: 16px;">
                                        <div style="background: var(--accent-primary); color: white; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i data-lucide="map-pin"></i>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0 0 5px 0; font-size: 1rem; color: var(--text-main);"><?php echo __('contact_address_label'); ?></h4>
                                            <p style="margin: 0; color: var(--text-muted); line-height: 1.5; font-size: 0.95rem;"><?php echo htmlspecialchars($contact_address); ?></p>
                                        </div>
                                    </li>
                                    
                                    <li style="display: flex; align-items: flex-start; gap: 16px;">
                                        <div style="background: var(--accent-primary); color: white; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i data-lucide="phone"></i>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0 0 5px 0; font-size: 1rem; color: var(--text-main);"><?php echo __('contact_phone_label'); ?></h4>
                                            <p style="margin: 0; color: var(--text-muted); font-size: 0.95rem;" dir="ltr"><?php echo htmlspecialchars($contact_phone); ?></p>
                                        </div>
                                    </li>
                                    
                                    <li style="display: flex; align-items: flex-start; gap: 16px;">
                                        <div style="background: var(--accent-primary); color: white; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i data-lucide="mail"></i>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0 0 5px 0; font-size: 1rem; color: var(--text-main);"><?php echo __('contact_email_label'); ?></h4>
                                            <p style="margin: 0; color: var(--text-muted); font-size: 0.95rem;"><?php echo htmlspecialchars($contact_email); ?></p>
                                        </div>
                                    </li>
                                </ul>
                                
                                <?php if ($show_whatsapp && !empty($whatsapp_number)): ?>
                                    <div style="margin-top: 40px;">
                                        <?php
                                        $wa_custom_tpl = get_setting('contact_whatsapp_template_' . $lang);
                                        if (empty($wa_custom_tpl)) {
                                            $wa_custom_tpl = ($lang === 'ar') ? 'السلام عليكم، أود الاستفسار عن الرفوف.' : 'Hello, I have an inquiry about the shelves.';
                                        }
                                        $wa_btn_text = get_setting('contact_whatsapp_btn_' . $lang);
                                        if (empty($wa_btn_text)) {
                                            $wa_btn_text = __('contact_whatsapp_btn');
                                        }
                                        ?>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>?text=<?php echo rawurlencode($wa_custom_tpl); ?>" target="_blank" class="btn btn-whatsapp btn-block btn-lg">
                                            <i data-lucide="message-circle"></i>
                                            <?php echo htmlspecialchars($wa_btn_text); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Google Maps Embed -->
                        <?php if ($show_map): ?>
                            <div class="contact-map reveal-item" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); height: 100%; min-height: 300px; display: flex; flex-direction: column;">
                                <div style="background: var(--bg-main); padding: 20px; border-bottom: 1px solid var(--border-color);">
                                    <h2 style="font-size: 1.25rem; margin: 0; color: var(--accent-primary); display: flex; align-items: center; gap: 8px;">
                                        <i data-lucide="navigation"></i>
                                        <?php echo htmlspecialchars($map_card_title); ?>
                                    </h2>
                                </div>
                                <div style="flex-grow: 1; width: 100%;">
                                    <?php echo $map_iframe; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>
</main>

<style>
    .contact-map iframe {
        width: 100% !important;
        height: 100% !important;
        min-height: 400px;
        display: block;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
