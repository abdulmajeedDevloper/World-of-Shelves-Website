<?php
// includes/portfolio-helper.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
require_once dirname(__DIR__) . '/config/db.php';

/**
 * Returns list of projects from MySQL or SQLite DB (table 'projects')
 * Retire automatic public demo fallback: returns empty array on failure/empty.
 */
function get_portfolio_projects($featured_only = false) {
    global $pdo;
    
    try {
        $sql = "SELECT p.*, pc.category_key, pc.slug AS category_slug, pc.name_ar AS category_name_ar, pc.name_en AS category_name_en 
                FROM projects p 
                LEFT JOIN project_categories pc ON p.category_id = pc.id 
                WHERE p.is_active = 1";
        if ($featured_only) {
            $sql .= " AND p.is_featured = 1";
        }
        $sql .= " ORDER BY p.sort_order ASC, p.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            foreach ($results as &$r) {
                // Map project_date to date for backward compatibility
                $r['date'] = $r['project_date'];
                // Ensure category string key exists for legacy template rendering
                if (!empty($r['category_key'])) {
                    $r['category'] = $r['category_key'];
                }
            }
            return $results;
        }
        return [];
    } catch (PDOException $e) {
        error_log("Database error in get_portfolio_projects: " . $e->getMessage());
        return [];
    }
}

/**
 * Self-healing helper: ensures project_categories table and projects.category_id exist
 */
function ensure_project_categories_table($pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    if (!$pdo) return;

    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db_driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($db_driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS project_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_key VARCHAR(64) NOT NULL UNIQUE,
                name_ar VARCHAR(255) NOT NULL,
                name_en VARCHAR(255) NOT NULL,
                sort_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $columns_to_add = [
                'category_id' => 'INTEGER NULL',
                'service_ar' => 'TEXT NULL',
                'service_en' => 'TEXT NULL',
                'duration_ar' => 'TEXT NULL',
                'duration_en' => 'TEXT NULL'
            ];

            $stmt = $pdo->query("PRAGMA table_info(projects)");
            $existing_cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $existing_cols[$c['name']] = true;
            }
            foreach ($columns_to_add as $col_name => $col_def) {
                if (!isset($existing_cols[$col_name])) {
                    $pdo->exec("ALTER TABLE projects ADD COLUMN {$col_name} {$col_def}");
                }
            }
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS project_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_key VARCHAR(64) NOT NULL UNIQUE,
                name_ar VARCHAR(255) NOT NULL,
                name_en VARCHAR(255) NOT NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pc_sort (sort_order, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $columns_to_add = [
                'category_id' => 'INT NULL',
                'service_ar' => 'VARCHAR(255) NULL',
                'service_en' => 'VARCHAR(255) NULL',
                'duration_ar' => 'VARCHAR(100) NULL',
                'duration_en' => 'VARCHAR(100) NULL'
            ];

            foreach ($columns_to_add as $col_name => $col_def) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = :col");
                $stmt->execute([':col' => $col_name]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE projects ADD COLUMN {$col_name} {$col_def}");
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to ensure project_categories table and project columns: " . $e->getMessage());
    }
}

/**
 * Returns project categories from DB (table 'project_categories')
 */
function get_project_categories($active_only = true) {
    global $pdo;
    ensure_project_categories_table($pdo);
    try {
        $sql = "SELECT * FROM project_categories";
        if ($active_only) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Database error in get_project_categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Returns single category by ID
 */
function get_project_category_by_id($id) {
    global $pdo;
    if (!$id || $id <= 0) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM project_categories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Database error in get_project_category_by_id: " . $e->getMessage());
        return null;
    }
}

/**
 * Returns category display label for a category_id or category_key in target language
 */
function get_project_category_label($category_identifier, $lang = null) {
    global $pdo;
    if (empty($category_identifier)) return '';
    if (!$lang) {
        $lang = function_exists('get_current_lang') ? get_current_lang() : 'ar';
    }
    try {
        if (is_numeric($category_identifier)) {
            $stmt = $pdo->prepare("SELECT name_ar, name_en FROM project_categories WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$category_identifier]);
        } else {
            $stmt = $pdo->prepare("SELECT name_ar, name_en FROM project_categories WHERE category_key = :k LIMIT 1");
            $stmt->execute([':k' => $category_identifier]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ($lang === 'ar') ? $row['name_ar'] : $row['name_en'];
        }
    } catch (PDOException $e) {
        error_log("Database error in get_project_category_label: " . $e->getMessage());
    }
    
    // Fallback to translation helper if exists
    if (function_exists('__')) {
        $trans = __('filter_' . $category_identifier);
        if ($trans !== 'filter_' . $category_identifier) {
            return $trans;
        }
    }
    return ucfirst($category_identifier);
}

/**
 * Returns single project from MySQL or SQLite DB (table 'projects') matching slug.
 * Retrieve complete gallery rows dynamically.
 */
function get_project_by_slug($slug) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT p.*, pc.category_key, pc.slug AS category_slug, pc.name_ar AS category_name_ar, pc.name_en AS category_name_en 
                               FROM projects p 
                               LEFT JOIN project_categories pc ON p.category_id = pc.id 
                               WHERE LOWER(p.slug) = LOWER(:slug) AND p.is_active = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Map project_date to date for backward compatibility
            $row['date'] = $row['project_date'];
            if (!empty($row['category_key'])) {
                $row['category'] = $row['category_key'];
            }
            
            // Retrieve complete gallery rows rather than only image_path to preserve compatibility
            $galStmt = $pdo->prepare("SELECT * FROM project_images WHERE project_id = :project_id ORDER BY sort_order ASC, id ASC");
            $galStmt->execute([':project_id' => $row['id']]);
            $galleryRows = $galStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $galleryPaths = [];
            foreach ($galleryRows as $g) {
                if (!empty($g['image_path'])) {
                    $galleryPaths[] = $g['image_path'];
                }
            }
            $row['gallery'] = $galleryPaths;
            return $row;
        }
    } catch (PDOException $e) {
        error_log("Database error in get_project_by_slug: " . $e->getMessage());
    }
    return null;
}

/**
 * Returns testimonials from DB (table 'testimonials')
 * Falls back to 4 demo testimonials if DB query fails.
 */
function get_testimonials() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Database error in get_testimonials(): " . $e->getMessage());
        return [];
    }
}

