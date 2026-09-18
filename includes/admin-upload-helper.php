<?php
// includes/admin-upload-helper.php
// Reusable image validation, upload and safe deletion helpers for admin panels.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

// Ensure the custom validation exception class exists
if (!class_exists('ProjectValidationException')) {
    class ProjectValidationException extends Exception {}
}

/**
 * Safe MIME type extension mapper.
 */
if (!function_exists('get_extension_from_mime')) {
    function get_extension_from_mime($mime) {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico'
        ];
        return isset($map[$mime]) ? $map[$mime] : null;
    }
}

/**
 * Upload directory validator and creator.
 * Verifies if directory exists and is writable.
 */
if (!function_exists('ensure_upload_directory')) {
    function ensure_upload_directory($subDirName = 'projects') {
        // Strict subdirectory validation
        $allowedSubDirs = ['projects', 'products', 'settings', 'media'];
        $baseDir = explode('/', $subDirName)[0];
        if (!in_array($baseDir, $allowedSubDirs, true)) {
            error_log("Sprint Admin Media 4.5.1 Error: Unauthorized upload subdirectory: " . $subDirName);
            return false;
        }

        // Prevent directory traversal or arbitrary names in media folder
        if ($baseDir === 'media') {
            $parts = explode('/', $subDirName);
            if (count($parts) > 2) {
                error_log("Sprint Admin Media 4.5.1 Error: Subdirectory nesting too deep: " . $subDirName);
                return false;
            }
            if (count($parts) === 2) {
                $folder = $parts[1];
                $folderAllowlist = ['general', 'branding', 'homepage', 'about', 'services', 'products', 'projects', 'testimonials', 'clients', 'seo', 'categories'];
                if (!in_array($folder, $folderAllowlist, true)) {
                    error_log("Sprint Admin Media 4.5.1 Error: Unauthorized media folder: " . $folder);
                    return false;
                }
            }
        }

        $destDir = dirname(__DIR__) . '/assets/images/' . $subDirName;
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                error_log("Sprint Admin Media 4.5.1 Error: Could not create upload directory: " . $destDir);
                return false;
            }
        }
        
        $baseRealPath = realpath($destDir);
        if ($baseRealPath === false || !is_dir($baseRealPath)) {
            error_log("Sprint Admin Media 4.5.1 Error: Approved base directory does not exist or is invalid: " . $destDir);
            return false;
        }
        
        if (!is_writable($baseRealPath)) {
            error_log("Sprint Admin Media 4.5.1 Error: Upload directory is not writable: " . $baseRealPath);
            return false;
        }
        
        return rtrim($baseRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}

/**
 * Validates file upload sizes, MIME types, and decodes the image securely.
 * Moves the file to the target assets/images/{subDirName}/ directory.
 */
