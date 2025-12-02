<?php
/**
 * Restore Citation API
 *
 * Restores a soft-deleted citation from the trash bin
 * Only admins can restore citations
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
    // Validate required field
    if (empty($_POST['citation_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Citation ID is required']);
        exit;
    }

    $citation_id = (int)$_POST['citation_id'];
    $pdo = getPDO();

    // Get citation info
    $stmt = $pdo->prepare("
        SELECT ticket_number, first_name, last_name, status, deleted_at, deleted_by, deletion_reason
        FROM citations
        WHERE citation_id = ?
    ");
    $stmt->execute([$citation_id]);
    $citation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citation) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Citation not found']);
        exit;
    }

    // Check if citation is actually deleted
    if ($citation['deleted_at'] === null) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Citation is not deleted. Cannot restore a citation that is not in the trash.'
        ]);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Restore citation (clear soft delete fields)
        $stmt = $pdo->prepare("
            UPDATE citations
            SET
                deleted_at = NULL,
                deleted_by = NULL,
                deletion_reason = NULL,
                updated_at = NOW()
            WHERE citation_id = ?
        ");

        $stmt->execute([$citation_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to restore citation');
        }

        // Log the restoration to audit trail
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
                'restore',
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
                'deleted_at' => $citation['deleted_at'],
                'deleted_by' => $citation['deleted_by'],
                'deletion_reason' => $citation['deletion_reason']
            ]),
            ':new_values' => json_encode([
                'deleted_at' => null,
                'restored_by' => $_SESSION['user_id'],
                'restored_at' => date('Y-m-d H:i:s')
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // Commit transaction
        $pdo->commit();

        // Log the restoration
        error_log("Citation restored: ID={$citation_id}, Ticket={$citation['ticket_number']}, Name={$citation['first_name']} {$citation['last_name']}, By User ID=" . $_SESSION['user_id']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Citation restored successfully!',
            'citation_id' => $citation_id,
            'ticket_number' => $citation['ticket_number']
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Citation restore error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
