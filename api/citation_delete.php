<?php
/**
 * Citation Delete API Endpoint
 *
 * Deletes a citation and all related records
 * Only admins can delete citations
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php'; // SECURITY: Audit logging

header('Content-Type: application/json');

// Require admin access
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
    exit;
}

// Validate CSRF
if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
    exit;
}

// Rate limiting
if (!check_rate_limit('citation_delete', 10, 300)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait.']);
    exit;
}

try {
    // Validate required field
    if (empty($_POST['citation_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Citation ID is required']);
        exit;
    }

    $citation_id = (int)$_POST['citation_id'];
    $pdo = getPDO();

    // Get citation info for logging
    $stmt = $pdo->prepare("SELECT ticket_number, first_name, last_name, status FROM citations WHERE citation_id = ?");
    $stmt->execute([$citation_id]);
    $citation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citation) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Citation not found']);
        exit;
    }

    // Enhanced validation: Check citation and payment status for proper validation
    $stmt = $pdo->prepare("
        SELECT
            COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN p.status = 'pending_print' THEN 1 END) as pending_print_count,
            COUNT(CASE WHEN p.status NOT IN ('voided', 'cancelled') THEN 1 END) as active_payment_count,
            GROUP_CONCAT(DISTINCT p.receipt_number ORDER BY p.created_at SEPARATOR ', ') as receipt_numbers
        FROM payments p
        WHERE p.citation_id = ?
    ");
    $stmt->execute([$citation_id]);
    $paymentCheck = $stmt->fetch(PDO::FETCH_ASSOC);

    // CASE 1: Citation is marked as PAID with completed payments (normal paid citation)
    if ($citation['status'] === 'paid' && $paymentCheck['completed_count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot delete: This is a PAID citation with completed payment records (OR: ' . $paymentCheck['receipt_numbers'] . '). Use the "Void Citation" function instead to maintain audit trail and financial records.',
            'suggestion' => 'void_citation'
        ]);
        exit;
    }

    // CASE 2: Citation is PENDING but has COMPLETED payments (data inconsistency)
    if ($citation['status'] === 'pending' && $paymentCheck['completed_count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot delete: Data inconsistency detected! This citation is marked as "pending" but has ' . $paymentCheck['completed_count'] . ' completed payment record(s) (OR: ' . $paymentCheck['receipt_numbers'] . '). Please run the data consistency checker first.',
            'suggestion' => 'fix_data_inconsistency',
            'fix_url' => '/tmg/fix_pending_paid_citations.php'
        ]);
        exit;
    }

    // CASE 3: Citation has pending_print payments (payment in progress)
    if ($paymentCheck['pending_print_count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot delete: This citation has ' . $paymentCheck['pending_print_count'] . ' payment(s) pending receipt confirmation. Please finalize or cancel the pending payment first.',
            'suggestion' => 'finalize_or_cancel_payment',
            'pending_payments_url' => '/tmg/public/pending_print_payments.php'
        ]);
        exit;
    }

    // CASE 4: Citation status is PAID but no completed payments (another inconsistency)
    if ($citation['status'] === 'paid' && $paymentCheck['completed_count'] === 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot delete: Citation is marked as "paid" but has no completed payment records. Data inconsistency detected. Please run the data consistency checker.',
            'suggestion' => 'fix_data_inconsistency',
            'fix_url' => '/tmg/fix_pending_paid_citations.php'
        ]);
        exit;
    }

    // If we reach here, citation can be safely deleted
    // This includes: pending citations with no payments, or only voided/cancelled payments

    // Get deletion reason from request (optional)
    $deletion_reason = isset($_POST['reason']) ? sanitize($_POST['reason']) : 'Deleted by admin';

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // SOFT DELETE: Update citation with deleted_at timestamp instead of hard delete
        // This preserves audit trail and referential integrity
        $stmt = $pdo->prepare("
            UPDATE citations
            SET
                deleted_at = NOW(),
                deleted_by = :deleted_by,
                deletion_reason = :reason,
                updated_at = NOW()
            WHERE citation_id = :citation_id
        ");

        $stmt->execute([
            ':citation_id' => $citation_id,
            ':deleted_by' => $_SESSION['user_id'],
            ':reason' => $deletion_reason
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to soft delete citation');
        }

        // Log the soft deletion to audit trail
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (
                user_id,
                action,
                table_name,
                record_id,
                old_values,
                new_values,
                ip_address,
                created_at
            ) VALUES (
                :user_id,
                'soft_delete',
                'citations',
                :citation_id,
                :old_values,
                :new_values,
                :ip_address,
                NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':citation_id' => $citation_id,
            ':old_values' => json_encode([
                'ticket_number' => $citation['ticket_number'],
                'status' => $citation['status'],
                'deleted_at' => null
            ]),
            ':new_values' => json_encode([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $_SESSION['user_id'],
                'deletion_reason' => $deletion_reason
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // Commit transaction
        $pdo->commit();

        // SECURITY: Audit logging for citation deletion
        log_audit(
            $_SESSION['user_id'] ?? null,
            'citation_deleted',
            "Ticket #: {$citation['ticket_number']}, Driver: {$citation['first_name']} {$citation['last_name']}, Citation ID: {$citation_id}, Reason: {$deletion_reason}",
            'success'
        );

        echo json_encode([
            'status' => 'success',
            'message' => 'Citation moved to trash successfully! You can restore it from the trash bin if needed.',
            'soft_delete' => true,
            'can_restore' => true
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // SECURITY: Audit logging for failed citation deletion
    log_audit(
        $_SESSION['user_id'] ?? null,
        'citation_delete_failed',
        "Citation ID: " . ($citation_id ?? 'unknown') . ", Error: " . $e->getMessage(),
        'failure'
    );

    error_log("Citation delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
