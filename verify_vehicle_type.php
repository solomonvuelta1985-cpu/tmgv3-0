<?php
require_once __DIR__ . '/includes/config.php';

try {
    $db = getPDO();

    // Check if vehicle_type column exists
    echo "Checking citations table structure...\n";
    echo "=====================================\n\n";

    $stmt = $db->query("SHOW COLUMNS FROM citations LIKE 'vehicle_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "✅ vehicle_type column EXISTS\n";
        echo "Type: " . $column['Type'] . "\n";
        echo "Null: " . $column['Null'] . "\n";
        echo "Default: " . $column['Default'] . "\n\n";
    } else {
        echo "❌ vehicle_type column DOES NOT EXIST\n";
        echo "Adding the column now...\n\n";

        $db->exec("ALTER TABLE citations ADD COLUMN vehicle_type VARCHAR(50) NULL AFTER plate_mv_engine_chassis_no");
        echo "✅ vehicle_type column has been added!\n\n";
    }

    // Show all columns in citations table
    echo "All columns in citations table:\n";
    echo "================================\n";
    $stmt = $db->query("SHOW COLUMNS FROM citations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        $marker = ($col['Field'] === 'vehicle_type') ? ' ← NEW' : '';
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")" . $marker . "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
