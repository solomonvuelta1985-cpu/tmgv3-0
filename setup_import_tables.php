<?php
/**
 * Setup Import Tables - One-Click Migration Runner
 *
 * This script creates all necessary staging tables for the Excel import system
 */

require_once __DIR__ . '/includes/config.php';

$migrationFile = __DIR__ . '/database/migrations/create_import_staging_tables.sql';

// Check if file exists
if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found at: $migrationFile");
}

$success = false;
$errors = [];
$messages = [];
$tablesCreated = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    try {
        $pdo = getPDO();

        if ($pdo === null) {
            throw new Exception("Database connection failed. Check your config.php credentials.");
        }

        // Read SQL file
        $sql = file_get_contents($migrationFile);

        if ($sql === false) {
            throw new Exception("Failed to read migration file.");
        }

        // Split into individual statements
        // Remove comments and split by semicolons
        $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments

        // Split by semicolons, but not those inside DELIMITER blocks
        $statements = [];
        $inDelimiter = false;
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

            // Check if statement ends with current delimiter
            if (substr(rtrim($line), -strlen($delimiter)) === $delimiter) {
                $stmt = trim(substr($currentStatement, 0, -strlen($delimiter)));
                if (!empty($stmt)) {
                    $statements[] = $stmt;
                }
                $currentStatement = '';
            }
        }

        // Add last statement if any
        if (!empty(trim($currentStatement))) {
            $statements[] = trim($currentStatement);
        }

        // Execute each statement
        $executed = 0;
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) continue;

            // Skip certain statements that might cause issues
            if (stripos($statement, 'GRANT') === 0) {
                $messages[] = "Skipped GRANT statement";
                continue;
            }

            if (stripos($statement, 'SELECT') === 0 && stripos($statement, 'FROM information_schema') !== false) {
                // This is the verification query at the end
                continue;
            }

            try {
                $pdo->exec($statement);
                $executed++;

                // Track table creation
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches);
                    if (isset($matches[1])) {
                        $tablesCreated[] = $matches[1];
                    }
                }

                // Track ALTER TABLE
                if (stripos($statement, 'ALTER TABLE') !== false) {
                    preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $statement, $matches);
                    if (isset($matches[1])) {
                        $messages[] = "Modified table: " . $matches[1];
                    }
                }

                // Track stored procedures
                if (stripos($statement, 'CREATE PROCEDURE') !== false) {
                    preg_match('/CREATE PROCEDURE\s+`?(\w+)`?/i', $statement, $matches);
                    if (isset($matches[1])) {
                        $messages[] = "Created stored procedure: " . $matches[1];
                    }
                }

            } catch (PDOException $e) {
                // Check if it's just a "duplicate column" error from ALTER TABLE
                if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    $messages[] = "Column already exists (skipped)";
                    continue;
                } else {
                    throw $e;
                }
            }
        }

        $success = true;
        $messages[] = "Successfully executed $executed SQL statements";

        // Verify tables were created
        $stmt = $pdo->query("
            SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME LIKE 'import_%'
            ORDER BY TABLE_NAME
        ");

        $verifyTables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Import Tables</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .content {
            padding: 40px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-error {
            background: #fee;
            border-color: #c33;
            color: #c33;
        }

        .alert-success {
            background: #efe;
            border-color: #3c3;
            color: #2a5;
        }

        .alert-info {
            background: #def;
            border-color: #39c;
            color: #16a;
        }

        .alert-warning {
            background: #ffc;
            border-color: #cc3;
            color: #885;
        }

        button {
            background: #28a745;
            color: white;
            padding: 15px 40px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            text-transform: uppercase;
        }

        button:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        button:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .table-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .table-list h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .table-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            font-family: 'Courier New', monospace;
        }

        .table-item:last-child {
            border-bottom: none;
        }

        .message-list {
            margin-top: 15px;
        }

        .message-item {
            padding: 8px 12px;
            background: #e9ecef;
            border-radius: 3px;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .check-icon::before {
            content: "✅ ";
        }

        .error-icon::before {
            content: "❌ ";
        }

        .info-icon::before {
            content: "ℹ️ ";
        }

        .next-step {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #667eea;
        }

        .next-step h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .btn-link {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .btn-link:hover {
            background: #5568d3;
        }

        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ Setup Import Tables</h1>
            <p>Create staging tables for Excel citation import</p>
        </div>

        <div class="content">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error">
                        <span class="error-icon"></span>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="check-icon"></span>
                    <strong>Success!</strong> Import tables have been created successfully.
                </div>

                <?php if (!empty($tablesCreated)): ?>
                    <div class="table-list">
                        <h3>Tables Created:</h3>
                        <?php foreach ($tablesCreated as $table): ?>
                            <div class="table-item"><span class="check-icon"></span><?php echo htmlspecialchars($table); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($messages)): ?>
                    <div class="message-list">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-item"><span class="info-icon"></span><?php echo htmlspecialchars($msg); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($verifyTables)): ?>
                    <div class="table-list">
                        <h3>Verified Import Tables in Database:</h3>
                        <?php foreach ($verifyTables as $table): ?>
                            <div class="table-item">
                                <strong><?php echo htmlspecialchars($table['TABLE_NAME']); ?></strong>
                                (<?php echo number_format($table['TABLE_ROWS']); ?> rows)
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="next-step">
                    <h3>🎉 You're All Set!</h3>
                    <p>The import staging tables have been created successfully. You can now proceed to import your Excel data.</p>
                    <a href="web_import.php" class="btn-link">→ Go to Import Interface</a>
                </div>

            <?php else: ?>
                <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                    <div class="alert alert-info">
                        <span class="info-icon"></span>
                        <strong>Ready to Setup:</strong> This will create all necessary staging tables for the Excel import system.
                    </div>

                    <div class="table-list">
                        <h3>Tables to be Created:</h3>
                        <div class="table-item">• import_batches - Track import sessions</div>
                        <div class="table-item">• import_logs - Detailed import logging</div>
                        <div class="table-item">• import_staging - Main staging table</div>
                        <div class="table-item">• import_violation_mappings - Violation text matching cache</div>
                        <div class="table-item">• import_ticket_conflicts - Ticket number conflicts</div>
                        <div class="table-item">• import_duplicates - Duplicate records log</div>
                        <div class="table-item">• import_citation_groups - Multi-violation citation groups</div>
                    </div>

                    <div class="table-list">
                        <h3>Database Modifications:</h3>
                        <div class="table-item">• Add import_batch_id column to citations table</div>
                        <div class="table-item">• Add import_batch_id column to drivers table</div>
                        <div class="table-item">• Add import_batch_id column to violations table</div>
                    </div>

                    <div class="table-list">
                        <h3>Stored Procedures:</h3>
                        <div class="table-item">• sp_generate_auto_ticket - Auto ticket generation</div>
                        <div class="table-item">• sp_cleanup_old_imports - Clean old import data</div>
                    </div>

                    <div class="alert alert-warning" style="margin-top: 20px;">
                        <strong>Note:</strong> This will drop existing import tables if they exist. Main data tables (citations, drivers, violations) will not be affected.
                    </div>

                    <form method="POST" style="margin-top: 30px;">
                        <button type="submit" name="run_migration" value="1">
                            🚀 Create Import Tables
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 14px; color: #6c757d;">
                <strong>Migration File:</strong> <?php echo htmlspecialchars($migrationFile); ?><br>
                <strong>Database:</strong> <?php echo DB_NAME; ?>
            </div>
        </div>
    </div>
</body>
</html>
