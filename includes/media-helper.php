<?php
// includes/media-helper.php
// Centralized helper functions for managing the Media Library.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/admin-upload-helper.php';

/**
 * Returns the allowlisted physical-to-logical folder mapping.
 */
function get_media_folder_map() {
    return [
        'general'      => 'media/general',
        'branding'     => 'media/branding',
        'homepage'     => 'media/homepage',
        'about'        => 'media/about',
        'services'     => 'media/services',
        'products'     => 'media/products',
        'projects'     => 'media/projects',
        'testimonials' => 'media/testimonials',
        'clients'      => 'media/clients',
        'seo'          => 'media/seo'
    ];
}

/**
 * Checks which of the confirmed reference columns actually exist in the active schema.
 */
function get_active_schema_reference_columns($pdo) {
    $columns = [];
    $candidates = [
        'users'          => ['image'],
        'products'       => ['image_url'],
        'projects'       => ['main_image', 'before_image', 'after_image'],
        'project_images' => ['image_path'],
        'portfolio'      => ['image_url'],
        'services'       => ['image_path'],
        'categories'     => ['image_path'],
        'hero_slides'    => ['image_path'],
        'product_images' => ['image_path'],
        'product_colors' => ['image_path'],
        'product_models' => ['image_path']
    ];

    foreach ($candidates as $table => $cols) {
        try {
            $test = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            if ($test !== false) {
                foreach ($cols as $col) {
                    try {
                        $col_test = $pdo->query("SELECT $col FROM $table LIMIT 1");
                        if ($col_test !== false) {
                            $columns[$table][] = $col;
                        }
                    } catch (PDOException $e) {
                        // Column does not exist
                    }
                }
            }
        } catch (PDOException $e) {
            // Table does not exist
        }
    }
    return $columns;
}

/**
 * Checks if a relative image path is referenced by any setting or database record.
 * Uses exact path equality.
 */
function is_media_file_in_use($pdo, $file_path) {
    if (empty($file_path)) {
        return false;
    }

    // 1. Check settings table (checking only confirmed image setting keys)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings 
            WHERE key_name IN ('site_logo', 'site_logo_dark', 'site_favicon', 'default_og_image', 'hero_image', 'about_image', 'about_cta_bg_image', 'seo_about_og_image', 'footer_logo', 'seo_contact_og_image', 'seo_used_og_image', 'seo_install_og_image', 'services_why_1_image', 'services_why_2_image', 'services_why_3_image', 'services_why_4_image', 'services_why_5_image', 'services_why_6_image') 
              AND val = :file_path");
        $stmt->execute([':file_path' => $file_path]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true;
        }
    } catch (PDOException $e) {
        error_log("Media usage check settings error: " . $e->getMessage());
        return true; // Fail-closed
    }

    // 2. Check other schema tables that dynamically exist in the database
    $active_refs = get_active_schema_reference_columns($pdo);
    foreach ($active_refs as $table => $cols) {
        foreach ($cols as $col) {
            try {
                // Precise path equality check
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $col = :file_path");
                $stmt->execute([':file_path' => $file_path]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return true;
                }
            } catch (PDOException $e) {
                error_log("Media usage check error on table $table ($col): " . $e->getMessage());
                return true; // Fail-closed
            }
        }
    }

    return false;
}

/**
 * Uploads a file to the Media Library, registers it in the database.
 * If database insert fails, rolls back the physical file.
 */
