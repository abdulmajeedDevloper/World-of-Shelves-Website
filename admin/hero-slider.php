<?php
// admin/hero-slider.php
require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';
require_once dirname(__DIR__) . '/includes/hero-slider-helper.php';

$error_message = '';
$success_message = '';

// Handle POST actions (Write operations only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "رمز الحماية (CSRF Token) غير صالح.";
        header("Location: hero-slider.php");
        exit;
    }

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    // --- ACTION: SAVE (CREATE OR UPDATE) ---
    if ($action === 'save') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $alt_ar = trim($_POST['alt_ar']);
        $alt_en = trim($_POST['alt_en']);
        $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
        $selected_media_id = isset($_POST['selected_media_id']) ? intval($_POST['selected_media_id']) : 0;
        $has_uploaded_file = (isset($_FILES['slide_image_file']) && $_FILES['slide_image_file']['error'] !== UPLOAD_ERR_NO_FILE);

        // Conflict check
        if ($remove_image && ($has_uploaded_file || $selected_media_id > 0)) {
            $_SESSION['flash_error'] = "لا يمكن اختيار حذف الصورة وتحديد صورة جديدة في نفس الوقت.";
            header("Location: hero-slider.php" . ($id > 0 ? "?action=edit&id=$id" : "?action=add"));
            exit;
        }

        // Get existing slide image path
        $old_image_path = '';
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT image_path FROM hero_slides WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $old_image_path = $stmt->fetchColumn() ?: '';
        }

        // Require image on create
        if ($id === 0 && !$has_uploaded_file && $selected_media_id === 0) {
            $_SESSION['flash_error'] = "يرجى تحديد أو رفع صورة الشريحة للإنشاء الجديد.";
            header("Location: hero-slider.php?action=add");
            exit;
        }

        // Enforce 5 slides limit on create
        if ($id === 0) {
            $total_slides = hero_slide_count($pdo);
            if ($total_slides >= 5) {
                $_SESSION['flash_error'] = "لا يمكن إضافة أكثر من 5 شرائح في قاعدة البيانات.";
                header("Location: hero-slider.php");
                exit;
            }
        }

        $new_image_path = $old_image_path;
        $newFilesTracked = [];
        $filesToCleanOnSuccess = [];

        try {
            if ($remove_image) {
                throw new ProjectValidationException("الشريحة بحاجة لصورة صالحة ولا يمكن تركها فارغة.");
            } elseif ($has_uploaded_file) {
                // Upload new slide image
                $uploaded = validate_and_upload_image($_FILES['slide_image_file'], $newFilesTracked, 'media/homepage', 'homepage');
                if ($uploaded) {
                    if (!empty($old_image_path)) {
                        $filesToCleanOnSuccess[] = $old_image_path;
                    }
                    $new_image_path = $uploaded;
                }
            } elseif ($selected_media_id > 0) {
                // Select from Media Library
                $stmt = $pdo->prepare("SELECT file_path, mime_type FROM media_library WHERE id = :id");
                $stmt->execute([':id' => $selected_media_id]);
                $media_record = $stmt->fetch();
                
                if (!$media_record) {
                    throw new ProjectValidationException("الملف المحدد من مكتبة الوسائط غير موجود.");
                }
                if (strpos($media_record['mime_type'], 'image/') !== 0) {
                    throw new ProjectValidationException("الملف المحدد ليس صورة صالحة.");
                }
                if (strpos($media_record['file_path'], 'assets/images/media/') !== 0 || strpos($media_record['file_path'], '..') !== false) {
                    throw new ProjectValidationException("مسار الملف المحدد غير مصرح به.");
                }
                
                if (!empty($old_image_path) && $old_image_path !== $media_record['file_path']) {
                    $filesToCleanOnSuccess[] = $old_image_path;
                }
                $new_image_path = $media_record['file_path'];
            }

            $slide_data = [
                'image_path' => $new_image_path,
                'alt_ar'     => $alt_ar,
                'alt_en'     => $alt_en,
                'sort_order' => $sort_order,
                'is_active'  => $is_active
            ];

            if ($id > 0) {
                hero_slide_update($pdo, $id, $slide_data);
                $_SESSION['flash_success'] = "تم تحديث الشريحة بنجاح.";
            } else {
                hero_slide_create($pdo, $slide_data);
                $_SESSION['flash_success'] = "تم إضافة الشريحة بنجاح.";
            }

            // Cleanup replaced files safely
            foreach ($filesToCleanOnSuccess as $file) {
                if (!empty($file) && !is_media_file_in_use($pdo, $file)) {
                    $fullPath = dirname(__DIR__) . '/' . $file;
                    if (strpos($file, 'assets/images/media/') === 0 && strpos($file, '..') === false) {
                        @unlink($fullPath);
                    }
                }
            }

            header("Location: hero-slider.php");
            exit;

        } catch (ProjectValidationException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            foreach ($newFilesTracked as $file) {
                if (file_exists($file)) @unlink($file);
            }
            header("Location: hero-slider.php" . ($id > 0 ? "?action=edit&id=$id" : "?action=add"));
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "خطأ أثناء الحفظ: " . $e->getMessage();
            foreach ($newFilesTracked as $file) {
                if (file_exists($file)) @unlink($file);
            }
            header("Location: hero-slider.php" . ($id > 0 ? "?action=edit&id=$id" : "?action=add"));
            exit;
        }
    }

    // --- ACTION: TOGGLE ---
    elseif ($action === 'toggle') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            hero_slide_toggle($pdo, $id);
            $_SESSION['flash_success'] = "تم تغيير حالة الشريحة.";
        }
        header("Location: hero-slider.php");
        exit;
    }

    // --- ACTION: DELETE ---
    elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $deleted_slide = hero_slide_delete($pdo, $id);
            if ($deleted_slide) {
                $_SESSION['flash_success'] = "تم حذف الشريحة بنجاح.";
                $img = $deleted_slide['image_path'];
                if (!empty($img) && !is_media_file_in_use($pdo, $img)) {
                    $fullPath = dirname(__DIR__) . '/' . $img;
                    if (strpos($img, 'assets/images/media/') === 0 && strpos($img, '..') === false) {
                        @unlink($fullPath);
                    }
                }
            } else {
                $_SESSION['flash_error'] = "الشريحة غير موجودة.";
            }
        }
        header("Location: hero-slider.php");
        exit;
    }

    // --- ACTION: REORDER ---
    elseif ($action === 'reorder') {
        $ordered_ids = isset($_POST['ids']) ? explode(',', $_POST['ids']) : [];
        if (!empty($ordered_ids)) {
            hero_slide_reorder($pdo, $ordered_ids);
            $_SESSION['flash_success'] = "تم تحديث ترتيب الشرائح.";
        }
        header("Location: hero-slider.php");
        exit;
    }
}

