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
if (!check_rate_limit('category_save', 20, 300)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait.']);
    exit;
}

try {
    // Validate required fields
    $required = ['category_name', 'category_icon', 'category_color'];
    $errors = [];

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
        exit;
    }

    // Sanitize inputs
    $category_name = sanitize($_POST['category_name']);
    $category_icon = sanitize($_POST['category_icon']);
    $category_color = sanitize($_POST['category_color']);
    $description = sanitize($_POST['description'] ?? '');
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;

    // Validate color format
    if (!preg_match('/^#[0-9A-F]{6}$/i', $category_color)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid color format']);
        exit;
    }

    // Check for duplicate
    $stmt = db_query(
        "SELECT category_id FROM violation_categories WHERE category_name = ?",
        [$category_name]
    );

    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'This category already exists']);
        exit;
    }

    // Insert new category
    db_query(
        "INSERT INTO violation_categories (category_name, category_icon, category_color, description, display_order, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, 1, NOW())",
        [$category_name, $category_icon, $category_color, $description, $display_order]
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Category added successfully!'
    ]);

} catch (Exception $e) {
    error_log("Category save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>
