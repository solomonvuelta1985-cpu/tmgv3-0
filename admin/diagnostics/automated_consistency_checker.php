<?php
/**
 * Automated Data Consistency Checker
 *
 * Runs comprehensive consistency checks on citation and payment data
 * Can be run from web browser or command line (for scheduling)
 *
 * Usage:
 *   Web: http://localhost/tmg/admin/diagnostics/automated_consistency_checker.php
 *   CLI: php c:\xampp\htdocs\tmg\admin\diagnostics\automated_consistency_checker.php
 *   Task Scheduler: php.exe "c:\xampp\htdocs\tmg\admin\diagnostics\automated_consistency_checker.php"
 *
 * @package TrafficCitationSystem
 * @subpackage Admin\Diagnostics
 */

// Determine if running from CLI or web
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    require_once '../../includes/config.php';
    require_once '../../includes/functions.php';
    require_once '../../includes/auth.php';

    // Require admin access for web access
    require_login();
    check_session_timeout();
    if (!is_admin()) {
        die('Admin access required');
    }
} else {
    // CLI mode - load config directly
    require_once dirname(dirname(__DIR__)) . '/includes/config.php';
    require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
}

// Configuration
$CONFIG = [
    'email_alerts' => false, // Set to true to enable email alerts
    'alert_email' => 'admin@example.com',
    'alert_threshold' => 5, // Send email if total issues >= this number
    'stale_pending_hours' => 24, // Hours before pending_print is considered stale
    'trigger_error_days' => 7, // Days to check for recent trigger errors
];

// Get database connection
$pdo = getPDO();

// Results storage
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => [],
    'total_issues' => 0,
    'has_critical_issues' => false
];

/**
 * Add check result
 */
function addCheckResult($name, $description, $issueCount, $issues, $severity = 'warning') {
    global $results;

    $results['checks'][] = [
        'name' => $name,
        'description' => $description,
        'issue_count' => $issueCount,
        'issues' => $issues,
        'severity' => $severity,
        'status' => $issueCount === 0 ? 'ok' : 'issues_found'
    ];

    $results['total_issues'] += $issueCount;

    if ($severity === 'critical' && $issueCount > 0) {
        $results['has_critical_issues'] = true;
    }
}

/**
 * Output message (CLI or HTML)
 */
function output($message, $type = 'info') {
    global $isCLI;

    if ($isCLI) {
        $prefix = [
            'success' => '[✓] ',
            'error' => '[✗] ',
            'warning' => '[!] ',
            'info' => '[i] '
        ];
        echo ($prefix[$type] ?? '') . $message . "\n";
    } else {
        // HTML output handled later
    }
}

// ============================================================================
// CHECK 1: Pending citations with completed payments
// ============================================================================
output('Running Check 1: Pending citations with completed payments...', 'info');

