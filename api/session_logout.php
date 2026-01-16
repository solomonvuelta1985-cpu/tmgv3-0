<?php
/**
 * Session Logout API
 * Logout from a specific session/device
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
    $sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);

    if (!$sessionId) {
        throw new Exception('Invalid session ID');
    }

    // Verify the session belongs to current user
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "SELECT user_id, session_token FROM active_sessions WHERE session_id = ?"
    );
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        throw new Exception('Session not found');
    }

    if ($session['user_id'] != $_SESSION['user_id']) {
        throw new Exception('Unauthorized: Session does not belong to you');
    }

    // Don't allow logout of current session
    $currentSessionToken = $_SESSION['session_token'] ?? '';
    if ($session['session_token'] === $currentSessionToken) {
        throw new Exception('Cannot logout current session. Use logout button instead.');
    }

    // Deactivate the session
    $stmt = $pdo->prepare(
        "UPDATE active_sessions SET is_active = 0 WHERE session_id = ?"
    );

    if ($stmt->execute([$sessionId])) {
        echo json_encode([
            'success' => true,
            'message' => 'Device logged out successfully'
        ]);
    } else {
        throw new Exception('Failed to logout session');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
