<?php
/**
 * Security Functions
 * SECURITY HARDENING: IP-based rate limiting, audit logging, and additional security measures
 */

/**
 * Check IP-based rate limit
 * SECURITY: Prevents brute force attacks by limiting requests per IP address
 *
 * @param string $action Action identifier (e.g., 'login', 'user_management')
 * @param int $max_attempts Maximum attempts allowed
 * @param int $window Time window in seconds
 * @return bool True if within limit, false if rate limit exceeded
 */
function check_ip_rate_limit($action, $max_attempts = 5, $window = 300) {
    try {
        $ip = get_client_ip();
        $pdo = getPDO();

        // Clean old attempts
        $pdo->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()")->execute();

        // Count recent attempts from this IP
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as count FROM rate_limits
             WHERE ip_address = ? AND action = ? AND expires_at > NOW()"
        );
        $stmt->execute([$ip, $action]);
        $result = $stmt->fetch();

        if ($result['count'] >= $max_attempts) {
            // Log the blocked attempt
            log_audit(null, 'rate_limit_exceeded', "Action: {$action}, IP: {$ip}", 'blocked');
            return false;
        }

        // Record this attempt
        $pdo->prepare(
            "INSERT INTO rate_limits (ip_address, action, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))"
        )->execute([$ip, $action, $window]);

        return true;
    } catch (Exception $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        // On error, allow the request (fail open for availability)
        return true;
    }
}

/**
 * Get client IP address
 * Handles proxies and load balancers
 *
 * @return string Client IP address
 */
function get_client_ip() {
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Log security audit event
 * SECURITY: Track security-relevant events for monitoring and forensics
 *
 * @param int|null $user_id User ID (null for unauthenticated actions)
 * @param string $action Action performed
 * @param string|null $details Additional details
 * @param string $status Status (success, failure, blocked)
 * @return bool Success status
 */
function log_audit($user_id, $action, $details = null, $status = 'success') {
    try {
        $pdo = getPDO();
        $ip = get_client_ip();

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, ip_address, action, details, status, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );

        return $stmt->execute([$user_id, $ip, $action, $details, $status]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if account is locked
 * SECURITY: Implements account lockout after failed login attempts
 *
 * @param array $user User data from database
 * @return array ['locked' => bool, 'message' => string, 'remaining_minutes' => int]
 */
function check_account_lockout($user) {
    if (!$user) {
        return ['locked' => false, 'message' => '', 'remaining_minutes' => 0];
    }

    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
        return [
            'locked' => true,
            'message' => "Account locked due to multiple failed login attempts. Try again in {$remaining} minute(s).",
            'remaining_minutes' => $remaining
        ];
    }

    return ['locked' => false, 'message' => '', 'remaining_minutes' => 0];
}

/**
 * Record failed login attempt
 * SECURITY: Increments failed login counter and locks account after threshold
 *
 * @param int $user_id User ID
 * @param int $max_attempts Maximum attempts before lockout (default: 5)
 * @param int $lockout_minutes Lockout duration in minutes (default: 30)
 * @return bool Success status
 */
function record_failed_login($user_id, $max_attempts = 5, $lockout_minutes = 30) {
    try {
        $pdo = getPDO();

        // Get current attempt count
        $stmt = $pdo->prepare("SELECT failed_login_attempts FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $attempts = $user['failed_login_attempts'] + 1;

        if ($attempts >= $max_attempts) {
            // Lock account
            $stmt = $pdo->prepare(
                "UPDATE users SET failed_login_attempts = ?,
                 locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                 WHERE user_id = ?"
            );
            $stmt->execute([$attempts, $lockout_minutes, $user_id]);

            log_audit($user_id, 'account_locked', "Failed attempts: {$attempts}", 'blocked');
        } else {
            // Just increment counter
            $stmt = $pdo->prepare(
                "UPDATE users SET failed_login_attempts = ? WHERE user_id = ?"
            );
            $stmt->execute([$attempts, $user_id]);

            log_audit($user_id, 'login_failed', "Failed attempt {$attempts}/{$max_attempts}", 'failure');
        }

        return true;
    } catch (Exception $e) {
        error_log("Failed login recording error: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset failed login attempts on successful login
 * SECURITY: Clears lockout status after successful authentication
 *
 * @param int $user_id User ID
 * @return bool Success status
 */
function reset_failed_login_attempts($user_id) {
    try {
        $stmt = db_query(
            "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?",
            [$user_id]
        );

        log_audit($user_id, 'login_success', 'Failed attempts reset', 'success');

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Failed login reset error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean expired rate limits
 * Call this periodically (e.g., via cron) to clean up old records
 *
 * @return int Number of records deleted
 */
function clean_expired_rate_limits() {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Rate limit cleanup error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get recent security events for a user
 *
 * @param int $user_id User ID
 * @param int $limit Number of records to return
 * @return array Audit log entries
 */
function get_user_security_events($user_id, $limit = 20) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT * FROM audit_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Security events retrieval error: " . $e->getMessage());
        return [];
    }
}

/**
 * Clean old audit logs
 * Call this periodically to maintain database size
 *
 * @param int $days Keep logs for this many days (default: 90)
 * @return int Number of records deleted
 */
function clean_old_audit_logs($days = 90) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Audit log cleanup error: " . $e->getMessage());
        return 0;
    }
}
?>
