<?php
/**
 * Check Pending Print Payments API
 *
 * Returns count of payments awaiting print confirmation
 * Used by toast notification system
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();

// Define root path
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Set JSON response header
header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Check cashier permission
if (!can_process_payment()) {
    echo json_encode([
        'success' => true,
        'pending_count' => 0,
        'message' => 'Not a cashier'
    ]);
    exit;
}

try {
    $pdo = getPDO();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Get count of pending_print payments and age of oldest
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as pending_count,
            TIMESTAMPDIFF(MINUTE, MIN(p.payment_date), NOW()) as oldest_minutes
        FROM payments p
        WHERE p.status = 'pending_print'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $pendingCount = (int)$result['pending_count'];
    $oldestMinutes = (int)$result['oldest_minutes'];

    // Only notify if there are pending payments
    if ($pendingCount > 0) {
        echo json_encode([
            'success' => true,
            'pending_count' => $pendingCount,
            'oldest_minutes' => $oldestMinutes
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'pending_count' => 0
        ]);
    }

} catch (Exception $e) {
    error_log("Error checking pending print payments: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error checking pending payments'
    ]);
}
