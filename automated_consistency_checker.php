<?php
/**
 * Automated Data Consistency Checker
 *
 * This script runs automated checks for data inconsistencies between
 * citations and payments tables. Can be run manually or via cron job.
 *
 * Usage:
 * - Manual: http://localhost/tmg/automated_consistency_checker.php
 * - Cron: php c:\xampp\htdocs\tmg\automated_consistency_checker.php
 * - Windows Task Scheduler: php.exe "c:\xampp\htdocs\tmg\automated_consistency_checker.php"
 */

// Allow running from command line or web
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';

// Configuration
$SEND_EMAIL_ALERTS = false; // Set to true to enable email alerts
$ADMIN_EMAIL = 'admin@traffic.local'; // Change to actual admin email
$AUTO_FIX = false; // Set to true to automatically fix inconsistencies (use with caution!)

// Initialize report
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks_performed' => [],
    'issues_found' => [],
    'trigger_errors' => [],
    'recommendations' => [],
    'total_issues' => 0
];

// Get database connection
$pdo = getPDO();

/**
 * Output message (works for both CLI and web)
 */
function output($message, $type = 'info') {
    global $isCLI;

    if ($isCLI) {
        $prefix = match($type) {
            'success' => '[✓] ',
            'error' => '[✗] ',
            'warning' => '[!] ',
            default => '[i] '
        };
        echo $prefix . $message . PHP_EOL;
    } else {
        $class = match($type) {
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            default => 'info'
        };
        echo "<div class='message {$class}'>{$message}</div>";
    }
}

/**
 * Send email alert to admin
 */
function sendAlert($subject, $body) {
    global $ADMIN_EMAIL, $SEND_EMAIL_ALERTS;

    if (!$SEND_EMAIL_ALERTS) {
        return false;
    }

    $headers = "From: Traffic System <noreply@traffic.local>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($ADMIN_EMAIL, $subject, $body, $headers);
}

