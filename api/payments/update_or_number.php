<?php
/**
 * Update OR Number API Endpoint
 *
 * Updates the OR/receipt number for a pending_print payment
 * Used when cashier needs to use a different receipt due to printer jam
 *
 * @package TrafficCitationSystem
 * @subpackage API\Payments
 */

session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../services/PaymentService.php';

// Set JSON header
header('Content-Type: application/json');

// Require authentication
require_login();

// Require cashier or admin privileges
if (!can_process_payment()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only cashiers can update OR numbers.'
    ]);
    exit;
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Get POST data
    $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
    $newOrNumber = isset($_POST['new_or_number']) ? trim($_POST['new_or_number']) : null;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;

    // Validate required fields
    if (!$paymentId) {
        throw new Exception('Payment ID is required');
    }

    if (empty($newOrNumber)) {
        throw new Exception('New OR number is required');
    }

    if (empty($reason)) {
        $reason = 'Printer jam - using different receipt';
    }

    // Initialize PaymentService
    $paymentService = new PaymentService(getPDO());

    // Update OR number
    $result = $paymentService->updateOrNumber($paymentId, $newOrNumber, $_SESSION['user_id'], $reason);

    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
