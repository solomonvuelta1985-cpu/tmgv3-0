<?php
/**
 * Check Citation Status API
 *
 * Returns citation status and payment information for validation purposes
 * Used by frontend payment validation to prevent invalid payment submissions
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Require authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Get citation ID from query parameter
$citation_id = filter_input(INPUT_GET, 'citation_id', FILTER_VALIDATE_INT);

if (!$citation_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid citation ID'
    ]);
    exit;
}

try {
    $pdo = getPDO();

    // Get citation details with payment information
    $stmt = $pdo->prepare("
        SELECT
            c.citation_id,
            c.ticket_number,
            c.status,
            c.total_fine,
            c.payment_date,
            CONCAT(c.first_name, ' ', c.last_name) as driver_name,
            c.license_number,
            COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_payment_count,
            COUNT(CASE WHEN p.status = 'pending_print' THEN 1 END) as pending_print_count,
            GROUP_CONCAT(
                CASE WHEN p.status = 'completed'
                THEN p.receipt_number
                END
                ORDER BY p.payment_date
                SEPARATOR ', '
            ) as receipt_numbers,
            SUM(CASE WHEN p.status = 'completed' THEN p.amount_paid ELSE 0 END) as total_paid
        FROM citations c
        LEFT JOIN payments p ON c.citation_id = p.citation_id
        WHERE c.citation_id = ?
        GROUP BY c.citation_id
    ");

    $stmt->execute([$citation_id]);
    $citation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citation) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Citation not found'
        ]);
        exit;
    }

    // Build response
    $response = [
        'success' => true,
        'citation' => [
            'citation_id' => $citation['citation_id'],
            'ticket_number' => $citation['ticket_number'],
            'status' => $citation['status'],
            'total_fine' => floatval($citation['total_fine']),
            'payment_date' => $citation['payment_date'],
            'driver_name' => $citation['driver_name'],
            'license_number' => $citation['license_number']
        ],
        'has_completed_payments' => $citation['completed_payment_count'] > 0,
        'has_pending_print' => $citation['pending_print_count'] > 0,
        'receipt_numbers' => $citation['receipt_numbers'],
        'total_paid' => floatval($citation['total_paid']),
        'completed_payment_count' => intval($citation['completed_payment_count']),
        'pending_print_count' => intval($citation['pending_print_count'])
    ];

    // Add validation flags
    $response['validations'] = [
        'can_accept_payment' => !in_array($citation['status'], ['void', 'dismissed']),
        'is_already_paid' => $citation['status'] === 'paid' && $citation['completed_payment_count'] > 0,
        'has_data_inconsistency' => (
            ($citation['status'] === 'paid' && $citation['completed_payment_count'] === 0) ||
            ($citation['status'] === 'pending' && $citation['completed_payment_count'] > 0)
        ),
        'is_contested' => $citation['status'] === 'contested'
    ];

    // Add warning messages
    $warnings = [];

    if ($response['validations']['is_already_paid']) {
        $warnings[] = "Citation is already paid (OR: {$citation['receipt_numbers']})";
    }

    if ($response['validations']['has_data_inconsistency']) {
        $warnings[] = "Data inconsistency detected - citation status does not match payment records";
    }

    if ($response['validations']['is_contested']) {
        $warnings[] = "Citation is currently contested";
    }

    if (!empty($warnings)) {
        $response['warnings'] = $warnings;
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Citation status check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