if (!function_exists('validate_and_upload_image')) {
    function validate_and_upload_image($fileInfo, &$newFilesTracked, $subDirName = 'projects', $filePrefix = 'project') {
        // Strict subdirectory validation
        $allowedSubDirs = ['projects', 'products', 'settings', 'media'];
        $baseDir = explode('/', $subDirName)[0];
        if (!in_array($baseDir, $allowedSubDirs, true)) {
            throw new ProjectValidationException(__('upload_failure'));
        }

        // Prefix allowlist mapping per directory
        $prefixAllowlist = [
            'projects' => ['project', 'main', 'before', 'after', 'gallery'],
            'products' => ['product'],
            'settings' => ['site-logo', 'hero-image', 'about-image', 'site-logo-dark', 'favicon', 'og-image'],
            'media' => ['media', 'general', 'branding', 'homepage', 'about', 'services', 'products', 'projects', 'testimonials', 'clients', 'seo', 'categories']
        ];

        // Format validation: lowercase ASCII letters, digits, and hyphens
        if (empty($filePrefix) || !preg_match('/^[a-z0-9-]+$/', $filePrefix)) {
            throw new ProjectValidationException(__('upload_failure'));
        }

        // Subdirectory-specific prefix allowlist check
        $allowlistKey = ($baseDir === 'media') ? 'media' : $subDirName;
        if (!in_array($filePrefix, $prefixAllowlist[$allowlistKey], true)) {
            throw new ProjectValidationException(__('upload_failure'));
        }

        // 1. Structure validation
        if (!is_array($fileInfo)) {
            throw new ProjectValidationException(__('upload_failure'));
        }

        // Ensure all required keys exist and are scalar
        $requiredKeys = ['error', 'tmp_name', 'size', 'name'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $fileInfo) || !is_scalar($fileInfo[$key])) {
                throw new ProjectValidationException(__('upload_failure'));
            }
        }

        // 2. Handle UPLOAD_ERR_NO_FILE explicitly
        if ($fileInfo['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
            throw new ProjectValidationException(__('upload_failure'));
        }

        // Ensure tmp_name is a non-empty string when UPLOAD_ERR_OK
        if (empty($fileInfo['tmp_name'])) {
            throw new ProjectValidationException(__('upload_failure'));
        }

        if (!is_uploaded_file($fileInfo['tmp_name']) && php_sapi_name() !== 'cli') {
            throw new ProjectValidationException(__('upload_failure'));
        }

        // Enforce strict size validation
        $size = filter_var($fileInfo['size'], FILTER_VALIDATE_INT);
        if ($size === false || $size <= 0) {
            throw new ProjectValidationException(__('upload_failure'));
        }

        // Size limit: 5 MB
        $maxSize = 5 * 1024 * 1024;
        if ($size > $maxSize) {
            throw new ProjectValidationException(__('oversized_image'));
        }

        // MIME type validation via finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            throw new ProjectValidationException(__('upload_failure'));
        }
        $mime = finfo_file($finfo, $fileInfo['tmp_name']);
        finfo_close($finfo);

        $ext = get_extension_from_mime($mime);
        if (!$ext) {
            throw new ProjectValidationException(__('unsupported_image'));
        }

        // Secure decode check with try/finally error handler restoration
        set_error_handler(static function () {
            return true;
        });

        try {
            $imgSize = getimagesize($fileInfo['tmp_name']);
        } finally {
            restore_error_handler();
        }

        if ($imgSize === false && $ext !== 'ico') {
            throw new ProjectValidationException(__('invalid_image'));
        }

        // Ensure consistency between finfo MIME and getimagesize derived type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];
        if ($baseDir === 'media') {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        }
        
        if (!in_array($mime, $allowedMimes, true)) {
            throw new ProjectValidationException(__('unsupported_image'));
        }
        
        if ($imgSize !== false && $imgSize['mime'] !== $mime) {
            throw new ProjectValidationException(__('unsupported_image'));
        }

        // Ensure directory is ready and writable
        $destDir = ensure_upload_directory($subDirName);
        if ($destDir === false) {
            throw new ProjectValidationException(__('upload_dir_unavailable'));
        }

        // Cryptographically secure filename
        try {
            $filename = $filePrefix . '-' . bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (Exception $e) {
            error_log("Sprint Admin Media 4.5.1 Error: Random bytes generation failed: " . $e->getMessage());
            throw new ProjectValidationException(__('upload_failure'));
        }
        
        $destPath = $destDir . $filename;
        
        $moved = false;
        if (php_sapi_name() === 'cli') {
            $moved = copy($fileInfo['tmp_name'], $destPath);
        } else {
            $moved = move_uploaded_file($fileInfo['tmp_name'], $destPath);
        }

        if (!$moved) {
            error_log("Sprint Admin Media 4.5.1 Error: failed to move/copy " . $fileInfo['tmp_name'] . " to " . $destPath);
            throw new ProjectValidationException(__('upload_failure'));
        }

        $newFilesTracked[] = $destPath;
        
        $finalFilename = $filename;
        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'], true)) {
            $webpPath = convert_to_webp($destPath);
            if ($webpPath !== false) {
                $finalFilename = pathinfo($webpPath, PATHINFO_BASENAME);
                $newFilesTracked[] = $webpPath;
            }
        }
        
        return 'assets/images/' . $subDirName . '/' . $finalFilename;
    }
}

/**
 * Internal helper to securely unlink a file using a custom error handler
 * to prevent raw path disclosure on warning emissions.
 */
if (!function_exists('safe_physical_unlink')) {
    function safe_physical_unlink($absolutePath) {
        if (empty($absolutePath) || !is_file($absolutePath)) {
            return false;
        }

        set_error_handler(static function () {
            return true;
        });

        try {
            $result = unlink($absolutePath);
        } finally {
            restore_error_handler();
        }

        if (!$result) {
            error_log("Sprint Admin Media 4.5.1 Warning: Physical unlink failed for: " . basename($absolutePath));
        }

        return $result;
    }
}

/**
 * Safe physical file deletion wrapper.
 * Prevents traversal using realpath boundaries and protects default shared assets.
 */
