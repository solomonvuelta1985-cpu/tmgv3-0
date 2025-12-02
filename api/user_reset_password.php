<?php
/**
 * User Password Reset API
 * Resets a user's password
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

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
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $new_password = $_POST['new_password'] ?? ''; // Don't filter password

    if (!$user_id) {
        throw new Exception('Valid user ID is required');
    }

    if (empty($new_password)) {
        throw new Exception('New password is required');
    }

    $result = reset_user_password($user_id, $new_password);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    } else {
        throw new Exception('Failed to reset password');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
