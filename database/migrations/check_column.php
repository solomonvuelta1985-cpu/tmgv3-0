<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    $result = db_query("DESCRIBE violation_types");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);

    echo "Current columns in violation_types table:\n";
    echo "=========================================\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }

    // Check if category exists
    $has_category = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'category') {
            $has_category = true;
            break;
        }
    }

    echo "\nCategory column exists: " . ($has_category ? "YES" : "NO") . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
