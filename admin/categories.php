<?php
// admin/categories.php
require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';
require_once dirname(__DIR__) . '/includes/category-helper.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

// Handle Delete Action
if ($action === 'delete' && $id > 0) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        $error_message = "رمز الحماية (CSRF Token) غير صالح.";
    } else {
        try {
            // Get category details first to inspect image_path
            $stmt = $pdo->prepare("SELECT image_path FROM categories WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $old_image = $stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute([':id' => $id]);
            
            // Delete file safely if not referenced globally
            if (!empty($old_image)) {
                if (!is_media_file_in_use($pdo, $old_image)) {
                    $fullPath = dirname(__DIR__) . '/' . $old_image;
                    if (strpos($old_image, 'assets/images/media/') === 0 && strpos($old_image, '..') === false) {
                        @unlink($fullPath);
                    }
                }
            }

            $success_message = "تم حذف التصنيف بنجاح.";
            header("Location: categories.php");
            exit;
        } catch (PDOException $e) {
            $error_message = "خطأ أثناء حذف التصنيف: " . $e->getMessage();
        }
    }
}

// Handle Add/Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = "رمز الحماية (CSRF Token) غير صالح.";
    } else {
        $code = trim($_POST['code']);
        $name_ar = trim($_POST['name_ar']);
        $name_en = trim($_POST['name_en']);
        $icon = trim($_POST['icon']);
        $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
        $selected_media_id = isset($_POST['selected_media_id']) ? intval($_POST['selected_media_id']) : 0;
        $has_uploaded_file = (isset($_FILES['category_image_file']) && $_FILES['category_image_file']['error'] !== UPLOAD_ERR_NO_FILE);

        $slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';
        $seo_title_ar = isset($_POST['seo_title_ar']) ? trim($_POST['seo_title_ar']) : '';
        $seo_title_en = isset($_POST['seo_title_en']) ? trim($_POST['seo_title_en']) : '';
        $meta_desc_ar = isset($_POST['meta_desc_ar']) ? trim($_POST['meta_desc_ar']) : '';
        $meta_desc_en = isset($_POST['meta_desc_en']) ? trim($_POST['meta_desc_en']) : '';
        $description_ar = isset($_POST['description_ar']) ? trim($_POST['description_ar']) : '';
        $description_en = isset($_POST['description_en']) ? trim($_POST['description_en']) : '';

        // 1. Conflict validation
        if ($remove_image && ($has_uploaded_file || $selected_media_id > 0)) {
            $error_message = "لا يمكن اختيار حذف الصورة وتحديد صورة جديدة في نفس الوقت.";
        } elseif (empty($code) || empty($name_ar) || empty($name_en)) {
            $error_message = "يرجى ملء الحقول الإلزامية.";
        } else {
            if (empty($icon)) {
                $icon = 'layers';
            }

            // Slug validation / generation logic
            $old_category = null;
            if ($id > 0) {
                $stmt_old = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
                $stmt_old->execute([':id' => $id]);
                $old_category = $stmt_old->fetch(PDO::FETCH_ASSOC);
            }

            // If empty, try to preserve old slug
            if (empty($slug) && $old_category && !empty($old_category['slug'])) {
                $slug = $old_category['slug'];
            }

            $slug_is_manual = !empty($slug);
            $slug = strtolower($slug);
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
            $slug = preg_replace('/[\s-]+/', '-', $slug);
            $slug = trim($slug, '-');

            if (empty($slug)) {
                $slug = strtolower(trim($name_en));
                $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
                $slug = preg_replace('/[\s-]+/', '-', $slug);
                $slug = trim($slug, '-');
                if (empty($slug)) {
                    $slug = 'category';
                }
            }

            // Uniqueness validation
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = :slug AND id != :id");
            $stmt_check->execute([':slug' => $slug, ':id' => $id]);
            if ((int)$stmt_check->fetchColumn() > 0) {
                if ($slug_is_manual || $id > 0) {
                    $error_message = get_current_lang() === 'ar' 
                        ? "معرّف الرابط (Slug) مستخدم بالفعل في تصنيف آخر. الرجاء اختيار معرّف فريد."
                        : "The URL Slug is already in use by another category. Please choose a unique one.";
                } else {
                    $base_slug = $slug;
                    $counter = 1;
                    while (true) {
                        $final_slug = $base_slug . '-' . $counter;
                        $stmt_check->execute([':slug' => $final_slug, ':id' => $id]);
                        if ((int)$stmt_check->fetchColumn() === 0) {
                            $slug = $final_slug;
                            break;
                        }
                        $counter++;
                    }
                }
            }

            if (empty($error_message)) {
                // Find current image if editing to preserve or clean up
                $old_image_path = '';
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT image_path FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $old_image_path = $stmt->fetchColumn() ?: '';
                }

                $new_image_path = $old_image_path; // Default to preserve
                $newFilesTracked = [];
                $filesToCleanOnSuccess = [];

                try {
                    if ($remove_image) {
                        $new_image_path = null;
                        if (!empty($old_image_path)) {
                            $filesToCleanOnSuccess[] = $old_image_path;
                        }
                    } elseif ($has_uploaded_file) {
                        // Upload new file
                        $uploaded = validate_and_upload_image($_FILES['category_image_file'], $newFilesTracked, 'media/categories', 'categories');
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
                        // Validate path starts with assets/images/media/
                        if (strpos($media_record['file_path'], 'assets/images/media/') !== 0 || strpos($media_record['file_path'], '..') !== false) {
                            throw new ProjectValidationException("مسار الملف المحدد غير مصرح به.");
                        }
                        
                        if (!empty($old_image_path) && $old_image_path !== $media_record['file_path']) {
                            $filesToCleanOnSuccess[] = $old_image_path;
                        }
                        $new_image_path = $media_record['file_path'];
                    }
                    if ($id > 0) {
                        // Update
                        $stmt = $pdo->prepare("UPDATE categories SET code = :code, name_ar = :name_ar, name_en = :name_en, icon = :icon, image_path = :image_path, slug = :slug, seo_title_ar = :seo_title_ar, seo_title_en = :seo_title_en, meta_desc_ar = :meta_desc_ar, meta_desc_en = :meta_desc_en, description_ar = :description_ar, description_en = :description_en, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmt->execute([
                            ':code' => $code,
                            ':name_ar' => $name_ar,
                            ':name_en' => $name_en,
                            ':icon' => $icon,
                            ':image_path' => $new_image_path,
                            ':slug' => $slug,
                            ':seo_title_ar' => $seo_title_ar,
                            ':seo_title_en' => $seo_title_en,
                            ':meta_desc_ar' => $meta_desc_ar,
                            ':meta_desc_en' => $meta_desc_en,
                            ':description_ar' => $description_ar,
                            ':description_en' => $description_en,
                            ':id' => $id
                        ]);
                        $success_message = "تم تحديث التصنيف بنجاح.";
                    } else {
                        // Add
                        $stmt = $pdo->prepare("INSERT INTO categories (code, name_ar, name_en, icon, image_path, slug, seo_title_ar, seo_title_en, meta_desc_ar, meta_desc_en, description_ar, description_en) VALUES (:code, :name_ar, :name_en, :icon, :image_path, :slug, :seo_title_ar, :seo_title_en, :meta_desc_ar, :meta_desc_en, :description_ar, :description_en)");
                        $stmt->execute([
                            ':code' => $code,
                            ':name_ar' => $name_ar,
                            ':name_en' => $name_en,
                            ':icon' => $icon,
                            ':image_path' => $new_image_path,
                            ':slug' => $slug,
                            ':seo_title_ar' => $seo_title_ar,
                            ':seo_title_en' => $seo_title_en,
                            ':meta_desc_ar' => $meta_desc_ar,
                            ':meta_desc_en' => $meta_desc_en,
                            ':description_ar' => $description_ar,
                            ':description_en' => $description_en
                        ]);
                        $success_message = "تم إضافة التصنيف الجديد بنجاح.";
                    }

                // Delete replaced physical files only if not in use globally
                foreach ($filesToCleanOnSuccess as $file) {
                    if (!empty($file) && !is_media_file_in_use($pdo, $file)) {
                        $fullPath = dirname(__DIR__) . '/' . $file;
                        if (strpos($file, 'assets/images/media/') === 0 && strpos($file, '..') === false) {
                            @unlink($fullPath);
                        }
                    }
                }

                header("Location: categories.php");
                exit;
            } catch (ProjectValidationException $e) {
                $error_message = $e->getMessage();
                // Clean up newly uploaded files on failure
                foreach ($newFilesTracked as $file) {
                    if (file_exists($file)) @unlink($file);
                }
            } catch (PDOException $e) {
                $error_message = "خطأ في قاعدة البيانات: " . $e->getMessage();
                // Clean up newly uploaded files on failure
                foreach ($newFilesTracked as $file) {
                    if (file_exists($file)) @unlink($file);
                }
            }
        }
    }
}
}

