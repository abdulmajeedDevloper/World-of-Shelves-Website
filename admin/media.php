<?php
// admin/media.php
// Centralized Media Library Dashboard for managing site assets.

require_once dirname(__DIR__) . '/config/admin_init.php';

$success_message = '';
$error_message = '';
$info_message = '';

$db_driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// Fixed folder allowlist
$folder_map = get_media_folder_map();
$folders_list = array_keys($folder_map);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Verification
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = get_current_lang() === 'ar' ? 'رمز الحماية (CSRF Token) غير صالح.' : 'Invalid security token (CSRF).';
    } else {
        
        // --- ACTION: UPLOAD ---
        if (isset($_POST['action']) && $_POST['action'] === 'upload') {
            $folder = isset($_POST['folder']) ? trim($_POST['folder']) : 'general';
            if (!in_array($folder, $folders_list, true)) {
                $folder = 'general';
            }

            $alt_ar = isset($_POST['alt_text_ar']) ? trim($_POST['alt_text_ar']) : '';
            $alt_en = isset($_POST['alt_text_en']) ? trim($_POST['alt_text_en']) : '';
            $title_ar = isset($_POST['title_ar']) ? trim($_POST['title_ar']) : '';
            $title_en = isset($_POST['title_en']) ? trim($_POST['title_en']) : '';

            // Handle multiple file upload (Max 10 files)
            if (isset($_FILES['media_files'])) {
                $files = $_FILES['media_files'];
                $file_count = count($files['name']);
                
                if ($file_count > 10) {
                    $error_message = get_current_lang() === 'ar' 
                        ? 'لا يمكن رفع أكثر من 10 ملفات في المرة الواحدة.' 
                        : 'Cannot upload more than 10 files at once.';
                } else {
                    $success_count = 0;
                    $fail_count = 0;
                    $errors = [];

                    for ($i = 0; $i < $file_count; $i++) {
                        // Skip if no file uploaded in this slot
                        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }

                        $fileInfo = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i]
                        ];

                        try {
                            $media_id = upload_to_media_library($pdo, $fileInfo, $folder, $alt_ar, $alt_en, $title_ar, $title_en);
                            if ($media_id) {
                                $success_count++;
                            }
                        } catch (ProjectValidationException $e) {
                            $fail_count++;
                            $errors[] = htmlspecialchars($fileInfo['name']) . ': ' . $e->getMessage();
                        } catch (Throwable $e) {
                            $fail_count++;
                            $errors[] = htmlspecialchars($fileInfo['name']) . ': ' . $e->getMessage();
                        }
                    }

                    if ($success_count > 0 && $fail_count === 0) {
                        $success_message = get_current_lang() === 'ar' 
                            ? "تم رفع {$success_count} ملفات بنجاح!" 
                            : "Uploaded {$success_count} files successfully!";
                    } elseif ($success_count > 0 && $fail_count > 0) {
                        $info_message = get_current_lang() === 'ar'
                            ? "تم رفع {$success_count} ملفات بنجاح. وفشل {$fail_count} ملفات: " . implode(', ', $errors)
                            : "Uploaded {$success_count} files successfully. {$fail_count} files failed: " . implode(', ', $errors);
                    } else {
                        $error_message = get_current_lang() === 'ar'
                            ? 'فشل رفع الملفات: ' . implode(', ', $errors)
                            : 'Upload failed: ' . implode(', ', $errors);
                    }
                }
            }
        }

        // --- ACTION: EDIT METADATA ---
        elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : 0;
            if ($id > 0) {
                $alt_ar = isset($_POST['alt_text_ar']) ? strip_tags(trim($_POST['alt_text_ar'])) : '';
                $alt_en = isset($_POST['alt_text_en']) ? strip_tags(trim($_POST['alt_text_en'])) : '';
                $title_ar = isset($_POST['title_ar']) ? strip_tags(trim($_POST['title_ar'])) : '';
                $title_en = isset($_POST['title_en']) ? strip_tags(trim($_POST['title_en'])) : '';
                $folder = isset($_POST['folder']) ? trim($_POST['folder']) : 'general';
                
                if (!in_array($folder, $folders_list, true)) {
                    $folder = 'general';
                }

                try {
                    $stmt = $pdo->prepare("UPDATE media_library SET 
                        alt_text_ar = :alt_ar, 
                        alt_text_en = :alt_en, 
                        title_ar = :title_ar, 
                        title_en = :title_en,
                        folder = :folder,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id");
                    
                    $stmt->execute([
                        ':alt_ar' => !empty($alt_ar) ? $alt_ar : null,
                        ':alt_en' => !empty($alt_en) ? $alt_en : null,
                        ':title_ar' => !empty($title_ar) ? $title_ar : null,
                        ':title_en' => !empty($title_en) ? $title_en : null,
                        ':folder' => $folder,
                        ':id' => $id
                    ]);

                    $success_message = get_current_lang() === 'ar' ? 'تم تحديث بيانات ملف الوسائط بنجاح.' : 'Media metadata updated successfully.';
                } catch (PDOException $e) {
                    error_log("Edit media metadata DB error: " . $e->getMessage());
                    $error_message = __('database_operation_failed');
                }
            } else {
                $error_message = __('media_invalid_id');
            }
        }

        // --- ACTION: DELETE ---
        elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : 0;
            if ($id > 0) {
                $res = delete_from_media_library($pdo, $id);
                if ($res['success']) {
                    $success_message = $res['message'];
                } else {
                    $error_message = $res['message'];
                }
            } else {
                $error_message = __('media_invalid_id');
            }
        }
    }
}

