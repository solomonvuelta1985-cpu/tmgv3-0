<?php
/**
 * Database Connection Test for btracs.online
 *
 * This file tests if your database credentials are correct
 * and if the necessary tables exist.
 *
 * DELETE THIS FILE after testing for security!
 */

// Include config
require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test - btracs.online</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Connection Test</h1>
        <p><strong>Domain:</strong> <?php echo $_SERVER['HTTP_HOST']; ?></p>
        <p><strong>Environment:</strong> <?php echo (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ? 'LOCALHOST' : 'PRODUCTION'); ?></p>

        <hr>

        <h2>1. Database Credentials</h2>
        <table>
            <tr><th>Setting</th><th>Value</th></tr>
            <tr><td>DB Host</td><td><?php echo DB_HOST; ?></td></tr>
            <tr><td>DB Name</td><td><?php echo DB_NAME; ?></td></tr>
            <tr><td>DB User</td><td><?php echo DB_USER; ?></td></tr>
            <tr><td>DB Password</td><td><?php echo str_repeat('*', strlen(DB_PASS)); ?></td></tr>
            <tr><td>Base Path</td><td><?php echo BASE_PATH; ?></td></tr>
        </table>

        <h2>2. Connection Test</h2>
        <?php
        try {
            $pdo = getPDO();

            if ($pdo) {
                echo '<div class="success">✅ <strong>SUCCESS!</strong> Database connection established.</div>';

                // Test database version
                $stmt = $pdo->query("SELECT VERSION() as version");
                $result = $stmt->fetch();
                echo '<div class="info">📊 MySQL Version: ' . $result['version'] . '</div>';

                // Test if tables exist
                echo '<h2>3. Database Tables</h2>';
                $tables = [
                    'citations',
                    'violations',
                    'violation_types',
                    'payments',
                    'users',
                    'drivers',
                    'audit_logs'
                ];

                echo '<table>';
                echo '<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>';

                foreach ($tables as $table) {
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
                        $result = $stmt->fetch();
                        $count = number_format($result['count']);
                        echo "<tr><td>$table</td><td style='color: green;'>✅ Exists</td><td>$count rows</td></tr>";
                    } catch (Exception $e) {
                        echo "<tr><td>$table</td><td style='color: red;'>❌ Missing</td><td>-</td></tr>";
                    }
                }
                echo '</table>';

                // Test a sample query
                echo '<h2>4. Sample Query Test</h2>';
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM citations");
                    $result = $stmt->fetch();
                    echo '<div class="success">✅ Sample query successful! Total citations: ' . number_format($result['total']) . '</div>';
                } catch (Exception $e) {
                    echo '<div class="error">❌ Sample query failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

            } else {
                echo '<div class="error">❌ <strong>FAILED!</strong> Could not establish database connection.</div>';
                echo '<div class="warning">⚠️ Check your database credentials in <code>includes/config.php</code></div>';
            }

        } catch (Exception $e) {
            echo '<div class="error">❌ <strong>CONNECTION ERROR:</strong><br><pre>' . htmlspecialchars($e->getMessage()) . '</pre></div>';
            echo '<div class="warning">';
            echo '<h3>Common Issues:</h3>';
            echo '<ul>';
            echo '<li>Database name is incorrect (check cPanel → MySQL Databases)</li>';
            echo '<li>Database user doesn\'t have permission to access the database</li>';
            echo '<li>Database password is incorrect</li>';
            echo '<li>Database hasn\'t been created yet on your hosting</li>';
            echo '<li>Database hasn\'t been imported (you need to import your SQL file)</li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>

        <hr>
        <div class="warning">
            <strong>⚠️ SECURITY WARNING:</strong> Delete this file (<code>test_db_connection.php</code>) after testing!
            <br>It exposes sensitive database information.
        </div>
    </div>
</body>
</html>
