<?php
// admin/project-form.php
require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/portfolio-helper.php';
require_once dirname(__DIR__) . '/includes/product-helper.php';

ensure_project_categories_table($pdo);

$cms_categories = get_project_categories(false);

// Custom validation exception class to separate expected vs unexpected exceptions
class ProjectValidationException extends Exception {}

// GET project ID validation (FILTER_VALIDATE_INT, positive integer only)
$id = 0;
if (isset($_GET['id'])) {
    $val = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($val === false || $val <= 0) {
        set_flash('error', __('invalid_project_id'));
        header('Location: projects.php');
        exit;
    }
    $id = $val;
}

$error_message = '';
$success_message = '';

// Reusable UTF-8 aware length validation helper
function safe_mb_strlen($str) {
    // Check if valid UTF-8. preg_match returns false if string is invalid UTF-8
    if (preg_match('//u', $str) === false) {
        return false;
    }

    if (function_exists('mb_strlen')) {
        return mb_strlen($str, 'UTF-8');
    }

    // Fallback: match characters with UTF-8 modifier 'u'
    $count = preg_match_all('/./us', $str, $matches);
    if ($count === false) {
        return 0;
    }
    return $count;
}

// Load admin-upload-helper
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';

// Slug Generator
function generate_project_slug($title_en, $title_ar, $pdo) {
    $slugSource = trim($title_en);
    if (empty($slugSource)) {
        $slugSource = 'project-' . bin2hex(random_bytes(4));
    } else {
        $slugSource = strtolower($slugSource);
        $slugSource = preg_replace('/[^a-z0-9\s-]/', '', $slugSource);
        $slugSource = preg_replace('/[\s-]+/', '-', $slugSource);
        $slugSource = trim($slugSource, '-');
        if (empty($slugSource)) {
            $slugSource = 'project-' . bin2hex(random_bytes(4));
        }
    }

    $originalSlug = $slugSource;
    $suffix = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE slug = :slug");
        $stmt->execute([':slug' => $slugSource]);
        $count = (int)$stmt->fetchColumn();
        if ($count === 0) {
            break;
        }
        $slugSource = $originalSlug . '-' . $suffix;
        $suffix++;
    }

    return $slugSource;
}

