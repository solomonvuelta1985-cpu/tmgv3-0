<?php
/**
 * Session Logout All API
 * Logout from all sessions except current
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session_manager.php';

header('Content-Type: application/json');

// Require admin authentication
require_admin();

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $currentSessionToken = $_SESSION['session_token'] ?? '';

    if (empty($currentSessionToken)) {
        throw new Exception('No active session token found');
    }

    // Deactivate all sessions for this user EXCEPT the current one
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "UPDATE active_sessions
         SET is_active = 0
         WHERE user_id = ?
         AND session_token != ?
         AND is_active = 1"
    );

    $result = $stmt->execute([$_SESSION['user_id'], $currentSessionToken]);

    if ($result) {
        $affectedRows = $stmt->rowCount();

        echo json_encode([
            'success' => true,
            'message' => "Successfully logged out from $affectedRows other device(s)",
            'logged_out_count' => $affectedRows
        ]);
    } else {
        throw new Exception('Failed to logout sessions');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
