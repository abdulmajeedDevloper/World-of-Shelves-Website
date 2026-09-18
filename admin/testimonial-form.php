<?php
// admin/testimonial-form.php
require_once dirname(__DIR__) . '/config/admin_init.php';

$id = 0;
if (isset($_GET['id'])) {
    $val = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($val === false || $val <= 0) {
        set_flash('error', __('invalid_testimonial_id'));
        header('Location: testimonials.php');
        exit;
    }
    $id = $val;
}

$error_message = '';
$success_message = '';

// Load testimonial if editing
$testimonial = null;
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$testimonial) {
            set_flash('error', __('testimonial_not_found'));
            header('Location: testimonials.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error loading testimonial: " . $e->getMessage());
        set_flash('error', __('database_operation_failed'));
        header('Location: testimonials.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_testimonial'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = __('csrf_invalid');
    } else {
        $name_ar = trim($_POST['name_ar'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $company_ar = trim($_POST['company_ar'] ?? '');
        $company_en = trim($_POST['company_en'] ?? '');
        $review_ar = trim($_POST['review_ar'] ?? '');
        $review_en = trim($_POST['review_en'] ?? '');
        
        $stars = filter_var($_POST['stars'] ?? 5, FILTER_VALIDATE_INT);
        $sort_order = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validations
        if (empty($name_ar) || empty($name_en) || empty($review_ar) || empty($review_en)) {
            $error_message = __('invalid_input');
        } elseif ($stars === false || $stars < 1 || $stars > 5) {
            $error_message = get_current_lang() === 'ar' ? 'التقييم يجب أن يكون بين 1 و 5 نجوم.' : 'Star rating must be between 1 and 5.';
        } elseif ($sort_order === false || $sort_order < 0 || $sort_order > 9999) {
            $error_message = __('invalid_sort_order');
        } else {
            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE testimonials SET 
                        name_ar = :name_ar, name_en = :name_en,
                        company_ar = :company_ar, company_en = :company_en,
                        review_ar = :review_ar, review_en = :review_en,
                        stars = :stars, is_featured = :is_featured,
                        is_active = :is_active, sort_order = :sort_order,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id");
                    $stmt->execute([
                        ':name_ar' => $name_ar, ':name_en' => $name_en,
                        ':company_ar' => $company_ar, ':company_en' => $company_en,
                        ':review_ar' => $review_ar, ':review_en' => $review_en,
                        ':stars' => $stars, ':is_featured' => $is_featured,
                        ':is_active' => $is_active, ':sort_order' => $sort_order,
                        ':id' => $id
                    ]);

                    // Verify row exists post-update
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM testimonials WHERE id = :id");
                    $stmtCheck->execute([':id' => $id]);
                    if ((int)$stmtCheck->fetchColumn() !== 1) {
                        throw new Exception('testimonial_not_found_on_update');
                    }
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO testimonials 
                        (name_ar, name_en, company_ar, company_en, review_ar, review_en, stars, is_featured, is_active, sort_order)
                        VALUES (:name_ar, :name_en, :company_ar, :company_en, :review_ar, :review_en, :stars, :is_featured, :is_active, :sort_order)");
                    $stmt->execute([
                        ':name_ar' => $name_ar, ':name_en' => $name_en,
                        ':company_ar' => $company_ar, ':company_en' => $company_en,
                        ':review_ar' => $review_ar, ':review_en' => $review_en,
                        ':stars' => $stars, ':is_featured' => $is_featured,
                        ':is_active' => $is_active, ':sort_order' => $sort_order
                    ]);
                }

                $pdo->commit();
                set_flash('success', __('testimonial_saved'));
                header('Location: testimonials.php');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getMessage() === 'testimonial_not_found_on_update') {
                    $error_message = __('testimonial_not_found');
                } else {
                    error_log("Database transaction error saving testimonial: " . $e->getMessage());
                    $error_message = __('database_operation_failed');
                }
            }
        }
    }
}

