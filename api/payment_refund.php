<?php
/**
 * Payment Refund API Endpoint
 *
 * Allows cashiers and admins to refund payments
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/PaymentService.php';

// Set JSON header
header('Content-Type: application/json');

// Require authentication
require_login();

// Require cashier or admin privileges to process refunds
if (!can_refund_payment()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only cashiers can process refunds.'
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
    // Validate required fields
    $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (!$paymentId) {
        throw new Exception('Valid payment ID is required');
    }

    if (empty($reason)) {
        throw new Exception('Refund reason is required');
    }

    // Initialize PaymentService
    $paymentService = new PaymentService(getPDO());

    // Process refund
    $result = $paymentService->refundPayment(
        $paymentId,
        $reason,
        $_SESSION['user_id']
    );

    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Payment refunded successfully',
            'payment_id' => $paymentId
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
