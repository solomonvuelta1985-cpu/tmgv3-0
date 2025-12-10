<?php
/**
 * API Endpoint: Check Citation Number Duplicate
 * Validates if a citation number already exists in the database
 */

// Define root path and require dependencies
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Require login
require_login();

// Set JSON header
header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Get citation number from request
$citation_no = $_POST['citation_no'] ?? '';

// Validate input
if (empty($citation_no)) {
    echo json_encode([
        'success' => false,
        'error' => 'Citation number is required'
    ]);
    exit;
}

// Sanitize input
$citation_no = trim($citation_no);

// Validate length (6-8 characters)
$length = strlen($citation_no);
if ($length < 6 || $length > 8) {
    echo json_encode([
        'success' => false,
        'error' => 'Citation number must be 6 to 8 characters long.',
        'exists' => false
    ]);
    exit;
}

// Validate format (alphanumeric and hyphens only)
if (!preg_match('/^[A-Z0-9\-]{6,8}$/', $citation_no)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid format. Use only uppercase letters, numbers, and hyphens (6-8 characters).',
        'exists' => false
    ]);
    exit;
}

try {
    // Check if citation number exists
    $stmt = db_query(
        "SELECT ticket_number FROM citations WHERE ticket_number = ? LIMIT 1",
        [$citation_no]
    );

    $exists = $stmt->fetch() !== false;

    if ($exists) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'message' => 'This citation number already exists in the database.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'Citation number is available.'
        ]);
    }

} catch (Exception $e) {
    error_log("Citation duplicate check error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'exists' => null
    ]);
}
