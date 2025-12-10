<?php
/**
 * Authentication System
 * Handles user login, logout, and access control
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // SECURITY: Enhanced session configuration
    ini_set('session.cookie_httponly', 1);

    // PRODUCTION: Auto-detect HTTPS and set secure cookie flag
    // LOCALHOST: Commented for development (no HTTPS)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    // UNCOMMENT FOR PRODUCTION: ini_set('session.cookie_secure', 1);

    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 1800); // 30 minutes

    session_start();
}

/**
 * Check if user is logged in
 * Redirects to login page if not authenticated
 * Returns JSON for API calls
 */
function require_login() {
    if (!is_logged_in()) {
        // Check if this is an API/AJAX request
        $isApiRequest = (
            strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        );

        if ($isApiRequest) {
            // Return JSON error for API calls
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'unauthorized',
                'message' => 'Please log in to access this resource'
            ]);
            exit;
        }

        // Regular page request - redirect to login
        set_flash('Please log in to access this page', 'warning');
        $basePath = defined('BASE_PATH') ? BASE_PATH : '/tmg';
        header('Location: ' . $basePath . '/public/login.php');
        exit;
    }
}

/**
 * Check if user is currently logged in
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if current user is an admin
 * @return bool
 */
function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require admin privileges
 * Redirects if user is not an admin
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        $basePath = defined('BASE_PATH') ? BASE_PATH : '/tmg';
        set_flash('Access denied. Administrator privileges required.', 'danger');
        header('Location: ' . $basePath . '/public/index.php');
        exit;
    }
}

/**
 * Check if current user is an enforcer
 * @return bool
 */
function is_enforcer() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'enforcer';
}

/**
 * Check if current user is a cashier
 * @return bool
 */
function is_cashier() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'cashier';
}

/**
 * Check if user has any of the specified roles
 * @param array|string $roles Array of role names or single role name
 * @return bool
 */
function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Require enforcer or admin privileges
 * Redirects if user is not an enforcer or admin
 */
function require_enforcer() {
    require_login();
    if (!is_enforcer() && !is_admin()) {
        $basePath = defined('BASE_PATH') ? BASE_PATH : '/tmg';
        set_flash('Access denied. Enforcer privileges required.', 'danger');
        header('Location: ' . $basePath . '/public/index.php');
        exit;
    }
}

/**
 * Require cashier or admin privileges
 * Redirects if user is not a cashier or admin
 */
function require_cashier() {
    require_login();
    if (!is_cashier() && !is_admin()) {
        $basePath = defined('BASE_PATH') ? BASE_PATH : '/tmg';
        set_flash('Access denied. Cashier privileges required.', 'danger');
        header('Location: ' . $basePath . '/public/index.php');
        exit;
    }
}

/**
 * Check if user can create citations
 * @return bool
 */
function can_create_citation() {
    return is_admin() || is_enforcer() || is_cashier();
}

/**
 * Check if user can edit citation
 * @param int|null $citation_id Citation ID (optional)
 * @param int|null $creator_id User ID who created the citation (optional)
 * @return bool
 */
function can_edit_citation($citation_id = null, $creator_id = null) {
    // Admins can edit anything
    if (is_admin()) {
        return true;
    }

    // Enforcers can edit their own citations
    if (is_enforcer()) {
        // If creator_id provided, check ownership
        if ($creator_id !== null) {
            return $creator_id == $_SESSION['user_id'];
        }
        // If no creator provided, allow (will check in API)
        return true;
    }

    return false;
}

/**
 * Check if user can change citation status
 * @return bool
 */
function can_change_status() {
    return is_admin() || is_enforcer();
}

/**
 * Check if user can process payments
 * @return bool
 */
function can_process_payment() {
    return is_admin() || is_cashier();
}

/**
 * Check if user can refund/cancel payments
 * @return bool
 */
function can_refund_payment() {
    return is_admin() || is_cashier();
}

/**
 * Check if user can view all citations
 * @return bool
 */