// Fetch query options
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_folder = isset($_GET['folder']) ? trim($_GET['folder']) : '';
if (!in_array($filter_folder, $folders_list, true)) {
    $filter_folder = '';
}

// Pagination setup
$page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
if ($page < 1) {
    $page = 1;
}
$limit = 24;
$offset = ($page - 1) * $limit;

// Build query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(filename LIKE :search OR title_ar LIKE :search OR title_en LIKE :search OR alt_text_ar LIKE :search OR alt_text_en LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_folder)) {
    $where_clauses[] = "folder = :folder";
    $params[':folder'] = $filter_folder;
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM media_library $where_sql");
    $count_stmt->execute($params);
    $total_items = (int)$count_stmt->fetchColumn();
} catch (PDOException $e) {
    $total_items = 0;
    error_log("Media count query error: " . $e->getMessage());
}

$total_pages = ceil($total_items / $limit);
if ($total_pages < 1) {
    $total_pages = 1;
}
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch media records
$media_items = [];
try {
    $query_stmt = $pdo->prepare("SELECT * FROM media_library $where_sql ORDER BY id DESC LIMIT :limit OFFSET :offset");
    
    // Bind limit & offset as integers for strict SQL engines
    $query_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $query_stmt->bindValue($key, $val);
    }
    
    $query_stmt->execute();
    $media_items = $query_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Media fetch query error: " . $e->getMessage());
}

