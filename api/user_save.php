<?php
/**
 * User Save API
 * Creates new user or updates existing user
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
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $password = $_POST['password'] ?? ''; // Don't filter password
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'active';

    // Validation
    if (empty($username) || empty($full_name) || empty($email) || empty($role)) {
        throw new Exception('All fields are required');
    }

    if (!validate_username($username)) {
        throw new Exception('Username must be 3-20 characters (letters, numbers, underscore, dash only)');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    if (!in_array($role, ['user', 'admin', 'enforcer', 'cashier'])) {
        throw new Exception('Invalid role');
    }

    if ($user_id) {
        // UPDATE existing user
        $result = update_user($user_id, [
            'full_name' => $full_name,
            'email' => $email,
            'role' => $role,
            'status' => $status
        ]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully',
                'user_id' => $user_id
            ]);
        } else {
            throw new Exception('Failed to update user');
        }

    } else {
        // CREATE new user
        if (empty($password)) {
            throw new Exception('Password is required for new users');
        }

        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters');
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new Exception('Password must contain both letters and numbers');
        }

        // Use existing create_user function
        $new_user_id = create_user($username, $password, $full_name, $email, $role);

        if ($new_user_id) {
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => $new_user_id
            ]);
        } else {
            throw new Exception('Failed to create user (username may already exist)');
        }
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
