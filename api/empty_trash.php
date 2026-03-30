<?php
/**
 * Empty Trash API
 *
 * Permanently deletes citations that have been in trash for more than 30 days.
 * Removes related violations first, then the citations.
 * Only admins can empty the trash.
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

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

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getPDO();

    // Get citations eligible for permanent deletion (>30 days in trash)
    $stmt = $pdo->query("
        SELECT citation_id, ticket_number, first_name, last_name, deleted_at
        FROM citations
        WHERE deleted_at IS NOT NULL
        AND DATEDIFF(NOW(), deleted_at) > 30
    ");
    $eligible = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($eligible)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No citations eligible for permanent deletion. Citations must be in trash for more than 30 days.'
        ]);
        exit;
    }

    $citation_ids = array_column($eligible, 'citation_id');
    $placeholders = implode(',', array_fill(0, count($citation_ids), '?'));

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Get payment IDs linked to these citations (needed for nested FK cleanup)
        $stmt = $pdo->prepare("SELECT payment_id FROM payments WHERE citation_id IN ($placeholders)");
        $stmt->execute($citation_ids);
        $payment_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Delete payment child records first (payment_audit, receipts, payment_adjustments_log reference payments)
        if (!empty($payment_ids)) {
            $pay_placeholders = implode(',', array_fill(0, count($payment_ids), '?'));

            $stmt = $pdo->prepare("DELETE FROM payment_audit WHERE payment_id IN ($pay_placeholders)");
            $stmt->execute($payment_ids);

            $stmt = $pdo->prepare("DELETE FROM receipts WHERE payment_id IN ($pay_placeholders)");
            $stmt->execute($payment_ids);

            $stmt = $pdo->prepare("DELETE FROM payment_adjustments_log WHERE payment_id IN ($pay_placeholders)");
            $stmt->execute($payment_ids);
        }

        // Delete citation child records
        $stmt = $pdo->prepare("DELETE FROM payments WHERE citation_id IN ($placeholders)");
        $stmt->execute($citation_ids);

        $stmt = $pdo->prepare("DELETE FROM violations WHERE citation_id IN ($placeholders)");
        $stmt->execute($citation_ids);
        $violations_deleted = $stmt->rowCount();

        $stmt = $pdo->prepare("DELETE FROM citation_vehicles WHERE citation_id IN ($placeholders)");
        $stmt->execute($citation_ids);

        $stmt = $pdo->prepare("DELETE FROM payment_adjustments_log WHERE citation_id IN ($placeholders)");
        $stmt->execute($citation_ids);

        // Delete the citations
        $stmt = $pdo->prepare("DELETE FROM citations WHERE citation_id IN ($placeholders)");
        $stmt->execute($citation_ids);
        $citations_deleted = $stmt->rowCount();

        // Log to audit trail
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (
                user_id, action, table_name, record_id,
                old_values, new_values, ip_address, created_at
            ) VALUES (
                :user_id, 'empty_trash', 'citations', 0,
                :old_values, :new_values, :ip_address, NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':old_values' => json_encode([
                'citation_ids' => $citation_ids,
                'count' => $citations_deleted
            ]),
            ':new_values' => json_encode([
                'permanently_deleted' => $citations_deleted,
                'violations_removed' => $violations_deleted
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // Commit
        $pdo->commit();

        error_log("Trash emptied: {$citations_deleted} citations permanently deleted, {$violations_deleted} violations removed, by User ID=" . $_SESSION['user_id']);

        echo json_encode([
            'status' => 'success',
            'message' => "Permanently deleted {$citations_deleted} citation(s) and {$violations_deleted} violation record(s).",
            'citations_deleted' => $citations_deleted,
            'violations_deleted' => $violations_deleted
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Empty trash error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
