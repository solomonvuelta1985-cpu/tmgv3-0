<?php
/**
 * Check if a citation has a pending_print payment
 *
 * Returns existing payment details if found
 */

session_start();

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Require authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Require cashier or admin privileges
if (!can_process_payment()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get citation_id from GET parameter
$citation_id = $_GET['citation_id'] ?? null;

if (!$citation_id || !is_numeric($citation_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid citation ID']);
    exit;
}

try {
    $pdo = getPDO();

    if ($pdo === null) {
        throw new Exception('Database connection failed');
    }

    // Check if cash_received and change_amount columns exist (for backward compatibility)
    $columns = $pdo->query("SHOW COLUMNS FROM payments LIKE 'cash_received'")->fetchAll();
    $hasCashColumns = !empty($columns);

    // Check for existing pending_print payment (with backward compatibility)
    if ($hasCashColumns) {
        // New schema with cash_received and change_amount columns
        $sql = "SELECT
                    p.payment_id,
                    p.receipt_number,
                    p.amount_paid,
                    p.payment_method,
                    p.payment_date,
                    p.cash_received,
                    p.change_amount,
                    p.reference_number,
                    p.notes,
                    c.ticket_number,
                    c.total_fine,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.license_number,
                    c.plate_mv_engine_chassis_no as plate_number,
                    c.vehicle_description,
                    GROUP_CONCAT(vt.violation_type SEPARATOR ', ') as violations
                FROM payments p
                JOIN citations c ON p.citation_id = c.citation_id
                LEFT JOIN violations v ON c.citation_id = v.citation_id
                LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                WHERE p.citation_id = :citation_id
                AND p.status = 'pending_print'
                GROUP BY p.payment_id
                LIMIT 1";
    } else {
        // Old schema without cash_received and change_amount columns
        $sql = "SELECT
                    p.payment_id,
                    p.receipt_number,
                    p.amount_paid,
                    p.payment_method,
                    p.payment_date,
                    NULL as cash_received,
                    NULL as change_amount,
                    p.reference_number,
                    p.notes,
                    c.ticket_number,
                    c.total_fine,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.license_number,
                    c.plate_mv_engine_chassis_no as plate_number,
                    c.vehicle_description,
                    GROUP_CONCAT(vt.violation_type SEPARATOR ', ') as violations
                FROM payments p
                JOIN citations c ON p.citation_id = c.citation_id
                LEFT JOIN violations v ON c.citation_id = v.citation_id
                LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                WHERE p.citation_id = :citation_id
                AND p.status = 'pending_print'
                GROUP BY p.payment_id
                LIMIT 1";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['citation_id' => $citation_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        // Pending payment exists
        echo json_encode([
            'success' => true,
            'has_pending' => true,
            'payment' => $payment
        ]);
    } else {
        // No pending payment
        echo json_encode([
            'success' => true,
            'has_pending' => false
        ]);
    }

} catch (Exception $e) {
    error_log("Check Pending Payment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error checking payment status: ' . $e->getMessage()
    ]);
}