// Load project if edit
$project = null;
$gallery_images = [];
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$project) {
            set_flash('error', __('project_not_found'));
            header('Location: projects.php');
            exit;
        }
        // Fetch gallery images
        $galStmt = $pdo->prepare("SELECT * FROM project_images WHERE project_id = :project_id ORDER BY sort_order ASC, id ASC");
        $galStmt->execute([':project_id' => $id]);
        $gallery_images = $galStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error loading project: " . $e->getMessage());
        set_flash('error', __('database_operation_failed'));
        header('Location: projects.php');
        exit;
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Delete gallery image action (Independent Form style)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_gallery_image') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            set_flash('error', __('csrf_invalid'));
            header('Location: project-form.php?id=' . $id);
            exit;
        }
        
        // Strict ID validation using FILTER_VALIDATE_INT
        $imageId = filter_var($_POST['image_id'] ?? null, FILTER_VALIDATE_INT);
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
        
        if ($imageId === false || $imageId <= 0 || $projectId === false || $projectId <= 0 || $projectId !== $id) {
            set_flash('error', __('invalid_gallery_image_id'));
            header('Location: project-form.php?id=' . $id);
            exit;
        }
        
        try {
            // Confirm the gallery image belongs to the specified project
            $imgStmt = $pdo->prepare("SELECT image_path FROM project_images WHERE id = :id AND project_id = :project_id");
            $imgStmt->execute([':id' => $imageId, ':project_id' => $projectId]);
            $imgRow = $imgStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$imgRow) {
                set_flash('error', __('gallery_image_not_found'));
            } else {
                $pdo->beginTransaction();
                $delStmt = $pdo->prepare("DELETE FROM project_images WHERE id = :id");
                $delStmt->execute([':id' => $imageId]);
                $affected = $delStmt->rowCount();
                $pdo->commit();
                
                if ($affected > 0) {
                    // Safe filesystem deletion after commit - verify return semantics
                    if (safe_delete_file($imgRow['image_path'])) {
                        set_flash('success', __('gallery_image_delete_success'));
                    } else {
                        error_log("Sprint Admin 4.2.1 Error: Gallery image file deletion failed.");
                        set_flash('success', __('gallery_image_delete_success') . ' ' . __('cleanup_incomplete_warning'));
                    }
                } else {
                    set_flash('error', __('gallery_image_not_found'));
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Database error deleting gallery image: " . $e->getMessage());
            set_flash('error', __('database_operation_failed'));
        }
        header('Location: project-form.php?id=' . $id);
        exit;
    }

    // 2. Normal Save Project Action
    if (isset($_POST['save_project'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = __('csrf_invalid');
        } else {
            // Fields validation
            $title_ar = trim($_POST['title_ar'] ?? '');
            $title_en = trim($_POST['title_en'] ?? '');
            $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
            $project_date = trim($_POST['project_date'] ?? '');
            $client_ar = trim($_POST['client_ar'] ?? '');
            $client_en = trim($_POST['client_en'] ?? '');
            $location_ar = trim($_POST['location_ar'] ?? '');
            $location_en = trim($_POST['location_en'] ?? '');
            $service_ar = trim($_POST['service_ar'] ?? '');
            $service_en = trim($_POST['service_en'] ?? '');
            $duration_ar = trim($_POST['duration_ar'] ?? '');
            $duration_en = trim($_POST['duration_en'] ?? '');
            $desc_ar = trim($_POST['desc_ar'] ?? '');
            $desc_en = trim($_POST['desc_en'] ?? '');
            $long_desc_ar = trim($_POST['long_desc_ar'] ?? '');
            $long_desc_en = trim($_POST['long_desc_en'] ?? '');
            
            // SEO fields
            $seo_title_ar = trim($_POST['seo_title_ar'] ?? '');
            $seo_title_en = trim($_POST['seo_title_en'] ?? '');
            $seo_description_ar = trim($_POST['seo_description_ar'] ?? '');
            $seo_description_en = trim($_POST['seo_description_en'] ?? '');

            // Fetch string category_key matching category_id for backward compatibility
            $category_key_legacy = '';
            if ($category_id && $category_id > 0) {
                $cat_record = get_project_category_by_id($category_id);
                if ($cat_record) {
                    $category_key_legacy = $cat_record['category_key'];
                }
            }

            // Calculate lengths safely (detect invalid UTF-8)
            $len_title_ar = safe_mb_strlen($title_ar);
            $len_title_en = safe_mb_strlen($title_en);
            $len_client_ar = safe_mb_strlen($client_ar);
            $len_client_en = safe_mb_strlen($client_en);
            $len_location_ar = safe_mb_strlen($location_ar);
            $len_location_en = safe_mb_strlen($location_en);
            $len_service_ar = safe_mb_strlen($service_ar);
            $len_service_en = safe_mb_strlen($service_en);
            $len_duration_ar = safe_mb_strlen($duration_ar);
            $len_duration_en = safe_mb_strlen($duration_en);
            $len_desc_ar = safe_mb_strlen($desc_ar);
            $len_desc_en = safe_mb_strlen($desc_en);
            $len_long_desc_ar = safe_mb_strlen($long_desc_ar);
            $len_long_desc_en = safe_mb_strlen($long_desc_en);
            $len_seo_title_ar = safe_mb_strlen($seo_title_ar);
            $len_seo_title_en = safe_mb_strlen($seo_title_en);
            $len_seo_description_ar = safe_mb_strlen($seo_description_ar);
            $len_seo_description_en = safe_mb_strlen($seo_description_en);

            // Normalization
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_latest = isset($_POST['is_latest']) ? 1 : 0;
            
            $sort_order = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);

            if ($len_title_ar === false || $len_title_en === false || $len_client_ar === false || $len_client_en === false ||
                $len_location_ar === false || $len_location_en === false || $len_service_ar === false || $len_service_en === false ||
                $len_duration_ar === false || $len_duration_en === false || $len_desc_ar === false || $len_desc_en === false ||
                $len_long_desc_ar === false || $len_long_desc_en === false || $len_seo_title_ar === false || $len_seo_title_en === false ||
                $len_seo_description_ar === false || $len_seo_description_en === false) {
                // Invalid UTF-8 produces a safe translated validation error instead of exception
                $error_message = __('field_length_violation');
            } elseif (empty($title_ar) || empty($title_en)) {
                $error_message = __('titles_required');
            } elseif ($len_title_ar > 255 || $len_title_en > 255 || $len_client_ar > 255 || $len_client_en > 255 || $len_location_ar > 255 || $len_location_en > 255 || $len_service_ar > 255 || $len_service_en > 255) {
                $error_message = __('field_length_violation');
            } elseif ($len_duration_ar > 100 || $len_duration_en > 100) {
                $error_message = __('field_length_violation');
            } elseif ($len_desc_ar > 500 || $len_desc_en > 500 || $len_seo_title_ar > 255 || $len_seo_title_en > 255 || $len_seo_description_ar > 500 || $len_seo_description_en > 500) {
                $error_message = __('field_length_violation');
            } elseif ($len_long_desc_ar > 5000 || $len_long_desc_en > 5000) {
                $error_message = __('field_length_violation');
            } elseif (!$category_id || $category_id <= 0 || empty($category_key_legacy)) {
                $error_message = __('invalid_category');
            } elseif (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $project_date)) {
                $error_message = __('invalid_project_date');
            } elseif ($sort_order === false || $sort_order < 0 || $sort_order > 9999) {
                $error_message = __('invalid_sort_order');
            } elseif (!$project && (!isset($_FILES['main_image_file']) || $_FILES['main_image_file']['error'] === UPLOAD_ERR_NO_FILE)) {
                $error_message = __('main_image_required');
            } else {
                $newFilesTracked = [];
                $filesToCleanOnSuccess = [];

                try {
                    // 1. Process main image
                    $main_image = $project ? $project['main_image'] : '';
                    if (isset($_FILES['main_image_file']) && $_FILES['main_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $uploaded = validate_and_upload_image($_FILES['main_image_file'], $newFilesTracked);
                        if ($uploaded) {
                            if ($main_image) $filesToCleanOnSuccess[] = $main_image;
                            $main_image = $uploaded;
                        }
                    }

                    // 2. Process optional before image
                    $before_image = $project ? $project['before_image'] : '';
                    if (isset($_FILES['before_image_file']) && $_FILES['before_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $uploaded = validate_and_upload_image($_FILES['before_image_file'], $newFilesTracked);
                        if ($uploaded) {
                            if ($before_image) $filesToCleanOnSuccess[] = $before_image;
                            $before_image = $uploaded;
                        }
                    }

                    // 3. Process optional after image
                    $after_image = $project ? $project['after_image'] : '';
                    if (isset($_FILES['after_image_file']) && $_FILES['after_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $uploaded = validate_and_upload_image($_FILES['after_image_file'], $newFilesTracked);
                        if ($uploaded) {
                            if ($after_image) $filesToCleanOnSuccess[] = $after_image;
                            $after_image = $uploaded;
                        }
                    }

                    // 4. Process multi-file gallery uploads
                    $uploadedGalleryPaths = [];
                    if (isset($_FILES['gallery_files'])) {
                        $files = $_FILES['gallery_files'];
                        
                        $fileCount = 0;
                        if (isset($files['name']) && is_array($files['name'])) {
                            $fileCount = count($files['name']);
                        }

                        $validFilesCount = 0;
                        for ($i = 0; $i < $fileCount; $i++) {
                            if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                                $validFilesCount++;
                            }
                        }

                        if ($validFilesCount > 0) {
                            $existingCount = count($gallery_images);
                            if (($existingCount + $validFilesCount) > 10) {
                                throw new ProjectValidationException(__('gallery_limit_exceeded'));
                            }

                            for ($i = 0; $i < $fileCount; $i++) {
                                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                                    continue;
                                }

                                $singleFile = [
                                    'name' => $files['name'][$i],
                                    'type' => $files['type'][$i],
                                    'tmp_name' => $files['tmp_name'][$i],
                                    'error' => $files['error'][$i],
                                    'size' => $files['size'][$i],
                                ];
                                $uploaded = validate_and_upload_image($singleFile, $newFilesTracked);
                                if ($uploaded) {
                                    $uploadedGalleryPaths[] = $uploaded;
                                }
                            }
                        }
                    }

                    // DB transaction starts ONLY after upload validation succeeds
                    $pdo->beginTransaction();

                    if ($id > 0) {
                        // Confirm project still exists
                        $existCheck = $pdo->prepare("SELECT id FROM projects WHERE id = :id");
                        $existCheck->execute([':id' => $id]);
                        if (!$existCheck->fetch()) {
                            throw new ProjectValidationException(__('project_not_found'));
                        }

                        // Update projects
                        $stmt = $pdo->prepare("UPDATE projects SET 
                            title_ar = :title_ar, title_en = :title_en,
                            category_id = :category_id, category = :category, project_date = :project_date,
                            client_ar = :client_ar, client_en = :client_en,
                            location_ar = :location_ar, location_en = :location_en,
                            service_ar = :service_ar, service_en = :service_en,
                            duration_ar = :duration_ar, duration_en = :duration_en,
                            desc_ar = :desc_ar, desc_en = :desc_en,
                            long_desc_ar = :long_desc_ar, long_desc_en = :long_desc_en,
                            main_image = :main_image, before_image = :before_image, after_image = :after_image,
                            seo_title_ar = :seo_title_ar, seo_title_en = :seo_title_en,
                            seo_description_ar = :seo_description_ar, seo_description_en = :seo_description_en,
                            is_active = :is_active, is_featured = :is_featured, is_latest = :is_latest,
                            sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP
                            WHERE id = :id");
                        $stmt->execute([
                            ':title_ar' => $title_ar, ':title_en' => $title_en,
                            ':category_id' => $category_id, ':category' => $category_key_legacy, ':project_date' => $project_date,
                            ':client_ar' => $client_ar, ':client_en' => $client_en,
                            ':location_ar' => $location_ar, ':location_en' => $location_en,
                            ':service_ar' => $service_ar, ':service_en' => $service_en,
                            ':duration_ar' => $duration_ar, ':duration_en' => $duration_en,
                            ':desc_ar' => $desc_ar, ':desc_en' => $desc_en,
                            ':long_desc_ar' => $long_desc_ar, ':long_desc_en' => $long_desc_en,
                            ':main_image' => $main_image, ':before_image' => $before_image, ':after_image' => $after_image,
                            ':seo_title_ar' => $seo_title_ar, ':seo_title_en' => $seo_title_en,
                            ':seo_description_ar' => $seo_description_ar, ':seo_description_en' => $seo_description_en,
                            ':is_active' => $is_active, ':is_featured' => $is_featured, ':is_latest' => $is_latest,
                            ':sort_order' => $sort_order, ':id' => $id
                        ]);
                        $projectId = $id;
                    } else {
                        $slug = generate_project_slug($title_en, $title_ar, $pdo);
                        
                        $stmt = $pdo->prepare("INSERT INTO projects (
                            slug, title_ar, title_en, category_id, category, project_date,
                            client_ar, client_en, location_ar, location_en,
                            service_ar, service_en, duration_ar, duration_en,
                            desc_ar, desc_en, long_desc_ar, long_desc_en,
                            main_image, before_image, after_image,
                            seo_title_ar, seo_title_en, seo_description_ar, seo_description_en,
                            is_active, is_featured, is_latest, sort_order
                        ) VALUES (
                            :slug, :title_ar, :title_en, :category_id, :category, :project_date,
                            :client_ar, :client_en, :location_ar, :location_en,
                            :service_ar, :service_en, :duration_ar, :duration_en,
                            :desc_ar, :desc_en, :long_desc_ar, :long_desc_en,
                            :main_image, :before_image, :after_image,
                            :seo_title_ar, :seo_title_en, :seo_description_ar, :seo_description_en,
                            :is_active, :is_featured, :is_latest, :sort_order
                        )");
                        $stmt->execute([
                            ':slug' => $slug, ':title_ar' => $title_ar, ':title_en' => $title_en,
                            ':category_id' => $category_id, ':category' => $category_key_legacy, ':project_date' => $project_date,
                            ':client_ar' => $client_ar, ':client_en' => $client_en,
                            ':location_ar' => $location_ar, ':location_en' => $location_en,
                            ':service_ar' => $service_ar, ':service_en' => $service_en,
                            ':duration_ar' => $duration_ar, ':duration_en' => $duration_en,
                            ':desc_ar' => $desc_ar, ':desc_en' => $desc_en,
                            ':long_desc_ar' => $long_desc_ar, ':long_desc_en' => $long_desc_en,
                            ':main_image' => $main_image, ':before_image' => $before_image, ':after_image' => $after_image,
                            ':seo_title_ar' => $seo_title_ar, ':seo_title_en' => $seo_title_en,
                            ':seo_description_ar' => $seo_description_ar, ':seo_description_en' => $seo_description_en,
                            ':is_active' => $is_active, ':is_featured' => $is_featured, ':is_latest' => $is_latest,
                            ':sort_order' => $sort_order
                        ]);
                        $projectId = $pdo->lastInsertId();
                    }

                    // Insert gallery rows
                    foreach ($uploadedGalleryPaths as $gp) {
                        $galIns = $pdo->prepare("INSERT INTO project_images (project_id, image_path) VALUES (:project_id, :image_path)");
                        $galIns->execute([':project_id' => $projectId, ':image_path' => $gp]);
                    }

                    // Related products synchronization
                    $selected_product_ids = [];
                    if (isset($_POST['related_product_ids']) && is_array($_POST['related_product_ids'])) {
                        foreach ($_POST['related_product_ids'] as $pid_val) {
                            $pid_int = filter_var($pid_val, FILTER_VALIDATE_INT);
                            if ($pid_int !== false && $pid_int > 0) {
                                $selected_product_ids[] = $pid_int;
                            }
                        }
                    }

                    // Fetch all products to update their related_project_ids column
                    $all_products_stmt = $pdo->query("SELECT id, is_active, name_ar, name_en, category, related_project_ids FROM products");
                    $all_db_products = $all_products_stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Map active products IDs in database
                    $active_product_ids = [];
                    foreach ($all_db_products as $p) {
                        if ($p['is_active']) {
                            $active_product_ids[] = (int)$p['id'];
                        }
                    }

                    foreach ($all_db_products as $p) {
                        $p_id = (int)$p['id'];
                        $is_active_product = in_array($p_id, $active_product_ids, true);

                        // Decode current related projects safely
                        $raw_ids = get_product_json_field($p, 'related_project_ids');
                        if (!is_array($raw_ids)) {
                            $raw_ids = [];
                        }
                        
                        // Parse as clean unique integer array
                        $current_project_ids = [];
                        foreach ($raw_ids as $val) {
                            $c_val = (int)$val;
                            if ($c_val > 0 && !in_array($c_val, $current_project_ids, true)) {
                                $current_project_ids[] = $c_val;
                            }
                        }

                        // Only sync active products; completely touch-free for inactive ones
                        if ($is_active_product) {
                            $should_be_related = in_array($p_id, $selected_product_ids, true);
                            if ($should_be_related) {
                                if (!in_array((int)$projectId, $current_project_ids, true)) {
                                    $current_project_ids[] = (int)$projectId;
                                }
                            } else {
                                $current_project_ids = array_diff($current_project_ids, [(int)$projectId]);
                            }
                            
                            // Re-normalize indices
                            $current_project_ids = array_values($current_project_ids);
                            $new_json = empty($current_project_ids) ? null : json_encode($current_project_ids);

                            // Update database record
                            $up_stmt = $pdo->prepare("UPDATE products SET related_project_ids = :ids, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                            $up_stmt->execute([':ids' => $new_json, ':id' => $p_id]);
                        }
                    }

                    $pdo->commit();

                    // Successful commit: delete replaced files from the filesystem without error suppression
                    $cleanupFailed = false;
                    foreach ($filesToCleanOnSuccess as $oldFile) {
                        if (!safe_delete_file($oldFile)) {
                            $cleanupFailed = true;
                        }
                    }

                    if ($cleanupFailed) {
                        set_flash('success', __('project_save_success') . ' ' . __('cleanup_incomplete_warning'));
                    } else {
                        set_flash('success', __('project_save_success'));
                    }
                    
                    header('Location: projects.php');
                    exit;

                } catch (ProjectValidationException $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    rollback_cleanup_files($newFilesTracked);
                    $error_message = $ex->getMessage();
                } catch (PDOException $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    rollback_cleanup_files($newFilesTracked);
                    error_log("Database transaction failed during project save: " . $ex->getMessage());
                    $error_message = __('database_operation_failed');
                } catch (Throwable $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    rollback_cleanup_files($newFilesTracked);
                    error_log("Unexpected error during project save: " . $ex->getMessage());
                    $error_message = get_current_lang() === 'ar' ? 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.' : 'An unexpected error occurred. Please try again.';
                }
            }
        }
    }
}

$page_title = $id > 0 ? __('admin_edit_project') : __('admin_add_project');

// Fetch active products for the related products checklist
$products_stmt = $pdo->query("SELECT id, name_ar, name_en, category, related_project_ids FROM products WHERE is_active = 1 ORDER BY name_ar ASC");
$active_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active categories only by default
$form_categories = get_project_categories(true);

// If editing a project whose assigned category is inactive, include that category with an (Inactive) badge
if ($project) {
    $curr_cat_id = (int)($project['category_id'] ?? 0);
    $curr_cat_key = (string)($project['category'] ?? '');
    
    $already_in_list = false;
    foreach ($form_categories as $fc) {
        if ($curr_cat_id > 0 && (int)$fc['id'] === $curr_cat_id) {
            $already_in_list = true;
            break;
        } elseif (!empty($curr_cat_key) && $fc['category_key'] === $curr_cat_key) {
            $already_in_list = true;
            break;
        }
    }

    if (!$already_in_list) {
        // Fetch inactive category to preserve relationship
        $inactive_record = null;
        if ($curr_cat_id > 0) {
            $inactive_record = get_project_category_by_id($curr_cat_id);
        }
        if ($inactive_record) {
            $form_categories[] = $inactive_record;
        }
    }
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
$lang = get_current_lang();
?>

<!-- Main Workspace Container -->
<main class="admin-content">
    <!-- Compact Page Header -->
    <header class="admin-header" style="padding-bottom: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                <a href="index.php" style="color: var(--text-muted); text-decoration: none;"><?php echo htmlspecialchars(__('brand_name')); ?></a>
                <span>/</span>
                <a href="projects.php" style="color: var(--text-muted); text-decoration: none;"><?php echo $lang === 'ar' ? 'المشاريع' : 'Projects'; ?></a>
                <span>/</span>
                <span><?php echo htmlspecialchars($page_title); ?></span>
            </div>
            <h1 style="font-size: 22px; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>

        <a href="projects.php" class="btn btn-secondary btn-sm" style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;">
            <i data-lucide="arrow-right" class="rtl-only" style="width:15px;"></i>
            <i data-lucide="arrow-left" class="ltr-only" style="width:15px;"></i>
            <span><?php echo __('back_to_list'); ?></span>
        </a>
    </header>

    <!-- Message Alerts -->
    <?php if ($error_message): ?>
        <div class="alert-danger" style="background-color: var(--danger-light); color: var(--danger); padding: 14px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <section class="admin-table-card" style="padding: 24px;">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="save_project" value="1">

            <!-- Title & Basic Info -->
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="title_ar"><?php echo __('project_title_ar'); ?> *</label>
                    <input type="text" name="title_ar" id="title_ar" class="form-control" dir="rtl" maxlength="255" value="<?php echo $project ? htmlspecialchars($project['title_ar']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="title_en"><?php echo __('project_title_en'); ?> *</label>
                    <input type="text" name="title_en" id="title_en" class="form-control" dir="ltr" maxlength="255" value="<?php echo $project ? htmlspecialchars($project['title_en']) : ''; ?>" required>
                </div>
            </div>

            <!-- Client Info -->
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="client_ar"><?php echo __('project_client'); ?> (عربي)</label>
                    <input type="text" name="client_ar" id="client_ar" class="form-control" dir="rtl" maxlength="255" placeholder="مثال: شركة الرمز للمستودعات" value="<?php echo $project ? htmlspecialchars($project['client_ar'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="client_en"><?php echo __('project_client'); ?> (إنجليزي)</label>
                    <input type="text" name="client_en" id="client_en" class="form-control" dir="ltr" maxlength="255" placeholder="e.g. Al-Ramz Warehousing Co." value="<?php echo $project ? htmlspecialchars($project['client_en'] ?? '') : ''; ?>">
                </div>
            </div>

            <!-- Location Details -->
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="location_ar"><?php echo __('project_location'); ?> (عربي)</label>
                    <input type="text" name="location_ar" id="location_ar" class="form-control" dir="rtl" maxlength="255" placeholder="مثال: الرياض، المملكة العربية السعودية" value="<?php echo $project ? htmlspecialchars($project['location_ar'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="location_en"><?php echo __('project_location'); ?> (إنجليزي)</label>
                    <input type="text" name="location_en" id="location_en" class="form-control" dir="ltr" maxlength="255" placeholder="e.g. Riyadh, Saudi Arabia" value="<?php echo $project ? htmlspecialchars($project['location_en'] ?? '') : ''; ?>">
                </div>
            </div>

            <!-- Service Details -->
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="service_ar"><?php echo __('project_service'); ?> (عربي)</label>
                    <input type="text" name="service_ar" id="service_ar" class="form-control" dir="rtl" maxlength="255" placeholder="مثال: توريد وتركيب أرفف حديدية خفيفة" value="<?php echo $project ? htmlspecialchars($project['service_ar'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="service_en"><?php echo __('project_service'); ?> (إنجليزي)</label>
                    <input type="text" name="service_en" id="service_en" class="form-control" dir="ltr" maxlength="255" placeholder="e.g. Supply & Installation of Light Duty Shelves" value="<?php echo $project ? htmlspecialchars($project['service_en'] ?? '') : ''; ?>">
                </div>
            </div>

            <!-- Duration Details -->
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="duration_ar"><?php echo __('project_duration'); ?> (عربي)</label>
                    <input type="text" name="duration_ar" id="duration_ar" class="form-control" dir="rtl" maxlength="100" placeholder="مثال: 5 أيام عمل" value="<?php echo $project ? htmlspecialchars($project['duration_ar'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="duration_en"><?php echo __('project_duration'); ?> (إنجليزي)</label>
                    <input type="text" name="duration_en" id="duration_en" class="form-control" dir="ltr" maxlength="100" placeholder="e.g. 5 Business Days" value="<?php echo $project ? htmlspecialchars($project['duration_en'] ?? '') : ''; ?>">
                </div>
            </div>

            <!-- Category & Date -->
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="category_id"><?php echo __('project_category'); ?> *</label>
                    <select name="category_id" id="category_id" class="form-control" required>
                        <option value=""><?php echo get_current_lang() === 'ar' ? '-- اختر تصنيف المشروع --' : '-- Select Category --'; ?></option>
                        <?php foreach ($form_categories as $c): ?>
                            <?php 
                            $c_label = (get_current_lang() === 'ar') ? $c['name_ar'] : $c['name_en']; 
                            $is_sel = false;
                            if ($project) {
                                if (!empty($project['category_id']) && (int)$project['category_id'] === (int)$c['id']) {
                                    $is_sel = true;
                                } elseif (!empty($project['category']) && $project['category'] === $c['category_key']) {
                                    $is_sel = true;
                                }
                            }
                            ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $is_sel ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c_label); ?><?php echo !$c['is_active'] ? (' (' . (get_current_lang() === 'ar' ? 'معطل' : 'Inactive') . ')') : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="project_date"><?php echo __('admin_project_date_label'); ?> *</label>
                    <input type="text" name="project_date" id="project_date" class="form-control" dir="ltr" placeholder="YYYY-MM (e.g. 2025-06)" value="<?php echo $project ? htmlspecialchars($project['project_date']) : ''; ?>" required>
                </div>
            </div>

            <!-- Short Descriptions -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="desc_ar"><?php echo __('project_desc_ar'); ?> (Max 500)</label>
                <textarea name="desc_ar" id="desc_ar" class="form-control" dir="rtl" rows="2" maxlength="500"><?php echo $project ? htmlspecialchars($project['desc_ar']) : ''; ?></textarea>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="desc_en"><?php echo __('project_desc_en'); ?> (Max 500)</label>
                <textarea name="desc_en" id="desc_en" class="form-control" dir="ltr" rows="2" maxlength="500"><?php echo $project ? htmlspecialchars($project['desc_en']) : ''; ?></textarea>
            </div>

            <!-- Long Descriptions -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="long_desc_ar"><?php echo __('project_long_desc_ar'); ?> (Max 5000)</label>
                <textarea name="long_desc_ar" id="long_desc_ar" class="form-control" dir="rtl" rows="4" maxlength="5000"><?php echo $project ? htmlspecialchars($project['long_desc_ar']) : ''; ?></textarea>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="long_desc_en"><?php echo __('project_long_desc_en'); ?> (Max 5000)</label>
                <textarea name="long_desc_en" id="long_desc_en" class="form-control" dir="ltr" rows="4" maxlength="5000"><?php echo $project ? htmlspecialchars($project['long_desc_en']) : ''; ?></textarea>
            </div>

            <!-- Upload Primary Images -->
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="main_image_file"><?php echo __('project_main_image'); ?> <?php echo !$project ? '*' : ''; ?> (JPEG, PNG, WebP only)</label>
                    <input type="file" name="main_image_file" id="main_image_file" class="form-control" accept="image/jpeg,image/png,image/webp" <?php echo !$project ? 'required' : ''; ?>>
                    <?php if ($project && !empty($project['main_image'])): ?>
                        <div style="margin-top: 10px;">
                            <img src="../<?php echo htmlspecialchars($project['main_image']); ?>" alt="Main preview" style="max-width: 100%; max-height: 100px; border-radius: var(--radius-sm);">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="before_image_file"><?php echo __('project_before_image'); ?> (JPEG, PNG, WebP only)</label>
                    <input type="file" name="before_image_file" id="before_image_file" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <?php if ($project && !empty($project['before_image'])): ?>
                        <div style="margin-top: 10px;">
                            <img src="../<?php echo htmlspecialchars($project['before_image']); ?>" alt="Before preview" style="max-width: 100%; max-height: 100px; border-radius: var(--radius-sm);">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="after_image_file"><?php echo __('project_after_image'); ?> (JPEG, PNG, WebP only)</label>
                    <input type="file" name="after_image_file" id="after_image_file" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <?php if ($project && !empty($project['after_image'])): ?>
                        <div style="margin-top: 10px;">
                            <img src="../<?php echo htmlspecialchars($project['after_image']); ?>" alt="After preview" style="max-width: 100%; max-height: 100px; border-radius: var(--radius-sm);">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upload Multiple Gallery Images -->
            <div class="form-group" style="margin-bottom: 20px; background: #fafafa; border: 1px solid var(--border-color); padding: 16px; border-radius: var(--radius-md);">
                <label for="gallery_files" style="font-weight: 700;"><?php echo __('project_gallery'); ?> (JPEG, PNG, WebP only - Max 10 total)</label>
                <input type="file" name="gallery_files[]" id="gallery_files" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
            </div>

            <!-- SEO Settings (Bilingual) -->
            <div style="margin-top: 30px; margin-bottom: 30px; background: #fafafa; border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-md);">
                <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 8px; font-weight: 700;">
                    <?php echo __('seo_settings_title'); ?>
                </h3>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="seo_title_ar"><?php echo get_current_lang() === 'ar' ? 'عنوان SEO (بالعربية)' : 'SEO Title (Arabic)'; ?></label>
                        <input type="text" name="seo_title_ar" id="seo_title_ar" class="form-control" dir="rtl" maxlength="255" value="<?php echo $project ? htmlspecialchars($project['seo_title_ar']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="seo_title_en"><?php echo get_current_lang() === 'ar' ? 'عنوان SEO (بالإنجليزية)' : 'SEO Title (English)'; ?></label>
                        <input type="text" name="seo_title_en" id="seo_title_en" class="form-control" dir="ltr" maxlength="255" value="<?php echo $project ? htmlspecialchars($project['seo_title_en']) : ''; ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="seo_description_ar"><?php echo get_current_lang() === 'ar' ? 'وصف SEO (بالعربية)' : 'SEO Description (Arabic)'; ?></label>
                    <textarea name="seo_description_ar" id="seo_description_ar" class="form-control" dir="rtl" rows="2" maxlength="500"><?php echo $project ? htmlspecialchars($project['seo_description_ar']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label for="seo_description_en"><?php echo get_current_lang() === 'ar' ? 'وصف SEO (بالإنجليزية)' : 'SEO Description (English)'; ?></label>
                    <textarea name="seo_description_en" id="seo_description_en" class="form-control" dir="ltr" rows="2" maxlength="500"><?php echo $project ? htmlspecialchars($project['seo_description_en']) : ''; ?></textarea>
                </div>
            </div>

            <!-- Status Flags & Promotion -->
            <div style="margin-top: 15px; margin-bottom: 20px; background: #fafafa; border: 1px solid var(--border-color); padding: 16px; border-radius: var(--radius-md);">
                <h4 style="margin-top:0; margin-bottom:12px; font-size:14px; border-bottom:1px solid #eee; padding-bottom:6px;"><?php echo __('visibility_promotion_title'); ?></h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" name="is_featured" value="1" <?php echo ($project && !empty($project['is_featured'])) ? 'checked' : ''; ?>>
                        <span><?php echo __('show_on_homepage'); ?></span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" name="is_latest" value="1" <?php echo ($project && !empty($project['is_latest'])) ? 'checked' : ''; ?>>
                        <span><?php echo __('project_latest'); ?></span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" <?php echo (!$project || !isset($project['is_active']) || $project['is_active']) ? 'checked' : ''; ?>>
                        <span><?php echo __('active'); ?></span>
                    </label>
                </div>
            </div>

            <!-- Related Products Section -->
            <div style="margin-top: 30px; margin-bottom: 30px; background: #fafafa; border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-md);">
                <h3 style="margin-top: 0; margin-bottom: 8px; font-size: 16px; font-weight: 700; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                    <?php echo get_current_lang() === 'ar' ? 'المنتجات المرتبطة بالمشروع' : 'Related Products'; ?>
                </h3>
                <div style="margin-bottom: 12px;">
                    <input type="text" id="product-search-input" class="form-control" placeholder="<?php echo get_current_lang() === 'ar' ? '🔍 ابحث عن منتج بالاسم أو التصنيف...' : '🔍 Search product by name or category...'; ?>" oninput="filterProductsChecklist()" style="max-width: 400px; margin-bottom: 12px;">
                </div>
                <div id="products-checklist-container" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; max-height: 300px; overflow-y: auto; padding: 4px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); background: #ffffff;">
                    <?php foreach ($active_products as $ap): 
                        // Determine if already related safely
                        $is_related = false;
                        if ($project && $id > 0) {
                            $proj_ids = get_product_json_field($ap, 'related_project_ids');
                            if (is_array($proj_ids)) {
                                $clean_proj_ids = array_map('intval', $proj_ids);
                                if (in_array((int)$id, $clean_proj_ids, true)) {
                                    $is_related = true;
                                }
                            }
                        }
                        $ap_name_ar = htmlspecialchars($ap['name_ar']);
                        $ap_name_en = htmlspecialchars($ap['name_en']);
                        $ap_category = htmlspecialchars($ap['category']);
                    ?>
                        <label class="product-checklist-item" data-search-term="<?php echo strtolower($ap_name_ar . ' ' . $ap_name_en . ' ' . $ap_category); ?>" style="display:flex; align-items:flex-start; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; padding:10px; border-radius:6px; font-size:12.5px; cursor:pointer; transition: all 0.2s;">
                            <input type="checkbox" name="related_product_ids[]" value="<?php echo $ap['id']; ?>" <?php echo $is_related ? 'checked' : ''; ?> style="margin-top: 3px;">
                            <div>
                                <span style="font-weight: 700; display: block; color: var(--text-primary);"><?php echo get_current_lang() === 'ar' ? $ap_name_ar : $ap_name_en; ?></span>
                                <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">
                                    <?php echo get_current_lang() === 'ar' ? $ap_name_en : $ap_name_ar; ?>
                                </span>
                                <span style="display: inline-block; font-size: 10px; background: #e2e8f0; color: #475569; padding: 2px 6px; border-radius: 4px; margin-top: 4px; font-weight: 600;">
                                    <?php echo $ap_category; ?>
                                </span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <script>
            function filterProductsChecklist() {
                const query = document.getElementById('product-search-input').value.toLowerCase().trim();
                const items = document.querySelectorAll('.product-checklist-item');
                items.forEach(item => {
                    const term = item.getAttribute('data-search-term');
                    if (term.includes(query)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
            </script>

            <!-- Sort Order -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="sort_order"><?php echo __('sort_order'); ?></label>
                <input type="number" name="sort_order" id="sort_order" class="form-control" dir="ltr" min="0" max="9999" value="<?php echo $project ? intval($project['sort_order']) : '0'; ?>">
            </div>

            <!-- Form Actions -->
            <div class="admin-modal-footer" style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <a href="projects.php" class="btn btn-secondary"><?php echo __('admin_cancel'); ?></a>
                <button type="submit" class="btn btn-primary"><?php echo __('admin_save'); ?></button>
            </div>
        </form>
    </section>

    <!-- Gallery Image List & Individual Delete Forms -->
    <?php if ($id > 0 && !empty($gallery_images)): ?>
        <section class="admin-table-card" style="padding: 24px; margin-top: 30px;">
            <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 8px; font-weight: 700;">
                <?php echo __('manage_gallery_images'); ?>
            </h3>
            
            <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                <?php foreach ($gallery_images as $gimg): ?>
                    <div style="position: relative; width: 120px; height: 120px; border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; background: #f8fafc; display: flex; align-items: center; justify-content: center;">
                        <img src="../<?php echo htmlspecialchars($gimg['image_path']); ?>" alt="Gallery item" style="max-width:100%; max-height:100%; object-fit:contain;">
                        
                        <!-- Independent small delete form -->
                        <form method="POST" action="" style="position: absolute; top: 6px; right: 6px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="delete_gallery_image">
                            <input type="hidden" name="image_id" value="<?php echo $gimg['id']; ?>">
                            <input type="hidden" name="project_id" value="<?php echo $id; ?>">
                            
                            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px; line-height: 1; color: var(--danger); border-color: var(--danger-light); background: #ffffffcc;" onclick="return confirm('<?php echo __('gallery_delete_confirm'); ?>')">
                                <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