$stmt = $pdo->query("
    SELECT
        c.citation_id,
        c.ticket_number,
        CONCAT(c.first_name, ' ', c.last_name) as driver_name,
        c.status as citation_status,
        COUNT(p.payment_id) as payment_count,
        GROUP_CONCAT(p.receipt_number SEPARATOR ', ') as receipt_numbers
    FROM citations c
    INNER JOIN payments p ON c.citation_id = p.citation_id
    WHERE c.status = 'pending'
        AND p.status = 'completed'
        AND c.deleted_at IS NULL
    GROUP BY c.citation_id
");

$pendingWithPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
addCheckResult(
    'Pending Citations with Completed Payments',
    'Citations marked as "pending" but have completed payment records',
    count($pendingWithPayments),
    $pendingWithPayments,
    'critical'
);

output('Found ' . count($pendingWithPayments) . ' issue(s)', count($pendingWithPayments) > 0 ? 'warning' : 'success');

// ============================================================================
// CHECK 2: Paid citations without completed payments
// ============================================================================
output('Running Check 2: Paid citations without completed payments...', 'info');

$stmt = $pdo->query("
    SELECT
        c.citation_id,
        c.ticket_number,
        CONCAT(c.first_name, ' ', c.last_name) as driver_name,
        c.status as citation_status,
        COUNT(p.payment_id) as total_payments,
        COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_payments
    FROM citations c
    LEFT JOIN payments p ON c.citation_id = p.citation_id
    WHERE c.status = 'paid'
        AND c.deleted_at IS NULL
    GROUP BY c.citation_id
    HAVING completed_payments = 0
");

$paidWithoutPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
addCheckResult(
    'Paid Citations Without Completed Payments',
    'Citations marked as "paid" but have no completed payment records',
    count($paidWithoutPayments),
    $paidWithoutPayments,
    'critical'
);

output('Found ' . count($paidWithoutPayments) . ' issue(s)', count($paidWithoutPayments) > 0 ? 'warning' : 'success');

// ============================================================================
// CHECK 3: Orphaned payments (citation doesn't exist or is deleted)
// ============================================================================
output('Running Check 3: Orphaned payments...', 'info');

$stmt = $pdo->query("
    SELECT
        p.payment_id,
        p.citation_id,
        p.receipt_number,
        p.amount_paid,
        p.status,
        c.citation_id as citation_exists,
        c.deleted_at
    FROM payments p
    LEFT JOIN citations c ON p.citation_id = c.citation_id
    WHERE c.citation_id IS NULL
        OR c.deleted_at IS NOT NULL
");

$orphanedPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
addCheckResult(
    'Orphaned Payments',
    'Payment records whose citations do not exist or are deleted',
    count($orphanedPayments),
    $orphanedPayments,
    'warning'
);

output('Found ' . count($orphanedPayments) . ' issue(s)', count($orphanedPayments) > 0 ? 'warning' : 'success');

// ============================================================================
// CHECK 4: Stale pending_print payments (older than configured hours)
// ============================================================================
output('Running Check 4: Stale pending_print payments...', 'info');

$stmt = $pdo->prepare("
    SELECT
        p.payment_id,
        p.citation_id,
        p.receipt_number,
        p.amount_paid,
        c.ticket_number,
        CONCAT(c.first_name, ' ', c.last_name) as driver_name,
        p.created_at,
        TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_pending
    FROM payments p
    INNER JOIN citations c ON p.citation_id = c.citation_id
    WHERE p.status = 'pending_print'
        AND TIMESTAMPDIFF(HOUR, p.created_at, NOW()) > :hours
        AND c.deleted_at IS NULL
    ORDER BY p.created_at ASC
");

$stmt->execute([':hours' => $CONFIG['stale_pending_hours']]);
$stalePending = $stmt->fetchAll(PDO::FETCH_ASSOC);

addCheckResult(
    'Stale Pending Print Payments',
    "Payments stuck in 'pending_print' status for more than {$CONFIG['stale_pending_hours']} hours",
    count($stalePending),
    $stalePending,
    'warning'
);

output('Found ' . count($stalePending) . ' issue(s)', count($stalePending) > 0 ? 'warning' : 'success');

// ============================================================================
// CHECK 5: Recent trigger errors
// ============================================================================
output('Running Check 5: Recent trigger errors...', 'info');

// Check if trigger_error_log table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'trigger_error_log'");
$hasErrorLog = $stmt->rowCount() > 0;

if ($hasErrorLog) {
    $stmt = $pdo->prepare("
        SELECT
            log_id,
            trigger_name,
            error_message,
            citation_id,
            payment_id,
            created_at,
            DATEDIFF(NOW(), created_at) as days_ago
        FROM trigger_error_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ORDER BY created_at DESC
    ");

    $stmt->execute([':days' => $CONFIG['trigger_error_days']]);
    $triggerErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    addCheckResult(
        'Recent Trigger Errors',
        "Database trigger errors in the last {$CONFIG['trigger_error_days']} days",
        count($triggerErrors),
        $triggerErrors,
        'critical'
    );

    output('Found ' . count($triggerErrors) . ' issue(s)', count($triggerErrors) > 0 ? 'error' : 'success');
} else {
    addCheckResult(
        'Recent Trigger Errors',
        'trigger_error_log table does not exist',
        0,
        [],
        'info'
    );
    output('trigger_error_log table not found (migration may not have been run)', 'warning');
}

// ============================================================================
// Send email alert if configured and threshold exceeded
// ============================================================================
if ($CONFIG['email_alerts'] && $results['total_issues'] >= $CONFIG['alert_threshold']) {
    $subject = "[TMG Alert] Data Consistency Issues Detected - {$results['total_issues']} issues found";
    $message = "Automated consistency check found {$results['total_issues']} issues:\n\n";

    foreach ($results['checks'] as $check) {
        if ($check['issue_count'] > 0) {
            $message .= "- {$check['name']}: {$check['issue_count']} issues\n";
        }
    }

    $message .= "\nPlease review the data integrity dashboard for details.\n";
    $message .= "Timestamp: {$results['timestamp']}\n";

    @mail($CONFIG['alert_email'], $subject, $message);
    output('Email alert sent to ' . $CONFIG['alert_email'], 'info');
}

// ============================================================================
// CLI Output Summary
// ============================================================================
if ($isCLI) {
    echo "\n";
    echo "═══════════════════════════════════════════\n";
    echo "  CONSISTENCY CHECK SUMMARY\n";
    echo "═══════════════════════════════════════════\n";
    echo "Timestamp: {$results['timestamp']}\n";
    echo "Total Issues: {$results['total_issues']}\n";
    echo "Critical Issues: " . ($results['has_critical_issues'] ? 'YES' : 'NO') . "\n";
    echo "═══════════════════════════════════════════\n";

    // Exit with appropriate code
    if ($results['has_critical_issues']) {
        exit(2); // Critical issues
    } elseif ($results['total_issues'] > 0) {
        exit(1); // Non-critical issues
    } else {
        exit(0); // All good
    }
}

// ============================================================================
// Web HTML Output
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automated Consistency Checker - TMG Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .checker-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .check-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #e5e7eb;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .check-card.ok {
            border-left-color: #10b981;
        }
        .check-card.warning {
            border-left-color: #f59e0b;
        }
        .check-card.critical {
            border-left-color: #ef4444;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-ok {
            background: #d1fae5;
            color: #065f46;
        }
        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .status-critical {
            background: #fee2e2;
            color: #991b1b;
        }
        .issue-table {
            font-size: 0.9em;
            margin-top: 15px;
        }
        .metric-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .metric-number {
            font-size: 2.5em;
            font-weight: bold;
        }
        .metric-number.good {
            color: #10b981;
        }
        .metric-number.bad {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="checker-container">
        <!-- Header -->
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2><i class="fas fa-tasks"></i> Automated Consistency Checker</h2>
                    <p class="text-muted mb-0">Comprehensive data integrity validation</p>
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

            <!-- Summary Metrics -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="metric-box">
                        <div class="metric-number <?= $results['total_issues'] === 0 ? 'good' : 'bad' ?>">
                            <?= $results['total_issues'] ?>
                        </div>
                        <div class="text-muted">Total Issues</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-box">
                        <div class="metric-number <?= $results['has_critical_issues'] ? 'bad' : 'good' ?>">
                            <?php
                            $criticalCount = 0;
                            foreach ($results['checks'] as $check) {
                                if ($check['severity'] === 'critical' && $check['issue_count'] > 0) {
                                    $criticalCount++;
                                }
                            }
                            echo $criticalCount;
                            ?>
                        </div>
                        <div class="text-muted">Critical Issues</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-box">
                        <div class="metric-number" style="color: #667eea;">
                            <?= count($results['checks']) ?>
                        </div>
                        <div class="text-muted">Checks Run</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-box">
                        <div style="font-size: 1.2em; color: #6b7280;">
                            <?= date('Y-m-d H:i:s') ?>
                        </div>
                        <div class="text-muted">Check Time</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Check Results -->
        <h4 class="mb-3">Check Results</h4>

        <?php foreach ($results['checks'] as $check): ?>
            <div class="check-card <?= $check['status'] === 'ok' ? 'ok' : $check['severity'] ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h5 class="mb-1">
                            <?php if ($check['status'] === 'ok'): ?>
                                <i class="fas fa-check-circle text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($check['name']) ?>
                        </h5>
                        <p class="text-muted mb-2"><?= htmlspecialchars($check['description']) ?></p>
                    </div>
                    <span class="status-badge status-<?= $check['status'] === 'ok' ? 'ok' : $check['severity'] ?>">
                        <?= $check['issue_count'] ?> issue(s)
                    </span>
                </div>

                <?php if ($check['issue_count'] > 0 && !empty($check['issues'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover issue-table">
                            <thead class="table-light">
                                <tr>
                                    <?php
                                    // Get column headers from first issue
                                    $firstIssue = $check['issues'][0];
                                    foreach (array_keys($firstIssue) as $column) {
                                        echo '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $column))) . '</th>';
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($check['issues'] as $issue): ?>
                                    <tr>
                                        <?php foreach ($issue as $value): ?>
                                            <td><?= htmlspecialchars($value ?? 'N/A') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Configuration Info -->
        <div class="summary-card mt-4">
            <h5><i class="fas fa-cog"></i> Configuration</h5>
            <table class="table table-sm">
                <tr>
                    <th width="250">Email Alerts:</th>
                    <td><?= $CONFIG['email_alerts'] ? 'Enabled' : 'Disabled' ?></td>
                </tr>
                <?php if ($CONFIG['email_alerts']): ?>
                <tr>
                    <th>Alert Email:</th>
                    <td><?= htmlspecialchars($CONFIG['alert_email']) ?></td>
                </tr>
                <tr>
                    <th>Alert Threshold:</th>
                    <td><?= $CONFIG['alert_threshold'] ?> issues</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Stale Pending Threshold:</th>
                    <td><?= $CONFIG['stale_pending_hours'] ?> hours</td>
                </tr>
                <tr>
                    <th>Trigger Error Check Period:</th>
                    <td><?= $CONFIG['trigger_error_days'] ?> days</td>
                </tr>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
