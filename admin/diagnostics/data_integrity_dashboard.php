<?php
/**
 * Admin Data Integrity Dashboard
 *
 * Comprehensive dashboard for monitoring and managing data integrity issues
 * Shows all data inconsistencies, orphaned records, and system health metrics
 */

// Define root path (up two levels from admin/diagnostics/)
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Require admin access
require_login();
check_session_timeout();
if (!is_admin()) {
    header('Location: /tmg/public/dashboard.php');
    exit;
}

// Get database connection
$pdo = getPDO();

// Initialize stats
$stats = [
    'citations_with_mismatched_status' => 0,
    'orphaned_payments' => 0,
    'citations_with_multiple_payments' => 0,
    'voided_payments_not_logged' => 0,
    'or_number_duplicates' => 0,
    'or_number_gaps' => 0,
    'stale_pending_print' => 0,
    'trigger_errors_last_7_days' => 0
];

$issues = [
    'mismatched_status' => [],
    'orphaned_payments' => [],
    'multiple_payments' => [],
    'voided_not_logged' => [],
    'or_duplicates' => [],
    'or_gaps' => [],
    'stale_pending' => [],
    'trigger_errors' => []
];

// ============================================================================
// CHECK 1: Citations with Mismatched Status
// ============================================================================

try {
    // Pending citations with completed payments
    $stmt = $pdo->query("
        SELECT
            c.citation_id,
            c.ticket_number,
            CONCAT(c.first_name, ' ', c.last_name) as driver_name,
            c.status as citation_status,
            COUNT(p.payment_id) as payment_count,
            GROUP_CONCAT(CONCAT(p.receipt_number, ':', p.status) ORDER BY p.created_at SEPARATOR ', ') as payments
        FROM citations c
        INNER JOIN payments p ON c.citation_id = p.citation_id
        WHERE c.deleted_at IS NULL
          AND (
              (c.status = 'pending' AND p.status = 'completed')
              OR
              (c.status = 'paid' AND p.status != 'completed')
          )
        GROUP BY c.citation_id
    ");
    $issues['mismatched_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['citations_with_mismatched_status'] = count($issues['mismatched_status']);
} catch (PDOException $e) {
    error_log("Dashboard error (mismatched status): " . $e->getMessage());
}

// ============================================================================
// CHECK 2: Orphaned Payments (payments without valid citations)
// ============================================================================

try {
    $stmt = $pdo->query("
        SELECT
            p.payment_id,
            p.citation_id,
            p.receipt_number,
            p.amount_paid,
            p.status,
            p.payment_date,
            p.payment_method
        FROM payments p
        LEFT JOIN citations c ON p.citation_id = c.citation_id
        WHERE c.citation_id IS NULL
          OR c.deleted_at IS NOT NULL
    ");
    $issues['orphaned_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['orphaned_payments'] = count($issues['orphaned_payments']);
} catch (PDOException $e) {
    error_log("Dashboard error (orphaned payments): " . $e->getMessage());
}

// ============================================================================
// CHECK 3: Citations with Multiple Active Payments
// ============================================================================

try {
    $stmt = $pdo->query("
        SELECT
            c.citation_id,
            c.ticket_number,
            CONCAT(c.first_name, ' ', c.last_name) as driver_name,
            c.status,
            COUNT(p.payment_id) as payment_count,
            GROUP_CONCAT(CONCAT(p.receipt_number, ' (', p.status, ')') ORDER BY p.created_at SEPARATOR ', ') as all_payments,
            SUM(CASE WHEN p.status = 'completed' THEN p.amount_paid ELSE 0 END) as total_completed
        FROM citations c
        INNER JOIN payments p ON c.citation_id = p.citation_id
        WHERE c.deleted_at IS NULL
          AND p.status NOT IN ('voided', 'cancelled')
        GROUP BY c.citation_id
        HAVING payment_count > 1
    ");
    $issues['multiple_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['citations_with_multiple_payments'] = count($issues['multiple_payments']);
} catch (PDOException $e) {
    error_log("Dashboard error (multiple payments): " . $e->getMessage());
}

// ============================================================================
// CHECK 4: Voided Payments Not Properly Logged
// ============================================================================

try {
    // Check if audit_logs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT
                p.payment_id,
                p.receipt_number,
                p.citation_id,
                c.ticket_number,
                p.amount_paid,
                p.status,
                p.updated_at,
                (SELECT COUNT(*) FROM audit_logs WHERE entity_id = p.payment_id AND action_type = 'payment_voided') as has_audit
            FROM payments p
            LEFT JOIN citations c ON p.citation_id = c.citation_id
            WHERE p.status = 'voided'
            HAVING has_audit = 0
        ");
        $issues['voided_not_logged'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['voided_payments_not_logged'] = count($issues['voided_not_logged']);
    }
} catch (PDOException $e) {
    error_log("Dashboard error (voided payments): " . $e->getMessage());
}

// ============================================================================
// CHECK 5: Duplicate OR Numbers
// ============================================================================

try {
    $stmt = $pdo->query("
        SELECT
            p.receipt_number,
            COUNT(*) as usage_count,
            GROUP_CONCAT(CONCAT('P', p.payment_id, '(', p.status, ')') ORDER BY p.payment_date SEPARATOR ', ') as payment_ids,
            GROUP_CONCAT(DISTINCT c.ticket_number ORDER BY p.payment_date SEPARATOR ', ') as ticket_numbers
        FROM payments p
        LEFT JOIN citations c ON p.citation_id = c.citation_id
        WHERE p.status NOT IN ('voided', 'cancelled')
        GROUP BY p.receipt_number
        HAVING usage_count > 1
    ");
    $issues['or_duplicates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['or_number_duplicates'] = count($issues['or_duplicates']);
} catch (PDOException $e) {
    error_log("Dashboard error (OR duplicates): " . $e->getMessage());
}

// ============================================================================
// CHECK 6: OR Number Gaps (missing sequence numbers)
// ============================================================================

try {
    // This is a simplified check for CGVM format only
    $stmt = $pdo->query("
        SELECT
            receipt_number,
            CAST(SUBSTRING(receipt_number, 5) AS UNSIGNED) as or_number
        FROM payments
        WHERE receipt_number LIKE 'CGVM%'
          AND status NOT IN ('voided', 'cancelled')
        ORDER BY or_number
    ");
    $allOrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($allOrs) > 1) {
        $gaps = [];
        for ($i = 0; $i < count($allOrs) - 1; $i++) {
            $current = $allOrs[$i]['or_number'];
            $next = $allOrs[$i + 1]['or_number'];

            if ($next - $current > 1) {
                $gapCount = $next - $current - 1;
                if ($gapCount <= 10) { // Only report small gaps
                    $missingNumbers = [];
                    for ($j = $current + 1; $j < $next; $j++) {
                        $missingNumbers[] = 'CGVM' . str_pad($j, 8, '0', STR_PAD_LEFT);
                    }
                    $gaps[] = [
                        'after' => $allOrs[$i]['receipt_number'],
                        'before' => $allOrs[$i + 1]['receipt_number'],
                        'gap_count' => $gapCount,
                        'missing_numbers' => implode(', ', $missingNumbers)
                    ];
                }
            }
        }
        $issues['or_gaps'] = $gaps;
        $stats['or_number_gaps'] = count($gaps);
    }
} catch (PDOException $e) {
    error_log("Dashboard error (OR gaps): " . $e->getMessage());
}

// ============================================================================
// CHECK 7: Stale Pending Print Payments (>24 hours)
// ============================================================================

try {
    $stmt = $pdo->query("
        SELECT
            p.payment_id,
            p.citation_id,
            c.ticket_number,
            p.receipt_number,
            p.amount_paid,
            p.created_at,
            TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_pending,
            u.full_name as collected_by_name
        FROM payments p
        INNER JOIN citations c ON p.citation_id = c.citation_id
        LEFT JOIN users u ON p.collected_by = u.user_id
        WHERE p.status = 'pending_print'
          AND TIMESTAMPDIFF(HOUR, p.created_at, NOW()) > 24
          AND c.deleted_at IS NULL
        ORDER BY p.created_at ASC
    ");
    $issues['stale_pending'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['stale_pending_print'] = count($issues['stale_pending']);
} catch (PDOException $e) {
    error_log("Dashboard error (stale pending): " . $e->getMessage());
}

// ============================================================================
// CHECK 8: Recent Trigger Errors
// ============================================================================

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'trigger_error_log'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT
                log_id,
                trigger_name,
                error_message,
                citation_id,
                payment_id,
                created_at
            FROM trigger_error_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $issues['trigger_errors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['trigger_errors_last_7_days'] = count($issues['trigger_errors']);
    }
} catch (PDOException $e) {
    error_log("Dashboard error (trigger errors): " . $e->getMessage());
}

// Calculate total issues
$total_issues = array_sum($stats);

// Get system health score (0-100)
$max_possible_issues = 100; // Threshold
$health_score = max(0, 100 - ($total_issues * 2)); // Decrease 2 points per issue
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Integrity Dashboard - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .content {
            padding: 20px;
        }
        .health-score {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .health-circle {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            font-weight: bold;
            color: white;
            position: relative;
        }
        .health-excellent { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .health-good { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .health-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .health-critical { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            color: #6b7280;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .issue-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .issue-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        .badge-count {
            font-size: 1.2em;
            padding: 8px 16px;
        }
        table {
            font-size: 0.9em;
        }
        .btn-action {
            padding: 4px 12px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <?php include '../../public/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-shield-alt"></i> Data Integrity Dashboard</h1>
                <div>
                    <a href="automated_consistency_checker.php" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Run Full Check
                    </a>
                    <a href="../maintenance/fix_pending_paid_citations.php" class="btn btn-warning">
                        <i class="fas fa-wrench"></i> Auto-Fix Issues
                    </a>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Admin
                    </a>
                </div>
            </div>

            <!-- Health Score -->
            <div class="health-score">
                <div class="health-circle <?php
                    if ($health_score >= 90) echo 'health-excellent';
                    elseif ($health_score >= 70) echo 'health-good';
                    elseif ($health_score >= 50) echo 'health-warning';
                    else echo 'health-critical';
                ?>">
                    <?= $health_score ?>%
                </div>
                <h3>System Health Score</h3>
                <p class="text-muted">
                    <?php
                    if ($health_score >= 90) echo "Excellent - System is operating normally";
                    elseif ($health_score >= 70) echo "Good - Minor issues detected";
                    elseif ($health_score >= 50) echo "Warning - Multiple issues need attention";
                    else echo "Critical - Immediate action required";
                    ?>
                </p>
                <div class="row mt-4">
                    <div class="col-md-4">
                        <strong><?= $total_issues ?></strong><br>
                        <small class="text-muted">Total Issues</small>
                    </div>
                    <div class="col-md-4">
                        <strong><?= date('Y-m-d H:i:s') ?></strong><br>
                        <small class="text-muted">Last Check</small>
                    </div>
                    <div class="col-md-4">
                        <strong><?= $_SESSION['full_name'] ?></strong><br>
                        <small class="text-muted">Checked By</small>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Mismatched Status</div>
                        <div class="stat-number text-danger">
                            <?= $stats['citations_with_mismatched_status'] ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Orphaned Payments</div>
                        <div class="stat-number text-warning">
                            <?= $stats['orphaned_payments'] ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Duplicate ORs</div>
                        <div class="stat-number text-danger">
                            <?= $stats['or_number_duplicates'] ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Stale Pending</div>
                        <div class="stat-number text-warning">
                            <?= $stats['stale_pending_print'] ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            // Helper function to render issue section
            function renderIssueSection($title, $icon, $count, $tableHeaders, $tableRows, $emptyMessage = "No issues found") {
                ?>
                <div class="issue-section">
                    <div class="issue-header">
                        <h4><i class="<?= $icon ?>"></i> <?= $title ?></h4>
                        <span class="badge <?= $count > 0 ? 'bg-danger' : 'bg-success' ?> badge-count">
                            <?= $count ?> <?= $count === 1 ? 'Issue' : 'Issues' ?>
                        </span>
                    </div>
                    <?php if ($count > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <?php foreach ($tableHeaders as $header): ?>
                                            <th><?= $header ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?= $tableRows ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i> <?= $emptyMessage ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }
            ?>

            <!-- Issue 1: Mismatched Status -->
            <?php
            ob_start();
            foreach ($issues['mismatched_status'] as $row) {
                echo "<tr>";
                echo "<td><a href='/tmg/public/view_citation.php?id={$row['citation_id']}'>{$row['ticket_number']}</a></td>";
                echo "<td>{$row['driver_name']}</td>";
                echo "<td><span class='badge bg-warning'>{$row['citation_status']}</span></td>";
                echo "<td>{$row['payment_count']}</td>";
                echo "<td style='font-size: 0.85em;'>{$row['payments']}</td>";
                echo "<td><a href='../maintenance/fix_pending_paid_citations.php' class='btn btn-sm btn-primary btn-action'>Fix</a></td>";
                echo "</tr>";
            }
            $tableRows = ob_get_clean();

            renderIssueSection(
                "Citations with Mismatched Status",
                "fas fa-exclamation-triangle",
                $stats['citations_with_mismatched_status'],
                ["Ticket #", "Driver", "Citation Status", "Payment Count", "Payments", "Action"],
                $tableRows,
                "All citations have correct status matching their payments"
            );
            ?>

            <!-- Issue 2: Orphaned Payments -->
            <?php
            ob_start();
            foreach ($issues['orphaned_payments'] as $row) {
                echo "<tr>";
                echo "<td>P{$row['payment_id']}</td>";
                echo "<td>C{$row['citation_id']} (missing)</td>";
                echo "<td>{$row['receipt_number']}</td>";
                echo "<td>₱" . number_format($row['amount_paid'], 2) . "</td>";
                echo "<td><span class='badge bg-info'>{$row['status']}</span></td>";
                echo "<td>{$row['payment_date']}</td>";
                echo "<td><a href='investigate_citation_payment_inconsistency.php' class='btn btn-sm btn-danger btn-action'>Investigate</a></td>";
                echo "</tr>";
            }
            $tableRows = ob_get_clean();

            renderIssueSection(
                "Orphaned Payments (No Valid Citation)",
                "fas fa-unlink",
                $stats['orphaned_payments'],
                ["Payment ID", "Citation ID", "OR Number", "Amount", "Status", "Date", "Action"],
                $tableRows,
                "All payments are properly linked to citations"
            );
            ?>

            <!-- Issue 3: Multiple Active Payments -->
            <?php
            ob_start();
            foreach ($issues['multiple_payments'] as $row) {
                echo "<tr>";
                echo "<td><a href='/tmg/public/view_citation.php?id={$row['citation_id']}'>{$row['ticket_number']}</a></td>";
                echo "<td>{$row['driver_name']}</td>";
                echo "<td><span class='badge bg-warning'>{$row['payment_count']}</span></td>";
                echo "<td style='font-size: 0.85em;'>{$row['all_payments']}</td>";
                echo "<td>₱" . number_format($row['total_completed'], 2) . "</td>";
                echo "<td><button class='btn btn-sm btn-warning btn-action'>Review</button></td>";
                echo "</tr>";
            }
            $tableRows = ob_get_clean();

            renderIssueSection(
                "Citations with Multiple Active Payments",
                "fas fa-copy",
                $stats['citations_with_multiple_payments'],
                ["Ticket #", "Driver", "Payment Count", "All Payments", "Total Paid", "Action"],
                $tableRows,
                "All citations have single payment records"
            );
            ?>

            <!-- Issue 4: Duplicate OR Numbers -->
            <?php
            ob_start();
            foreach ($issues['or_duplicates'] as $row) {
                echo "<tr class='table-danger'>";
                echo "<td><strong>{$row['receipt_number']}</strong></td>";
                echo "<td><span class='badge bg-danger'>{$row['usage_count']}x</span></td>";
                echo "<td style='font-size: 0.85em;'>{$row['payment_ids']}</td>";
                echo "<td>{$row['ticket_numbers']}</td>";
                echo "<td><button class='btn btn-sm btn-danger btn-action'>Fix</button></td>";
                echo "</tr>";
            }
            $tableRows = ob_get_clean();

            renderIssueSection(
                "Duplicate OR Numbers",
                "fas fa-clone",
                $stats['or_number_duplicates'],
                ["OR Number", "Usage Count", "Payment IDs", "Ticket Numbers", "Action"],
                $tableRows,
                "All OR numbers are unique"
            );
            ?>

            <!-- Issue 5: Stale Pending Print -->
            <?php
            ob_start();
            foreach ($issues['stale_pending'] as $row) {
                echo "<tr>";
                echo "<td><a href='/tmg/public/view_citation.php?id={$row['citation_id']}'>{$row['ticket_number']}</a></td>";
                echo "<td>{$row['receipt_number']}</td>";
                echo "<td>₱" . number_format($row['amount_paid'], 2) . "</td>";
                echo "<td>{$row['collected_by_name']}</td>";
                echo "<td><span class='badge bg-warning'>{$row['hours_pending']} hours</span></td>";
                echo "<td>{$row['created_at']}</td>";
                echo "<td><a href='/tmg/public/pending_print_payments.php' class='btn btn-sm btn-primary btn-action'>Finalize</a></td>";
                echo "</tr>";
            }
            $tableRows = ob_get_clean();

            renderIssueSection(
                "Stale Pending Print Payments (>24 hours)",
                "fas fa-clock",
                $stats['stale_pending_print'],
                ["Ticket #", "OR Number", "Amount", "Collected By", "Hours Pending", "Created", "Action"],
                $tableRows,
                "All pending payments are recent"
            );
            ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
