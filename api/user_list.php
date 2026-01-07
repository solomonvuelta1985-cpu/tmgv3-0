<?php
/**
 * User List API
 * Returns list of all users with optional filters
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Require admin authentication
require_admin();

try {
    // Check if requesting a specific user by ID
    $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

    if ($user_id) {
        // Get specific user by ID
        $user = get_user_by_id($user_id);
        if ($user) {
            echo json_encode([
                'success' => true,
                'users' => [$user],
                'count' => 1
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
    } else {
        // Get all users with filters
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $role = filter_input(INPUT_GET, 'role', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $users = get_all_users($search, $role, $status);

        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