function upload_to_media_library($pdo, $fileInfo, $folder, $alt_ar = '', $alt_en = '', $title_ar = '', $title_en = '') {
    $folder_map = get_media_folder_map();
    if (!array_key_exists($folder, $folder_map)) {
        throw new ProjectValidationException(get_current_lang() === 'ar' ? 'فولدر تصنيف غير صالح.' : 'Invalid category folder.');
    }

    // 1. Enforce strict image dimensions and readability using getimagesize
    if (empty($fileInfo['tmp_name']) || !file_exists($fileInfo['tmp_name'])) {
        throw new ProjectValidationException(get_current_lang() === 'ar' ? 'ملف الرفع غير متوفر.' : 'Uploaded file not found.');
    }

    set_error_handler(static function () { return true; });
    try {
        $imgSize = getimagesize($fileInfo['tmp_name']);
    } finally {
        restore_error_handler();
    }

    if ($imgSize === false || empty($imgSize[0]) || empty($imgSize[1])) {
        throw new ProjectValidationException(get_current_lang() === 'ar' ? 'الملف تالف أو غير صالح للقياس.' : 'File is corrupt or invalid image.');
    }

    $width = (int)$imgSize[0];
    $height = (int)$imgSize[1];
    $mime = $imgSize['mime'];
    $ext = get_extension_from_mime($mime);

    // 2. Perform the upload using existing secure helper
    $newFilesTracked = [];
    $subDirName = $folder_map[$folder]; // e.g. 'media/general'
    
    // We use folder name as file name prefix
    $prefix = $folder;

    $uploaded_path = validate_and_upload_image($fileInfo, $newFilesTracked, $subDirName, $prefix);
    if (!$uploaded_path) {
        throw new ProjectValidationException(get_current_lang() === 'ar' ? 'فشل رفع الملف.' : 'File upload failed.');
    }

    // 3. Database record insertion
    try {
        $uploaded_by = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : null;
        $original_filename = basename($fileInfo['name']);

        $stmt = $pdo->prepare("INSERT INTO media_library 
            (filename, stored_name, file_path, mime_type, extension, file_size, width, height, alt_text_ar, alt_text_en, title_ar, title_en, folder, uploaded_by_username)
            VALUES (:filename, :stored_name, :file_path, :mime_type, :extension, :file_size, :width, :height, :alt_text_ar, :alt_text_en, :title_ar, :title_en, :folder, :uploaded_by)");
        
        $stmt->execute([
            ':filename' => $original_filename,
            ':stored_name' => basename($uploaded_path),
            ':file_path' => $uploaded_path,
            ':mime_type' => $mime,
            ':extension' => $ext,
            ':file_size' => $fileInfo['size'],
            ':width' => $width,
            ':height' => $height,
            ':alt_text_ar' => !empty($alt_ar) ? trim($alt_ar) : null,
            ':alt_text_en' => !empty($alt_en) ? trim($alt_en) : null,
            ':title_ar' => !empty($title_ar) ? trim($title_ar) : null,
            ':title_en' => !empty($title_en) ? trim($title_en) : null,
            ':folder' => $folder,
            ':uploaded_by' => $uploaded_by
        ]);

        return $pdo->lastInsertId();

    } catch (Throwable $e) {
        // Physical upload rollback on database failure
        rollback_cleanup_files($newFilesTracked);
        error_log("Media Library DB insertion failed: " . $e->getMessage());
        throw new Exception(get_current_lang() === 'ar' ? 'فشلت عملية التسجيل في قاعدة البيانات.' : 'Database registration failed.');
    }
}

/**
 * Safely deletes a media item from the database and physical directory.
 * If file is missing physically, still cleans the DB record.
 */
function delete_from_media_library($pdo, $id) {
    // 1. Fetch record
    $stmt = $pdo->prepare("SELECT * FROM media_library WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$media) {
        return ['success' => false, 'message' => __('media_invalid_id')];
    }

    $relative_path = $media['file_path'];
    $absolute_path = dirname(__DIR__) . '/' . $relative_path;

    // 2. Security: Verify path is inside the approved media base directory
    $approved_base = dirname(__DIR__) . '/assets/images/media';
    $real_approved = realpath($approved_base);
    $real_file = realpath(dirname($absolute_path));

    if ($real_approved === false || $real_file === false || strpos($real_file, $real_approved) !== 0) {
        return ['success' => false, 'message' => __('media_path_traversal_warning')];
    }

    // 3. Usage check: Verify not referenced anywhere
    if (is_media_file_in_use($pdo, $relative_path)) {
        return ['success' => false, 'message' => __('media_file_in_use')];
    }

    // 4. Physical deletion
    if (file_exists($absolute_path)) {
        if (!unlink($absolute_path)) {
            return ['success' => false, 'message' => __('media_delete_physical_failed')];
        }
        
        // Delete database record only after physical success
        $delete_stmt = $pdo->prepare("DELETE FROM media_library WHERE id = :id");
        $delete_stmt->execute([':id' => $id]);
        return ['success' => true, 'message' => __('media_delete_success')];
    } else {
        // Physical file is already missing. Clean the DB record and report warning-success
        $delete_stmt = $pdo->prepare("DELETE FROM media_library WHERE id = :id");
        $delete_stmt->execute([':id' => $id]);
        
        error_log("Media Library Warning: Physical file was already missing during record cleanup: " . $absolute_path);
        return [
            'success' => true, 
            'message' => get_current_lang() === 'ar' 
                ? 'تم تنظيف سجل قاعدة البيانات للملف المفقود بنجاح.' 
                : 'Database record cleaned successfully for already missing file.'
        ];
    }
}

/**
 * Returns alternative alt-text display strategy.
 */
function get_media_alt($media, $lang = 'ar') {
    if (is_array($media)) {
        if ($lang === 'ar') {
            return !empty($media['alt_text_ar']) ? $media['alt_text_ar'] : (!empty($media['title_ar']) ? $media['title_ar'] : $media['filename']);
        } else {
            return !empty($media['alt_text_en']) ? $media['alt_text_en'] : (!empty($media['title_en']) ? $media['title_en'] : $media['filename']);
        }
    }
    return '';
}
