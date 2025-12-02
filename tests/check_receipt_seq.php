<?php
require_once 'includes/config.php';

try {
    $pdo = getPDO();

    // Check receipt_sequence table
    $stmt = $pdo->query("SELECT * FROM receipt_sequence");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "✓ Receipt sequence exists:\n";
        echo "  Current Year: " . $result['current_year'] . "\n";
        echo "  Current Number: " . $result['current_number'] . "\n";
    } else {
        echo "✗ Receipt sequence NOT initialized!\n";
        echo "\nInitializing receipt sequence...\n";

        $pdo->exec("INSERT INTO receipt_sequence (id, current_year, current_number)
                    VALUES (1, " . date('Y') . ", 0)");

        echo "✓ Receipt sequence initialized successfully!\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
