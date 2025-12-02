<?php
/**
 * Payment List API Endpoint
 *
 * Retrieves list of all payments with optional filters
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
    // Get filter parameters
    $filters = [];

    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = trim($_GET['date_from']);
    }
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = trim($_GET['date_to']);
    }
    if (!empty($_GET['payment_method'])) {
        $filters['payment_method'] = trim($_GET['payment_method']);
    }
    if (!empty($_GET['collected_by'])) {
        $filters['collected_by'] = filter_input(INPUT_GET, 'collected_by', FILTER_VALIDATE_INT);
    }
    if (!empty($_GET['status'])) {
        $filters['status'] = trim($_GET['status']);
    }
    if (!empty($_GET['receipt_number'])) {
        $filters['receipt_number'] = trim($_GET['receipt_number']);
    }
    if (!empty($_GET['ticket_number'])) {
        $filters['ticket_number'] = trim($_GET['ticket_number']);
    }

    // Pagination
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 50;
    $offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT) ?: 0;

    // Initialize PaymentService
    $paymentService = new PaymentService(getPDO());

    // Get payments list
    $payments = $paymentService->getAllPayments($filters, $limit, $offset);

    // Get statistics if requested
    $statistics = null;
    if (isset($_GET['include_stats']) && $_GET['include_stats'] === 'true') {
        $dateRange = [];
        if (!empty($filters['date_from'])) {
            $dateRange['from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $dateRange['to'] = $filters['date_to'];
        }
        $statistics = $paymentService->getPaymentStatistics($dateRange);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $payments,
        'statistics' => $statistics,
        'filters' => $filters,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($payments)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