/**
 * Returns client logos from DB (table 'client_logos')
 * Falls back to 6 demo logo definitions if DB query fails.
 */
function get_client_logos() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM client_logos ORDER BY id ASC");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            return $results;
        }
    } catch (PDOException $e) {
        // Fall back silently
    }

    // Demo Client Logos Fallback
    return [
        ['name' => 'Demo Brand 1', 'logo_url' => 'assets/images/shelf1.png'],
        ['name' => 'Demo Brand 2', 'logo_url' => 'assets/images/shelf2.png'],
        ['name' => 'Demo Brand 3', 'logo_url' => 'assets/images/shelf3.png'],
        ['name' => 'Demo Brand 4', 'logo_url' => 'assets/images/shelf4.png'],
        ['name' => 'Demo Brand 5', 'logo_url' => 'assets/images/shelf5.png'],
        ['name' => 'Demo Brand 6', 'logo_url' => 'assets/images/shelf1.png']
    ];
}

/**
 * Returns milestones from DB (table 'milestones')
 * Falls back to 4 demo milestones if DB query fails.
 */
function get_milestones() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM milestones ORDER BY id ASC");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            return $results;
        }
    } catch (PDOException $e) {
        // Fall back silently
    }

    // Demo Milestones Fallback (Generic steps, no hardcoded years)
    return [
        [
            'step_key' => 'timeline_1_step',
            'title_key' => 'timeline_1_title',
            'desc_key' => 'timeline_1_desc'
        ],
        [
            'step_key' => 'timeline_2_step',
            'title_key' => 'timeline_2_title',
            'desc_key' => 'timeline_2_desc'
        ],
        [
            'step_key' => 'timeline_3_step',
            'title_key' => 'timeline_3_title',
            'desc_key' => 'timeline_3_desc'
        ],
        [
            'step_key' => 'timeline_4_step',
            'title_key' => 'timeline_4_title',
            'desc_key' => 'timeline_4_desc'
        ]
    ];
}

/**
 * Centralized URL helper for project details pages.
 * Normalizes slugs, handles query parameters, prevents open redirects,
 * and maintains full backward compatibility for local subdirectories.
 */
function get_project_details_url($slug, $lang = null, $absolute = false) {
    $normalized_slug = trim((string)$slug);
    $params = ['project' => $normalized_slug];
    if ($lang && $lang !== 'ar') {
        $params['lang'] = $lang;
    }
    $query = http_build_query($params);
    $base = 'project-details';
    if ($absolute) {
        $base = get_site_url() . '/' . $base;
    }
    return $base . '?' . $query;
}

/**
 * Retrieves the project for the Homepage Before/After section.
 * Priority:
 * 1. Active Featured project with both before_image and after_image (ordered by sort_order ASC, then newest).
 * 2. Active non-Featured project with both before_image and after_image (ordered by sort_order ASC, then newest).
 * 3. Graceful fallback to null.
 */
function get_homepage_before_after_project() {
    global $pdo;
    
    try {
        // Priority 1: Featured + Active + Both Images
        $stmt = $pdo->prepare("SELECT * FROM projects 
            WHERE is_active = 1 
              AND is_featured = 1 
              AND before_image IS NOT NULL AND before_image != '' 
              AND after_image IS NOT NULL AND after_image != '' 
            ORDER BY sort_order ASC, id DESC LIMIT 1");
        $stmt->execute();
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($project) {
            $project['date'] = $project['project_date'];
            return $project;
        }
        
        // Priority 2: Active + Both Images (Featured or not)
        $stmt = $pdo->prepare("SELECT * FROM projects 
            WHERE is_active = 1 
              AND before_image IS NOT NULL AND before_image != '' 
              AND after_image IS NOT NULL AND after_image != '' 
            ORDER BY sort_order ASC, id DESC LIMIT 1");
        $stmt->execute();
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($project) {
            $project['date'] = $project['project_date'];
            return $project;
        }
    } catch (PDOException $e) {
        error_log("Database error in get_homepage_before_after_project: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Safely validates if the image path is within approved directories to prevent path traversal/unsafe output.
 */
function is_safe_image_path($path) {
    if (empty($path)) {
        return false;
    }
    // Reject directory traversal sequences
    if (strpos($path, '..') !== false) {
        return false;
    }
    // Check if it's within approved folders: uploads/ or assets/
    $allowed_folders = ['uploads/', 'assets/'];
    foreach ($allowed_folders as $folder) {
        if (strpos($path, $folder) === 0 || strpos(ltrim($path, '/'), $folder) === 0) {
            return true;
        }
    }
    // If it's a valid remote URL, we can check if it starts with http/https
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return true;
    }
    return false;
}
