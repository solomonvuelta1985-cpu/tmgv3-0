<?php
/**
 * Payment Details API Endpoint
 *
 * Returns detailed information about a specific payment
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

// Require cashier or admin privileges
if (!can_process_payment()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only cashiers can view payment details.'
    ]);
    exit;
}

// Get payment ID from query parameter
$paymentId = filter_input(INPUT_GET, 'payment_id', FILTER_VALIDATE_INT);

if (!$paymentId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payment ID is required'
    ]);
    exit;
}

try {
    $pdo = getPDO();

    if ($pdo === null) {
        throw new Exception('Database connection failed');
    }

    // Get payment details with related information
    $sql = "SELECT
                p.payment_id,
                p.citation_id,
                p.receipt_number,
                p.amount_paid,
                p.payment_method,
                p.payment_date,
                p.status,
                p.reference_number,
                p.notes,
                c.ticket_number,
                c.apprehension_datetime as citation_date,
                CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                c.license_number,
                c.plate_mv_engine_chassis_no as plate_number,
                u.full_name as collector_name
            FROM payments p
            INNER JOIN citations c ON p.citation_id = c.citation_id
            LEFT JOIN users u ON p.collected_by = u.user_id
            WHERE p.payment_id = :payment_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':payment_id' => $paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Payment not found'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'payment' => $payment
    ]);

} catch (Exception $e) {
    error_log("Error fetching payment details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching payment details: ' . $e->getMessage()
    ]);
}
