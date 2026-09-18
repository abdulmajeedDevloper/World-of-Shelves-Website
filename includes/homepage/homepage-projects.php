<?php
// includes/homepage/homepage-projects.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}

require_once dirname(dirname(__DIR__)) . '/includes/portfolio-helper.php';

$lang = get_current_lang();

$limit_val = intval(get_setting('homepage_projects_limit', '3'));
if ($limit_val < 1 || $limit_val > 12) {
    $limit_val = 3;
}

$source_mode = get_setting('homepage_projects_source', 'featured');

// Map sources to SQL clauses
$sql_clauses = [
    'featured'        => 'is_active = 1 AND is_featured = 1 ORDER BY sort_order ASC, id DESC',
    'latest'          => 'is_active = 1 AND is_latest = 1 ORDER BY sort_order ASC, id DESC',
    'featured_latest' => 'is_active = 1 ORDER BY is_featured DESC, is_latest DESC, sort_order ASC, id DESC'
];

$order_sql = isset($sql_clauses[$source_mode]) ? $sql_clauses[$source_mode] : $sql_clauses['featured'];

$projs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE $order_sql LIMIT :limit");
    $stmt->bindValue(':limit', $limit_val, PDO::PARAM_INT);
    $stmt->execute();
    $projs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Homepage Projects Query failed: " . $e->getMessage());
}

$sec_title = get_setting('homepage_projects_title_' . $lang, __('featured_projects_title'));
$sec_subtitle = get_setting('homepage_projects_subtitle_' . $lang, __('featured_projects_subtitle'));
?>
<?php if (count($projs) > 0): ?>
<section class="portfolio-section reveal-on-scroll">
    <div class="container">
        <div class="section-header">
            <h2><?php echo htmlspecialchars($sec_title); ?></h2>
            <div class="section-divider"></div>
            <?php if (!empty($sec_subtitle)): ?>
                <p><?php echo htmlspecialchars($sec_subtitle); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="projects-grid">
            <?php foreach ($projs as $fp): 
                $fp_title_ar = (string)($fp['title_ar'] ?? '');
                $fp_title_en = (string)($fp['title_en'] ?? '');
                $fp_location_ar = (string)($fp['location_ar'] ?? '');
                $fp_location_en = (string)($fp['location_en'] ?? '');
                $fp_category = (string)($fp['category'] ?? '');
                $fp_slug = (string)($fp['slug'] ?? '');
                $fp_main_image = (string)($fp['main_image'] ?? '');
                $fp_date = (string)($fp['date'] ?? '');
                $fp_desc_ar = (string)($fp['desc_ar'] ?? '');
                $fp_desc_en = (string)($fp['desc_en'] ?? '');

                $fp_title = ($lang === 'ar') ? $fp_title_ar : $fp_title_en;
                $fp_location = ($lang === 'ar') ? $fp_location_ar : $fp_location_en;
                $fp_category_label = ($fp_category !== '') ? __('filter_' . $fp_category) : '';
                $fp_link = get_project_details_url($fp_slug, $lang);
            ?>
                <div class="project-card">
                    <div class="project-image-wrapper">
                        <span class="project-badge"><?php echo htmlspecialchars($fp_category_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        <img src="<?php echo htmlspecialchars($fp_main_image, ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="<?php echo htmlspecialchars($fp_title, ENT_QUOTES, 'UTF-8'); ?>" 
                             class="project-img" 
                             width="400"
                             height="260"
                             loading="lazy" 
                             decoding="async" />
                    </div>
                    <div class="project-card-content">
                        <h3><?php echo htmlspecialchars($fp_title, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="project-meta-info">
                            <span><i data-lucide="map-pin"></i> <?php echo htmlspecialchars($fp_location, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><i data-lucide="calendar"></i> <?php echo htmlspecialchars($fp_date, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <p><?php echo htmlspecialchars(($lang === 'ar') ? $fp_desc_ar : $fp_desc_en, ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="<?php echo htmlspecialchars($fp_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary-cta">
                            <span><?php echo __('view_project'); ?></span>
                            <i data-lucide="arrow-right" class="arrow-icon"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="projects" class="btn btn-secondary">
                <span><?php echo $lang === 'ar' ? 'عرض جميع المشاريع' : 'View All Projects'; ?></span>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>
