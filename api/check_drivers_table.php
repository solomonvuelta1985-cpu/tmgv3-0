<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/auth.php';

// SECURITY: Require admin authentication
require_admin();

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = getPDO();

    echo "<h2>Drivers Table Structure</h2>";
    $stmt = $pdo->query("DESCRIBE drivers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . ($col['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h2>Drivers Table Data</h2>";
    $stmt = $pdo->query("SELECT * FROM drivers");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Total drivers: " . count($drivers) . "</strong></p>";

    if (count($drivers) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>";
        foreach (array_keys($drivers[0]) as $header) {
            echo "<th>$header</th>";
        }
        echo "</tr>";
        foreach ($drivers as $driver) {
            echo "<tr>";
            foreach ($driver as $value) {
                echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffc107;'>";
        echo "<strong>⚠ WARNING: The drivers table is EMPTY!</strong><br>";
        echo "This is why duplicate detection and offense tracking don't work.";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
