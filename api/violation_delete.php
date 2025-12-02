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
if (!check_rate_limit('violation_delete', 10, 300)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait.']);
    exit;
}

try {
    // Validate required field
    if (empty($_POST['violation_type_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Violation type ID is required']);
        exit;
    }

    $violation_type_id = (int)$_POST['violation_type_id'];

    // Check if violation type is in use
    $stmt = db_query(
        "SELECT COUNT(*) as count FROM violations WHERE violation_type_id = ?",
        [$violation_type_id]
    );
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot delete: This violation type is used in ' . $result['count'] . ' citation(s). Consider deactivating instead.'
        ]);
        exit;
    }

    // Delete violation type
    db_query(
        "DELETE FROM violation_types WHERE violation_type_id = ?",
        [$violation_type_id]
    );

    // Clear cached violation types
    unset($_SESSION['violation_types']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Violation type deleted successfully!'
    ]);

} catch (Exception $e) {
    error_log("Violation delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>
