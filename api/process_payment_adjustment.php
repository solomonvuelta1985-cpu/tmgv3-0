<?php
/**
 * API: Process Payment Adjustment
 *
 * Creates a payment adjustment record with full audit trail
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/payment_adjustment_functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check admin permission
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

// Get database connection
$pdo = getPDO();
if ($pdo === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
if (!isset($input['csrf_token']) || !verify_token($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Security token validation failed'
    ]);
    exit;
}

// Extract and validate input
$citation_id = intval($input['citation_id'] ?? 0);
$adjustment_type = trim($input['adjustment_type'] ?? '');
$or_number = trim($input['or_number'] ?? '');
$payment_date = trim($input['payment_date'] ?? '');
$amount = floatval($input['amount'] ?? 0);
$reason = trim($input['reason'] ?? '');
$admin_password = $input['admin_password'] ?? '';

// Validate required fields
if ($citation_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid citation ID'
    ]);
    exit;
}

if (empty($adjustment_type)) {
    echo json_encode([
        'success' => false,
        'message' => 'Adjustment type is required'
    ]);
    exit;
}

if (empty($reason) || strlen($reason) < 20) {
    echo json_encode([
        'success' => false,
        'message' => 'Reason is required (minimum 20 characters)'
    ]);
    exit;
}

if ($amount < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Amount cannot be negative'
    ]);
    exit;
}

if (empty($payment_date)) {
    $payment_date = date('Y-m-d');
}

// Validate payment date is not in the future
if (strtotime($payment_date) > time()) {
    echo json_encode([
        'success' => false,
        'message' => 'Payment date cannot be in the future'
    ]);
    exit;
}

try {
    // Get adjustment type configuration
    $types = get_adjustment_types();
    if (!isset($types[$adjustment_type])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid adjustment type'
        ]);
        exit;
    }

    $type_config = $types[$adjustment_type];

    // Validate OR number if required
    if ($type_config['requires_or'] && empty($or_number)) {
        echo json_encode([
            'success' => false,
            'message' => 'OR number is required for this adjustment type'
        ]);
        exit;
    }

    // Verify admin password if required
    if ($type_config['requires_password']) {
        if (empty($admin_password)) {
            echo json_encode([
                'success' => false,
                'message' => 'Admin password confirmation is required for this adjustment type'
            ]);
            exit;
        }

        if (!verify_admin_password($_SESSION['user_id'], $admin_password, $pdo)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid admin password'
            ]);
            exit;
        }
    }

    // Check rate limit
    $rate_limit = check_adjustment_rate_limit($_SESSION['user_id'], $pdo);
    if (!$rate_limit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => $rate_limit['message']
        ]);
        exit;
    }

    // Validate citation eligibility
    $validation = validate_citation_for_adjustment($citation_id, $pdo);
    if (!$validation['valid']) {
        echo json_encode([
            'success' => false,
            'message' => $validation['message']
        ]);
        exit;
    }

    // Check OR number uniqueness if provided
    if (!empty($or_number) && !is_or_number_unique($or_number, $pdo)) {
        echo json_encode([
            'success' => false,
            'message' => 'OR number already exists in the system'
        ]);
        exit;
    }

    // Set amount to 0 for waived fines
    if ($adjustment_type === 'waived') {
        $amount = 0.00;
    }

    // Prepare adjustment data
    $adjustment_data = [
        'citation_id' => $citation_id,
        'adjustment_type' => $adjustment_type,
        'or_number' => !empty($or_number) ? $or_number : null,
        'payment_date' => $payment_date,
        'original_payment_date' => $payment_date,
        'amount' => $amount,
        'reason' => $reason,
        'admin_user_id' => $_SESSION['user_id']
    ];

    // Create the adjustment
    $result = create_payment_adjustment($adjustment_data, $pdo);

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
        exit;
    }

    // Send email notification (async, don't wait for result)
    try {
        send_adjustment_notification($result['payment_id'], $pdo);
    } catch (Exception $e) {
        // Log but don't fail the request
        error_log("Failed to send adjustment notification: " . $e->getMessage());
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment adjustment created successfully',
        'data' => [
            'payment_id' => $result['payment_id'],
            'citation_id' => $citation_id,
            'ticket_number' => $validation['citation']['ticket_number'],
            'adjustment_type' => $adjustment_type,
            'new_status' => $type_config['target_status'],
            'amount' => $amount,
            'or_number' => $or_number
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in process_payment_adjustment.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in process_payment_adjustment.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing the adjustment'
    ]);
}