function can_view_all_citations() {
    // All roles except 'user' can view all citations
    return is_admin() || is_enforcer() || is_cashier();
}

/**
 * Authenticate user with username and password
 * SECURITY: Enhanced with account lockout mechanism
 *
 * @param string $username
 * @param string $password
 * @return bool|array Returns user data on success, false on failure
 */
function authenticate($username, $password) {
    // Load security functions if not already loaded
    if (!function_exists('check_account_lockout')) {
        require_once __DIR__ . '/security.php';
    }

    try {
        $stmt = db_query(
            "SELECT user_id, username, password_hash, full_name, email, role, status, failed_login_attempts, locked_until
             FROM users WHERE username = ? AND status = 'active' LIMIT 1",
            [$username]
        );
        $user = $stmt->fetch();

        if (!$user) {
            // Log failed attempt for non-existent user
            log_audit(null, 'login_failed', "Username: {$username} (user not found)", 'failure');
            return false;
        }

        // SECURITY: Check if account is locked
        $lockout = check_account_lockout($user);
        if ($lockout['locked']) {
            log_audit($user['user_id'], 'login_blocked', $lockout['message'], 'blocked');
            throw new Exception($lockout['message']);
        }

        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // SECURITY: Reset failed login attempts on success
            reset_failed_login_attempts($user['user_id']);

            // Update last login
            db_query(
                "UPDATE users SET last_login = NOW() WHERE user_id = ?",
                [$user['user_id']]
            );

            return $user;
        }

        // SECURITY: Record failed login attempt
        record_failed_login($user['user_id']);

        return false;
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        // Re-throw account lockout exceptions to show message to user
        if (strpos($e->getMessage(), 'Account locked') !== false) {
            throw $e;
        }
    }
    return false;
}

/**
 * Create user session after successful login
 * @param array $user User data from database
 */
function create_session($user) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    // Generate new CSRF token for this session
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Destroy user session (logout)
 */
function destroy_session() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Check session timeout (15 minutes of inactivity)
 * @param int $timeout Timeout in seconds (default 900 = 15 minutes)
 */
function check_session_timeout($timeout = 900) {
    if (is_logged_in()) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            destroy_session();

            // Check if this is an API/AJAX request
            $isApiRequest = (
                strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
                (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            );

            if ($isApiRequest) {
                // Return JSON error for API calls
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'session_expired',
                    'message' => 'Your session has expired. Please log in again.'
                ]);
                exit;
            }

            set_flash('Your session has expired. Please log in again.', 'warning');
            $basePath = defined('BASE_PATH') ? BASE_PATH : '/tmg';
            header('Location: ' . $basePath . '/public/login.php');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Get current user info
 * @param string $key Optional specific key to retrieve
 * @return mixed User data or specific value
 */
if (!function_exists('get_current_user')) {
    function get_current_user($key = null) {
        if (!is_logged_in()) {
            return null;
        }

        if ($key) {
            return $_SESSION[$key] ?? null;
        }

        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['user_role']
        ];
    }
}

/**
 * Create a new user account
 * @param string $username Username
 * @param string $password Plain text password (will be hashed)
 * @param string $full_name Full name
 * @param string $email Email address
 * @param string $role User role (default: 'user')
 * @return int|false User ID on success, false on failure
 */
