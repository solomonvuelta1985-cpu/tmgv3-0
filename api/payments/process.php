<?php
/**
 * Payment Processing API Endpoint
 *
 * Handles recording new payments for citations
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

// Require cashier or admin privileges to process payments
if (!can_process_payment()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only cashiers can process payments.'
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
    $citationId = filter_input(INPUT_POST, 'citation_id', FILTER_VALIDATE_INT);
    $amountPaid = filter_input(INPUT_POST, 'amount_paid', FILTER_VALIDATE_FLOAT);
    $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : null;
    $collectedBy = $_SESSION['user_id'];

    // Validate required fields
    if (!$citationId || !$amountPaid || !$paymentMethod) {
        throw new Exception('Missing required fields');
    }

    // Validate payment method
    $validMethods = ['cash', 'check', 'online', 'gcash', 'paymaya', 'bank_transfer', 'money_order'];
    if (!in_array($paymentMethod, $validMethods)) {
        throw new Exception('Invalid payment method');
    }

    // Prepare additional data
    $additionalData = [];

    // Check-specific fields
    if ($paymentMethod === 'check') {
        $additionalData['check_number'] = isset($_POST['check_number']) ? trim($_POST['check_number']) : null;
        $additionalData['check_bank'] = isset($_POST['check_bank']) ? trim($_POST['check_bank']) : null;
        $additionalData['check_date'] = isset($_POST['check_date']) ? trim($_POST['check_date']) : null;

        if (empty($additionalData['check_number'])) {
            throw new Exception('Check number is required for check payments');
        }
    }

    // Online payment reference
    if (in_array($paymentMethod, ['online', 'gcash', 'paymaya', 'bank_transfer'])) {
        $additionalData['reference_number'] = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : null;
    }

    // Receipt/OR number (REQUIRED - manual entry from physical receipt)
    $additionalData['receipt_number'] = isset($_POST['receipt_number']) ? trim($_POST['receipt_number']) : null;

    if (empty($additionalData['receipt_number'])) {
        throw new Exception('Receipt/OR number is required. Please enter the OR number from the physical receipt.');
    }

    // Notes
    $additionalData['notes'] = isset($_POST['notes']) ? trim($_POST['notes']) : null;

    // Initialize PaymentService
    $paymentService = new PaymentService(getPDO());

    // Record payment
    $result = $paymentService->recordPayment(
        $citationId,
        $amountPaid,
        $paymentMethod,
        $collectedBy,
        $additionalData
    );

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
