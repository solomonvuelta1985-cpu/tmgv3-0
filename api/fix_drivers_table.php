<?php
/**
 * Fix drivers table - Add missing columns
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = getPDO();

    echo "<h2>Fixing drivers Table Structure</h2>";
    echo "<p>Adding missing columns: date_of_birth and age...</p>";

    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE drivers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existing_columns = array_column($columns, 'Field');

    $changes_made = [];

    // Add date_of_birth if missing
    if (!in_array('date_of_birth', $existing_columns)) {
        $pdo->exec("ALTER TABLE drivers ADD COLUMN date_of_birth DATE NULL AFTER suffix");
        $changes_made[] = "✅ Added date_of_birth column";
        echo "<p style='color: green;'>✅ Added date_of_birth column</p>";
    } else {
        echo "<p style='color: blue;'>ℹ date_of_birth column already exists</p>";
    }

    // Add age if missing
    if (!in_array('age', $existing_columns)) {
        $pdo->exec("ALTER TABLE drivers ADD COLUMN age INT(11) NULL AFTER date_of_birth");
        $changes_made[] = "✅ Added age column";
        echo "<p style='color: green;'>✅ Added age column</p>";
    } else {
        echo "<p style='color: blue;'>ℹ age column already exists</p>";
    }

    if (empty($changes_made)) {
        echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; margin: 20px 0;'>";
        echo "<strong>ℹ No changes needed</strong> - All columns already exist!";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; margin: 20px 0;'>";
        echo "<strong>✅ SUCCESS!</strong> Drivers table has been fixed:<br>";
        echo "<ul>";
        foreach ($changes_made as $change) {
            echo "<li>$change</li>";
        }
        echo "</ul>";
        echo "</div>";
    }

    // Show updated structure
    echo "<h3>Updated drivers Table Structure</h3>";
    $stmt = $pdo->query("DESCRIBE drivers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='5' style='margin-top: 10px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        $highlight = in_array($col['Field'], ['date_of_birth', 'age']) ? 'background: #ffffcc;' : '';
        echo "<tr style='$highlight'>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffc107; margin: 20px 0;'>";
    echo "<h4>⚠ Next Steps:</h4>";
    echo "<ol>";
    echo "<li><strong>Clear your error log</strong> or note the timestamp</li>";
    echo "<li><strong>Try creating a new citation</strong> at: <a href='/tmg/public/index2.php' target='_blank'>index2.php</a></li>";
    echo "<li><strong>The citation should now:</strong>";
    echo "<ul>";
    echo "<li>✅ Create a driver record successfully</li>";
    echo "<li>✅ Link citation to driver_id</li>";
    echo "<li>✅ Enable duplicate detection</li>";
    echo "<li>✅ Enable offense tracking</li>";
    echo "</ul>";
    echo "</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
