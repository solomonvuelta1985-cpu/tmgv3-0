<?php
/**
 * Complete Database Structure Analysis
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
th { background: #007bff; color: white; padding: 10px; text-align: left; }
td { padding: 8px; border: 1px solid #ddd; }
.missing { background: #f8d7da; color: #721c24; font-weight: bold; }
.exists { background: #d4edda; color: #155724; }
.warning { background: #fff3cd; padding: 15px; border: 1px solid #ffc107; margin: 20px 0; }
.error { background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; margin: 20px 0; }
.success { background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; margin: 20px 0; }
h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
</style>";

try {
    $pdo = getPDO();

    echo "<h1>🔍 Complete Database Analysis: traffic_system</h1>";

    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h2>📋 Database Tables (" . count($tables) . " total)</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li><strong>$table</strong></li>";
    }
    echo "</ul>";

    // Critical tables to check
    $critical_tables = [
        'citations',
        'drivers',
        'violations',
        'violation_types',
        'apprehending_officers'
    ];

    foreach ($critical_tables as $table) {
        if (!in_array($table, $tables)) {
            echo "<div class='error'>❌ <strong>MISSING TABLE:</strong> $table</div>";
        }
    }

    echo "<hr>";

    // Analyze each table structure
    foreach ($tables as $table) {
        echo "<h2>📊 Table: <code>$table</code></h2>";

        // Get structure
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>" . $col['Field'] . "</strong></td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . ($col['Key'] ?? '') . "</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . ($col['Extra'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<p><strong>Rows:</strong> $count</p>";

        // Show sample data for small tables
        if ($count > 0 && $count <= 10) {
            echo "<details><summary>📄 Sample Data (click to expand)</summary>";
            $stmt = $pdo->query("SELECT * FROM $table LIMIT 5");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                echo "<table style='font-size: 12px;'>";
                echo "<tr>";
                foreach (array_keys($rows[0]) as $header) {
                    echo "<th>$header</th>";
                }
                echo "</tr>";
                foreach ($rows as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
            echo "</details>";
        }

        echo "<hr>";
    }

    // CRITICAL CHECK: violations table for offense_count
    echo "<h2>🔴 CRITICAL CHECK: violations Table</h2>";

    if (in_array('violations', $tables)) {
        $stmt = $pdo->query("DESCRIBE violations");
        $violation_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $column_names = array_column($violation_columns, 'Field');

        echo "<div class='warning'>";
        echo "<h3>⚠ Checking for OFFENSE_COUNT column...</h3>";

        if (in_array('offense_count', $column_names)) {
            echo "<p class='exists'>✅ <strong>offense_count</strong> column EXISTS!</p>";

            // Check data
            $stmt = $pdo->query("SELECT offense_count, COUNT(*) as count FROM violations GROUP BY offense_count");
            $offense_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<p><strong>Offense Count Distribution:</strong></p>";
            echo "<table style='width: auto;'>";
            echo "<tr><th>Offense Count</th><th>Number of Violations</th></tr>";
            foreach ($offense_data as $row) {
                echo "<tr><td>" . ($row['offense_count'] ?? 'NULL') . "</td><td>" . $row['count'] . "</td></tr>";
            }
            echo "</table>";

        } else {
            echo "<p class='missing'>❌ <strong>offense_count</strong> column is MISSING!</p>";
            echo "<p><strong>This column is REQUIRED for:</strong></p>";
            echo "<ul>";
            echo "<li>Tracking 1st, 2nd, 3rd offenses</li>";
            echo "<li>Applying correct fine amounts</li>";
            echo "<li>Repeat offender detection</li>";
            echo "</ul>";
            echo "<p><a href='/tmg/api/fix_violations_table.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 FIX IT NOW</a></p>";
        }
        echo "</div>";

        // Check for fine_amount column
        if (in_array('fine_amount', $column_names)) {
            echo "<p class='exists'>✅ <strong>fine_amount</strong> column EXISTS</p>";
        } else {
            echo "<p class='missing'>❌ <strong>fine_amount</strong> column is MISSING!</p>";
        }

    } else {
        echo "<div class='error'>";
        echo "<h3>❌ violations TABLE DOES NOT EXIST!</h3>";
        echo "<p>This is a critical table. The system cannot track violations without it.</p>";
        echo "</div>";
    }

    // Check violation_types table for fine columns
    echo "<h2>💰 CHECKING: violation_types Fine Columns</h2>";

    if (in_array('violation_types', $tables)) {
        $stmt = $pdo->query("DESCRIBE violation_types");
        $vt_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $vt_column_names = array_column($vt_columns, 'Field');

        echo "<div class='warning'>";
        $required_fine_columns = ['fine_amount_1', 'fine_amount_2', 'fine_amount_3'];
        $missing_fine_cols = [];

        foreach ($required_fine_columns as $col) {
            if (in_array($col, $vt_column_names)) {
                echo "<p class='exists'>✅ <strong>$col</strong> EXISTS</p>";
            } else {
                echo "<p class='missing'>❌ <strong>$col</strong> MISSING!</p>";
                $missing_fine_cols[] = $col;
            }
        }

        if (!empty($missing_fine_cols)) {
            echo "<p><strong>Missing columns:</strong> " . implode(', ', $missing_fine_cols) . "</p>";
        }
        echo "</div>";
    }

    // Check drivers table
    echo "<h2>👤 CHECKING: drivers Table</h2>";

    if (in_array('drivers', $tables)) {
        $stmt = $pdo->query("DESCRIBE drivers");
        $driver_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $driver_column_names = array_column($driver_columns, 'Field');

        echo "<div class='warning'>";
        $required_driver_columns = ['driver_id', 'last_name', 'first_name', 'date_of_birth', 'age', 'license_number'];

        foreach ($required_driver_columns as $col) {
            if (in_array($col, $driver_column_names)) {
                echo "<p class='exists'>✅ <strong>$col</strong> EXISTS</p>";
            } else {
                echo "<p class='missing'>❌ <strong>$col</strong> MISSING!</p>";
            }
        }
        echo "</div>";

        // Check if drivers table is empty
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM drivers");
        $driver_count = $stmt->fetch()['count'];

        if ($driver_count == 0) {
            echo "<div class='warning'>";
            echo "<p>⚠ <strong>drivers table is EMPTY</strong></p>";
            echo "<p>This is why duplicate detection isn't working yet. Driver records will be created when you submit new citations.</p>";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "<p>✅ <strong>$driver_count driver(s)</strong> in database</p>";
            echo "</div>";
        }
    }

    // Summary
    echo "<h2>📝 SUMMARY & ACTION ITEMS</h2>";
    echo "<div class='success'>";
    echo "<h3>✅ What's Working:</h3>";
    echo "<ul>";
    echo "<li>Database connection: OK</li>";
    echo "<li>Total tables: " . count($tables) . "</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div class='warning'>";
    echo "<h3>⚠ Action Required:</h3>";
    echo "<p>Check the analysis above for any MISSING columns or tables marked with ❌</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ ERROR</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>