$page_title = __('admin_media_library');
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

    <main class="admin-content">
        <header class="admin-header">
            <div>
                <h1 style="font-size: 28px;"><?php echo __('admin_media_library'); ?></h1>
                <p><?php echo get_current_lang() === 'ar' ? 'رفع وإدارة وتنسيق أصول الصور والملفات بمستودع موحد' : 'Upload, organize, and manage shared image files in one place'; ?></p>
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
        <?php if (!empty($info_message)): ?>
            <div style="background-color: var(--accent-light); color: var(--text-main); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600; border-left: 4px solid var(--accent-primary);">
                <?php echo htmlspecialchars($info_message); ?>
            </div>
        <?php endif; ?>

        <div class="media-lib-container">
            
            <!-- Upload section card -->
            <section class="admin-table-card" style="padding: 24px; margin-bottom: 24px;">
                <h3 style="font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="upload-cloud"></i>
                    <span><?php echo __('media_upload_btn'); ?></span>
                </h3>
                
                <form action="media.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="upload">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; align-items: flex-end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="media_files" style="font-weight:600;"><?php echo get_current_lang() === 'ar' ? 'اختر ملفات الصور (أقصى 10)' : 'Select image files (Max 10)'; ?></label>
                            <input type="file" name="media_files[]" id="media_files" class="form-control" accept="image/jpeg,image/png,image/webp" multiple required style="padding:5px;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="upload_folder" style="font-weight:600;"><?php echo __('media_folder_label'); ?></label>
                            <select name="folder" id="upload_folder" class="form-control">
                                <?php foreach ($folders_list as $f): ?>
                                    <option value="<?php echo $f; ?>"><?php echo htmlspecialchars(ucfirst($f)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; height: 46px;">
                                <i data-lucide="plus"></i>
                                <span><?php echo get_current_lang() === 'ar' ? 'بدء الرفع' : 'Start Upload'; ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Alt / Title default fields (optional, applies to batch upload) -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="batch_alt_ar" style="font-size:12px;"><?php echo __('media_alt_text_ar'); ?></label>
                            <input type="text" name="alt_text_ar" id="batch_alt_ar" class="form-control" placeholder="<?php echo get_current_lang() === 'ar' ? 'مثال: رفوف خشبية عائمة' : 'e.g. Floating wooden shelves'; ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="batch_alt_en" style="font-size:12px;"><?php echo __('media_alt_text_en'); ?></label>
                            <input type="text" name="alt_text_en" id="batch_alt_en" class="form-control" placeholder="<?php echo get_current_lang() === 'ar' ? 'مثال: Floating wood shelf' : 'e.g. Floating wood shelf'; ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="batch_title_ar" style="font-size:12px;"><?php echo __('media_title_ar'); ?></label>
                            <input type="text" name="title_ar" id="batch_title_ar" class="form-control" placeholder="<?php echo get_current_lang() === 'ar' ? 'العنوان بالعربية' : 'Title in Arabic'; ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="batch_title_en" style="font-size:12px;"><?php echo __('media_title_en'); ?></label>
                            <input type="text" name="title_en" id="batch_title_en" class="form-control" placeholder="<?php echo get_current_lang() === 'ar' ? 'العنوان بالإنجليزية' : 'Title in English'; ?>">
                        </div>
                    </div>
                </form>
            </section>

            <!-- Search, Filter, and Grid View Toolbar -->
            <div class="media-lib-toolbar">
                <form action="media.php" method="GET" style="display:contents;">
                    <div class="media-lib-search-filters">
                        <!-- Search Box -->
                        <div style="position: relative;">
                            <input type="text" name="search" class="form-control" placeholder="<?php echo __('media_search_placeholder'); ?>" value="<?php echo htmlspecialchars($search); ?>" style="padding-inline-start: 36px; min-width: 260px;">
                            <i data-lucide="search" style="position: absolute; left: 12px; top: 15px; width: 16px; height: 16px; color: var(--text-muted);"></i>
                            <?php if (get_current_lang() === 'ar'): ?>
                                <style>
                                    [dir="rtl"] .media-lib-search-filters i { left: auto; right: 12px; }
                                    [dir="rtl"] .media-lib-search-filters input { padding-inline-start: 12px; padding-inline-end: 36px; }
                                </style>
                            <?php endif; ?>
                        </div>

                        <!-- Folder Filter Dropdown -->
                        <div>
                            <select name="folder" class="form-control" onchange="this.form.submit()">
                                <option value=""><?php echo __('media_filter_all'); ?></option>
                                <?php foreach ($folders_list as $f): ?>
                                    <option value="<?php echo $f; ?>" <?php echo ($filter_folder === $f) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($f)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-secondary" style="height: 46px; padding: 10px 20px;">
                            <span><?php echo get_current_lang() === 'ar' ? 'تطبيق الفلتر' : 'Filter'; ?></span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Media Grid -->
            <?php if (count($media_items) > 0): ?>
                <div class="media-lib-grid">
                    <?php foreach ($media_items as $item): ?>
                        <div class="media-card" id="media-card-<?php echo $item['id']; ?>">
                            <div class="media-card-preview">
                                <img src="../<?php echo htmlspecialchars($item['file_path']); ?>" alt="<?php echo htmlspecialchars(get_media_alt($item, get_current_lang())); ?>" loading="lazy">
                            </div>
                            
                            <div class="media-card-info">
                                <div class="media-card-title" title="<?php echo htmlspecialchars($item['filename']); ?>">
                                    <?php echo htmlspecialchars($item['filename']); ?>
                                </div>
                                <div class="media-card-meta">
                                    <span class="media-card-folder"><?php echo htmlspecialchars($item['folder']); ?></span>
                                    <span>
                                        <?php 
                                        if ($item['width'] && $item['height']) {
                                            echo $item['width'] . 'x' . $item['height'];
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div style="font-size: 10px; color: var(--text-muted);">
                                    <?php echo htmlspecialchars(round($item['file_size'] / 1024, 1)) . ' KB'; ?>
                                </div>
                            </div>
                            
                            <div class="media-card-actions">
                                <button type="button" class="btn btn-secondary" style="padding: 6px; font-size:11px;" onclick="copyPath('<?php echo htmlspecialchars($item['file_path']); ?>')">
                                    <i data-lucide="copy" style="width:12px; height:12px; margin-inline-end:4px; display:inline-block; vertical-align:middle;"></i>
                                    <span style="display:inline-block; vertical-align:middle;"><?php echo get_current_lang() === 'ar' ? 'مسار' : 'Path'; ?></span>
                                </button>
                                <button type="button" class="btn btn-secondary" style="padding: 6px; font-size:11px;" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                    <i data-lucide="edit" style="width:12px; height:12px; margin-inline-end:4px; display:inline-block; vertical-align:middle;"></i>
                                    <span style="display:inline-block; vertical-align:middle;"><?php echo get_current_lang() === 'ar' ? 'تعديل' : 'Edit'; ?></span>
                                </button>
                                <form action="media.php" method="POST" onsubmit="return confirm('<?php echo get_current_lang() === 'ar' ? 'هل أنت متأكد من رغبتك في حذف هذا الملف نهائياً؟' : 'Are you sure you want to permanently delete this file?'; ?>');" style="display:inline; flex:1;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding: 6px; font-size:11px; color: var(--danger); width:100%;">
                                        <i data-lucide="trash-2" style="width:12px; height:12px; margin-inline-end:4px; display:inline-block; vertical-align:middle;"></i>
                                        <span style="display:inline-block; vertical-align:middle;"><?php echo get_current_lang() === 'ar' ? 'حذف' : 'Del'; ?></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination Control -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; flex-wrap: wrap; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                    <div style="font-size: 14px; color: var(--text-muted);">
                        <?php 
                        $showing_start = $offset + 1;
                        $showing_end = min($offset + $limit, $total_items);
                        printf(__('media_pagination_showing'), $showing_start, $showing_end, $total_items); 
                        ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div style="display: flex; gap: 6px;">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&folder=<?php echo urlencode($filter_folder); ?>" class="btn btn-secondary" style="padding: 6px 12px;">
                                    <i data-lucide="chevron-right" style="width:16px; height:16px;"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&folder=<?php echo urlencode($filter_folder); ?>" class="btn <?php echo ($page === $i) ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 6px 12px; min-width: 38px; text-align: center;">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&folder=<?php echo urlencode($filter_folder); ?>" class="btn btn-secondary" style="padding: 6px 12px;">
                                    <i data-lucide="chevron-left" style="width:16px; height:16px;"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Empty State -->
                <div style="text-align: center; padding: 60px 24px; background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: var(--radius-md); margin-top: 20px;">
                    <i data-lucide="image" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 16px;"></i>
                    <p style="font-size: 16px; color: var(--text-muted); font-weight: 500;">
                        <?php echo __('media_no_items'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal for Metadata Editing -->
    <div id="mediaEditModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 16px;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); max-width: 500px; width: 100%; padding: 24px; position: relative; box-shadow: var(--shadow-lg);">
            <h3 style="font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="edit-3"></i>
                <span><?php echo __('media_edit_title'); ?></span>
            </h3>
            
            <form action="media.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_media_id" value="">

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="edit_folder" style="font-weight:600;"><?php echo __('media_folder_label'); ?></label>
                        <select name="folder" id="edit_folder" class="form-control">
                            <?php foreach ($folders_list as $f): ?>
                                <option value="<?php echo $f; ?>"><?php echo htmlspecialchars(ucfirst($f)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="edit_alt_ar" style="font-weight:600;"><?php echo __('media_alt_text_ar'); ?></label>
                        <input type="text" name="alt_text_ar" id="edit_alt_ar" class="form-control">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="edit_alt_en" style="font-weight:600;"><?php echo __('media_alt_text_en'); ?></label>
                        <input type="text" name="alt_text_en" id="edit_alt_en" class="form-control">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="edit_title_ar" style="font-weight:600;"><?php echo __('media_title_ar'); ?></label>
                        <input type="text" name="title_ar" id="edit_title_ar" class="form-control">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="edit_title_en" style="font-weight:600;"><?php echo __('media_title_en'); ?></label>
                        <input type="text" name="title_en" id="edit_title_en" class="form-control">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()"><?php echo get_current_lang() === 'ar' ? 'إلغاء' : 'Cancel'; ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo get_current_lang() === 'ar' ? 'حفظ التعديلات' : 'Save Changes'; ?></button>
                </div>
            </form>
        </div>
    </div>

<script>
    // Copy path functionality to clipboard without absolute path leaking
    function copyPath(relativePath) {
        navigator.clipboard.writeText(relativePath).then(function() {
            alert(<?php echo get_current_lang() === 'ar' ? '"تم نسخ المسار النسبي بنجاح!"' : '"Relative path copied successfully!"'; ?>);
        }, function(err) {
            alert('Could not copy text: ', err);
        });
    }

    // Modal control
    function openEditModal(mediaItem) {
        document.getElementById('edit_media_id').value = mediaItem.id;
        document.getElementById('edit_folder').value = mediaItem.folder;
        document.getElementById('edit_alt_ar').value = mediaItem.alt_text_ar || '';
        document.getElementById('edit_alt_en').value = mediaItem.alt_text_en || '';
        document.getElementById('edit_title_ar').value = mediaItem.title_ar || '';
        document.getElementById('edit_title_en').value = mediaItem.title_en || '';
        
        const modal = document.getElementById('mediaEditModal');
        modal.style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('mediaEditModal').style.display = 'none';
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
