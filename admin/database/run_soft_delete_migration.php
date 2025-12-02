<?php
/**
 * Soft Delete Migration Runner
 * Adds deleted_at, deleted_by, and deletion_reason columns to citations table
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
$migrationFile = dirname(dirname(__DIR__)) . '/database/migrations/add_soft_deletes.sql';
$migrationRun = false;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $migrationRun = true;

    try {
        // Read the SQL file
        if (!file_exists($migrationFile)) {
            throw new Exception("Migration file not found: $migrationFile");
        }

        $sql = file_get_contents($migrationFile);

        // Split into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && substr($stmt, 0, 2) !== '--';
            }
        );

        $pdo->beginTransaction();

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    $results[] = [
                        'success' => true,
                        'statement' => substr($statement, 0, 100) . '...',
                        'message' => 'Success'
                    ];
                } catch (PDOException $e) {
                    // Check if it's an "already exists" error
                    if (strpos($e->getMessage(), 'Duplicate column') !== false ||
                        strpos($e->getMessage(), 'already exists') !== false) {
                        $results[] = [
                            'success' => true,
                            'statement' => substr($statement, 0, 100) . '...',
                            'message' => 'Already exists (skipped)'
                        ];
                    } else {
                        throw $e;
                    }
                }
            }
        }

        $pdo->commit();

        $results[] = [
            'success' => true,
            'statement' => 'MIGRATION COMPLETED',
            'message' => 'All soft delete features have been installed successfully!'
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $results[] = [
            'success' => false,
            'statement' => 'MIGRATION FAILED',
            'message' => $e->getMessage()
        ];
    }
}

// Check if columns already exist
$columnsExist = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM citations LIKE 'deleted_at'");
    $columnsExist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    // Ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soft Delete Migration - TMG Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .migration-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .result-success {
            color: #10b981;
        }
        .result-error {
            color: #ef4444;
        }
        .result-item {
            padding: 10px;
            border-left: 3px solid #e5e7eb;
            margin: 10px 0;
            background: #f9fafb;
            border-radius: 4px;
        }
        .result-item.success {
            border-left-color: #10b981;
        }
        .result-item.error {
            border-left-color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="migration-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-database"></i> Soft Delete Migration</h2>
                <p class="text-muted mb-0">Add soft delete columns to citations table</p>
            </div>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a>
        </div>

        <?php if ($migrationRun): ?>
            <div class="alert alert-<?= end($results)['success'] ? 'success' : 'danger' ?>">
                <h5>
                    <i class="fas fa-<?= end($results)['success'] ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= end($results)['statement'] ?>
                </h5>
                <p class="mb-0"><?= end($results)['message'] ?></p>
            </div>

            <h5 class="mt-4">Migration Log:</h5>
            <?php foreach ($results as $result): ?>
                <div class="result-item <?= $result['success'] ? 'success' : 'error' ?>">
                    <small><strong><?= htmlspecialchars($result['statement']) ?></strong></small><br>
                    <small class="result-<?= $result['success'] ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($result['message']) ?>
                    </small>
                </div>
            <?php endforeach; ?>

            <div class="alert alert-info mt-4">
                <h6><i class="fas fa-info-circle"></i> Next Steps:</h6>
                <ol class="mb-0">
                    <li>Visit <a href="../diagnostics/trash_bin.php">Trash Bin</a> to test soft delete functionality</li>
                    <li>Try deleting a citation - it will be moved to trash instead of permanently deleted</li>
                    <li>Visit <a href="../diagnostics/data_integrity_dashboard.php">Data Integrity Dashboard</a></li>
                </ol>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> What This Migration Does</h5>
                </div>
                <div class="card-body">
                    <p>This migration adds the following columns to the <code>citations</code> table:</p>
                    <ul>
                        <li><code>deleted_at</code> - Timestamp when citation was deleted (NULL if not deleted)</li>
                        <li><code>deleted_by</code> - User ID who deleted the citation</li>
                        <li><code>deletion_reason</code> - Reason for deletion (for audit trail)</li>
                    </ul>

                    <p class="mb-0">It also creates:</p>
                    <ul>
                        <li>Views: <code>vw_active_citations</code> and <code>vw_deleted_citations</code></li>
                        <li>Stored procedures for soft delete operations</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-database"></i> Current Status</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Migration File:</th>
                            <td>
                                <code><?= htmlspecialchars($migrationFile) ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th>File Exists:</th>
                            <td>
                                <?php if (file_exists($migrationFile)): ?>
                                    <span class="badge bg-success">✓ Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">✗ No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Columns Exist:</th>
                            <td>
                                <?php if ($columnsExist): ?>
                                    <span class="badge bg-success">✓ Already Installed</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">⚠ Not Yet Installed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if ($columnsExist): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> The soft delete columns are already installed!
                    You can proceed to use the <a href="../diagnostics/trash_bin.php">Trash Bin</a>.
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_token() ?>">
                    <div class="d-grid">
                        <button type="submit" name="run_migration" class="btn btn-primary btn-lg">
                            <i class="fas fa-play"></i> Run Migration Now
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
