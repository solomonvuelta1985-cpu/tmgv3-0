<?php
// Timezone Configuration
date_default_timezone_set('Asia/Manila');

// Error Logging Configuration
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');

// ============================================================
// ENVIRONMENT-BASED CONFIGURATION
// Automatically detects localhost vs production
// ============================================================

// Detect if running on localhost (XAMPP) or production server
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalhost = (
    $httpHost === 'localhost' ||
    $httpHost === '127.0.0.1' ||
    strpos($httpHost, 'localhost:') === 0 ||
    strpos($httpHost, '127.0.0.1:') === 0
);

if ($isLocalhost) {
    // ========================================
    // LOCALHOST (XAMPP) CONFIGURATION
    // ========================================
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'traffic_system');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_PATH', '/tmg');

} else {
    // ========================================
    // PRODUCTION SERVER CONFIGURATION (btracs.online)
    // ========================================
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'btrahnqi_traffic_system');
    define('DB_USER', 'btrahnqi_richmond');
    define('DB_PASS', 'Almondmamon@17');
    define('BASE_PATH', '/tmg');
}

// Common configuration for both environments
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// SECURITY: DEBUG MODE CONFIGURATION
// ============================================================
// LOCALHOST: Debug mode enabled - shows detailed error messages
// PRODUCTION: Debug mode disabled - shows generic error messages only
// IMPORTANT: Set to false in production to prevent information disclosure
define('DEBUG_MODE', $isLocalhost); // Auto-detect: true for localhost, false for production
// UNCOMMENT FOR PRODUCTION: define('DEBUG_MODE', false);

// Auto-detect base path (optional - for automatic configuration)
function getBasePath() {
    // If BASE_PATH is explicitly defined and not empty, use it
    if (defined('BASE_PATH') && BASE_PATH !== 'auto') {
        return BASE_PATH;
    }

    // Auto-detect from server variables
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';

    // Extract base path from script name
    $basePath = dirname($scriptName);

    // Remove public folder from path if present
    $basePath = str_replace('/public', '', $basePath);
    $basePath = str_replace('/admin', '', $basePath);
    $basePath = str_replace('/api', '', $basePath);

    // Clean up the path
    $basePath = rtrim($basePath, '/');

    return $basePath;
}

// Session configuration should be set BEFORE session_start()
// These are now commented out since session is already started
/*
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_strict_mode', 1);
*/

// ============================================================
// SECURITY HEADERS
// ============================================================
if (!headers_sent()) {
    // Basic Security Headers
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // SECURITY: Content Security Policy (CSP)
    // Helps prevent XSS attacks by controlling resource loading
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://unpkg.com; " .
           "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
           "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
           "img-src 'self' data: https:; " .
           "connect-src 'self' https://cdn.jsdelivr.net https://unpkg.com; " .
           "frame-ancestors 'self'; " .
           "base-uri 'self'; " .
           "form-action 'self';";
    header("Content-Security-Policy: " . $csp);

    // PRODUCTION ONLY: HTTPS Enforcement Headers
    // LOCALHOST: Commented out (no HTTPS)
    if (!$isLocalhost) {
        // HSTS: Force HTTPS for 1 year (enabled for production)
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

        // Upgrade insecure requests to HTTPS
        header("Content-Security-Policy: upgrade-insecure-requests");
    }

    // Permissions Policy (formerly Feature Policy)
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

// PDO Database Connection
function getPDO() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            // Don't throw exception to prevent breaking the form
            return null;
        }
    }
    return $pdo;
}
?>