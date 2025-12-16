<?php
/**
 * Run Import Tables Migration
 *
 * Simple CLI script to create all staging tables
 */

require_once __DIR__ . '/includes/config.php';

echo "========================================\n";
echo "IMPORT STAGING TABLES MIGRATION\n";
echo "========================================\n\n";

$migrationFile = __DIR__ . '/database/migrations/create_import_staging_tables.sql';

// Check if file exists
if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found at: $migrationFile\n");
}

echo "Reading SQL file...\n";
$sql = file_get_contents($migrationFile);

if ($sql === false) {
    die("ERROR: Failed to read migration file\n");
}

echo "Connecting to database...\n";
$pdo = getPDO();

if ($pdo === null) {
    die("ERROR: Database connection failed. Check your config.php credentials.\n");
}

echo "Database connected: " . DB_NAME . "\n\n";

// Remove comments
$sql = preg_replace('/--.*$/m', '', $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

// Parse statements
$statements = [];
$currentStatement = '';
$delimiter = ';';
$lines = explode("\n", $sql);

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    // Check for DELIMITER command
    if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
        $delimiter = trim($matches[1]);
        continue;
    }

    $currentStatement .= $line . "\n";

    // Check if statement ends
    if (substr(rtrim($line), -strlen($delimiter)) === $delimiter) {
        $stmt = trim(substr($currentStatement, 0, -strlen($delimiter)));
        if (!empty($stmt)) {
            $statements[] = $stmt;
        }
        $currentStatement = '';
    }
}

if (!empty(trim($currentStatement))) {
    $statements[] = trim($currentStatement);
}

echo "Found " . count($statements) . " SQL statements to execute\n\n";

// Execute statements
$executed = 0;
$errors = 0;
$tablesCreated = [];

foreach ($statements as $index => $statement) {
    $statement = trim($statement);
    if (empty($statement)) continue;

    // Skip GRANT statements
    if (stripos($statement, 'GRANT') === 0) {
        echo "[" . ($index + 1) . "] Skipped GRANT statement\n";
        continue;
    }

    // Skip verification SELECT
    if (stripos($statement, 'SELECT') === 0 && stripos($statement, 'information_schema') !== false) {
        continue;
    }

    try {
        $pdo->exec($statement);
        $executed++;

        // Track what was created
        if (stripos($statement, 'CREATE TABLE') !== false) {
            preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches);
            if (isset($matches[1])) {
                $tablesCreated[] = $matches[1];
                echo "[" . ($index + 1) . "] ✓ Created table: " . $matches[1] . "\n";
            }
        } elseif (stripos($statement, 'ALTER TABLE') !== false) {
            preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $statement, $matches);
            if (isset($matches[1])) {
                echo "[" . ($index + 1) . "] ✓ Modified table: " . $matches[1] . "\n";
            }
        } elseif (stripos($statement, 'CREATE PROCEDURE') !== false) {
            preg_match('/CREATE PROCEDURE\s+`?(\w+)`?/i', $statement, $matches);
            if (isset($matches[1])) {
                echo "[" . ($index + 1) . "] ✓ Created procedure: " . $matches[1] . "\n";
            }
        } elseif (stripos($statement, 'DROP TABLE') !== false) {
            preg_match('/DROP TABLE\s+(?:IF EXISTS\s+)?`?(\w+)`?/i', $statement, $matches);
            if (isset($matches[1])) {
                echo "[" . ($index + 1) . "] ✓ Dropped table: " . $matches[1] . "\n";
            }
        }

    } catch (PDOException $e) {
        // Check if it's a "duplicate column" error
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "[" . ($index + 1) . "] ⚠ Column already exists (skipped)\n";
            continue;
        } elseif (strpos($e->getMessage(), 'already exists') !== false) {
            echo "[" . ($index + 1) . "] ⚠ Object already exists (skipped)\n";
            continue;
        } else {
            echo "[" . ($index + 1) . "] ✗ ERROR: " . $e->getMessage() . "\n";
            echo "    Statement: " . substr($statement, 0, 100) . "...\n";
            $errors++;
        }
    }
}

echo "\n========================================\n";
echo "MIGRATION COMPLETE\n";
echo "========================================\n";
echo "Executed: $executed statements\n";
echo "Errors: $errors\n";

if (!empty($tablesCreated)) {
    echo "\nTables Created:\n";
    foreach ($tablesCreated as $table) {
        echo "  ✓ $table\n";
    }
}

// Verify tables
echo "\n========================================\n";
echo "VERIFICATION\n";
echo "========================================\n";

try {
    $stmt = $pdo->query("
        SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME LIKE 'import_%'
        ORDER BY TABLE_NAME
    ");

    $verifyTables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($verifyTables)) {
        echo "⚠ WARNING: No import tables found in database!\n";
    } else {
        echo "Found " . count($verifyTables) . " import tables:\n\n";
        foreach ($verifyTables as $table) {
            echo sprintf("  ✓ %-30s (%s rows)\n",
                $table['TABLE_NAME'],
                number_format($table['TABLE_ROWS'])
            );
        }
    }
} catch (Exception $e) {
    echo "✗ Verification failed: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";

if ($errors === 0) {
    echo "✅ SUCCESS! All staging tables created.\n";
    echo "\nNext step: Run the import\n";
    echo "  Web: http://localhost/tmg/web_import.php\n";
    echo "  CLI: php import_excel.php --dry-run\n";
} else {
    echo "⚠ WARNING: Migration completed with $errors errors.\n";
    echo "Check the errors above.\n";
}

echo "========================================\n";