if (!$isCLI) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Automated Consistency Checker</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
            }
            .container {
                max-width: 1400px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            }
            h1 {
                color: #2d3748;
                border-bottom: 4px solid #667eea;
                padding-bottom: 15px;
            }
            h2 {
                color: #4a5568;
                margin-top: 30px;
                padding: 10px;
                background: #f7fafc;
                border-left: 4px solid #667eea;
            }
            .message {
                padding: 15px;
                margin: 10px 0;
                border-radius: 6px;
                border-left: 4px solid;
            }
            .message.success {
                background: #d1fae5;
                border-color: #10b981;
                color: #065f46;
            }
            .message.error {
                background: #fee2e2;
                border-color: #ef4444;
                color: #991b1b;
            }
            .message.warning {
                background: #fef3c7;
                border-color: #f59e0b;
                color: #92400e;
            }
            .message.info {
                background: #dbeafe;
                border-color: #3b82f6;
                color: #1e40af;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                margin: 15px 0;
            }
            th, td {
                border: 1px solid #e5e7eb;
                padding: 12px;
                text-align: left;
            }
            th {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            tr:nth-child(even) {
                background: #f9fafb;
            }
            .metric-box {
                display: inline-block;
                background: #f3f4f6;
                padding: 20px;
                margin: 10px;
                border-radius: 8px;
                text-align: center;
                min-width: 150px;
            }
            .metric-value {
                font-size: 2em;
                font-weight: bold;
            }
            .metric-value.good {
                color: #10b981;
            }
            .metric-value.bad {
                color: #ef4444;
            }
            .metric-label {
                color: #6b7280;
                margin-top: 5px;
            }
            code {
                background: #f3f4f6;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔍 Automated Data Consistency Checker</h1>
            <p><strong>Check Date:</strong> <?= date('Y-m-d H:i:s') ?></p>
    <?php
}

output("=== DATA CONSISTENCY CHECK STARTED ===", 'info');
output("Timestamp: " . $report['timestamp'], 'info');

// ============================================================================
// CHECK 1: Pending Citations with Completed Payments
// ============================================================================
output("\n--- Check 1: Pending Citations with Completed Payments ---", 'info');
$report['checks_performed'][] = 'Pending Citations with Completed Payments';

try {
    $stmt = $pdo->query("
        SELECT
            c.citation_id,
            c.ticket_number,
            CONCAT(c.first_name, ' ', c.last_name) as driver_name,
            c.status as citation_status,
            COUNT(p.payment_id) as completed_payment_count,
            GROUP_CONCAT(p.receipt_number SEPARATOR ', ') as or_numbers,
            SUM(p.amount_paid) as total_paid
        FROM citations c
        INNER JOIN payments p ON c.citation_id = p.citation_id
        WHERE c.status = 'pending'
          AND p.status = 'completed'
        GROUP BY c.citation_id
    ");
    $pendingWithCompleted = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($pendingWithCompleted) > 0) {
        output("Found " . count($pendingWithCompleted) . " pending citation(s) with completed payments!", 'error');
        $report['issues_found']['pending_with_completed'] = $pendingWithCompleted;
        $report['total_issues'] += count($pendingWithCompleted);

        if (!$isCLI) {
            echo "<table>";
            echo "<tr><th>Citation ID</th><th>Ticket #</th><th>Driver</th><th>OR Numbers</th><th>Total Paid</th></tr>";
            foreach ($pendingWithCompleted as $row) {
                echo "<tr>";
                echo "<td>{$row['citation_id']}</td>";
                echo "<td><strong>{$row['ticket_number']}</strong></td>";
                echo "<td>{$row['driver_name']}</td>";
                echo "<td>{$row['or_numbers']}</td>";
                echo "<td>₱" . number_format($row['total_paid'], 2) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        $report['recommendations'][] = "Run fix_pending_paid_citations.php to update these citations to 'paid' status";
    } else {
        output("✓ No pending citations with completed payments found", 'success');
    }
} catch (PDOException $e) {
    output("Error in Check 1: " . $e->getMessage(), 'error');
}

// ============================================================================
// CHECK 2: Paid Citations without Completed Payments
// ============================================================================
output("\n--- Check 2: Paid Citations without Completed Payments ---", 'info');
$report['checks_performed'][] = 'Paid Citations without Completed Payments';

try {
    $stmt = $pdo->query("
        SELECT
            c.citation_id,
            c.ticket_number,
            CONCAT(c.first_name, ' ', c.last_name) as driver_name,
            c.status as citation_status,
            c.total_fine,
            COUNT(p.payment_id) as payment_count,
            GROUP_CONCAT(CONCAT(p.receipt_number, ':', p.status) SEPARATOR ', ') as payments
        FROM citations c
        LEFT JOIN payments p ON c.citation_id = p.citation_id
        WHERE c.status = 'paid'
        GROUP BY c.citation_id
        HAVING SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) = 0
    ");
    $paidWithoutCompleted = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($paidWithoutCompleted) > 0) {
        output("Found " . count($paidWithoutCompleted) . " paid citation(s) without completed payments!", 'error');
        $report['issues_found']['paid_without_completed'] = $paidWithoutCompleted;
        $report['total_issues'] += count($paidWithoutCompleted);

        if (!$isCLI) {
            echo "<table>";
            echo "<tr><th>Citation ID</th><th>Ticket #</th><th>Driver</th><th>Fine</th><th>Payments</th></tr>";
            foreach ($paidWithoutCompleted as $row) {
                echo "<tr>";
                echo "<td>{$row['citation_id']}</td>";
                echo "<td><strong>{$row['ticket_number']}</strong></td>";
                echo "<td>{$row['driver_name']}</td>";
                echo "<td>₱" . number_format($row['total_fine'], 2) . "</td>";
                echo "<td style='font-size: 0.85em;'>" . ($row['payments'] ?? 'No payments') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        $report['recommendations'][] = "Investigate these citations - they may need to be reverted to 'pending' status";
    } else {
        output("✓ All paid citations have completed payments", 'success');
    }
} catch (PDOException $e) {
    output("Error in Check 2: " . $e->getMessage(), 'error');
}

// ============================================================================
// CHECK 3: Orphaned Payments (payments without valid citations)
// ============================================================================
output("\n--- Check 3: Orphaned Payments ---", 'info');
$report['checks_performed'][] = 'Orphaned Payments';

try {
    $stmt = $pdo->query("
        SELECT
            p.payment_id,
            p.citation_id,
            p.receipt_number,
            p.amount_paid,
            p.status,
            p.payment_date
        FROM payments p
        LEFT JOIN citations c ON p.citation_id = c.citation_id
        WHERE c.citation_id IS NULL
    ");
    $orphanedPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($orphanedPayments) > 0) {
        output("Found " . count($orphanedPayments) . " orphaned payment(s)!", 'error');
        $report['issues_found']['orphaned_payments'] = $orphanedPayments;
        $report['total_issues'] += count($orphanedPayments);

        if (!$isCLI) {
            echo "<table>";
            echo "<tr><th>Payment ID</th><th>Citation ID</th><th>OR Number</th><th>Amount</th><th>Status</th></tr>";
            foreach ($orphanedPayments as $row) {
                echo "<tr>";
                echo "<td>{$row['payment_id']}</td>";
                echo "<td><strong>{$row['citation_id']}</strong> (missing)</td>";
                echo "<td>{$row['receipt_number']}</td>";
                echo "<td>₱" . number_format($row['amount_paid'], 2) . "</td>";
                echo "<td>{$row['status']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        $report['recommendations'][] = "Investigate orphaned payments - citations may have been deleted improperly";
    } else {
        output("✓ No orphaned payments found", 'success');
    }
} catch (PDOException $e) {
    output("Error in Check 3: " . $e->getMessage(), 'error');
}

// ============================================================================
// CHECK 4: Pending Print Payments Older than 24 Hours
// ============================================================================
output("\n--- Check 4: Stale Pending Print Payments ---", 'info');
$report['checks_performed'][] = 'Stale Pending Print Payments';

try {
    $stmt = $pdo->query("
        SELECT
            p.payment_id,
            p.citation_id,
            c.ticket_number,
            p.receipt_number,
            p.amount_paid,
            p.created_at,
            TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_pending
        FROM payments p
        INNER JOIN citations c ON p.citation_id = c.citation_id
        WHERE p.status = 'pending_print'
          AND TIMESTAMPDIFF(HOUR, p.created_at, NOW()) > 24
        ORDER BY p.created_at ASC
    ");
    $stalePendingPrint = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($stalePendingPrint) > 0) {
        output("Found " . count($stalePendingPrint) . " stale pending_print payment(s)!", 'warning');
        $report['issues_found']['stale_pending_print'] = $stalePendingPrint;
        $report['total_issues'] += count($stalePendingPrint);

        if (!$isCLI) {
            echo "<table>";
            echo "<tr><th>Payment ID</th><th>Ticket #</th><th>OR Number</th><th>Amount</th><th>Hours Pending</th></tr>";
            foreach ($stalePendingPrint as $row) {
                echo "<tr>";
                echo "<td>{$row['payment_id']}</td>";
                echo "<td>{$row['ticket_number']}</td>";
                echo "<td>{$row['receipt_number']}</td>";
                echo "<td>₱" . number_format($row['amount_paid'], 2) . "</td>";
                echo "<td><strong>{$row['hours_pending']} hours</strong></td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        $report['recommendations'][] = "Review pending_print payments - cashiers may have forgotten to confirm receipt printing";
    } else {
        output("✓ No stale pending_print payments found", 'success');
    }
} catch (PDOException $e) {
    output("Error in Check 4: " . $e->getMessage(), 'error');
}

// ============================================================================
// CHECK 5: Trigger Error Log
// ============================================================================
output("\n--- Check 5: Recent Trigger Errors ---", 'info');
$report['checks_performed'][] = 'Trigger Error Log';

try {
    // Check if table exists first
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
        $triggerErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($triggerErrors) > 0) {
            output("Found " . count($triggerErrors) . " trigger error(s) in the last 7 days!", 'error');
            $report['trigger_errors'] = $triggerErrors;
            $report['total_issues'] += count($triggerErrors);

            if (!$isCLI) {
                echo "<table>";
                echo "<tr><th>Log ID</th><th>Trigger</th><th>Error Message</th><th>Citation ID</th><th>Date</th></tr>";
                foreach ($triggerErrors as $row) {
                    echo "<tr>";
                    echo "<td>{$row['log_id']}</td>";
                    echo "<td><code>{$row['trigger_name']}</code></td>";
                    echo "<td style='font-size: 0.85em;'>" . htmlspecialchars(substr($row['error_message'], 0, 100)) . "</td>";
                    echo "<td>{$row['citation_id']}</td>";
                    echo "<td>{$row['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            $report['recommendations'][] = "Review trigger_error_log table to diagnose database trigger issues";
        } else {
            output("✓ No trigger errors in the last 7 days", 'success');
        }
    } else {
        output("ℹ Trigger error log table not found (run consistency migration first)", 'info');
    }
} catch (PDOException $e) {
    output("Error in Check 5: " . $e->getMessage(), 'error');
}

// ============================================================================
// SUMMARY
// ============================================================================
output("\n=== SUMMARY ===", 'info');
output("Total checks performed: " . count($report['checks_performed']), 'info');
output("Total issues found: " . $report['total_issues'], $report['total_issues'] > 0 ? 'warning' : 'success');

if (!$isCLI) {
    ?>
    <h2>📊 Summary</h2>
    <div class="metric-box">
        <div class="metric-value"><?= count($report['checks_performed']) ?></div>
        <div class="metric-label">Checks Performed</div>
    </div>
    <div class="metric-box">
        <div class="metric-value <?= $report['total_issues'] > 0 ? 'bad' : 'good' ?>">
            <?= $report['total_issues'] ?>
        </div>
        <div class="metric-label">Issues Found</div>
    </div>
    <?php

    if (!empty($report['recommendations'])) {
        echo "<h2>💡 Recommendations</h2>";
        echo "<ol>";
        foreach ($report['recommendations'] as $recommendation) {
            echo "<li>{$recommendation}</li>";
        }
        echo "</ol>";
    }

    if ($report['total_issues'] === 0) {
        echo "<div class='message success'><strong>🎉 All checks passed!</strong> Your database is consistent.</div>";
    } else {
        echo "<div class='message warning'><strong>⚠️ Issues detected.</strong> Please review the recommendations above.</div>";
    }

    echo "</div></body></html>";
}

// Send email alert if issues found
if ($report['total_issues'] > 0 && $SEND_EMAIL_ALERTS) {
    $emailBody = "<h2>Data Consistency Issues Detected</h2>";
    $emailBody .= "<p><strong>Total Issues:</strong> {$report['total_issues']}</p>";
    $emailBody .= "<p><strong>Timestamp:</strong> {$report['timestamp']}</p>";
    $emailBody .= "<h3>Recommendations:</h3><ol>";
    foreach ($report['recommendations'] as $rec) {
        $emailBody .= "<li>{$rec}</li>";
    }
    $emailBody .= "</ol>";
    $emailBody .= "<p><a href='http://localhost/tmg/automated_consistency_checker.php'>View Full Report</a></p>";

    sendAlert("Traffic System: Data Consistency Issues Detected", $emailBody);
    output("Email alert sent to {$ADMIN_EMAIL}", 'info');
}

output("\n=== CHECK COMPLETE ===", 'success');
exit($report['total_issues'] > 0 ? 1 : 0); // Exit code: 0 = success, 1 = issues found
