<?php
/**
 * Finalize Payment API Endpoint
 *
 * Confirms that receipt printed successfully and finalizes the payment
 * Updates payment status from 'pending_print' to 'completed'
 * Updates citation status to 'paid'
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
        'message' => 'Access denied. Only cashiers can finalize payments.'
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
    error_log("CSRF token validation failed. Session token: " . ($_SESSION['csrf_token'] ?? 'not set') . ", POST token: " . ($_POST['csrf_token'] ?? 'not set'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Get POST data (use $_POST as fallback for server compatibility)
    $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);

    // Fallback to $_POST if filter_input fails (some servers don't support it with FormData)
    if (!$paymentId && isset($_POST['payment_id'])) {
        $paymentId = filter_var($_POST['payment_id'], FILTER_VALIDATE_INT);
    }

    // Validate required fields (must check for false/null, not just falsy, since 0 could be valid in edge cases)
    if ($paymentId === false || $paymentId === null || $paymentId === '') {
        // Enhanced error for debugging
        error_log("Finalize Payment Error: Missing payment_id. POST data: " . json_encode($_POST));
        error_log("Finalize Payment Error: payment_id value: " . var_export($paymentId, true));
        throw new Exception('Payment ID is required');
    }

    // Additional validation: payment_id should be positive
    if ($paymentId <= 0) {
        error_log("Finalize Payment Error: Invalid payment_id=$paymentId (must be > 0)");
        throw new Exception("Invalid Payment ID: {$paymentId}. Payment ID must be a positive number.");
    }

    // Initialize PaymentService
    $paymentService = new PaymentService(getPDO());

    // Finalize payment
    $result = $paymentService->finalizePayment($paymentId, $_SESSION['user_id']);

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