$page_title = $id > 0 ? ($lang === 'ar' ? 'تعديل تقييم العميل' : 'Edit Testimonial') : ($lang === 'ar' ? 'إضافة تقييم عميل جديد' : 'Add New Testimonial');
$lang = get_current_lang();

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <?php 
    render_admin_page_header($page_title, [
        ['label' => $lang === 'ar' ? 'آراء التقييمات' : 'Testimonials', 'url' => 'testimonials.php'],
        ['label' => $id > 0 ? ($lang === 'ar' ? 'تعديل التقييم' : 'Edit') : ($lang === 'ar' ? 'تقييم جديد' : 'New'), 'url' => '']
    ]);
    ?>

    <!-- Error Message Alert -->
    <?php render_admin_alert('danger', $error_message); ?>

    <!-- Testimonial Form Container -->
    <form method="POST" action="testimonial-form.php<?php echo $id > 0 ? '?id=' . $id : ''; ?>" data-track-unsaved="true">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="save_testimonial" value="1">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <!-- Section 1: Customer Details -->
            <div class="form-section-card" style="margin-bottom: 0;">
                <h3>
                    <i data-lucide="user"></i>
                    <span><?php echo $lang === 'ar' ? 'بيانات العميل والشركة' : 'Client & Company Details'; ?></span>
                </h3>

                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div>
                        <label for="name_ar" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo __('t_name_ar'); ?> <span style="color:var(--admin-danger);">*</span></label>
                        <input type="text" id="name_ar" name="name_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars($testimonial['name_ar'] ?? $_POST['name_ar'] ?? ''); ?>" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                    </div>

                    <div>
                        <label for="name_en" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo __('t_name_en'); ?> <span style="color:var(--admin-danger);">*</span></label>
                        <input type="text" id="name_en" name="name_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars($testimonial['name_en'] ?? $_POST['name_en'] ?? ''); ?>" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                    </div>

                    <div>
                        <label for="company_ar" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo __('t_company_ar'); ?></label>
                        <input type="text" id="company_ar" name="company_ar" class="form-control" dir="rtl" value="<?php echo htmlspecialchars($testimonial['company_ar'] ?? $_POST['company_ar'] ?? ''); ?>" style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                    </div>

                    <div>
                        <label for="company_en" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo __('t_company_en'); ?></label>
                        <input type="text" id="company_en" name="company_en" class="form-control" dir="ltr" value="<?php echo htmlspecialchars($testimonial['company_en'] ?? $_POST['company_en'] ?? ''); ?>" style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                    </div>
                </div>
            </div>

            <!-- Section 2: Review Text & Rating -->
            <div class="form-section-card" style="margin-bottom: 0;">
                <h3>
                    <i data-lucide="message-square"></i>
                    <span><?php echo $lang === 'ar' ? 'نص التقييم والنجوم' : 'Review Text & Rating'; ?></span>
                </h3>

                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div>
                        <label for="review_ar" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo __('t_review_ar'); ?> <span style="color:var(--admin-danger);">*</span></label>
                        <textarea id="review_ar" name="review_ar" class="form-control" dir="rtl" rows="3" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);"><?php echo htmlspecialchars($testimonial['review_ar'] ?? $_POST['review_ar'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label for="review_en" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo __('t_review_en'); ?> <span style="color:var(--admin-danger);">*</span></label>
                        <textarea id="review_en" name="review_en" class="form-control" dir="ltr" rows="3" required style="width:100%; padding:8px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);"><?php echo htmlspecialchars($testimonial['review_en'] ?? $_POST['review_en'] ?? ''); ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label for="stars" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo __('t_stars'); ?></label>
                            <select id="stars" name="stars" class="filter-select" style="width:100%; padding:6px 10px; height:38px;">
                                <?php 
                                $selectedStars = (int)($testimonial['stars'] ?? $_POST['stars'] ?? 5);
                                for ($s = 1; $s <= 5; $s++):
                                    $starString = $s . ' ' . str_repeat('⭐', $s);
                                ?>
                                    <option value="<?php echo $s; ?>" <?php echo $s === $selectedStars ? 'selected' : ''; ?>><?php echo $starString; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label for="sort_order" class="form-label" style="font-weight:600; font-size:12.5px; margin-bottom:4px; display:block;"><?php echo __('sort_order'); ?></label>
                            <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?php echo (int)($testimonial['sort_order'] ?? $_POST['sort_order'] ?? 0); ?>" min="0" max="9999" style="width:100%; padding:7px 10px; font-size:13px; border:1px solid var(--admin-border-color); border-radius:var(--radius-md);">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Visibility & Options Card -->
        <div class="form-section-card" style="margin-bottom: 24px;">
            <h3>
                <i data-lucide="eye"></i>
                <span><?php echo $lang === 'ar' ? 'إعدادات النشر والظهور' : 'Publishing & Visibility'; ?></span>
            </h3>

            <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="is_featured" name="is_featured" value="1" <?php echo (int)($testimonial['is_featured'] ?? $_POST['is_featured'] ?? 0) === 1 ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
                    <span style="font-size: 13.5px; font-weight: 600;"><?php echo __('featured'); ?> (<?php echo $lang === 'ar' ? 'عرض بالصفحة الرئيسية' : 'Show on homepage'; ?>)</span>
                </label>

                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo (int)($testimonial['is_active'] ?? $_POST['is_active'] ?? 1) === 1 ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
                    <span style="font-size: 13.5px; font-weight: 600;"><?php echo __('active'); ?> (<?php echo $lang === 'ar' ? 'ظهور مباشر بالموقع' : 'Active online'; ?>)</span>
                </label>
            </div>
        </div>

        <!-- Enterprise Sticky Save Action Bar -->
        <?php render_admin_sticky_save_bar($lang === 'ar' ? 'جاهز لحفظ بيانات التقييم' : 'Ready to save testimonial', 'testimonials.php', $lang === 'ar' ? 'حفظ التقييم' : 'Save Testimonial', true); ?>
    </form>
</main>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
