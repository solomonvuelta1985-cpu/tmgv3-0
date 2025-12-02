<?php
/**
 * Comprehensive Citation-Payment Inconsistency Investigation
 *
 * This script investigates data inconsistencies between the citations table
 * and payments table to understand why citations marked as "pending" cannot
 * be deleted due to having "completed" payment records.
 *
 * Run this script to diagnose the issue before applying any fixes.
 */

require_once __DIR__ . '/includes/config.php';

// Get database connection
$pdo = getPDO();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citation-Payment Inconsistency Investigation</title>
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
            margin-bottom: 20px;
        }
        h2 {
            color: #4a5568;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 40px;
            margin-bottom: 20px;
        }
        h3 {
            color: #2d3748;
            border-left: 4px solid #f59e0b;
            padding-left: 15px;
            margin-top: 30px;
        }
        .info-box {
            background: #dbeafe;
            border-left: 6px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .warning-box {
            background: #fef3c7;
            border-left: 6px solid #f59e0b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .error-box {
            background: #fee2e2;
            border-left: 6px solid #ef4444;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .success-box {
            background: #d1fae5;
            border-left: 6px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: left;
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        tr:hover {
            background: #f3f4f6;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-paid {
            background: #d1fae5;
            color: #065f46;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-completed {
            background: #d1fae5;
            color: #065f46;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-pending_print {
            background: #e0e7ff;
            color: #3730a3;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-voided {
            background: #fee2e2;
            color: #991b1b;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        .sql-query {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 15px 0;
            border-left: 4px solid #10b981;
        }
        .metric {
            display: inline-block;
            background: #f3f4f6;
            padding: 20px;
            margin: 10px;
            border-radius: 8px;
            text-align: center;
            min-width: 150px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .metric-label {
            color: #6b7280;
            margin-top: 5px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 5px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .root-cause {
            background: #fee2e2;
            border: 2px solid #ef4444;
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
        }
        .root-cause h3 {
            color: #991b1b;
            margin-top: 0;
            border-left: none;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #be185d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Citation-Payment Inconsistency Investigation Report</h1>

        <div class="info-box">
            <p><strong>Investigation Purpose:</strong> To understand why citations with status="pending" cannot be deleted due to having "completed" payment records.</p>
            <p><strong>Date Generated:</strong> <?= date('Y-m-d H:i:s') ?></p>
        </div>

        <?php
        try {
            // ==================================================================
            // SECTION 1: Overall Statistics
            // ==================================================================
            echo "<h2>📊 Section 1: Overall Database Statistics</h2>";

            // Total citations by status
            $stmt = $pdo->query("
                SELECT status, COUNT(*) as count
                FROM citations
                GROUP BY status
                ORDER BY count DESC
            ");
            $citationStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h3>Citations by Status</h3>";
            echo "<div style='margin: 20px 0;'>";
            foreach ($citationStats as $stat) {
                echo "<div class='metric'>";
                echo "<div class='metric-value'>{$stat['count']}</div>";
                echo "<div class='metric-label'>" . strtoupper($stat['status']) . "</div>";
                echo "</div>";
            }
            echo "</div>";

            // Total payments by status
            $stmt = $pdo->query("
                SELECT status, COUNT(*) as count
                FROM payments
                GROUP BY status
                ORDER BY count DESC
            ");
            $paymentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h3>Payments by Status</h3>";
            echo "<div style='margin: 20px 0;'>";
            foreach ($paymentStats as $stat) {
                echo "<div class='metric'>";
                echo "<div class='metric-value'>{$stat['count']}</div>";
                echo "<div class='metric-label'>" . strtoupper($stat['status']) . "</div>";
                echo "</div>";
            }
            echo "</div>";

            // ==================================================================
            // SECTION 2: Critical Inconsistency - THE ROOT CAUSE
            // ==================================================================
            echo "<h2>🚨 Section 2: Critical Data Inconsistency (ROOT CAUSE)</h2>";

            $stmt = $pdo->query("
                SELECT
                    c.citation_id,
                    c.ticket_number,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.status as citation_status,
                    c.total_fine,
                    c.payment_date as citation_payment_date,
                    p.payment_id,
                    p.receipt_number,
                    p.amount_paid,
                    p.payment_date as payment_date,
                    p.status as payment_status,
                    p.payment_method,
                    p.created_at as payment_created_at
                FROM citations c
                INNER JOIN payments p ON c.citation_id = p.citation_id
                WHERE c.status = 'pending'
                  AND p.status = 'completed'
                ORDER BY c.citation_id DESC
            ");
            $inconsistencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($inconsistencies) > 0) {
                echo "<div class='root-cause'>";
                echo "<h3>🎯 ROOT CAUSE IDENTIFIED</h3>";
                echo "<p><strong>Found " . count($inconsistencies) . " citation(s) with MISMATCHED status:</strong></p>";
                echo "<ul>";
                echo "<li><strong>Citation Status:</strong> <span class='status-pending'>pending</span></li>";
                echo "<li><strong>Payment Status:</strong> <span class='status-completed'>completed</span></li>";
                echo "</ul>";
                echo "<p><strong>Why deletion fails:</strong> The deletion logic in <code>api/citation_delete.php:63-74</code> checks for <code>status='completed'</code> in the <strong>payments</strong> table, NOT the citation status. Even though the citation shows 'pending', it has completed payment records.</p>";
                echo "</div>";

                echo "<table>";
                echo "<tr>
                        <th>Citation ID</th>
                        <th>Ticket #</th>
                        <th>Driver Name</th>
                        <th>Citation Status</th>
                        <th>Payment Status</th>
                        <th>OR Number</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Payment Method</th>
                      </tr>";

                foreach ($inconsistencies as $row) {
                    echo "<tr>";
                    echo "<td>{$row['citation_id']}</td>";
                    echo "<td><strong>{$row['ticket_number']}</strong></td>";
                    echo "<td>{$row['driver_name']}</td>";
                    echo "<td><span class='status-{$row['citation_status']}'>{$row['citation_status']}</span></td>";
                    echo "<td><span class='status-{$row['payment_status']}'>{$row['payment_status']}</span></td>";
                    echo "<td>{$row['receipt_number']}</td>";
                    echo "<td>₱" . number_format($row['amount_paid'], 2) . "</td>";
                    echo "<td>{$row['payment_date']}</td>";
                    echo "<td>{$row['payment_method']}</td>";
                    echo "</tr>";
                }

                echo "</table>";

                echo "<div class='warning-box'>";
                echo "<h4>⚠️ How This Inconsistency Occurred</h4>";
                echo "<p><strong>Payment Workflow Analysis:</strong></p>";
                echo "<ol>";
                echo "<li><strong>Step 1:</strong> Payment is recorded with <code>status='pending_print'</code> (PaymentProcessor.php:73)</li>";
                echo "<li><strong>Step 2:</strong> Citation status is <strong>NOT</strong> updated at this point (PaymentProcessor.php:104-106)</li>";
                echo "<li><strong>Step 3:</strong> Cashier clicks 'Confirm Print' which calls <code>finalizePayment()</code></li>";
                echo "<li><strong>Step 4:</strong> Payment status changes to <code>'completed'</code> (PaymentProcessor.php:292-296)</li>";
                echo "<li><strong>Step 5:</strong> Citation status should change to <code>'paid'</code> (PaymentProcessor.php:299-304)</li>";
                echo "</ol>";
                echo "<p><strong>⚠️ THE PROBLEM:</strong> If the database trigger fails, or if there's an exception during the finalization process, or if old payment records were created before this workflow, the payment status becomes 'completed' but the citation remains 'pending'.</p>";
                echo "</div>";

            } else {
                echo "<div class='success-box'>";
                echo "<p><strong>✅ No inconsistencies found.</strong> All citations with completed payments are correctly marked as 'paid'.</p>";
                echo "</div>";
            }

            // ==================================================================
            // SECTION 3: Pending Print Payments (Expected State)
            // ==================================================================
            echo "<h2>📋 Section 3: Pending Print Payments (Expected State)</h2>";

            $stmt = $pdo->query("
                SELECT
                    c.citation_id,
                    c.ticket_number,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.status as citation_status,
                    p.payment_id,
                    p.receipt_number,
                    p.amount_paid,
                    p.payment_date,
                    p.status as payment_status,
                    p.created_at
                FROM citations c
                INNER JOIN payments p ON c.citation_id = p.citation_id
                WHERE p.status = 'pending_print'
                ORDER BY p.created_at DESC
            ");
            $pendingPrint = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($pendingPrint) > 0) {
                echo "<div class='info-box'>";
                echo "<p><strong>Found " . count($pendingPrint) . " payment(s) in 'pending_print' status.</strong> These are normal and expected - waiting for cashier to confirm receipt printed.</p>";
                echo "</div>";

                echo "<table>";
                echo "<tr>
                        <th>Citation ID</th>
                        <th>Ticket #</th>
                        <th>Driver Name</th>
                        <th>Citation Status</th>
                        <th>Payment Status</th>
                        <th>OR Number</th>
                        <th>Amount</th>
                        <th>Created At</th>
                      </tr>";

                foreach ($pendingPrint as $row) {
                    echo "<tr>";
                    echo "<td>{$row['citation_id']}</td>";
                    echo "<td><strong>{$row['ticket_number']}</strong></td>";
                    echo "<td>{$row['driver_name']}</td>";
                    echo "<td><span class='status-{$row['citation_status']}'>{$row['citation_status']}</span></td>";
                    echo "<td><span class='status-{$row['payment_status']}'>{$row['payment_status']}</span></td>";
                    echo "<td>{$row['receipt_number']}</td>";
                    echo "<td>₱" . number_format($row['amount_paid'], 2) . "</td>";
                    echo "<td>{$row['created_at']}</td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<div class='success-box'>";
                echo "<p><strong>✅ No pending print payments.</strong> All payments have been finalized or cancelled.</p>";
                echo "</div>";
            }

            // ==================================================================
            // SECTION 4: Voided Payments
            // ==================================================================
            echo "<h2>🚫 Section 4: Voided Payments</h2>";

            $stmt = $pdo->query("
                SELECT
                    c.citation_id,
                    c.ticket_number,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.status as citation_status,
                    p.payment_id,
                    p.receipt_number,
                    p.amount_paid,
                    p.status as payment_status,
                    p.notes,
                    p.updated_at
                FROM citations c
                INNER JOIN payments p ON c.citation_id = p.citation_id
                WHERE p.status = 'voided'
                ORDER BY p.updated_at DESC
            ");
            $voidedPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($voidedPayments) > 0) {
                echo "<div class='info-box'>";
                echo "<p><strong>Found " . count($voidedPayments) . " voided payment(s).</strong></p>";
                echo "</div>";

                echo "<table>";
                echo "<tr>
                        <th>Citation ID</th>
                        <th>Ticket #</th>
                        <th>Citation Status</th>
                        <th>Payment Status</th>
                        <th>OR Number</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th>Voided At</th>
                      </tr>";

                foreach ($voidedPayments as $row) {
                    echo "<tr>";
                    echo "<td>{$row['citation_id']}</td>";
                    echo "<td><strong>{$row['ticket_number']}</strong></td>";
                    echo "<td><span class='status-{$row['citation_status']}'>{$row['citation_status']}</span></td>";
                    echo "<td><span class='status-{$row['payment_status']}'>{$row['payment_status']}</span></td>";
                    echo "<td>{$row['receipt_number']}</td>";
                    echo "<td>₱" . number_format($row['amount_paid'], 2) . "</td>";
                    echo "<td style='font-size: 0.85em; max-width: 300px;'>" . htmlspecialchars(substr($row['notes'], 0, 150)) . "</td>";
                    echo "<td>{$row['updated_at']}</td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<div class='success-box'>";
                echo "<p><strong>✅ No voided payments found.</strong></p>";
                echo "</div>";
            }

            // ==================================================================
            // SECTION 5: Multiple Payments for Single Citation
            // ==================================================================
            echo "<h2>🔁 Section 5: Citations with Multiple Payment Records</h2>";

            $stmt = $pdo->query("
                SELECT
                    c.citation_id,
                    c.ticket_number,
                    c.status as citation_status,
                    COUNT(p.payment_id) as payment_count,
                    GROUP_CONCAT(CONCAT(p.receipt_number, ' (', p.status, ')') ORDER BY p.created_at SEPARATOR ', ') as all_payments,
                    SUM(CASE WHEN p.status = 'completed' THEN p.amount_paid ELSE 0 END) as total_completed_amount
                FROM citations c
                INNER JOIN payments p ON c.citation_id = p.citation_id
                GROUP BY c.citation_id
                HAVING payment_count > 1
                ORDER BY payment_count DESC
            ");
            $multiplePayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($multiplePayments) > 0) {
                echo "<div class='warning-box'>";
                echo "<p><strong>⚠️ Found " . count($multiplePayments) . " citation(s) with multiple payment records.</strong></p>";
                echo "<p>This can happen when payments are voided and re-processed, or when errors occur during payment processing.</p>";
                echo "</div>";

                echo "<table>";
                echo "<tr>
                        <th>Citation ID</th>
                        <th>Ticket #</th>
                        <th>Citation Status</th>
                        <th>Payment Count</th>
                        <th>All Payments (OR + Status)</th>
                        <th>Total Completed</th>
                      </tr>";

                foreach ($multiplePayments as $row) {
                    echo "<tr>";
                    echo "<td>{$row['citation_id']}</td>";
                    echo "<td><strong>{$row['ticket_number']}</strong></td>";
                    echo "<td><span class='status-{$row['citation_status']}'>{$row['citation_status']}</span></td>";
                    echo "<td><span class='badge badge-warning'>{$row['payment_count']}</span></td>";
                    echo "<td style='font-size: 0.85em;'>{$row['all_payments']}</td>";
                    echo "<td>₱" . number_format($row['total_completed_amount'], 2) . "</td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<div class='success-box'>";
                echo "<p><strong>✅ All citations have single payment records.</strong></p>";
                echo "</div>";
            }

            // ==================================================================
            // SECTION 6: Database Trigger Status
            // ==================================================================
            echo "<h2>⚙️ Section 6: Database Trigger Status</h2>";

            $stmt = $pdo->query("
                SELECT
                    TRIGGER_NAME,
                    EVENT_MANIPULATION,
                    EVENT_OBJECT_TABLE,
                    ACTION_TIMING,
                    ACTION_STATEMENT
                FROM INFORMATION_SCHEMA.TRIGGERS
                WHERE TRIGGER_SCHEMA = 'traffic_system'
                  AND TRIGGER_NAME IN ('after_payment_insert', 'after_payment_update')
                ORDER BY TRIGGER_NAME
            ");
            $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($triggers) > 0) {
                echo "<div class='success-box'>";
                echo "<p><strong>✅ Found " . count($triggers) . " payment-related trigger(s).</strong></p>";
                echo "</div>";

                echo "<table>";
                echo "<tr>
                        <th>Trigger Name</th>
                        <th>Event</th>
                        <th>Table</th>
                        <th>Timing</th>
                      </tr>";

                foreach ($triggers as $row) {
                    echo "<tr>";
                    echo "<td><code>{$row['TRIGGER_NAME']}</code></td>";
                    echo "<td><span class='badge badge-info'>{$row['EVENT_MANIPULATION']}</span></td>";
                    echo "<td>{$row['EVENT_OBJECT_TABLE']}</td>";
                    echo "<td>{$row['ACTION_TIMING']}</td>";
                    echo "</tr>";
                }

                echo "</table>";

                echo "<h3>Trigger Logic Analysis</h3>";
                echo "<div class='info-box'>";
                echo "<p><strong>after_payment_insert trigger:</strong></p>";
                echo "<ul>";
                echo "<li>Updates citation status to 'paid' <strong>ONLY IF</strong> payment status = 'completed'</li>";
                echo "<li>This explains why pending_print payments don't update citation status</li>";
                echo "</ul>";
                echo "<p><strong>after_payment_update trigger:</strong></p>";
                echo "<ul>";
                echo "<li>Updates citation to 'paid' when payment changes from other status → 'completed'</li>";
                echo "<li>Reverts citation to 'pending' when payment is refunded or cancelled</li>";
                echo "</ul>";
                echo "</div>";
            } else {
                echo "<div class='error-box'>";
                echo "<p><strong>❌ No payment triggers found!</strong> This could explain the inconsistency.</p>";
                echo "</div>";
            }

        } catch (Exception $e) {
            echo "<div class='error-box'>";
            echo "<h3>❌ Error During Investigation</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
        ?>

        <!-- RECOMMENDATIONS SECTION -->
        <h2>💡 Section 7: Recommendations & Fix Options</h2>

        <div class="warning-box">
            <h3>🎯 Three Options to Fix This Issue:</h3>

            <h4>Option 1: Fix the Data (Recommended for Clean State)</h4>
            <p>Update all citations that have completed payments to status='paid':</p>
            <div class="sql-query">
UPDATE citations c<br>
INNER JOIN payments p ON c.citation_id = p.citation_id<br>
SET c.status = 'paid', c.payment_date = p.payment_date<br>
WHERE c.status = 'pending' AND p.status = 'completed';
            </div>
            <p><strong>✅ Pros:</strong> Fixes root cause, keeps data consistent</p>
            <p><strong>⚠️ Cons:</strong> May change citation status you intended to keep as pending</p>

            <h4>Option 2: Delete Invalid Payments</h4>
            <p>If the completed payment records are erroneous, delete them:</p>
            <div class="sql-query">
DELETE p FROM payments p<br>
INNER JOIN citations c ON p.citation_id = c.citation_id<br>
WHERE c.status = 'pending' AND p.status = 'completed';
            </div>
            <p><strong>✅ Pros:</strong> Removes incorrect payment records</p>
            <p><strong>⚠️ Cons:</strong> Deletes financial records (may violate audit requirements)</p>

            <h4>Option 3: Modify Delete Logic (Quick Fix)</h4>
            <p>Change the deletion check to also consider citation status:</p>
            <div class="sql-query">
-- In api/citation_delete.php, change line 63 from:<br>
WHERE citation_id = ? AND status = 'completed'<br><br>
-- To:<br>
WHERE citation_id = ? AND status = 'completed' AND EXISTS (<br>
&nbsp;&nbsp;SELECT 1 FROM citations WHERE citation_id = ? AND status = 'paid'<br>
)
            </div>
            <p><strong>✅ Pros:</strong> Allows deletion of pending citations even with completed payments</p>
            <p><strong>⚠️ Cons:</strong> Doesn't fix underlying data inconsistency</p>
        </div>

        <div class="success-box">
            <h3>📝 Recommended Action Plan</h3>
            <ol>
                <li><strong>Run the existing fix script:</strong> <code>fix_pending_paid_citations.php</code> to update all mismatched citations</li>
                <li><strong>Verify triggers are active:</strong> Ensure database triggers are working correctly</li>
                <li><strong>Monitor new payments:</strong> Check that new payments properly update citation status</li>
                <li><strong>Consider modifying delete logic:</strong> Add additional validation to prevent this issue</li>
            </ol>
        </div>

        <!-- SQL QUERIES FOR MANUAL FIXES -->
        <h2>🛠️ Section 8: SQL Queries for Manual Fixes</h2>

        <h3>Query 1: Find All Inconsistent Citations</h3>
        <div class="sql-query">
SELECT <br>
&nbsp;&nbsp;c.citation_id,<br>
&nbsp;&nbsp;c.ticket_number,<br>
&nbsp;&nbsp;c.status as citation_status,<br>
&nbsp;&nbsp;p.payment_id,<br>
&nbsp;&nbsp;p.receipt_number,<br>
&nbsp;&nbsp;p.status as payment_status<br>
FROM citations c<br>
INNER JOIN payments p ON c.citation_id = p.citation_id<br>
WHERE c.status = 'pending' AND p.status = 'completed';
        </div>

        <h3>Query 2: Fix Specific Citation by ID</h3>
        <div class="sql-query">
UPDATE citations <br>
SET status = 'paid', payment_date = NOW()<br>
WHERE citation_id = YOUR_CITATION_ID;
        </div>

        <h3>Query 3: Delete Specific Payment by ID</h3>
        <div class="sql-query">
-- First delete receipt record<br>
DELETE FROM receipts WHERE payment_id = YOUR_PAYMENT_ID;<br><br>
-- Then delete payment<br>
DELETE FROM payments WHERE payment_id = YOUR_PAYMENT_ID;
        </div>

        <div style="margin-top: 40px; padding: 20px; background: #f9fafb; border-radius: 8px;">
            <p style="text-align: center; margin: 0;">
                <a href="fix_pending_paid_citations.php" class="btn">🔧 Run Auto-Fix Script</a>
                <a href="public/citations.php" class="btn">📋 View Citations</a>
                <a href="public/pending_print_payments.php" class="btn">💰 View Pending Payments</a>
            </p>
        </div>
    </div>
</body>
</html>
