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
if (!check_rate_limit('violation_update', 20, 300)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait.']);
    exit;
}

try {
    // Validate required fields
    $required = ['violation_type_id', 'violation_type', 'fine_amount_1', 'fine_amount_2', 'fine_amount_3'];
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
    $violation_type_id = (int)$_POST['violation_type_id'];
    $violation_type = sanitize($_POST['violation_type']);
    $fine_1 = floatval($_POST['fine_amount_1']);
    $fine_2 = floatval($_POST['fine_amount_2']);
    $fine_3 = floatval($_POST['fine_amount_3']);
    $description = sanitize($_POST['description'] ?? '');

    // Validate amounts
    if ($fine_1 < 0 || $fine_2 < 0 || $fine_3 < 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Fine amounts cannot be negative']);
        exit;
    }

    // Check for duplicate (excluding current record)
    $stmt = db_query(
        "SELECT violation_type_id FROM violation_types WHERE violation_type = ? AND violation_type_id != ?",
        [$violation_type, $violation_type_id]
    );

    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'This violation type already exists']);
        exit;
    }

    // Update violation type
    db_query(
        "UPDATE violation_types
         SET violation_type = ?, description = ?, fine_amount_1 = ?, fine_amount_2 = ?, fine_amount_3 = ?
         WHERE violation_type_id = ?",
        [$violation_type, $description, $fine_1, $fine_2, $fine_3, $violation_type_id]
    );

    // Clear cached violation types
    unset($_SESSION['violation_types']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Violation type updated successfully!'
    ]);

} catch (Exception $e) {
    error_log("Violation update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>
