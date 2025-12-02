<?php
/**
 * Receipt Generation API Endpoint
 *
 * Generates and outputs receipt PDF
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/ReceiptService.php';

// Require authentication
require_login();

// Only GET requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

try {
    // Get parameters
    $paymentId = filter_input(INPUT_GET, 'payment_id', FILTER_VALIDATE_INT);
    $copyNumber = filter_input(INPUT_GET, 'copy', FILTER_VALIDATE_INT) ?: 1;
    $outputMode = isset($_GET['mode']) ? trim($_GET['mode']) : 'download';

    if (!$paymentId) {
        throw new Exception('Payment ID is required');
    }

    // Validate copy number
    if ($copyNumber < 1 || $copyNumber > 3) {
        $copyNumber = 1;
    }

    // Validate output mode
    $validModes = ['download', 'inline', 'preview'];
    if (!in_array($outputMode, $validModes)) {
        $outputMode = 'download';
    }

    // Initialize ReceiptService
    $receiptService = new ReceiptService(getPDO());

    // Generate PDF
    $receiptService->generateReceiptPDF($paymentId, $copyNumber, $outputMode);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