// Fetch flash messages
if (isset($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (isset($_SESSION['flash_success'])) {
    $success_message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Get view variables
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$slide_to_edit = null;

if (($action === 'edit' || $action === 'add') && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM hero_slides WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $slide_to_edit = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM hero_slides ORDER BY sort_order ASC, id ASC");
$slides = $stmt->fetchAll();

$page_title = get_current_lang() === 'ar' ? 'إدارة سلايدر الهيرو' : 'Hero Slider Carousel';
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

    <!-- Admin Content Area -->
    <main class="admin-content">
        <header class="admin-header">
            <div>
                <h1 style="font-size: 28px;"><?php echo get_current_lang() === 'ar' ? 'سلايدر الواجهة الرئيسية' : 'Homepage Hero Slider'; ?></h1>
                <p><?php echo get_current_lang() === 'ar' ? 'إدارة شرائح الخلفية الدوارة (بحد أقصى 5 شرائح)' : 'Manage background carousel slides (Max 5 slides total)'; ?></p>
            </div>
            
            <a href="?lang=<?php echo get_current_lang() === 'ar' ? 'en' : 'ar'; ?>" class="lang-toggle">
                <i data-lucide="globe"></i>
                <span><?php echo get_current_lang() === 'ar' ? 'English' : 'العربية'; ?></span>
            </a>
        </header>

        <!-- Message Alerts -->
        <?php if (!empty($error_message)): ?>
            <div style="background-color: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div style="background-color: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Slide Management Card -->
        <section class="admin-table-card">
            <div class="admin-table-card-header">
                <h3><?php echo get_current_lang() === 'ar' ? 'قائمة الشرائح' : 'Slides List'; ?></h3>
                <?php if (count($slides) < 5): ?>
                    <a href="hero-slider.php?action=add" class="btn btn-primary btn-sm">
                        <i data-lucide="plus" style="width: 16px;"></i>
                        <span><?php echo get_current_lang() === 'ar' ? 'إضافة شريحة جديدة' : 'Add New Slide'; ?></span>
                    </a>
                <?php else: ?>
                    <button class="btn btn-primary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;">
                        <i data-lucide="plus" style="width: 16px;"></i>
                        <span><?php echo get_current_lang() === 'ar' ? 'الحد الأقصى 5' : 'Limit 5 Reached'; ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($slides)): ?>
                <div class="admin-table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 120px;"><?php echo get_current_lang() === 'ar' ? 'الصورة' : 'Image'; ?></th>
                                <th><?php echo get_current_lang() === 'ar' ? 'النص البديل (AR)' : 'Alt Text (AR)'; ?></th>
                                <th><?php echo get_current_lang() === 'ar' ? 'النص البديل (EN)' : 'Alt Text (EN)'; ?></th>
                                <th style="width: 100px;"><?php echo get_current_lang() === 'ar' ? 'الترتيب' : 'Order'; ?></th>
                                <th style="width: 100px;"><?php echo get_current_lang() === 'ar' ? 'الحالة' : 'Status'; ?></th>
                                <th style="width: 140px;"><?php echo get_current_lang() === 'ar' ? 'إجراءات' : 'Actions'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slides as $slide): ?>
                                <tr>
                                    <td>
                                        <div style="background: var(--bg-main); width: 80px; height: 45px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border-color);">
                                            <img src="../<?php echo htmlspecialchars($slide['image_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="">
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($slide['alt_ar']); ?></td>
                                    <td><?php echo htmlspecialchars($slide['alt_en']); ?></td>
                                    <td><?php echo htmlspecialchars($slide['sort_order']); ?></td>
                                    <td>
                                        <form action="hero-slider.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                                            <button type="submit" class="btn btn-sm" style="padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; background: <?php echo $slide['is_active'] ? 'var(--success-light)' : '#f3f4f6'; ?>; color: <?php echo $slide['is_active'] ? 'var(--success)' : '#6b7280'; ?>; border: none; cursor: pointer;">
                                                <?php echo $slide['is_active'] ? (get_current_lang() === 'ar' ? 'نشط' : 'Active') : (get_current_lang() === 'ar' ? 'معطل' : 'Inactive'); ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="actions-cell">
                                        <div style="display: flex; gap: 8px;">
                                            <a href="hero-slider.php?action=edit&id=<?php echo $slide['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px;">
                                                <i data-lucide="edit-3" style="width: 14px;"></i>
                                            </a>
                                            <form action="hero-slider.php" method="POST" style="display:inline;" onsubmit="return confirm('<?php echo get_current_lang() === 'ar' ? 'هل أنت متأكد من حذف هذه الشريحة؟' : 'Are you sure you want to delete this slide?'; ?>')">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 10px; color: var(--danger); border-color: var(--danger-light); cursor: pointer; background: transparent;">
                                                    <i data-lucide="trash-2" style="width: 14px;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="admin-empty-state">
                    <i data-lucide="image" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 12px; display: inline-block;"></i>
                    <p style="font-size: 15px; color: var(--text-secondary); margin: 0;"><?php echo get_current_lang() === 'ar' ? 'لا توجد شرائح مضافة حالياً.' : 'No slides added yet.'; ?></p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Slide Edit/Add Form Overlay -->
        <?php if ($action === 'add' || ($action === 'edit' && $slide_to_edit)): ?>
            <div class="admin-modal" id="slideModal">
                <div class="admin-modal-content">
                    <div class="admin-modal-header">
                        <h2><?php echo $action === 'edit' ? (get_current_lang() === 'ar' ? 'تعديل الشريحة' : 'Edit Slide') : (get_current_lang() === 'ar' ? 'إضافة شريحة جديدة' : 'Add New Slide'); ?></h2>
                        <a href="hero-slider.php" class="icon-btn"><i data-lucide="x"></i></a>
                    </div>
                    
                    <form action="hero-slider.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?php echo $slide_to_edit ? $slide_to_edit['id'] : 0; ?>">
                        
                        <div class="admin-modal-body">
                            <!-- Image Selection & Preview -->
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 700;"><?php echo get_current_lang() === 'ar' ? 'صورة الشريحة' : 'Slide Image'; ?> *</label>
                                
                                <!-- Preview -->
                                <div id="slide-preview-container" style="margin-bottom: 12px; <?php echo ($slide_to_edit && !empty($slide_to_edit['image_path'])) ? '' : 'display: none;'; ?>">
                                    <img id="slide-preview-img" src="../<?php echo $slide_to_edit ? htmlspecialchars($slide_to_edit['image_path']) : ''; ?>" style="max-height: 150px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); object-fit: cover;">
                                </div>
                                
                                <!-- File Upload -->
                                <div style="margin-bottom: 12px;">
                                    <label for="slide_image_file" style="font-size: 13px; color: var(--text-secondary);"><?php echo get_current_lang() === 'ar' ? 'رفع صورة جديدة (JPEG, PNG, WebP):' : 'Upload new image (JPEG, PNG, WebP):'; ?></label>
                                    <input type="file" name="slide_image_file" id="slide_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" onchange="previewSlideUpload(this)">
                                </div>
                                
                                <!-- Select Media Library by ID -->
                                <input type="hidden" name="selected_media_id" id="selected_media_id" value="0">
                                <div style="margin-bottom: 12px;">
                                    <label style="font-size: 13px; color: var(--text-secondary);"><?php echo get_current_lang() === 'ar' ? 'أو اختر من مكتبة الوسائط:' : 'Or select from Media Library:'; ?></label>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 8px; max-height: 120px; overflow-y: auto; border: 1px solid var(--border-color); padding: 8px; border-radius: var(--radius-sm); background: #fafafa;" id="media-library-grid">
                                        <?php
                                        // Fetch images from media library
                                        $media_stmt = $pdo->query("SELECT id, file_path FROM media_library WHERE mime_type LIKE 'image/%' ORDER BY id DESC LIMIT 50");
                                        $media_items = $media_stmt->fetchAll();
                                        if (empty($media_items)):
                                            echo '<p style="grid-column: span 10; font-size: 12px; color: var(--text-muted); margin: 0; text-align: center;">لا توجد صور في مكتبة الوسائط</p>';
                                        else:
                                            foreach ($media_items as $m):
                                                $is_selected = ($slide_to_edit && $slide_to_edit['image_path'] === $m['file_path']);
                                        ?>
                                            <div class="media-thumb-item" data-id="<?php echo $m['id']; ?>" data-path="../<?php echo htmlspecialchars($m['file_path']); ?>" onclick="selectMediaThumb(this)" style="cursor: pointer; border: 2px solid <?php echo $is_selected ? 'var(--accent-primary)' : 'transparent'; ?>; border-radius: 4px; overflow: hidden; height: 50px; width: 50px; position: relative; box-sizing: border-box; display: inline-block;">
                                                <img src="../<?php echo htmlspecialchars($m['file_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            </div>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Alt texts -->
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label for="alt_ar"><?php echo get_current_lang() === 'ar' ? 'النص البديل (AR)' : 'Alt Text (Arabic)'; ?> *</label>
                                    <input type="text" name="alt_ar" id="alt_ar" class="form-control" dir="rtl" value="<?php echo $slide_to_edit ? htmlspecialchars($slide_to_edit['alt_ar']) : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="alt_en"><?php echo get_current_lang() === 'ar' ? 'النص البديل (EN)' : 'Alt Text (English)'; ?> *</label>
                                    <input type="text" name="alt_en" id="alt_en" class="form-control" dir="ltr" value="<?php echo $slide_to_edit ? htmlspecialchars($slide_to_edit['alt_en']) : ''; ?>" required>
                                </div>
                            </div>

                            <!-- Sorting and Visibility -->
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="sort_order"><?php echo get_current_lang() === 'ar' ? 'ترتيب الظهور' : 'Sort Order'; ?></label>
                                    <input type="number" name="sort_order" id="sort_order" class="form-control" dir="ltr" value="<?php echo $slide_to_edit ? intval($slide_to_edit['sort_order']) : (count($slides) + 1); ?>" min="1" required>
                                </div>
                                <div class="form-group">
                                    <label for="is_active"><?php echo get_current_lang() === 'ar' ? 'حالة الشريحة' : 'Visibility'; ?></label>
                                    <select name="is_active" id="is_active" class="form-control">
                                        <option value="1" <?php echo ($slide_to_edit && $slide_to_edit['is_active'] === 1) || !$slide_to_edit ? 'selected' : ''; ?>><?php echo get_current_lang() === 'ar' ? 'نشط' : 'Visible'; ?></option>
                                        <option value="0" <?php echo $slide_to_edit && $slide_to_edit['is_active'] === 0 ? 'selected' : ''; ?>><?php echo get_current_lang() === 'ar' ? 'مخفي' : 'Hidden'; ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="admin-modal-footer">
                            <a href="hero-slider.php" class="btn btn-secondary"><?php echo __('admin_cancel'); ?></a>
                            <button type="submit" class="btn btn-primary"><?php echo __('admin_save'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function previewSlideUpload(input) {
                if (input.files && input.files[0]) {
                    document.querySelectorAll('.media-thumb-item').forEach(el => el.style.borderColor = 'transparent');
                    document.getElementById('selected_media_id').value = '0';
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewContainer = document.getElementById('slide-preview-container');
                        const previewImg = document.getElementById('slide-preview-img');
                        previewImg.src = e.target.result;
                        previewContainer.style.display = 'block';
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function selectMediaThumb(el) {
                document.getElementById('slide_image_file').value = '';
                
                document.querySelectorAll('.media-thumb-item').forEach(item => item.style.borderColor = 'transparent');
                el.style.borderColor = 'var(--accent-primary)';
                
                document.getElementById('selected_media_id').value = el.getAttribute('data-id');
                
                const previewContainer = document.getElementById('slide-preview-container');
                const previewImg = document.getElementById('slide-preview-img');
                previewImg.src = el.getAttribute('data-path');
                previewContainer.style.display = 'block';
            }
            </script>
        <?php endif; ?>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