if (!function_exists('safe_delete_file')) {
    function safe_delete_file($relPath, $subDirName = 'projects') {
        if (empty($relPath)) {
            return true;
        }
        
        // Strict subdirectory validation
        $allowedSubDirs = ['projects', 'products', 'settings'];
        if (!in_array($subDirName, $allowedSubDirs, true)) {
            error_log("Sprint Admin Media 4.5.1 Error: Unauthorized deletion subdirectory: " . $subDirName);
            return false;
        }

        $relPath = str_replace('\\', '/', $relPath);
        
        $approvedDirectory = dirname(__DIR__) . '/assets/images/' . $subDirName;
        $baseRealPath = realpath($approvedDirectory);
        if ($baseRealPath === false || !is_dir($baseRealPath)) {
            error_log("Sprint Admin Media 4.5.1 Error: Approved base directory does not exist or is invalid: " . $approvedDirectory);
            return false;
        }
        $baseDir = rtrim($baseRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $fullPath = dirname(__DIR__) . '/' . $relPath;
        $resolvedPath = realpath($fullPath);
        
        if ($resolvedPath === false) {
            // File does not exist on disk, treat as already cleaned
            return true;
        }
        
        // Path traversal safety checks
        if (strpos($resolvedPath, $baseDir) !== 0) {
            error_log("Sprint Admin Media 4.5.1 Security Alert: Attempted path traversal file deletion of relative path: " . $relPath);
            return false;
        }
        
        if (!is_file($resolvedPath)) {
            error_log("Sprint Admin Media 4.5.1 Error: Deletion target is not a regular file: " . $relPath);
            return false;
        }

        // Never delete default shared assets
        $filename = basename($resolvedPath);
        $sharedAssets = ['shelf1.png', 'shelf2.png', 'shelf3.png', 'shelf4.png', 'shelf5.png', 'favicon.svg'];
        if (in_array($filename, $sharedAssets, true)) {
            return true; // Safe bypass, do not unlink
        }

        return safe_physical_unlink($resolvedPath);
    }
}

/**
 * Safe rollback cleanup for absolute tracked paths during a request failure.
 */
if (!function_exists('rollback_cleanup_files')) {
    function rollback_cleanup_files($filesTracked) {
        if (!is_array($filesTracked) || empty($filesTracked)) {
            return true;
        }

        $allSuccess = true;
        $allowedDirs = ['projects', 'products', 'settings'];

        foreach ($filesTracked as $filePath) {
            // Require non-empty scalar string
            if (empty($filePath) || !is_string($filePath)) {
                $allSuccess = false;
                continue;
            }

            $filePath = str_replace('\\', '/', $filePath);
            $resolvedPath = realpath($filePath);

            if ($resolvedPath === false) {
                // Not found on disk, skip
                continue;
            }

            $approved = false;
            foreach ($allowedDirs as $subDir) {
                $dirPath = dirname(__DIR__) . '/assets/images/' . $subDir;
                $realDir = realpath($dirPath);
                if ($realDir !== false) {
                    $realDir = rtrim($realDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                    if (strpos($resolvedPath, $realDir) === 0) {
                        $approved = true;
                        break;
                    }
                }
            }

            if (!$approved) {
                error_log("Sprint Admin Media 4.5.1 Security Alert: Rollback cleanup target is outside approved directories: " . basename($filePath));
                $allSuccess = false;
                continue;
            }

            if (!is_file($resolvedPath)) {
                error_log("Sprint Admin Media 4.5.1 Error: Rollback cleanup target is not a regular file: " . basename($filePath));
                $allSuccess = false;
                continue;
            }

            // Never delete default shared assets
            $filename = basename($resolvedPath);
            $sharedAssets = ['shelf1.png', 'shelf2.png', 'shelf3.png', 'shelf4.png', 'shelf5.png', 'favicon.svg'];
            if (in_array($filename, $sharedAssets, true)) {
                continue;
            }

            if (!safe_physical_unlink($resolvedPath)) {
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }
}

/**
 * Safe conversion of JPEG/PNG to WebP with transparency preservation.
 */
if (!function_exists('convert_to_webp')) {
    function convert_to_webp($source_path, $quality = 85) {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            return false;
        }
        
        try {
            $info = @getimagesize($source_path);
            if ($info === false) {
                return false;
            }
            
            $mime = $info['mime'];
            if ($mime === 'image/jpeg') {
                $image = @imagecreatefromjpeg($source_path);
            } elseif ($mime === 'image/png') {
                $image = @imagecreatefrompng($source_path);
                if ($image) {
                    @imagepalettetotruecolor($image);
                    @imagealphavelending($image, false);
                    @imagesavealpha($image, true);
                }
            } else {
                return false;
            }
            
            if (!$image) {
                return false;
            }
            
            $path_info = pathinfo($source_path);
            $webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
            
            $success = @imagewebp($image, $webp_path, $quality);
            @imagedestroy($image);
            
            if ($success && file_exists($webp_path)) {
                return $webp_path;
            }
        } catch (Exception $e) {
            error_log("WebP conversion warning: " . $e->getMessage());
        }
        
        return false;
    }
}
