<?php
/**
 * Payment System Installation Script
 *
 * This script automates the installation of the payment management system
 * Run this file once to set up all required database tables and triggers
 *
 * Usage: php install_payment_system.php
 * Or access via browser: http://localhost/tmg/install_payment_system.php
 *
 * @package TrafficCitationSystem
 */

// Include configuration
require_once __DIR__ . '/includes/config.php';

// Set execution time
set_time_limit(300);

// Initialize result array
$results = [];
$errors = [];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Payment System Installation</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { max-width: 900px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #198754; }
        .error { color: #dc3545; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #0d6efd; background: #f8f9fa; }
        .code { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
    <h1><i class='fas fa-cogs'></i> Payment System Installation</h1>
    <p class='text-muted'>Installing payment management and receipt generation system...</p>
    <hr>
";

try {
    // ========================================================================
    // STEP 1: Check database connection
    // ========================================================================
    echo "<div class='step'>";
    echo "<h4>Step 1: Checking Database Connection</h4>";

    $pdo = getPDO();

    if (!$pdo) {
        echo "<p class='error'>✗ Database connection failed!</p>";
        echo "<p><strong>Possible causes:</strong></p>";
        echo "<ul>";
        echo "<li>MySQL/MariaDB is not running - Start it in XAMPP Control Panel</li>";
        echo "<li>Database 'traffic_system' does not exist - Create it first</li>";
        echo "<li>Wrong database credentials in <code>includes/config.php</code></li>";
        echo "</ul>";
        echo "<p><strong>Check the error log:</strong> <code>php_errors.log</code></p>";
        throw new Exception("Database connection failed. Please fix the issues above.");
    }

    echo "<p class='success'>✓ Database connection successful</p>";
    echo "<p class='text-muted'>Connected to: <strong>" . DB_NAME . "</strong> on <strong>" . DB_HOST . "</strong></p>";
    echo "</div>";

    // ========================================================================
    // STEP 2: Create payment tables
    // ========================================================================
    echo "<div class='step'>";
    echo "<h4>Step 2: Creating Payment Tables</h4>";

    $sqlFile = __DIR__ . '/database/migrations/add_payment_tables.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);

    // Remove comments and split into individual queries
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments

    // Split by semicolon but keep in mind we need to handle DELIMITER changes
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    $tableCount = 0;
    foreach ($queries as $query) {
        if (empty($query)) continue;

        // Skip USE database statements
        if (stripos($query, 'USE ') === 0) continue;

        // Skip SELECT statements (verification queries)
        if (stripos($query, 'SELECT ') === 0) continue;

        try {
            $pdo->exec($query);

            // Check if this was a CREATE TABLE statement
            if (stripos($query, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                if (isset($matches[1])) {
                    $tableCount++;
                    echo "<p class='success'>✓ Created table: <strong>{$matches[1]}</strong></p>";
                }
            }
        } catch (PDOException $e) {
            // If table already exists, that's okay
            if (strpos($e->getMessage(), 'already exists') !== false) {
                preg_match('/Table \'(\w+)\'/', $e->getMessage(), $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p class='text-warning'>⚠ Table already exists: <strong>$tableName</strong></p>";
            } else {
                throw $e;
            }
        }
    }

    echo "<p class='success'><strong>Tables created: $tableCount</strong></p>";
    echo "</div>";

    // ========================================================================
    // STEP 3: Create triggers
    // ========================================================================
    echo "<div class='step'>";
    echo "<h4>Step 3: Creating Database Triggers</h4>";

    $triggersFile = __DIR__ . '/database/migrations/add_payment_triggers.sql';

    if (!file_exists($triggersFile)) {
        throw new Exception("Triggers file not found: $triggersFile");
    }

    $sql = file_get_contents($triggersFile);

    // Handle DELIMITER statements
    $sql = preg_replace('/DELIMITER\s+\/\//i', '', $sql);
    $sql = preg_replace('/DELIMITER\s+;/i', '', $sql);
    $sql = str_replace('END//', 'END;', $sql);

    // Remove comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Split into queries
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    $triggerCount = 0;
    foreach ($queries as $query) {
        if (empty($query)) continue;

        // Skip USE database statements
        if (stripos($query, 'USE ') === 0) continue;

        // Skip SELECT statements
        if (stripos($query, 'SELECT ') === 0) continue;

        try {
            $pdo->exec($query);

            // Check if this was a CREATE TRIGGER statement
            if (stripos($query, 'CREATE TRIGGER') !== false) {
                preg_match('/CREATE TRIGGER\s+`?(\w+)`?/i', $query, $matches);
                if (isset($matches[1])) {
                    $triggerCount++;
                    echo "<p class='success'>✓ Created trigger: <strong>{$matches[1]}</strong></p>";
                }
            } elseif (stripos($query, 'DROP TRIGGER') !== false) {
                preg_match('/DROP TRIGGER\s+(?:IF EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                if (isset($matches[1])) {
                    echo "<p class='text-muted'>Dropped existing trigger: {$matches[1]}</p>";
                }
            }
        } catch (PDOException $e) {
            echo "<p class='text-warning'>⚠ Trigger warning: {$e->getMessage()}</p>";
        }
    }

    echo "<p class='success'><strong>Triggers created: $triggerCount</strong></p>";
    echo "</div>";

    // ========================================================================
    // STEP 4: Verify installation
    // ========================================================================
    echo "<div class='step'>";
    echo "<h4>Step 4: Verifying Installation</h4>";

    // Check tables
    $stmt = $pdo->query("SHOW TABLES LIKE '%payment%'");
    $paymentTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SHOW TABLES LIKE '%receipt%'");
    $receiptTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $allTables = array_merge($paymentTables, $receiptTables);

    echo "<p><strong>Tables found:</strong></p><ul>";
    foreach ($allTables as $table) {
        echo "<li class='success'>$table</li>";
    }
    echo "</ul>";

    // Check triggers
    $stmt = $pdo->query("SHOW TRIGGERS WHERE `Trigger` LIKE '%payment%' OR `Trigger` LIKE '%receipt%'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Triggers found:</strong></p><ul>";
    foreach ($triggers as $trigger) {
        echo "<li class='success'>{$trigger['Trigger']}</li>";
    }
    echo "</ul>";

    // Check receipt_sequence initialization
    $stmt = $pdo->query("SELECT * FROM receipt_sequence");
    $sequence = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sequence) {
        echo "<p class='success'>✓ Receipt sequence initialized: Year {$sequence['current_year']}, Number {$sequence['current_number']}</p>";
    }

    echo "</div>";

    // ========================================================================
    // SUCCESS MESSAGE
    // ========================================================================
    echo "<div class='alert alert-success'>";
    echo "<h4>✅ Installation Complete!</h4>";
    echo "<p>The payment management system has been successfully installed.</p>";
    echo "<ul>";
    echo "<li>Tables created: " . count($allTables) . "</li>";
    echo "<li>Triggers created: " . count($triggers) . "</li>";
    echo "</ul>";
    echo "</div>";

    // ========================================================================
    // NEXT STEPS
    // ========================================================================
    echo "<div class='step'>";
    echo "<h4>Next Steps:</h4>";
    echo "<ol>";
    echo "<li>Configure receipt settings in <code>includes/pdf_config.php</code></li>";
    echo "<li>Add your LGU logo to <code>assets/images/logo.png</code> (80x80 pixels)</li>";
    echo "<li>Access payment management at: <a href='public/payments.php' target='_blank'>public/payments.php</a></li>";
    echo "<li>Test payment recording on a pending citation</li>";
    echo "<li>Verify receipt PDF generation</li>";
    echo "</ol>";
    echo "</div>";

    // ========================================================================
    // SECURITY NOTICE
    // ========================================================================
    echo "<div class='alert alert-warning'>";
    echo "<h5>⚠ Security Notice</h5>";
    echo "<p>For security reasons, you should:</p>";
    echo "<ol>";
    echo "<li><strong>Delete this installation file</strong> after successful installation</li>";
    echo "<li>Or move it outside the web-accessible directory</li>";
    echo "</ol>";
    echo "<div class='code'>rm install_payment_system.php</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Installation Failed</h4>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";

    echo "<div class='step'>";
    echo "<h4>Troubleshooting:</h4>";
    echo "<ul>";
    echo "<li>Check database connection in <code>includes/config.php</code></li>";
    echo "<li>Verify MySQL/MariaDB is running</li>";
    echo "<li>Check database user permissions</li>";
    echo "<li>Review error log: <code>php_errors.log</code></li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div>"; // Close container
echo "</body></html>";
?>
