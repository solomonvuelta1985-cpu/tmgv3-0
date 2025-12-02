<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require login
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Require enforcer or admin privileges to change status
if (!can_change_status()) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Access denied. Only enforcers and admins can change citation status.'
    ]);
    exit;
}

// Rate Limiting
if (!check_rate_limit('citation_status', 30, 300)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Too many attempts. Please try again later.'
    ]);
    exit;
}

// Validate CSRF Token
if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Security token validation failed.',
        'new_csrf_token' => generate_token()
    ]);
    exit;
}

try {
    // Validate required fields
    if (empty($_POST['citation_id']) || empty($_POST['new_status'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Citation ID and new status are required']);
        exit;
    }

    $citation_id = (int)$_POST['citation_id'];
    $new_status = sanitize($_POST['new_status']);
    $reason = !empty($_POST['reason']) ? sanitize($_POST['reason']) : null;

    // Validate status
    $valid_statuses = ['pending', 'paid', 'contested', 'dismissed', 'void'];
    if (!in_array($new_status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid status value']);
        exit;
    }

    // Check if citation exists
    $stmt = db_query("SELECT citation_id, status FROM citations WHERE citation_id = ?", [$citation_id]);
    $citation = $stmt->fetch();

    if (!$citation) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Citation not found']);
        exit;
    }

    $old_status = $citation['status'];

    // Update citation status
    if ($reason) {
        // Append reason to remarks
        $stmt = db_query("SELECT remarks FROM citations WHERE citation_id = ?", [$citation_id]);
        $current_remarks = $stmt->fetch()['remarks'] ?? '';

        $timestamp = date('Y-m-d H:i:s');
        $user = $_SESSION['username'] ?? 'System';
        $new_remarks = $current_remarks;
        if (!empty($new_remarks)) {
            $new_remarks .= "\n\n";
        }
        $new_remarks .= "[$timestamp] Status changed from '$old_status' to '$new_status' by $user\nReason: $reason";

        db_query(
            "UPDATE citations SET status = ?, remarks = ?, updated_at = NOW() WHERE citation_id = ?",
            [$new_status, $new_remarks, $citation_id]
        );
    } else {
        db_query(
            "UPDATE citations SET status = ?, updated_at = NOW() WHERE citation_id = ?",
            [$new_status, $citation_id]
        );
    }

    // Log the action
    error_log("Citation $citation_id status changed from '$old_status' to '$new_status' by " . ($_SESSION['username'] ?? 'unknown'));

    echo json_encode([
        'status' => 'success',
        'message' => "Citation status updated to '$new_status' successfully",
        'new_status' => $new_status,
        'new_csrf_token' => generate_token()
    ]);

} catch (Exception $e) {
    error_log("Citation status update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred',
        'new_csrf_token' => generate_token()
    ]);
}
?>
