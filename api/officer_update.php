<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require login
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Rate Limiting
if (!check_rate_limit('officer_update', 20, 300)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Too many attempts. Please try again later.'
    ]);
    exit;
}

// Validate CSRF Token
if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Security token validation failed.',
        'new_csrf_token' => generate_token()
    ]);
    exit;
}

try {
    // Validate required fields
    if (empty($_POST['officer_id']) || empty($_POST['officer_name'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Officer ID and name are required']);
        exit;
    }

    // Sanitize inputs
    $officer_id = (int)$_POST['officer_id'];
    $officer_name = sanitize($_POST['officer_name']);
    $badge_number = !empty($_POST['badge_number']) ? sanitize($_POST['badge_number']) : null;
    $position = !empty($_POST['position']) ? sanitize($_POST['position']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Update officer
    db_query(
        "UPDATE apprehending_officers SET officer_name = ?, badge_number = ?, position = ?, is_active = ? WHERE officer_id = ?",
        [$officer_name, $badge_number, $position, $is_active, $officer_id]
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Officer updated successfully',
        'new_csrf_token' => generate_token()
    ]);

} catch (Exception $e) {
    error_log("Officer update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred',
        'new_csrf_token' => generate_token()
    ]);
}
?>