function create_user($username, $password, $full_name, $email, $role = 'user') {
    try {
        $pdo = getPDO();

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existing = $stmt->fetch();

        if ($existing) {
            return false;
        }

        // SECURITY: Validate password strength
        $validation = validate_password_strength($password);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (username, password_hash, full_name, email, role, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'active', NOW())"
        );

        $stmt->execute([
            $username,
            $passwordHash,
            $full_name,
            $email,
            $role
        ]);

        return $stmt->rowCount() > 0 ? $pdo->lastInsertId() : false;
    } catch (Exception $e) {
        error_log("User creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user password
 * @param int $userId
 * @param string $newPassword
 * @return bool
 */
function update_password($userId, $newPassword) {
    try {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = db_query(
            "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?",
            [$passwordHash, $userId]
        );
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Password update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all users with optional search/filter
 *
 * @param string|null $search Search term for username/name/email
 * @param string|null $role Filter by role
 * @param string|null $status Filter by status
 * @return array List of users
 */
function get_all_users($search = null, $role = null, $status = null) {
    $pdo = getPDO();

    $sql = "SELECT user_id, username, full_name, email, role, status,
                   last_login, created_at
            FROM users
            WHERE 1=1";

    $params = [];

    if ($search) {
        $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($role) {
        $sql .= " AND role = ?";
        $params[] = $role;
    }

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get a single user by ID
 *
 * @param int $user_id User ID
 * @return array|false User data or false if not found
 */
function get_user_by_id($user_id) {
    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT user_id, username, full_name, email, role,
                                  status, last_login, created_at
                           FROM users
                           WHERE user_id = ?");
    $stmt->execute([$user_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update user information
 *
 * @param int $user_id User ID
 * @param array $data User data (full_name, email, role, status)
 * @return bool Success status
 */
function update_user($user_id, $data) {
    $pdo = getPDO();

    // Validate email if provided
    if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Build update query dynamically
    $fields = [];
    $params = [];

    if (isset($data['full_name'])) {
        $fields[] = "full_name = ?";
        $params[] = trim($data['full_name']);
    }

    if (isset($data['email'])) {
        $fields[] = "email = ?";
        $params[] = trim($data['email']);
    }

    if (isset($data['role']) && in_array($data['role'], ['user', 'admin', 'enforcer', 'cashier'])) {
        $fields[] = "role = ?";
        $params[] = $data['role'];
    }

    if (isset($data['status']) && in_array($data['status'], ['active', 'inactive', 'suspended'])) {
        $fields[] = "status = ?";
        $params[] = $data['status'];
    }

    if (empty($fields)) {
        return false;
    }

    $params[] = $user_id;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Delete a user
 *
 * @param int $user_id User ID to delete
 * @return bool Success status
 */
function delete_user($user_id) {
    $pdo = getPDO();

    // Prevent deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        throw new Exception('You cannot delete your own account');
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Validate password strength
 * SECURITY: Enhanced password complexity requirements
 *
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_password_strength($password) {
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = 'at least 12 characters';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'one lowercase letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'one number';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'one special character (!@#$%^&*)';
    }

    if (!empty($errors)) {
        return [
            'valid' => false,
            'message' => 'Password must contain ' . implode(', ', $errors)
        ];
    }

    return ['valid' => true, 'message' => ''];
}

/**
 * Reset user password
 *
 * @param int $user_id User ID
 * @param string $new_password New password (plain text, will be hashed)
 * @return bool Success status
 */
function reset_user_password($user_id, $new_password) {
    $pdo = getPDO();

    // SECURITY: Validate password strength
    $validation = validate_password_strength($new_password);
    if (!$validation['valid']) {
        throw new Exception($validation['message']);
    }

    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    return $stmt->execute([$password_hash, $user_id]);
}

/**
 * Update user status
 *
 * @param int $user_id User ID
 * @param string $status New status (active/inactive/suspended)
 * @return bool Success status
 */
function update_user_status($user_id, $status) {
    if (!in_array($status, ['active', 'inactive', 'suspended'])) {
        throw new Exception('Invalid status');
    }

    return update_user($user_id, ['status' => $status]);
}

/**
 * Validate username
 *
 * @param string $username Username to validate
 * @return bool Validation result
 */
function validate_username($username) {
    // 3-20 characters, alphanumeric, underscore, dash
    return preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username);
}

/**
 * Redirect back to previous page or default location
 * @param string $default Default URL if no referer
 */
function redirect_back($default = null) {
    if ($default === null) {
        $basePath = defined('BASE_PATH') ? BASE_PATH : '/tmg';
        $default = $basePath . '/public/index.php';
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? $default;
    header("Location: $referer");
    exit;
}

/**
 * Redirect to specific URL
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}
?>
