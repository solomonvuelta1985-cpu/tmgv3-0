<?php
/**
 * Receipt Print/Reprint API Endpoint
 *
 * Handles receipt printing and reprinting
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
    $receiptId = filter_input(INPUT_GET, 'receipt_id', FILTER_VALIDATE_INT);
    $outputMode = isset($_GET['mode']) ? trim($_GET['mode']) : 'download';

    if (!$receiptId) {
        throw new Exception('Receipt ID is required');
    }

    // Validate output mode
    $validModes = ['download', 'inline', 'preview'];
    if (!in_array($outputMode, $validModes)) {
        $outputMode = 'download';
    }

    // Initialize ReceiptService
    $receiptService = new ReceiptService(getPDO());

    // Reprint receipt
    $receiptService->reprintReceipt($receiptId, $_SESSION['user_id'], $outputMode);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
