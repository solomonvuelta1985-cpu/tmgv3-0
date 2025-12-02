<?php
/**
 * Data Integrity Cleanup Script
 *
 * Fixes existing data integrity issues:
 * 1. Citations marked "paid" with voided payments
 * 2. Orphaned payments pointing to missing citations
 *
 * @package TrafficCitationSystem
 * @subpackage Admin\Maintenance
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Data Integrity Issues - TMG Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .maintenance-container {
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
        .log-success { color: #4ec9b0; }
        .log-error { color: #f48771; }
        .log-info { color: #9cdcfe; }
        .log-warning { color: #dcdcaa; }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-tools"></i> Fix Data Integrity Issues</h2>
                <p class="text-muted mb-0">Clean up mismatched citations and orphaned payments</p>
            </div>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_issues'])): ?>
            <div class="log-output">
                <?php
                // Verify CSRF
                if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
                    echo '<span class="log-error">✗ CSRF token validation failed</span><br>';
                    echo '</div></div></body></html>';
                    exit;
                }

                echo '<span class="log-info">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br>';
                echo '<span class="log-info">  DATA INTEGRITY CLEANUP</span><br>';
                echo '<span class="log-info">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br><br>';

                $fixedCount = 0;
                $errorCount = 0;

                try {
                    $pdo->beginTransaction();

                    // ================================================================
                    // ISSUE 1: Fix citations marked "paid" with voided payments
                    // ================================================================
                    echo '<span class="log-info">→ Checking citations with voided payments...</span><br>';

                    $stmt = $pdo->query("
                        SELECT
                            c.citation_id,
                            c.ticket_number,
                            c.status as citation_status,
                            GROUP_CONCAT(p.receipt_number SEPARATOR ', ') as receipt_numbers,
                            GROUP_CONCAT(p.status SEPARATOR ', ') as payment_statuses
                        FROM citations c
                        INNER JOIN payments p ON c.citation_id = p.citation_id
                        WHERE c.status = 'paid'
                          AND p.status IN ('voided', 'cancelled', 'refunded')
                        GROUP BY c.citation_id
                    ");

                    $mismatched = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo '<span class="log-info">  Found ' . count($mismatched) . ' mismatched citations</span><br><br>';

                    foreach ($mismatched as $issue) {
                        // Check if there are any completed payments for this citation
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*)
                            FROM payments
                            WHERE citation_id = ?
                              AND status = 'completed'
                        ");
                        $stmt->execute([$issue['citation_id']]);
                        $hasCompletedPayment = $stmt->fetchColumn() > 0;

                        if ($hasCompletedPayment) {
                            echo '<span class="log-warning">  ⚠ Citation ' . $issue['ticket_number'] . ' has completed payments - keeping as paid</span><br>';
                        } else {
                            // No completed payment - revert to pending
                            $stmt = $pdo->prepare("
                                UPDATE citations
                                SET status = 'pending',
                                    payment_date = NULL,
                                    updated_at = NOW()
                                WHERE citation_id = ?
                            ");
                            $stmt->execute([$issue['citation_id']]);

                            echo '<span class="log-success">  ✓ Citation ' . $issue['ticket_number'] . ' reverted to pending</span><br>';
                            $fixedCount++;
                        }
                    }

                    // ================================================================
                    // ISSUE 2: Handle orphaned payments (optional - for information)
                    // ================================================================
                    echo '<br><span class="log-info">→ Checking orphaned payments...</span><br>';

                    $stmt = $pdo->query("
                        SELECT
                            p.payment_id,
                            p.receipt_number,
                            p.citation_id,
                            p.status
                        FROM payments p
                        LEFT JOIN citations c ON p.citation_id = c.citation_id
                        WHERE c.citation_id IS NULL
                    ");

                    $orphaned = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo '<span class="log-info">  Found ' . count($orphaned) . ' orphaned payments</span><br>';

                    if (count($orphaned) > 0) {
                        echo '<span class="log-warning">  ⚠ These payments reference missing citations</span><br>';
                        echo '<span class="log-warning">  → Manual investigation recommended</span><br>';
                        foreach ($orphaned as $payment) {
                            echo '<span class="log-warning">    - Payment #' . $payment['payment_id'] .
                                 ' (OR: ' . $payment['receipt_number'] . ') → Citation #' .
                                 $payment['citation_id'] . ' (missing)</span><br>';
                        }
                    }

                    $pdo->commit();

                    echo '<br><span class="log-info">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br>';
                    echo '<span class="log-success">✓ CLEANUP COMPLETED</span><br>';
                    echo '<span class="log-info">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br><br>';
                    echo '<span class="log-success">  Fixed: ' . $fixedCount . ' issues</span><br>';
                    echo '<span class="log-warning">  Warnings: ' . count($orphaned) . ' orphaned payments need review</span><br>';

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo '<br><span class="log-error">✗ CLEANUP FAILED</span><br>';
                    echo '<span class="log-error">  Error: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
                }
                ?>
            </div>

            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> Next Steps:</h5>
                <ol class="mb-0">
                    <li>Return to <a href="../../public/data_integrity_dashboard.php" class="alert-link">Data Integrity Dashboard</a> to verify fixes</li>
                    <li>Ensure database triggers are installed to prevent future issues</li>
                    <li>Review orphaned payments manually if any were found</li>
                </ol>
            </div>

        <?php else: ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> What This Will Fix</h5>
                <ul class="mb-2">
                    <li><strong>Citations marked "paid" with voided payments</strong> - Will be reverted to "pending"</li>
                    <li><strong>Orphaned payments</strong> - Will be identified for manual review (not auto-deleted)</li>
                </ul>
                <p class="mb-0"><strong>Note:</strong> This operation is reversible via database backups.</p>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-database"></i> Current Status</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Check current issues
                    $stmt = $pdo->query("
                        SELECT COUNT(*)
                        FROM citations c
                        INNER JOIN payments p ON c.citation_id = p.citation_id
                        WHERE c.status = 'paid'
                          AND p.status IN ('voided', 'cancelled', 'refunded')
                    ");
                    $mismatchedCount = $stmt->fetchColumn();

                    $stmt = $pdo->query("
                        SELECT COUNT(*)
                        FROM payments p
                        LEFT JOIN citations c ON p.citation_id = c.citation_id
                        WHERE c.citation_id IS NULL
                    ");
                    $orphanedCount = $stmt->fetchColumn();
                    ?>
                    <table class="table table-sm">
                        <tr>
                            <th width="300">Mismatched Citations:</th>
                            <td>
                                <span class="badge <?= $mismatchedCount > 0 ? 'bg-danger' : 'bg-success' ?>">
                                    <?= $mismatchedCount ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Orphaned Payments:</th>
                            <td>
                                <span class="badge <?= $orphanedCount > 0 ? 'bg-warning' : 'bg-success' ?>">
                                    <?= $orphanedCount ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_token() ?>">
                <div class="d-grid">
                    <button type="submit" name="fix_issues" class="btn btn-danger btn-lg">
                        <i class="fas fa-wrench"></i> Fix Data Integrity Issues Now
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
