<?php
/**
 * Migration Runner
 * Executes the category migration
 */

require_once __DIR__ . '/../../includes/config.php';

try {
    echo "Starting migration: Add category to violation_types...\n\n";

    // Read the migration SQL
    $sql = file_get_contents(__DIR__ . '/add_category_to_violations.sql');

    // Remove comments and split into individual statements
    $statements = array_filter(
        array_map('trim',
            preg_split('/;[\r\n]+/',
                preg_replace('/^--.*$/m', '', $sql)
            )
        )
    );

    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 100) . "...\n";
            db_query($statement);
            echo "✓ Success\n\n";
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
