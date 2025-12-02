<?php
/**
 * Payment History API Endpoint
 *
 * Retrieves payment history for a specific citation
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

// Only GET requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get citation ID from query string
    $citationId = filter_input(INPUT_GET, 'citation_id', FILTER_VALIDATE_INT);

    if (!$citationId) {
        throw new Exception('Citation ID is required');
    }

    // Initialize PaymentService
    $paymentService = new PaymentService(getPDO());

    // Get payment history
    $history = $paymentService->getPaymentHistory($citationId);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $history
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
