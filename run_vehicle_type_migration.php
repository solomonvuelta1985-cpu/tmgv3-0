<?php
/**
 * Run migration to add vehicle_type column to citations table
 */

require_once __DIR__ . '/includes/config.php';

try {
    $db = getPDO();

    if ($db === null) {
        die("ERROR: Database connection failed. Check your database credentials in config.php\n");
    }

    echo "Running migration: add_vehicle_type_column.sql\n";
    echo "=========================================\n\n";

    // Read the migration file
    $migrationFile = __DIR__ . '/database/migrations/add_vehicle_type_column.sql';

    if (!file_exists($migrationFile)) {
        die("ERROR: Migration file not found: $migrationFile\n");
    }

    $sql = file_get_contents($migrationFile);

    // Split by semicolons to execute each statement separately
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Filter out empty statements and comments
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );

    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;

        echo "Executing: " . substr($statement, 0, 100) . "...\n";

        try {
            $db->exec($statement);
            echo "✓ Success\n\n";
        } catch (PDOException $e) {
            // Check if error is "Duplicate column" which means column already exists
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "✓ Column already exists - skipping\n\n";
            } else {
                throw $e;
            }
        }
    }

    echo "=========================================\n";
    echo "Migration completed successfully!\n";
    echo "The vehicle_type column has been added to the citations table.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