// Fetch Category for Editing
$category_to_edit = null;
if (($action === 'edit' || $action === 'add') && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $category_to_edit = $stmt->fetch();
}

// Fetch all categories for table
try {
    $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$page_title = __('admin_manage_categories');
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

    <!-- Admin Content Area -->
    <main class="admin-content">
        <header class="admin-header">
            <div>
                <h1 style="font-size: 28px;"><?php echo __('cat_title'); ?></h1>
                <p><?php echo __('cat_subtitle'); ?></p>
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

        <!-- Categories Table Card -->
        <section class="admin-table-card">
            <div class="admin-table-card-header">
                <h3><?php echo __('cat_table_title'); ?></h3>
                <a href="categories.php?action=add" class="btn btn-primary btn-sm">
                    <i data-lucide="plus" style="width: 16px;"></i>
                    <span><?php echo __('cat_btn_add'); ?></span>
                </a>
            </div>

            <?php if (!empty($categories)): ?>
                <div class="admin-table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;"><?php echo __('cat_col_image'); ?></th>
                                <th><?php echo __('cat_col_code'); ?></th>
                                <th><?php echo __('cat_col_name_ar'); ?></th>
                                <th><?php echo __('cat_col_name_en'); ?></th>
                                <th><?php echo __('cat_col_actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <div style="background: var(--bg-main); width: 40px; height: 40px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border-color);">
                                            <img src="../<?php echo htmlspecialchars(get_category_image_url($cat)); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo get_category_image_alt($cat); ?>">
                                        </div>
                                    </td>
                                    <td class="code-cell">
                                        <span style="background-color: var(--accent-primary-light, #e0f2fe); color: var(--accent-primary, #0284c7); padding: 5px 12px; border-radius: var(--radius-sm, 6px); font-size: 13px; font-weight: 600; font-family: 'Outfit', monospace; display: inline-block;">
                                            <?php echo htmlspecialchars($cat['code']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($cat['name_ar']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($cat['name_en']); ?></td>
                                    <td class="actions-cell">
                                        <div style="display: flex; gap: 8px;">
                                            <a href="categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px;">
                                                <i data-lucide="edit-3" style="width: 14px;"></i>
                                            </a>
                                            <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px; color: var(--danger); border-color: var(--danger-light);" onclick="return confirm('<?php echo addslashes(__('cat_delete_confirm')); ?>')">
                                                <i data-lucide="trash-2" style="width: 14px;"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="admin-empty-state">
                    <i data-lucide="folder" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 12px; display: inline-block;"></i>
                    <p style="font-size: 15px; color: var(--text-secondary); margin: 0;"><?php echo __('cat_no_records'); ?></p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Add / Edit Modal Overlay -->
        <?php if ($action === 'add' || ($action === 'edit' && $category_to_edit)): ?>
            <div class="admin-modal" id="categoryModal">
                <div class="admin-modal-content">
                    <div class="admin-modal-header">
                        <h2><?php echo $action === 'edit' ? __('cat_modal_edit') : __('cat_modal_add'); ?></h2>
                        <a href="categories.php" class="icon-btn"><i data-lucide="x"></i></a>
                    </div>
                    
                    <form action="categories.php<?php echo $action === 'edit' ? '?id='.$id : ''; ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="save_category" value="1">
                        <input type="hidden" name="icon" value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['icon']) : 'layers'; ?>">
                        
                        <div class="admin-modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="code"><?php echo __('cat_field_code'); ?></label>
                                    <input type="text" name="code" id="code" class="form-control" dir="ltr" value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['code']) : ''; ?>" placeholder="e.g. wall" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name_ar"><?php echo __('cat_field_name_ar'); ?></label>
                                    <input type="text" name="name_ar" id="name_ar" class="form-control" dir="rtl" value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['name_ar']) : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="name_en"><?php echo __('cat_field_name_en'); ?></label>
                                    <input type="text" name="name_en" id="name_en" class="form-control" dir="ltr" value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['name_en']) : ''; ?>" required>
                                </div>
                            </div>

                            <!-- Category Image Management Section -->
                            <div class="form-group" style="margin-top: 15px;">
                                <label style="font-weight: 700;"><?php echo get_current_lang() === 'ar' ? 'صورة التصنيف' : 'Category Image'; ?></label>
                                
                                <!-- Image Preview -->
                                <div id="category-preview-container" style="margin-bottom: 12px; <?php echo ($category_to_edit && !empty($category_to_edit['image_path'])) ? '' : 'display: none;'; ?>">
                                    <img id="category-preview-img" src="../<?php echo $category_to_edit ? htmlspecialchars(get_category_image_url($category_to_edit)) : ''; ?>" style="max-height: 120px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); object-fit: cover;">
                                </div>
                                
                                <!-- Upload Input -->
                                <div style="margin-bottom: 12px;">
                                    <label for="category_image_file" style="font-size: 13px; color: var(--text-secondary);"><?php echo get_current_lang() === 'ar' ? 'رفع صورة جديدة (JPEG, PNG, WebP):' : 'Upload new image (JPEG, PNG, WebP):'; ?></label>
                                    <input type="file" name="category_image_file" id="category_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" onchange="previewCategoryUpload(this)">
                                </div>
                                
                                <!-- Media Library Selection -->
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
                                                $is_selected = ($category_to_edit && $category_to_edit['image_path'] === $m['file_path']);
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

                                <!-- Remove Checkbox -->
                                <?php if ($category_to_edit && !empty($category_to_edit['image_path'])): ?>
                                    <div style="margin-top: 8px;">
                                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; color: var(--danger); font-weight: 600;">
                                            <input type="checkbox" name="remove_image" id="remove_image" value="1" onchange="toggleRemoveImage(this)">
                                            <span><?php echo get_current_lang() === 'ar' ? 'إزالة الصورة الحالية' : 'Remove current image'; ?></span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- SEO & Content Fields -->
                            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
                            <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 15px; color: var(--text-primary);"><?php echo get_current_lang() === 'ar' ? 'إعدادات تحسين محركات البحث (SEO)' : 'SEO Settings'; ?></h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="slug"><?php echo get_current_lang() === 'ar' ? 'معرّف الرابط (Slug)' : 'URL Slug'; ?></label>
                                    <input type="text" name="slug" id="slug" class="form-control" dir="ltr" value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['slug'] ?? '') : ''; ?>" placeholder="e.g. wall-shelves">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="seo_title_ar"><?php echo get_current_lang() === 'ar' ? 'عنوان SEO (بالعربية)' : 'SEO Title (Arabic)'; ?></label>
                                    <input type="text" name="seo_title_ar" id="seo_title_ar" class="form-control" dir="rtl" value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['seo_title_ar'] ?? '') : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="seo_title_en"><?php echo get_current_lang() === 'ar' ? 'عنوان SEO (بالإنجليزية)' : 'SEO Title (English)'; ?></label>
                                    <input type="text" name="seo_title_en" id="seo_title_en" class="form-control" dir="ltr" value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['seo_title_en'] ?? '') : ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="meta_desc_ar"><?php echo get_current_lang() === 'ar' ? 'وصف SEO (بالعربية)' : 'Meta Description (Arabic)'; ?></label>
                                    <textarea name="meta_desc_ar" id="meta_desc_ar" class="form-control" dir="rtl" rows="2"><?php echo $category_to_edit ? htmlspecialchars($category_to_edit['meta_desc_ar'] ?? '') : ''; ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="meta_desc_en"><?php echo get_current_lang() === 'ar' ? 'وصف SEO (بالإنجليزية)' : 'Meta Description (English)'; ?></label>
                                    <textarea name="meta_desc_en" id="meta_desc_en" class="form-control" dir="ltr" rows="2"><?php echo $category_to_edit ? htmlspecialchars($category_to_edit['meta_desc_en'] ?? '') : ''; ?></textarea>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="description_ar"><?php echo get_current_lang() === 'ar' ? 'الوصف التعريفي (بالعربية)' : 'Intro Description (Arabic)'; ?></label>
                                    <textarea name="description_ar" id="description_ar" class="form-control" dir="rtl" rows="3"><?php echo $category_to_edit ? htmlspecialchars($category_to_edit['description_ar'] ?? '') : ''; ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="description_en"><?php echo get_current_lang() === 'ar' ? 'الوصف التعريفي (بالإنجليزية)' : 'Intro Description (English)'; ?></label>
                                    <textarea name="description_en" id="description_en" class="form-control" dir="ltr" rows="3"><?php echo $category_to_edit ? htmlspecialchars($category_to_edit['description_en'] ?? '') : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="admin-modal-footer">
                            <a href="categories.php" class="btn btn-secondary"><?php echo __('admin_cancel'); ?></a>
                            <button type="submit" class="btn btn-primary"><?php echo __('admin_save'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function previewCategoryUpload(input) {
                if (input.files && input.files[0]) {
                    // Clear media library selection styling and input
                    document.querySelectorAll('.media-thumb-item').forEach(el => el.style.borderColor = 'transparent');
                    document.getElementById('selected_media_id').value = '0';
                    
                    // Uncheck remove checkbox if any
                    const removeChk = document.getElementById('remove_image');
                    if (removeChk) removeChk.checked = false;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewContainer = document.getElementById('category-preview-container');
                        const previewImg = document.getElementById('category-preview-img');
                        previewImg.src = e.target.result;
                        previewContainer.style.display = 'block';
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function selectMediaThumb(el) {
                // Clear file input
                document.getElementById('category_image_file').value = '';
                
                // Toggle styling
                document.querySelectorAll('.media-thumb-item').forEach(item => item.style.borderColor = 'transparent');
                el.style.borderColor = 'var(--accent-primary)';
                
                // Set selected ID
                document.getElementById('selected_media_id').value = el.getAttribute('data-id');
                
                // Update preview img
                const previewContainer = document.getElementById('category-preview-container');
                const previewImg = document.getElementById('category-preview-img');
                previewImg.src = el.getAttribute('data-path');
                previewContainer.style.display = 'block';
                
                // Uncheck remove checkbox if any
                const removeChk = document.getElementById('remove_image');
                if (removeChk) removeChk.checked = false;
            }

            function toggleRemoveImage(chk) {
                if (chk.checked) {
                    // Clear file upload and selected media
                    document.getElementById('category_image_file').value = '';
                    document.querySelectorAll('.media-thumb-item').forEach(el => el.style.borderColor = 'transparent');
                    document.getElementById('selected_media_id').value = '0';
                    
                    // Hide preview
                    document.getElementById('category-preview-container').style.display = 'none';
                } else {
                    // Restore original image preview if any
                    <?php if ($category_to_edit && !empty($category_to_edit['image_path'])): ?>
                        document.getElementById('category-preview-container').style.display = 'block';
                        document.getElementById('category-preview-img').src = '../<?php echo htmlspecialchars(get_category_image_url($category_to_edit)); ?>';
                    <?php endif; ?>
                }
            }
            // Auto generate slug for new category if empty
            let slugManuallyEdited = false;
            const isEditMode = <?php echo ($action === 'edit') ? 'true' : 'false'; ?>;

            document.addEventListener('DOMContentLoaded', () => {
                const nameEnInput = document.getElementById('name_en');
                const slugInput = document.getElementById('slug');
                
                if (slugInput) {
                    slugInput.addEventListener('input', () => {
                        slugManuallyEdited = true;
                    });
                }

                if (nameEnInput && slugInput) {
                    nameEnInput.addEventListener('input', () => {
                        if (isEditMode && slugInput.value.trim() !== '') return;
                        if (slugManuallyEdited) return;
                        const val = nameEnInput.value.trim().toLowerCase();
                        slugInput.value = val.replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
                    });
                }
            });
            </script>
        <?php endif; ?>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
