<?php
/**
 * Diagnostic Page - Database & System Check
 * Upload this to your production server to diagnose issues
 * Access: https://btracs.online/tmg/public/diagnostic.php
 * DELETE THIS FILE after troubleshooting for security!
 */

// Force display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>TMG System Diagnostic</h1>";
echo "<p>Testing database connection and system configuration...</p>";
echo "<hr>";

// 1. Check PHP Version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Required: 7.4 or higher<br>";
echo phpversion() >= '7.4' ? "✅ PASS" : "❌ FAIL";
echo "<hr>";

// 2. Check Required Files
echo "<h2>2. Required Files</h2>";
$requiredFiles = [
    '../includes/config.php',
    '../includes/functions.php',
    '../includes/auth.php',
    '../includes/security.php'
];

foreach ($requiredFiles as $file) {
    $exists = file_exists($file);
    echo ($exists ? "✅" : "❌") . " $file<br>";
}
echo "<hr>";

// 3. Test Database Configuration
echo "<h2>3. Database Configuration</h2>";
try {
    require_once '../includes/config.php';

    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_USER: " . DB_USER . "<br>";
    echo "DB_PASS: " . str_repeat('*', strlen(DB_PASS)) . " (hidden)<br>";
    echo "BASE_PATH: " . BASE_PATH . "<br>";
    echo "DEBUG_MODE: " . (DEBUG_MODE ? 'ON' : 'OFF') . "<br>";
    echo "<hr>";

} catch (Exception $e) {
    echo "❌ ERROR loading config: " . $e->getMessage() . "<br><hr>";
    exit;
}

// 4. Test Database Connection
echo "<h2>4. Database Connection</h2>";
try {
    $pdo = getPDO();

    if ($pdo === null) {
        echo "❌ FAIL: Database connection returned NULL<br>";
        echo "This means the database credentials are incorrect or the database doesn't exist.<br>";
    } else {
        echo "✅ SUCCESS: Connected to database<br>";
    }
    echo "<hr>";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br><hr>";
}

// 5. Check Database Tables
echo "<h2>5. Database Tables</h2>";
if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            echo "❌ WARNING: No tables found in database!<br>";
            echo "You need to import your database schema.<br>";
        } else {
            echo "✅ Found " . count($tables) . " tables:<br>";
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>$table</li>";
            }
            echo "</ul>";

            // Check for required tables
            $requiredTables = ['users', 'citations', 'violations', 'payments'];
            echo "<br><strong>Required tables:</strong><br>";
            foreach ($requiredTables as $table) {
                $exists = in_array($table, $tables);
                echo ($exists ? "✅" : "❌") . " $table<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ ERROR checking tables: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ Skipped (no database connection)<br>";
}
echo "<hr>";

// 6. Check for Admin User
echo "<h2>6. Admin User Check</h2>";
if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            echo "✅ Found {$result['count']} admin user(s)<br>";

            // Show admin usernames
            $stmt = $pdo->query("SELECT username, status FROM users WHERE role = 'admin'");
            $admins = $stmt->fetchAll();
            echo "<ul>";
            foreach ($admins as $admin) {
                echo "<li>Username: <strong>{$admin['username']}</strong> (Status: {$admin['status']})</li>";
            }
            echo "</ul>";
        } else {
            echo "❌ WARNING: No admin users found!<br>";
            echo "You need to create an admin user or import your database.<br>";
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "<br>";
        echo "The 'users' table might not exist.<br>";
    }
} else {
    echo "⚠️ Skipped (no database connection)<br>";
}
echo "<hr>";

// 7. Test Authentication Functions
echo "<h2>7. Authentication System</h2>";
try {
    require_once '../includes/functions.php';
    require_once '../includes/auth.php';

    echo "✅ Auth system loaded successfully<br>";
    echo "Functions available:<br>";
    echo (function_exists('authenticate') ? "✅" : "❌") . " authenticate()<br>";
    echo (function_exists('create_session') ? "✅" : "❌") . " create_session()<br>";
    echo (function_exists('is_logged_in') ? "✅" : "❌") . " is_logged_in()<br>";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 8. Session Test
echo "<h2>8. Session Configuration</h2>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive') . "<br>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "Session ID: " . session_id() . "<br>";
}
echo "<hr>";

// Summary
echo "<h2>Summary</h2>";
echo "<p><strong>If you see any ❌ errors above, fix those first!</strong></p>";
echo "<p><strong style='color:red;'>⚠️ DELETE THIS FILE (diagnostic.php) after troubleshooting for security!</strong></p>";

echo "<hr>";
echo "<p>Diagnostic completed at: " . date('Y-m-d H:i:s') . "</p>";
?>
