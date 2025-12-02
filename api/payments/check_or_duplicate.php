<?php
/**
 * Check OR Number Duplicate API
 *
 * Checks if an OR number has already been used in the system
 * Returns detailed information if OR is already used
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();

// Define root path
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Set JSON response header
header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please log in.'
    ]);
    exit;
}

// Check cashier permission
if (!can_process_payment()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only cashiers can check OR numbers.'
    ]);
    exit;
}

// Get OR number from query parameter
// Note: FILTER_SANITIZE_STRING is deprecated in PHP 8.1+, using FILTER_SANITIZE_FULL_SPECIAL_CHARS
$orNumber = filter_input(INPUT_GET, 'or_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$orNumber) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'OR number is required'
    ]);
    exit;
}

// Normalize OR number (uppercase, trim)
$orNumber = strtoupper(trim($orNumber));

try {
    $pdo = getPDO();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Check if OR number exists in payments table
    $stmt = $pdo->prepare("
        SELECT
            p.payment_id,
            p.receipt_number,
            p.amount_paid,
            p.payment_date,
            p.status,
            c.ticket_number,
            CONCAT(c.first_name, ' ', c.last_name) as driver_name
        FROM payments p
        JOIN citations c ON p.citation_id = c.citation_id
        WHERE p.receipt_number = ?
        LIMIT 1
    ");
    $stmt->execute([$orNumber]);
    $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPayment) {
        // OR number already used
        echo json_encode([
            'available' => false,
            'message' => 'OR number has already been used',
            'existing_payment' => $existingPayment
        ]);
    } else {
        // OR number is available
        echo json_encode([
            'available' => true,
            'message' => 'OR number is available',
            'or_number' => $orNumber
        ]);
    }

} catch (Exception $e) {
    error_log("Error checking OR duplicate: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error checking OR number: ' . $e->getMessage()
    ]);
}
