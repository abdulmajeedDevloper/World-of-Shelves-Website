<?php
// includes/hero-slider-helper.php
// Centralized slide database repository operations.

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

/**
 * Retrieves all active hero slides from the database.
 * Returns an array of slides, ordered by sort_order ASC, then id ASC.
 */
function hero_slide_find_active(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Database error in hero_slide_find_active: " . $e->getMessage());
        return [];
    }
}

/**
 * Count total number of slide records (both active and inactive) in the database.
 */
function hero_slide_count(PDO $pdo): int {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM hero_slides");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database error in hero_slide_count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Creates a new hero slide.
 * Enforces the maximum 5 total slide records inside a transaction.
 */
function hero_slide_create(PDO $pdo, array $data): int {
    $pdo->beginTransaction();
    try {
        // Count records inside the same transaction
        $stmt = $pdo->query("SELECT COUNT(*) FROM hero_slides");
        $total = (int)$stmt->fetchColumn();
        
        if ($total >= 5) {
            $pdo->rollBack();
            return -1; // Max slides limit reached
        }

        $ins = $pdo->prepare("INSERT INTO hero_slides (image_path, alt_ar, alt_en, sort_order, is_active) 
            VALUES (:image_path, :alt_ar, :alt_en, :sort_order, :is_active)");
        
        $ins->execute([
            ':image_path' => trim($data['image_path']),
            ':alt_ar'     => trim($data['alt_ar']),
            ':alt_en'     => trim($data['alt_en']),
            ':sort_order' => intval($data['sort_order']),
            ':is_active'  => intval($data['is_active'])
        ]);
        
        $insertId = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $insertId;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in hero_slide_create: " . $e->getMessage());
        return 0;
    }
}

/**
 * Updates an existing hero slide.
 */
function hero_slide_update(PDO $pdo, int $id, array $data): bool {
    try {
        $stmt = $pdo->prepare("UPDATE hero_slides SET 
            image_path = :image_path, 
            alt_ar = :alt_ar, 
            alt_en = :alt_en, 
            sort_order = :sort_order, 
            is_active = :is_active,
            updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id");
        return $stmt->execute([
            ':image_path' => trim($data['image_path']),
            ':alt_ar'     => trim($data['alt_ar']),
            ':alt_en'     => trim($data['alt_en']),
            ':sort_order' => intval($data['sort_order']),
            ':is_active'  => intval($data['is_active']),
            ':id'         => $id
        ]);
    } catch (PDOException $e) {
        error_log("Database error in hero_slide_update: " . $e->getMessage());
        return false;
    }
}

/**
 * Toggles a hero slide's active status.
 */
function hero_slide_toggle(PDO $pdo, int $id): bool {
    try {
        $stmt = $pdo->prepare("UPDATE hero_slides SET is_active = 1 - is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    } catch (PDOException $e) {
        error_log("Database error in hero_slide_toggle: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a hero slide.
 * Returns the slide record data array before deletion so the caller can check and delete files.
 */
function hero_slide_delete(PDO $pdo, int $id): ?array {
    $pdo->beginTransaction();
    try {
        // Fetch record details first
        $stmt = $pdo->prepare("SELECT * FROM hero_slides WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $slide = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$slide) {
            $pdo->rollBack();
            return null;
        }

        $del = $pdo->prepare("DELETE FROM hero_slides WHERE id = :id");
        $del->execute([':id' => $id]);
        
        $pdo->commit();
        return $slide;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in hero_slide_delete: " . $e->getMessage());
        return null;
    }
}

/**
 * Normalizes sort order values starting from 1, preventing duplicates and negative values.
 */
function hero_slide_reorder(PDO $pdo, array $orderedIds): void {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE hero_slides SET sort_order = :sort_order WHERE id = :id");
        $idx = 1;
        foreach ($orderedIds as $id) {
            $stmt->execute([
                ':sort_order' => $idx++,
                ':id'         => intval($id)
            ]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in hero_slide_reorder: " . $e->getMessage());
    }
}
