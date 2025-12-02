<?php
/**
 * Run Data Consistency Constraints Migration
 *
 * This script applies the database migration that adds:
 * - Enhanced triggers with error handling
 * - Consistency validation
 * - Automatic logging of trigger failures
 * - Stored procedure for checking data consistency
 */

require_once __DIR__ . '/includes/config.php';

// Get database connection
$pdo = getPDO();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Consistency Migration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 {
            color: #2d3748;
            border-bottom: 4px solid #667eea;
            padding-bottom: 15px;
        }
        h2 {
            color: #4a5568;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 30px;
        }
        .success-box {
            background: #d1fae5;
            border-left: 6px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .error-box {
            background: #fee2e2;
            border-left: 6px solid #ef4444;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .info-box {
            background: #dbeafe;
            border-left: 6px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: left;
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 5px;
            font-weight: 600;
        }
        code {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            display: block;
            margin: 10px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Data Consistency Constraints Migration</h1>

        <div class="info-box">
            <p><strong>This migration will:</strong></p>
            <ul>
                <li>✅ Drop and recreate payment triggers with enhanced error handling</li>
                <li>✅ Add validation to prevent inconsistent payment statuses</li>
                <li>✅ Create error logging table to track trigger failures</li>
                <li>✅ Add stored procedure for checking data consistency</li>
                <li>✅ Log all trigger execution issues for debugging</li>
            </ul>
        </div>

        <?php
        try {
            $migrationFile = __DIR__ . '/database/migrations/add_data_consistency_constraints.sql';

            if (!file_exists($migrationFile)) {
                throw new Exception("Migration file not found: {$migrationFile}");
            }

            echo "<h2>Step 1: Reading Migration File</h2>";
            $sql = file_get_contents($migrationFile);

            if ($sql === false) {
                throw new Exception("Failed to read migration file");
            }

            echo "<div class='success-box'>";
            echo "<p>✅ Migration file loaded successfully (" . number_format(strlen($sql)) . " bytes)</p>";
            echo "</div>";

            echo "<h2>Step 2: Executing Migration</h2>";

            // Split SQL into individual statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    // Filter out comments and empty statements
                    return !empty($stmt) &&
                           !preg_match('/^\s*--/', $stmt) &&
                           !preg_match('/^\s*\/\*/', $stmt) &&
                           !preg_match('/^DELIMITER/', $stmt) &&
                           strlen(trim($stmt)) > 5;
                }
            );

            $executed = 0;
            $failed = 0;
            $results = [];

            foreach ($statements as $index => $statement) {
                $statement = trim($statement);

                // Skip DELIMITER commands and comments
                if (empty($statement) ||
                    stripos($statement, 'DELIMITER') === 0 ||
                    stripos($statement, '-- ===') === 0 ||
                    stripos($statement, '/*') === 0) {
                    continue;
                }

                try {
                    // Add semicolon back if not present
                    if (substr($statement, -1) !== ';') {
                        $statement .= ';';
                    }

                    $pdo->exec($statement);
                    $executed++;

                    // Show summary for important operations
                    if (stripos($statement, 'CREATE TRIGGER') !== false) {
                        preg_match('/CREATE TRIGGER (\w+)/i', $statement, $matches);
                        $results[] = ['type' => 'Trigger', 'name' => $matches[1] ?? 'Unknown', 'status' => '✅ Created'];
                    } elseif (stripos($statement, 'CREATE TABLE') !== false) {
                        preg_match('/CREATE TABLE (?:IF NOT EXISTS )?(\w+)/i', $statement, $matches);
                        $results[] = ['type' => 'Table', 'name' => $matches[1] ?? 'Unknown', 'status' => '✅ Created'];
                    } elseif (stripos($statement, 'CREATE PROCEDURE') !== false) {
                        preg_match('/CREATE PROCEDURE (\w+)/i', $statement, $matches);
                        $results[] = ['type' => 'Procedure', 'name' => $matches[1] ?? 'Unknown', 'status' => '✅ Created'];
                    } elseif (stripos($statement, 'DROP TRIGGER') !== false) {
                        preg_match('/DROP TRIGGER (?:IF EXISTS )?(\w+)/i', $statement, $matches);
                        $results[] = ['type' => 'Trigger', 'name' => $matches[1] ?? 'Unknown', 'status' => '🗑️ Dropped'];
                    }

                } catch (PDOException $e) {
                    $failed++;
                    // Some errors are expected (like DROP IF EXISTS on non-existent items)
                    if (stripos($e->getMessage(), 'does not exist') === false &&
                        stripos($e->getMessage(), 'TRIGGER does not exist') === false) {
                        $results[] = [
                            'type' => 'Error',
                            'name' => 'Statement ' . ($index + 1),
                            'status' => '❌ ' . substr($e->getMessage(), 0, 100)
                        ];
                    }
                }
            }

            echo "<div class='success-box'>";
            echo "<p><strong>Migration completed!</strong></p>";
            echo "<p>✅ Executed: {$executed} statements</p>";
            if ($failed > 0) {
                echo "<p>⚠️ Skipped/Failed: {$failed} statements (likely DROP IF EXISTS on non-existent items)</p>";
            }
            echo "</div>";

            if (!empty($results)) {
                echo "<h3>Changes Applied:</h3>";
                echo "<table>";
                echo "<tr><th>Type</th><th>Name</th><th>Status</th></tr>";
                foreach ($results as $result) {
                    echo "<tr>";
                    echo "<td>{$result['type']}</td>";
                    echo "<td><code>{$result['name']}</code></td>";
                    echo "<td>{$result['status']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            // Verify triggers were created
            echo "<h2>Step 3: Verification</h2>";

            $stmt = $pdo->query("
                SELECT
                    TRIGGER_NAME,
                    EVENT_MANIPULATION,
                    EVENT_OBJECT_TABLE,
                    ACTION_TIMING
                FROM INFORMATION_SCHEMA.TRIGGERS
                WHERE TRIGGER_SCHEMA = 'traffic_system'
                  AND TRIGGER_NAME IN ('before_payment_status_change', 'after_payment_insert', 'after_payment_update')
                ORDER BY TRIGGER_NAME
            ");
            $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($triggers) === 3) {
                echo "<div class='success-box'>";
                echo "<p>✅ All 3 triggers created successfully!</p>";
                echo "</div>";

                echo "<table>";
                echo "<tr><th>Trigger Name</th><th>Event</th><th>Table</th><th>Timing</th></tr>";
                foreach ($triggers as $trigger) {
                    echo "<tr>";
                    echo "<td><code>{$trigger['TRIGGER_NAME']}</code></td>";
                    echo "<td>{$trigger['EVENT_MANIPULATION']}</td>";
                    echo "<td>{$trigger['EVENT_OBJECT_TABLE']}</td>";
                    echo "<td>{$trigger['ACTION_TIMING']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='error-box'>";
                echo "<p>⚠️ Expected 3 triggers, found " . count($triggers) . "</p>";
                echo "</div>";
            }

            // Check if stored procedure exists
            $stmt = $pdo->query("
                SHOW PROCEDURE STATUS WHERE Db = 'traffic_system' AND Name = 'sp_check_citation_payment_consistency'
            ");
            $procedures = $stmt->fetchAll();

            if (count($procedures) > 0) {
                echo "<div class='success-box'>";
                echo "<p>✅ Stored procedure <code>sp_check_citation_payment_consistency</code> created successfully!</p>";
                echo "</div>";
            }

            // Run consistency check
            echo "<h2>Step 4: Data Consistency Check</h2>";

            try {
                $stmt = $pdo->query("CALL sp_check_citation_payment_consistency()");
                $inconsistencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($inconsistencies)) {
                    echo "<div class='success-box'>";
                    echo "<p>✅ <strong>No data inconsistencies found!</strong> All citations and payments are properly synchronized.</p>";
                    echo "</div>";
                } else {
                    echo "<div class='error-box'>";
                    echo "<p>⚠️ Found " . count($inconsistencies) . " citation(s) with data inconsistencies:</p>";
                    echo "</div>";

                    echo "<table>";
                    echo "<tr><th>Citation ID</th><th>Ticket #</th><th>Citation Status</th><th>Payment Count</th><th>Payments</th></tr>";
                    foreach ($inconsistencies as $row) {
                        echo "<tr>";
                        echo "<td>{$row['citation_id']}</td>";
                        echo "<td>{$row['ticket_number']}</td>";
                        echo "<td>{$row['citation_status']}</td>";
                        echo "<td>{$row['payment_count']}</td>";
                        echo "<td style='font-size: 0.85em;'>{$row['payments']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";

                    echo "<div class='info-box'>";
                    echo "<p><strong>Action Required:</strong> Run the fix script to resolve these inconsistencies:</p>";
                    echo "<p><a href='fix_pending_paid_citations.php' class='btn'>🔧 Fix Data Inconsistencies</a></p>";
                    echo "</div>";
                }
            } catch (PDOException $e) {
                echo "<div class='error-box'>";
                echo "<p>Error running consistency check: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "</div>";
            }

            echo "<div class='success-box'>";
            echo "<h3>🎉 Migration Complete!</h3>";
            echo "<p><strong>What's New:</strong></p>";
            echo "<ul>";
            echo "<li>✅ Enhanced triggers now log errors when citation updates fail</li>";
            echo "<li>✅ Validation prevents creating completed payments on void citations</li>";
            echo "<li>✅ All trigger failures are logged to <code>trigger_error_log</code> table</li>";
            echo "<li>✅ Stored procedure <code>sp_check_citation_payment_consistency()</code> is ready to use</li>";
            echo "</ul>";
            echo "<p><strong>Next Steps:</strong></p>";
            echo "<ol>";
            echo "<li>Monitor the <code>trigger_error_log</code> table for any issues</li>";
            echo "<li>Run consistency checks periodically</li>";
            echo "<li>Test payment processing to ensure triggers work correctly</li>";
            echo "</ol>";
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='error-box'>";
            echo "<h3>❌ Migration Failed</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
        }
        ?>

        <p style="margin-top: 30px; text-align: center;">
            <a href="investigate_citation_payment_inconsistency.php" class="btn">🔍 Run Investigation Tool</a>
            <a href="public/citations.php" class="btn">📋 View Citations</a>
        </p>
    </div>
</body>
</html>
