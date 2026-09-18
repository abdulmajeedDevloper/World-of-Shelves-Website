<?php
// admin/update_product_flags.php
// Handles AJAX POST requests to update product visibility flags and sort order.

require_once dirname(__DIR__) . '/config/admin_init.php'; // bootstrap, sets up $pdo and session

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Verify CSRF token using existing helper
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
if ($action === 'toggle_flag') {
    $flag = isset($_POST['flag']) ? trim($_POST['flag']) : '';
    $allowed_flags = ['is_featured', 'is_most_requested', 'is_new', 'is_active'];
    if (!in_array($flag, $allowed_flags, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid flag']);
        exit;
    }
    try {
        // Get current flag state
        $stmt = $pdo->prepare("SELECT $flag FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $current = (int)$stmt->fetchColumn();
        $new = $current === 1 ? 0 : 1;
        // Update flag
        $update = $pdo->prepare("UPDATE products SET $flag = :new, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $update->execute([':new' => $new, ':id' => $id]);
        echo json_encode(['success' => true, 'new_state' => $new]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_sort_order') {
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
    try {
        $stmt = $pdo->prepare('UPDATE products SET sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':sort_order' => $sort_order, ':id' => $id]);
        echo json_encode(['success' => true, 'sort_order' => $sort_order]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Unknown action
http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
?>
