<?php
/**
 * Cancel Payment API
 *
 * Cancels a pending_print payment by deleting it from the database.
 * This frees up the OR number for reuse since the receipt was never printed.
 *
 * IMPORTANT: This should ONLY be used for pending_print payments.
 * For completed payments, use void_payment.php instead.
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
        'message' => 'Unauthorized. Please log in.'
    ]);
    exit;
}

// Check cashier permission
if (!can_process_payment()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only cashiers can cancel payments.'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]);
    exit;
}

// Get payment ID
$paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);

if (!$paymentId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment ID'
    ]);
    exit;
}

try {
    $pdo = getPDO();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Get payment details
    $stmt = $pdo->prepare("
        SELECT p.*, c.ticket_number, c.status as citation_status
        FROM payments p
        JOIN citations c ON p.citation_id = c.citation_id
        WHERE p.payment_id = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Payment not found'
        ]);
        exit;
    }

    // Verify payment is in pending_print status
    if ($payment['status'] !== 'pending_print') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Can only cancel payments in pending_print status. This payment is: ' . $payment['status']
        ]);
        exit;
    }

    // Log the cancellation for audit trail (before deletion)
    $logMessage = sprintf(
        "Payment CANCELLED (not voided) by user %s. Payment ID: %d, OR: %s, Citation: %s, Amount: %.2f. Reason: Receipt was never printed, OR number freed for reuse.",
        $_SESSION['user_id'],
        $paymentId,
        $payment['receipt_number'],
        $payment['ticket_number'],
        $payment['amount_paid']
    );
    error_log($logMessage);

    // Insert audit log entry
    $auditStmt = $pdo->prepare("
        INSERT INTO audit_logs (
            action_type,
            entity_type,
            entity_id,
            or_number_old,
            ticket_number,
            amount,
            payment_status_old,
            payment_status_new,
            user_id,
            username,
            reason,
            ip_address,
            user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $auditStmt->execute([
        'payment_cancelled',
        'payment',
        $paymentId,
        $payment['receipt_number'],
        $payment['ticket_number'],
        $payment['amount_paid'],
        $payment['status'], // old status
        'deleted', // new status
        $_SESSION['user_id'],
        $_SESSION['username'] ?? 'Unknown',
        'Receipt was never printed - OR number freed for reuse',
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Delete related records first (due to foreign key constraints)

    // 1. Delete receipt record
    $stmt = $pdo->prepare("DELETE FROM receipts WHERE payment_id = ?");
    $stmt->execute([$paymentId]);

    // 2. Delete payment audit records
    $stmt = $pdo->prepare("DELETE FROM payment_audit WHERE payment_id = ?");
    $stmt->execute([$paymentId]);

    // 3. DELETE the payment record (frees up OR number)
    $stmt = $pdo->prepare("DELETE FROM payments WHERE payment_id = ?");
    $stmt->execute([$paymentId]);

    // Revert citation status to pending
    $stmt = $pdo->prepare("
        UPDATE citations
        SET status = 'pending',
            updated_at = NOW()
        WHERE citation_id = ?
    ");
    $stmt->execute([$payment['citation_id']]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment cancelled successfully. OR number is now available for reuse.',
        'or_number' => $payment['receipt_number'],
        'citation_status' => 'pending'
    ]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Error cancelling payment: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error cancelling payment: ' . $e->getMessage()
    ]);
}
