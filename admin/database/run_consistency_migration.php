<?php
/**
 * Database Consistency Migration Runner
 *
 * Applies data consistency constraints, triggers, and stored procedures
 * Run this once to install the database-level data integrity features
 *
 * @package TrafficCitationSystem
 * @subpackage Admin\Database
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Require admin access
require_login();
check_session_timeout();
if (!is_admin()) {
    die('Admin access required');
}

$pdo = getPDO();
$migrationFile = dirname(dirname(__DIR__)) . '/database/migrations/add_data_consistency_constraints.sql';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Consistency Migration - TMG Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .migration-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .log-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .log-success {
            color: #4ec9b0;
        }
        .log-error {
            color: #f48771;
        }
        .log-info {
            color: #9cdcfe;
        }
        .log-warning {
            color: #dcdcaa;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        .status-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="migration-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-database"></i> Database Consistency Migration</h2>
                <p class="text-muted mb-0">Install data integrity triggers and stored procedures</p>
            </div>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])): ?>
            <div class="log-output">
                <?php
                // Verify CSRF
                if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
                    echo '<span class="log-error">✗ CSRF token validation failed</span><br>';
                    echo '</div></div></body></html>';
                    exit;
                }

                echo '<span class="log-info">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br>';
                echo '<span class="log-info">  DATA CONSISTENCY MIGRATION</span><br>';
                echo '<span class="log-info">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br><br>';

                // Check if migration file exists
                if (!file_exists($migrationFile)) {
                    echo '<span class="log-error">✗ Migration file not found:</span><br>';
                    echo '<span class="log-error">  ' . htmlspecialchars($migrationFile) . '</span><br>';
                    echo '</div></div></body></html>';
                    exit;
                }

                echo '<span class="log-info">→ Reading migration file...</span><br>';
                $sql = file_get_contents($migrationFile);

                // Parse SQL file handling DELIMITER statements
                $statements = [];
                $currentStatement = '';
                $lines = explode("\n", $sql);
                $currentDelimiter = ';';
                $inTriggerOrProcedure = false;

                foreach ($lines as $line) {
                    $trimmedLine = trim($line);

                    // Skip empty lines and comments
                    if (empty($trimmedLine) || substr($trimmedLine, 0, 2) === '--') {
                        continue;
                    }

                    // Handle DELIMITER changes
                    if (stripos($trimmedLine, 'DELIMITER') === 0) {
                        // Extract new delimiter
                        $parts = preg_split('/\s+/', $trimmedLine);
                        if (isset($parts[1])) {
                            $currentDelimiter = $parts[1];
                            $inTriggerOrProcedure = ($currentDelimiter !== ';');
                        }
                        continue; // Skip DELIMITER commands
                    }

                    $currentStatement .= $line . "\n";

                    // Check if statement is complete based on current delimiter
                    if (substr(rtrim($trimmedLine), -strlen($currentDelimiter)) === $currentDelimiter) {
                        // Remove the delimiter from the statement
                        $statement = substr(trim($currentStatement), 0, -strlen($currentDelimiter));
                        if (!empty($statement)) {
                            $statements[] = $statement;
                        }
                        $currentStatement = '';
                    }
                }

                echo '<span class="log-success">✓ Found ' . count($statements) . ' SQL statements</span><br><br>';

                // Execute each statement
                $successCount = 0;
                $errorCount = 0;

                try {
                    $pdo->beginTransaction();

                    foreach ($statements as $index => $statement) {
                        $num = $index + 1;

                        // Determine statement type
                        $type = 'UNKNOWN';
                        $shouldExecute = true;

                        if (stripos($statement, 'CREATE TABLE') !== false) {
                            $type = 'CREATE TABLE';
                        } elseif (stripos($statement, 'CREATE TRIGGER') !== false) {
                            $type = 'CREATE TRIGGER';
                        } elseif (stripos($statement, 'DROP TRIGGER') !== false) {
                            $type = 'DROP TRIGGER';
                        } elseif (stripos($statement, 'CREATE PROCEDURE') !== false) {
                            $type = 'CREATE PROCEDURE';
                        } elseif (stripos($statement, 'DROP PROCEDURE') !== false) {
                            $type = 'DROP PROCEDURE';
                        } elseif (stripos($statement, 'USE ') === 0) {
                            $type = 'USE DATABASE';
                        } elseif (stripos($statement, 'SELECT') === 0 ||
                                  stripos($statement, 'SHOW') === 0 ||
                                  stripos($statement, 'CALL') === 0) {
                            $type = 'VERIFICATION QUERY';
                            $shouldExecute = false; // Skip verification queries - we'll run our own
                        }

                        echo '<span class="log-info">[' . $num . '/' . count($statements) . '] ' . $type . '</span><br>';

                        if (!$shouldExecute) {
                            echo '<span class="log-warning">    ⚠ Skipped (verification query)</span><br>';
                            $successCount++;
                            continue;
                        }

                        try {
                            $pdo->exec($statement);
                            echo '<span class="log-success">    ✓ Success</span><br>';
                            $successCount++;
                        } catch (PDOException $e) {
                            // Check if error is "already exists" - we can ignore these
                            if (strpos($e->getMessage(), 'already exists') !== false) {
                                echo '<span class="log-warning">    ⚠ Already exists (skipped)</span><br>';
                                $successCount++;
                            } else {
                                echo '<span class="log-error">    ✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
                                $errorCount++;
                                throw $e; // Re-throw to rollback
                            }
                        }
                    }

                    $pdo->commit();

                    echo '<br><span class="log-info">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br>';
                    echo '<span class="log-success">✓ MIGRATION COMPLETED SUCCESSFULLY</span><br>';
                    echo '<span class="log-info">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br><br>';
                    echo '<span class="log-success">  Success: ' . $successCount . ' statements</span><br>';
                    echo '<span class="log-error">  Errors:  ' . $errorCount . ' statements</span><br><br>';

                    // Enable buffered queries for verification to avoid "unbuffered query" errors
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

                    // Verify triggers
                    echo '<span class="log-info">→ Verifying triggers...</span><br>';
                    $stmt = $pdo->query("SHOW TRIGGERS LIKE 'payments'");
                    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $expectedTriggers = [
                        'before_payment_status_change',
                        'after_payment_insert',
                        'after_payment_update'
                    ];

                    foreach ($expectedTriggers as $triggerName) {
                        $found = false;
                        foreach ($triggers as $trigger) {
                            if ($trigger['Trigger'] === $triggerName) {
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            echo '<span class="log-success">  ✓ ' . $triggerName . '</span><br>';
                        } else {
                            echo '<span class="log-warning">  ⚠ ' . $triggerName . ' not found</span><br>';
                        }
                    }

                    // Verify trigger_error_log table
                    echo '<br><span class="log-info">→ Verifying trigger_error_log table...</span><br>';
                    $stmt = $pdo->query("SHOW TABLES LIKE 'trigger_error_log'");
                    if ($stmt->rowCount() > 0) {
                        echo '<span class="log-success">  ✓ trigger_error_log table exists</span><br>';

                        $stmt = $pdo->query("SELECT COUNT(*) FROM trigger_error_log");
                        $count = $stmt->fetchColumn();
                        echo '<span class="log-info">  → Current error count: ' . $count . '</span><br>';
                    } else {
                        echo '<span class="log-warning">  ⚠ trigger_error_log table not found</span><br>';
                    }

                    // Verify stored procedure
                    echo '<br><span class="log-info">→ Verifying stored procedures...</span><br>';
                    $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Name = 'sp_check_citation_payment_consistency'");
                    if ($stmt->rowCount() > 0) {
                        echo '<span class="log-success">  ✓ sp_check_citation_payment_consistency</span><br>';
                    } else {
                        echo '<span class="log-warning">  ⚠ sp_check_citation_payment_consistency not found</span><br>';
                    }

                    echo '<br><span class="log-success">═══════════════════════════════════════════</span><br>';
                    echo '<span class="log-success">  ALL SYSTEMS READY!</span><br>';
                    echo '<span class="log-success">═══════════════════════════════════════════</span><br>';

                } catch (PDOException $e) {
                    // Try to rollback if there's an active transaction
                    // Note: DDL statements (CREATE TRIGGER, etc.) cause implicit commits in MySQL
                    try {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                            echo '<br><span class="log-warning">⚠ Changes have been rolled back.</span><br>';
                        } else {
                            echo '<br><span class="log-warning">⚠ Some changes may have been committed (DDL statements cause auto-commit).</span><br>';
                        }
                    } catch (PDOException $rollbackException) {
                        echo '<br><span class="log-warning">⚠ Note: Unable to rollback (some DDL changes may persist).</span><br>';
                    }

                    echo '<br><span class="log-error">✗ MIGRATION FAILED</span><br>';
                    echo '<span class="log-error">  Error: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
                }
                ?>
            </div>

            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> Next Steps:</h5>
                <ol class="mb-0">
                    <li>Visit the <a href="../diagnostics/data_integrity_dashboard.php" class="alert-link">Data Integrity Dashboard</a> to check system health</li>
                    <li>Run the <a href="../diagnostics/automated_consistency_checker.php" class="alert-link">Automated Consistency Checker</a></li>
                    <li>Review the <a href="../diagnostics/investigate_citation_payment_inconsistency.php" class="alert-link">Investigation Tool</a> if needed</li>
                </ol>
            </div>

        <?php else: ?>
            <!-- Migration Form -->
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Important Notice</h5>
                <p class="mb-2">This migration will install the following database objects:</p>
                <ul class="mb-2">
                    <li><strong>Triggers:</strong> before_payment_status_change, after_payment_insert, after_payment_update</li>
                    <li><strong>Table:</strong> trigger_error_log</li>
                    <li><strong>Stored Procedure:</strong> sp_check_citation_payment_consistency</li>
                </ul>
                <p class="mb-0"><strong>Note:</strong> If these objects already exist, they will be recreated.</p>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-code"></i> Migration File Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="150">File Path:</th>
                            <td><code><?= htmlspecialchars($migrationFile) ?></code></td>
                        </tr>
                        <tr>
                            <th>File Exists:</th>
                            <td>
                                <?php if (file_exists($migrationFile)): ?>
                                    <span class="status-badge status-success">
                                        <i class="fas fa-check"></i> Yes
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-error">
                                        <i class="fas fa-times"></i> No
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (file_exists($migrationFile)): ?>
                        <tr>
                            <th>File Size:</th>
                            <td><?= number_format(filesize($migrationFile)) ?> bytes</td>
                        </tr>
                        <tr>
                            <th>Last Modified:</th>
                            <td><?= date('Y-m-d H:i:s', filemtime($migrationFile)) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <?php if (file_exists($migrationFile)): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_token() ?>">
                    <div class="d-grid">
                        <button type="submit" name="run_migration" class="btn btn-primary btn-lg">
                            <i class="fas fa-play"></i> Run Migration Now
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-times-circle"></i> Migration File Not Found</h5>
                    <p class="mb-0">
                        The migration file does not exist. Please ensure
                        <code>database/migrations/add_data_consistency_constraints.sql</code>
                        is present before running this migration.
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
