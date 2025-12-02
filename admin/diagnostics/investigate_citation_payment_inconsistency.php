<?php
/**
 * Citation-Payment Inconsistency Investigation Tool
 *
 * Comprehensive diagnostic tool for identifying and understanding
 * data inconsistencies between citations and payments
 *
 * @package TrafficCitationSystem
 * @subpackage Admin\Diagnostics
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Require admin access
require_login();
check_session_timeout();
if (!is_admin()) {
    header('Location: ../../public/dashboard.php');
    exit;
}

$pdo = getPDO();

// Diagnostic data collection
$diagnostics = [];

// ============================================================================
// SECTION 1: Database Statistics
// ============================================================================
$diagnostics['stats'] = [];

$stmt = $pdo->query("SELECT COUNT(*) FROM citations WHERE deleted_at IS NULL");
$diagnostics['stats']['total_citations'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM citations WHERE status = 'pending' AND deleted_at IS NULL");
$diagnostics['stats']['pending_citations'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM citations WHERE status = 'paid' AND deleted_at IS NULL");
$diagnostics['stats']['paid_citations'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM payments");
$diagnostics['stats']['total_payments'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed'");
$diagnostics['stats']['completed_payments'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending_print'");
$diagnostics['stats']['pending_print_payments'] = $stmt->fetchColumn();

// ============================================================================
// SECTION 2: Citation Status Breakdown
// ============================================================================
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM citations
    WHERE deleted_at IS NULL
    GROUP BY status
    ORDER BY count DESC
");
$diagnostics['citation_status_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// SECTION 3: Payment Status Breakdown
// ============================================================================
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM payments
    GROUP BY status
    ORDER BY count DESC
");
$diagnostics['payment_status_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// SECTION 4: CRITICAL - Pending citations with completed payments
// ============================================================================
$stmt = $pdo->query("
    SELECT
        c.citation_id,
        c.ticket_number,
        CONCAT(c.first_name, ' ', c.last_name) as driver_name,
        c.status as citation_status,
        c.total_fine,
        c.created_at as citation_created,
        COUNT(p.payment_id) as payment_count,
        GROUP_CONCAT(p.receipt_number ORDER BY p.created_at SEPARATOR ', ') as receipt_numbers,
        GROUP_CONCAT(p.amount_paid ORDER BY p.created_at SEPARATOR ', ') as amounts_paid,
        MAX(p.created_at) as last_payment_date
    FROM citations c
    INNER JOIN payments p ON c.citation_id = p.citation_id
    WHERE c.status = 'pending'
        AND p.status = 'completed'
        AND c.deleted_at IS NULL
    GROUP BY c.citation_id
    ORDER BY last_payment_date DESC
");
$diagnostics['pending_with_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// SECTION 5: CRITICAL - Paid citations without completed payments
// ============================================================================
$stmt = $pdo->query("
    SELECT
        c.citation_id,
        c.ticket_number,
        CONCAT(c.first_name, ' ', c.last_name) as driver_name,
        c.status as citation_status,
        c.total_fine,
        c.created_at as citation_created,
        COUNT(p.payment_id) as total_payments,
        COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_payments,
        GROUP_CONCAT(DISTINCT p.status SEPARATOR ', ') as payment_statuses
    FROM citations c
    LEFT JOIN payments p ON c.citation_id = p.citation_id
    WHERE c.status = 'paid'
        AND c.deleted_at IS NULL
    GROUP BY c.citation_id
    HAVING completed_payments = 0
");
$diagnostics['paid_without_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// SECTION 6: Orphaned Payments
// ============================================================================
$stmt = $pdo->query("
    SELECT
        p.payment_id,
        p.citation_id,
        p.receipt_number,
        p.amount_paid,
        p.status as payment_status,
        p.created_at,
        CASE
            WHEN c.citation_id IS NULL THEN 'Citation does not exist'
            WHEN c.deleted_at IS NOT NULL THEN 'Citation is deleted'
            ELSE 'Unknown'
        END as issue_type
    FROM payments p
    LEFT JOIN citations c ON p.citation_id = c.citation_id
    WHERE c.citation_id IS NULL OR c.deleted_at IS NOT NULL
    ORDER BY p.created_at DESC
");
$diagnostics['orphaned_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// SECTION 7: Trigger Status
// ============================================================================
$stmt = $pdo->query("SHOW TRIGGERS LIKE 'payments'");
$diagnostics['triggers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if trigger_error_log exists
$stmt = $pdo->query("SHOW TABLES LIKE 'trigger_error_log'");
$diagnostics['has_error_log'] = $stmt->rowCount() > 0;

if ($diagnostics['has_error_log']) {
    $stmt = $pdo->query("
        SELECT
            log_id,
            trigger_name,
            error_message,
            citation_id,
            payment_id,
            created_at
        FROM trigger_error_log
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $diagnostics['recent_trigger_errors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $diagnostics['recent_trigger_errors'] = [];
}

// ============================================================================
// SECTION 8: Health Score Calculation
// ============================================================================
$health_score = 100;
$issues = [];

if (count($diagnostics['pending_with_payments']) > 0) {
    $penalty = min(30, count($diagnostics['pending_with_payments']) * 10);
    $health_score -= $penalty;
    $issues[] = count($diagnostics['pending_with_payments']) . ' pending citations with completed payments';
}

if (count($diagnostics['paid_without_payments']) > 0) {
    $penalty = min(30, count($diagnostics['paid_without_payments']) * 10);
    $health_score -= $penalty;
    $issues[] = count($diagnostics['paid_without_payments']) . ' paid citations without completed payments';
}

if (count($diagnostics['orphaned_payments']) > 0) {
    $penalty = min(20, count($diagnostics['orphaned_payments']) * 5);
    $health_score -= $penalty;
    $issues[] = count($diagnostics['orphaned_payments']) . ' orphaned payments';
}

if (count($diagnostics['recent_trigger_errors']) > 0) {
    $penalty = min(20, count($diagnostics['recent_trigger_errors']) * 5);
    $health_score -= $penalty;
    $issues[] = count($diagnostics['recent_trigger_errors']) . ' recent trigger errors';
}

$health_score = max(0, $health_score);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citation-Payment Inconsistency Investigation - TMG Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .investigation-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .section-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .health-score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 3em;
            font-weight: bold;
            color: white;
        }
        .health-excellent {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .health-good {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        .health-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .health-critical {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #6b7280;
            font-size: 0.9em;
        }
        .issue-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin: 4px;
        }
        .badge-critical {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-ok {
            background: #d1fae5;
            color: #065f46;
        }
        .fix-option {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .sql-code {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            overflow-x: auto;
            margin-top: 10px;
        }
        .trigger-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .trigger-active {
            background: #d1fae5;
            color: #065f46;
        }
        .trigger-missing {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="investigation-container">
        <!-- Header -->
        <div class="section-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-search"></i> Citation-Payment Inconsistency Investigation</h2>
                    <p class="text-muted mb-0">Comprehensive diagnostic analysis and repair recommendations</p>
                </div>
                <div>
                    <a href="data_integrity_dashboard.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-dashboard"></i> Dashboard
                    </a>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Health Score -->
        <div class="section-card">
            <h4 class="mb-4"><i class="fas fa-heartbeat"></i> System Health Score</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="health-score-circle health-<?php
                        if ($health_score >= 90) echo 'excellent';
                        elseif ($health_score >= 70) echo 'good';
                        elseif ($health_score >= 50) echo 'warning';
                        else echo 'critical';
                    ?>">
                        <?= $health_score ?>%
                    </div>
                    <div class="text-center mt-3">
                        <strong>
                            <?php
                            if ($health_score >= 90) echo 'Excellent';
                            elseif ($health_score >= 70) echo 'Good';
                            elseif ($health_score >= 50) echo 'Needs Attention';
                            else echo 'Critical';
                            ?>
                        </strong>
                    </div>
                </div>
                <div class="col-md-8">
                    <h5>Issues Detected:</h5>
                    <?php if (empty($issues)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> No critical issues detected! System is healthy.
                        </div>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($issues as $issue): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    <?= htmlspecialchars($issue) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Database Statistics -->
        <div class="section-card">
            <h4 class="mb-4"><i class="fas fa-chart-bar"></i> Database Statistics</h4>
            <div class="row">
                <div class="col-md-2">
                    <div class="stat-box">
                        <div class="stat-number"><?= number_format($diagnostics['stats']['total_citations']) ?></div>
                        <div class="stat-label">Total Citations</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box">
                        <div class="stat-number"><?= number_format($diagnostics['stats']['pending_citations']) ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box">
                        <div class="stat-number"><?= number_format($diagnostics['stats']['paid_citations']) ?></div>
                        <div class="stat-label">Paid</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box">
                        <div class="stat-number"><?= number_format($diagnostics['stats']['total_payments']) ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box">
                        <div class="stat-number"><?= number_format($diagnostics['stats']['completed_payments']) ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box">
                        <div class="stat-number"><?= number_format($diagnostics['stats']['pending_print_payments']) ?></div>
                        <div class="stat-label">Pending Print</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Critical Issue: Pending with Payments -->
        <div class="section-card">
            <h4 class="mb-3">
                <i class="fas fa-exclamation-circle text-danger"></i> Pending Citations with Completed Payments
                <span class="issue-badge badge-<?= count($diagnostics['pending_with_payments']) > 0 ? 'critical' : 'ok' ?>">
                    <?= count($diagnostics['pending_with_payments']) ?> found
                </span>
            </h4>

            <?php if (count($diagnostics['pending_with_payments']) > 0): ?>
                <div class="alert alert-danger">
                    <strong>Critical Issue:</strong> These citations are marked as "pending" but have completed payment records.
                    This indicates a synchronization failure between payment processing and citation status updates.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Citation ID</th>
                                <th>Ticket #</th>
                                <th>Driver Name</th>
                                <th>Total Fine</th>
                                <th>Payment Count</th>
                                <th>Receipt Numbers</th>
                                <th>Last Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnostics['pending_with_payments'] as $row): ?>
                                <tr>
                                    <td><?= $row['citation_id'] ?></td>
                                    <td><?= htmlspecialchars($row['ticket_number']) ?></td>
                                    <td><?= htmlspecialchars($row['driver_name']) ?></td>
                                    <td>₱<?= number_format($row['total_fine'], 2) ?></td>
                                    <td><?= $row['payment_count'] ?></td>
                                    <td style="font-size: 0.85em;"><?= htmlspecialchars($row['receipt_numbers']) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($row['last_payment_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="fix-option">
                    <h6><i class="fas fa-wrench"></i> Fix Option: Update Citation Status to PAID</h6>
                    <p>Run this SQL to update these citations to "paid" status:</p>
                    <div class="sql-code">UPDATE citations<br>SET status = 'paid', updated_at = NOW()<br>WHERE citation_id IN (<?= implode(', ', array_column($diagnostics['pending_with_payments'], 'citation_id')) ?>);</div>
                    <p class="mt-2 mb-0 small text-muted">Or use the <a href="../maintenance/fix_pending_paid_citations.php">Automated Fix Tool</a></p>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> No pending citations with completed payments found.
                </div>
            <?php endif; ?>
        </div>

        <!-- Critical Issue: Paid without Payments -->
        <div class="section-card">
            <h4 class="mb-3">
                <i class="fas fa-exclamation-circle text-danger"></i> Paid Citations Without Completed Payments
                <span class="issue-badge badge-<?= count($diagnostics['paid_without_payments']) > 0 ? 'critical' : 'ok' ?>">
                    <?= count($diagnostics['paid_without_payments']) ?> found
                </span>
            </h4>

            <?php if (count($diagnostics['paid_without_payments']) > 0): ?>
                <div class="alert alert-danger">
                    <strong>Critical Issue:</strong> These citations are marked as "paid" but have NO completed payment records.
                    This may indicate deleted payments, data corruption, or manual status changes.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Citation ID</th>
                                <th>Ticket #</th>
                                <th>Driver Name</th>
                                <th>Total Fine</th>
                                <th>Total Payments</th>
                                <th>Payment Statuses</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnostics['paid_without_payments'] as $row): ?>
                                <tr>
                                    <td><?= $row['citation_id'] ?></td>
                                    <td><?= htmlspecialchars($row['ticket_number']) ?></td>
                                    <td><?= htmlspecialchars($row['driver_name']) ?></td>
                                    <td>₱<?= number_format($row['total_fine'], 2) ?></td>
                                    <td><?= $row['total_payments'] ?></td>
                                    <td><?= htmlspecialchars($row['payment_statuses'] ?? 'None') ?></td>
                                    <td><?= date('Y-m-d', strtotime($row['citation_created'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="fix-option">
                    <h6><i class="fas fa-wrench"></i> Fix Option: Revert to PENDING Status</h6>
                    <p>Run this SQL to update these citations to "pending" status:</p>
                    <div class="sql-code">UPDATE citations<br>SET status = 'pending', updated_at = NOW()<br>WHERE citation_id IN (<?= implode(', ', array_column($diagnostics['paid_without_payments'], 'citation_id')) ?>);</div>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> No paid citations without completed payments found.
                </div>
            <?php endif; ?>
        </div>

        <!-- Orphaned Payments -->
        <div class="section-card">
            <h4 class="mb-3">
                <i class="fas fa-unlink text-warning"></i> Orphaned Payments
                <span class="issue-badge badge-<?= count($diagnostics['orphaned_payments']) > 0 ? 'warning' : 'ok' ?>">
                    <?= count($diagnostics['orphaned_payments']) ?> found
                </span>
            </h4>

            <?php if (count($diagnostics['orphaned_payments']) > 0): ?>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> These payments reference citations that don't exist or are deleted.
                    This preserves financial audit trails but indicates data integrity issues.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Payment ID</th>
                                <th>Citation ID</th>
                                <th>Receipt #</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Issue Type</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnostics['orphaned_payments'] as $row): ?>
                                <tr>
                                    <td><?= $row['payment_id'] ?></td>
                                    <td><?= $row['citation_id'] ?></td>
                                    <td><?= htmlspecialchars($row['receipt_number']) ?></td>
                                    <td>₱<?= number_format($row['amount_paid'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['payment_status']) ?></td>
                                    <td><?= htmlspecialchars($row['issue_type']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> No orphaned payments found.
                </div>
            <?php endif; ?>
        </div>

        <!-- Trigger Status -->
        <div class="section-card">
            <h4 class="mb-3"><i class="fas fa-cogs"></i> Database Triggers Status</h4>

            <?php if (count($diagnostics['triggers']) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Trigger Name</th>
                                <th>Event</th>
                                <th>Timing</th>
                                <th>Table</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnostics['triggers'] as $trigger): ?>
                                <tr>
                                    <td>
                                        <span class="trigger-status trigger-active">ACTIVE</span>
                                        <?= htmlspecialchars($trigger['Trigger']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($trigger['Event']) ?></td>
                                    <td><?= htmlspecialchars($trigger['Timing']) ?></td>
                                    <td><?= htmlspecialchars($trigger['Table']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($diagnostics['has_error_log']): ?>
                    <h5 class="mt-4">Recent Trigger Errors (Last 10)</h5>
                    <?php if (count($diagnostics['recent_trigger_errors']) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Trigger</th>
                                        <th>Error Message</th>
                                        <th>Citation ID</th>
                                        <th>Payment ID</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($diagnostics['recent_trigger_errors'] as $error): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($error['trigger_name']) ?></td>
                                            <td style="font-size: 0.85em; max-width: 300px;"><?= htmlspecialchars($error['error_message']) ?></td>
                                            <td><?= $error['citation_id'] ?? 'N/A' ?></td>
                                            <td><?= $error['payment_id'] ?? 'N/A' ?></td>
                                            <td><?= date('Y-m-d H:i', strtotime($error['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">No trigger errors logged recently.</div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No triggers found on payments table.
                    Database constraints may not be active. Run the <a href="../database/run_consistency_migration.php">consistency migration</a>.
                </div>
            <?php endif; ?>
        </div>

        <!-- Recommendations -->
        <div class="section-card">
            <h4 class="mb-3"><i class="fas fa-lightbulb"></i> Recommendations</h4>

            <?php if ($health_score >= 90): ?>
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> System is healthy!</h5>
                    <p class="mb-0">Your citation-payment system has excellent data integrity. Continue monitoring regularly.</p>
                </div>
            <?php else: ?>
                <ol>
                    <?php if (count($diagnostics['pending_with_payments']) > 0): ?>
                        <li>Run the <a href="../maintenance/fix_pending_paid_citations.php">automated fix tool</a> to update pending citations with completed payments</li>
                    <?php endif; ?>

                    <?php if (count($diagnostics['triggers']) === 0): ?>
                        <li>Run the <a href="../database/run_consistency_migration.php">database migration</a> to install protective triggers</li>
                    <?php endif; ?>

                    <li>Schedule the <a href="automated_consistency_checker.php">automated consistency checker</a> to run daily via Task Scheduler</li>
                    <li>Monitor the <a href="data_integrity_dashboard.php">data integrity dashboard</a> weekly</li>
                    <li>Review trigger errors regularly to identify systemic issues</li>
                </ol>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
