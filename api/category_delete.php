<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Require admin access
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
    exit;
}

// Validate CSRF
if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
    exit;
}

// Rate limiting
if (!check_rate_limit('category_delete', 20, 300)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait.']);
    exit;
}

try {
    // Validate required fields
    if (empty($_POST['category_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Category ID is required']);
        exit;
    }

    $category_id = (int)$_POST['category_id'];

    // Check if category exists
    $stmt = db_query(
        "SELECT category_name FROM violation_categories WHERE category_id = ?",
        [$category_id]
    );

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Category not found']);
        exit;
    }

    // Get "Other" category ID
    $stmt = db_query("SELECT category_id FROM violation_categories WHERE category_name = 'Other'");
    $otherCategory = $stmt->fetch();
    $otherCategoryId = $otherCategory ? $otherCategory['category_id'] : null;

    // Move violations to "Other" category before deleting
    if ($otherCategoryId) {
        db_query(
            "UPDATE violation_types SET category_id = ? WHERE category_id = ?",
            [$otherCategoryId, $category_id]
        );
    } else {
        // If no "Other" category, set to NULL
        db_query(
            "UPDATE violation_types SET category_id = NULL WHERE category_id = ?",
            [$category_id]
        );
    }

    // Delete category
    db_query(
        "DELETE FROM violation_categories WHERE category_id = ?",
        [$category_id]
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Category deleted successfully!'
    ]);

} catch (Exception $e) {
    error_log("Category delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>
