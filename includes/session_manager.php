<?php
/**
 * Session Management System
 * Handles multi-device session tracking and limits
 */

/**
 * Get device information from user agent
 * @param string $userAgent
 * @return string
 */
function get_device_info($userAgent) {
    // Simple device detection
    if (preg_match('/mobile/i', $userAgent)) {
        return 'Mobile Device';
    } elseif (preg_match('/tablet/i', $userAgent)) {
        return 'Tablet';
    } elseif (preg_match('/Windows/i', $userAgent)) {
        return 'Windows PC';
    } elseif (preg_match('/Mac/i', $userAgent)) {
        return 'Mac';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        return 'Linux PC';
    }
    return 'Unknown Device';
}

/**
 * Get browser name from user agent
 * @param string $userAgent
 * @return string
 */
function get_browser_name($userAgent) {
    if (preg_match('/Edge/i', $userAgent)) {
        return 'Edge';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        return 'Chrome';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        return 'Firefox';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        return 'Safari';
    } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
        return 'Opera';
    }
    return 'Unknown Browser';
}

/**
 * Create a new session record in database
 * @param int $userId
 * @param string $sessionToken
 * @return bool
 */
function create_session_record($userId, $sessionToken) {
    try {
        $pdo = getPDO();

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $deviceInfo = get_device_info($userAgent) . ' - ' . get_browser_name($userAgent);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmt = $pdo->prepare(
            "INSERT INTO active_sessions
            (user_id, session_token, device_info, ip_address, user_agent, login_time, last_activity, expires_at, is_active)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, 1)"
        );

        return $stmt->execute([
            $userId,
            $sessionToken,
            $deviceInfo,
            $ipAddress,
            $userAgent,
            $expiresAt
        ]);
    } catch (Exception $e) {
        error_log("Session creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get count of active sessions for a user
 * @param int $userId
 * @param string|null $role User role (to check if admin)
 * @return int
 */
function get_active_session_count($userId, $role = null) {
    try {
        $pdo = getPDO();

        // Clean up expired sessions first
        cleanup_expired_sessions();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as count
             FROM active_sessions
             WHERE user_id = ?
             AND is_active = 1
             AND expires_at > NOW()"
        );

        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        return (int)($result['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Get session count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get all active sessions for a user
 * @param int $userId
 * @return array
 */
function get_user_active_sessions($userId) {
    try {
        $pdo = getPDO();

        $stmt = $pdo->prepare(
            "SELECT session_id, session_token, device_info, ip_address, login_time, last_activity
             FROM active_sessions
             WHERE user_id = ?
             AND is_active = 1
             AND expires_at > NOW()
             ORDER BY login_time DESC"
        );

        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get user sessions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user can login (session limit check)
 * @param int $userId
 * @param string $role User role
 * @return array ['allowed' => bool, 'message' => string, 'active_count' => int]
 */
function check_session_limit($userId, $role) {
    $maxSessions = ($role === 'admin') ? 2 : 999; // Only admins have 2-device limit

    $activeCount = get_active_session_count($userId, $role);

    if ($activeCount >= $maxSessions) {
        return [
            'allowed' => false,
            'message' => "Maximum device limit reached ($maxSessions devices). Please logout from another device first.",
            'active_count' => $activeCount
        ];
    }

    return [
        'allowed' => true,
        'message' => '',
        'active_count' => $activeCount
    ];
}

/**
 * Remove oldest session for a user (to make room for new login)
 * @param int $userId
 * @return bool
 */
function remove_oldest_session($userId) {
    try {
        $pdo = getPDO();

        // Get oldest active session
        $stmt = $pdo->prepare(
            "SELECT session_id FROM active_sessions
             WHERE user_id = ?
             AND is_active = 1
             ORDER BY login_time ASC
             LIMIT 1"
        );

        $stmt->execute([$userId]);
        $session = $stmt->fetch();

        if ($session) {
            $stmt = $pdo->prepare("UPDATE active_sessions SET is_active = 0 WHERE session_id = ?");
            return $stmt->execute([$session['session_id']]);
        }

        return false;
    } catch (Exception $e) {
        error_log("Remove oldest session error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update session activity time
 * @param string $sessionToken
 * @return bool
 */
function update_session_activity($sessionToken) {
    try {
        $pdo = getPDO();

        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmt = $pdo->prepare(
            "UPDATE active_sessions
             SET last_activity = NOW(), expires_at = ?
             WHERE session_token = ? AND is_active = 1"
        );

        return $stmt->execute([$expiresAt, $sessionToken]);
    } catch (Exception $e) {
        error_log("Update session activity error: " . $e->getMessage());
        return false;
    }
}

/**
 * Destroy a session record
 * @param string $sessionToken
 * @return bool
 */
function destroy_session_record($sessionToken) {
    try {
        $pdo = getPDO();

        $stmt = $pdo->prepare(
            "UPDATE active_sessions
             SET is_active = 0
             WHERE session_token = ?"
        );

        return $stmt->execute([$sessionToken]);
    } catch (Exception $e) {
        error_log("Destroy session error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up expired sessions
 * @return bool
 */
function cleanup_expired_sessions() {
    try {
        $pdo = getPDO();

        $stmt = $pdo->prepare(
            "UPDATE active_sessions
             SET is_active = 0
             WHERE expires_at < NOW() AND is_active = 1"
        );

        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Cleanup expired sessions error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up all sessions for a user
 * @param int $userId
 * @return bool
 */
function cleanup_user_sessions($userId) {
    try {
        $pdo = getPDO();

        $stmt = $pdo->prepare(
            "UPDATE active_sessions
             SET is_active = 0
             WHERE user_id = ?"
        );

        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Cleanup user sessions error: " . $e->getMessage());
        return false;
    }
}
?>
