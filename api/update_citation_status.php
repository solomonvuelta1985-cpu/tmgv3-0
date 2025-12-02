<?php
/**
 * Update Citation Status API Endpoint
 *
 * Allows admins to manually change citation status with audit trail
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

// Require admin privileges only
if (!is_admin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only administrators can change citation status.'
    ]);
    exit;
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Get POST data
    $citationId = filter_input(INPUT_POST, 'citation_id', FILTER_VALIDATE_INT);
    $newStatus = isset($_POST['status']) ? trim($_POST['status']) : null;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
    $userId = $_SESSION['user_id'];

    // Validate required fields
    if (!$citationId || !$newStatus) {
        throw new Exception('Missing required fields: citation_id and status are required');
    }

    // Validate status value
    $validStatuses = ['pending', 'paid', 'contested', 'dismissed', 'void'];
    if (!in_array($newStatus, $validStatuses)) {
        throw new Exception('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
    }

    // Get current citation details
    $pdo = getPDO();
    $sql = "SELECT citation_id, ticket_number, status, total_fine
            FROM citations
            WHERE citation_id = :citation_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':citation_id' => $citationId]);
    $citation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citation) {
        throw new Exception('Citation not found');
    }

    // Check if status is already the same
    if ($citation['status'] === $newStatus) {
        echo json_encode([
            'success' => true,
            'message' => 'Citation is already in ' . $newStatus . ' status',
            'citation' => $citation
        ]);
        exit;
    }

    // Validate status transitions
    $validationMessage = validateStatusTransition($citation['status'], $newStatus, $citation, $pdo);
    if ($validationMessage !== true) {
        throw new Exception($validationMessage);
    }

    // Initialize PaymentService
    $paymentService = new PaymentService($pdo);

    // Change citation status
    $result = $paymentService->changeCitationStatus(
        $citationId,
        $newStatus,
        $userId,
        $reason
    );

    if ($result['success']) {
        // Get updated citation
        $stmt->execute([':citation_id' => $citationId]);
        $updatedCitation = $stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'old_status' => $citation['status'],
            'new_status' => $newStatus,
            'citation' => $updatedCitation
        ]);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Validate status transition rules
 *
 * @param string $currentStatus Current citation status
 * @param string $newStatus New desired status
 * @param array $citation Citation data
 * @param PDO $pdo Database connection
 * @return bool|string True if valid, error message if invalid
 */
function validateStatusTransition($currentStatus, $newStatus, $citation, $pdo) {
    // Changing from 'paid' to 'pending' requires special validation
    if ($currentStatus === 'paid' && $newStatus === 'pending') {
        // Check if there are active payments
        $sql = "SELECT payment_id, status, receipt_number
                FROM payments
                WHERE citation_id = :citation_id
                AND status IN ('completed', 'pending')
                ORDER BY payment_date DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':citation_id' => $citation['citation_id']]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment) {
            return 'Cannot change status to pending. Active payment exists (Receipt: ' .
                   $payment['receipt_number'] . '). Please refund the payment first.';
        }
    }

    // Changing to 'paid' should go through payment process
    if ($newStatus === 'paid' && $currentStatus !== 'paid') {
        return 'To mark citation as paid, please process payment through the payment system. ' .
               'Manual status change to "paid" is not allowed for financial integrity.';
    }

    // Void and dismissed citations generally cannot be changed
    if (in_array($currentStatus, ['void', 'dismissed']) && $newStatus !== $currentStatus) {
        // Allow, but log warning
        // Admin override is permitted for correcting errors
    }

    return true;
}
