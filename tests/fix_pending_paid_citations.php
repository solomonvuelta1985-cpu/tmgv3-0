<?php
/**
 * Fix Pending Paid Citations Script
 *
 * This script fixes citations that have completed payments but are still marked as "pending".
 * It updates their status to "paid" and sets the payment_date to match the payment record.
 * All changes are logged to the audit trail.
 *
 * Run this script ONCE to fix historical data from before the auto-update feature was implemented.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/services/AuditService.php';

// Get database connection
$pdo = getPDO();

// Initialize audit service
$auditService = new AuditService($pdo);

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<title>Fix Pending Paid Citations</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    h1 { color: #2563eb; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
    h2 { color: #059669; margin-top: 30px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #2563eb; color: white; font-weight: bold; }
    tr:nth-child(even) { background: #f8fafc; }
    .success { color: #059669; font-weight: bold; }
    .error { color: #dc2626; font-weight: bold; }
    .info { color: #0284c7; }
    .warning { color: #ea580c; font-weight: bold; }
    .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
    .btn:hover { background: #1d4ed8; }
    .summary-box { background: #dbeafe; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #2563eb; }
</style>
</head><body>";
echo "<div class='container'>";

echo "<h1>🔧 Fix Pending Paid Citations</h1>";
echo "<p>This script will update citations that have completed payments but are still marked as 'pending'.</p>";

try {
    // Step 1: Find citations with completed payments but still marked as pending
    echo "<h2>Step 1: Finding Affected Citations</h2>";

    $sql = "SELECT
                c.citation_id,
                c.ticket_number,
                c.status as current_status,
                c.total_fine,
                c.payment_date,
                p.payment_id,
                p.receipt_number,
                p.amount_paid,
                p.payment_date as payment_recorded_date,
                p.payment_method,
                p.status as payment_status,
                CONCAT(c.first_name, ' ', c.last_name) as driver_name
            FROM citations c
            INNER JOIN payments p ON c.citation_id = p.citation_id
            WHERE c.status = 'pending'
            AND p.status = 'completed'
            ORDER BY c.citation_id";

    $stmt = $pdo->query($sql);
    $affectedCitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($affectedCitations)) {
        echo "<div class='summary-box'>";
        echo "<p class='success'>✅ No citations found that need fixing. All citations with completed payments are already marked as 'paid'.</p>";
        echo "</div>";
    } else {
        echo "<div class='summary-box'>";
        echo "<p class='warning'>⚠️ Found <strong>" . count($affectedCitations) . "</strong> citation(s) that need to be fixed.</p>";
        echo "</div>";

        echo "<table>";
        echo "<tr>
                <th>Citation ID</th>
                <th>Ticket Number</th>
                <th>Driver Name</th>
                <th>Current Status</th>
                <th>Payment Receipt</th>
                <th>Amount Paid</th>
                <th>Payment Date</th>
                <th>Payment Status</th>
              </tr>";

        foreach ($affectedCitations as $citation) {
            echo "<tr>";
            echo "<td>{$citation['citation_id']}</td>";
            echo "<td><strong>{$citation['ticket_number']}</strong></td>";
            echo "<td>{$citation['driver_name']}</td>";
            echo "<td class='warning'>{$citation['current_status']}</td>";
            echo "<td>{$citation['receipt_number']}</td>";
            echo "<td>₱" . number_format($citation['amount_paid'], 2) . "</td>";
            echo "<td>{$citation['payment_recorded_date']}</td>";
            echo "<td class='success'>{$citation['payment_status']}</td>";
            echo "</tr>";
        }

        echo "</table>";

        // Step 2: Fix the citations
        echo "<h2>Step 2: Updating Citations</h2>";

        $pdo->beginTransaction();

        $fixedCount = 0;
        $errorCount = 0;

        echo "<table>";
        echo "<tr>
                <th>Ticket Number</th>
                <th>Action</th>
                <th>Old Status</th>
                <th>New Status</th>
                <th>Payment Date Set</th>
                <th>Result</th>
              </tr>";

        foreach ($affectedCitations as $citation) {
            try {
                // Update citation status to 'paid' and set payment_date
                $updateSql = "UPDATE citations
                             SET status = 'paid',
                                 payment_date = :payment_date
                             WHERE citation_id = :citation_id";

                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':payment_date' => $citation['payment_recorded_date'],
                    ':citation_id' => $citation['citation_id']
                ]);

                // Log to audit trail
                $auditService->logCitationStatusChange(
                    $citation['citation_id'],
                    'pending',
                    'paid',
                    null, // System action (no user ID)
                    'Fixed by script: Citation had completed payment but was still marked as pending. Payment Receipt: ' . $citation['receipt_number']
                );

                echo "<tr>";
                echo "<td><strong>{$citation['ticket_number']}</strong></td>";
                echo "<td>UPDATE</td>";
                echo "<td class='warning'>pending</td>";
                echo "<td class='success'>paid</td>";
                echo "<td>{$citation['payment_recorded_date']}</td>";
                echo "<td class='success'>✅ Fixed</td>";
                echo "</tr>";

                $fixedCount++;

            } catch (Exception $e) {
                echo "<tr>";
                echo "<td><strong>{$citation['ticket_number']}</strong></td>";
                echo "<td>UPDATE</td>";
                echo "<td class='warning'>pending</td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "<td class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</td>";
                echo "</tr>";

                $errorCount++;
            }
        }

        echo "</table>";

        // Commit transaction if no errors
        if ($errorCount === 0) {
            $pdo->commit();

            echo "<div class='summary-box'>";
            echo "<h3 class='success'>✅ Success!</h3>";
            echo "<p><strong>{$fixedCount}</strong> citation(s) have been successfully updated to 'paid' status.</p>";
            echo "<p>All changes have been logged to the audit trail for transparency.</p>";
            echo "</div>";
        } else {
            $pdo->rollBack();

            echo "<div class='summary-box'>";
            echo "<h3 class='error'>❌ Errors Occurred</h3>";
            echo "<p>Transaction rolled back due to errors.</p>";
            echo "<p>Fixed: {$fixedCount} | Errors: {$errorCount}</p>";
            echo "</div>";
        }
    }

    // Step 3: Verification
    echo "<h2>Step 3: Verification</h2>";

    $verifySql = "SELECT
                    c.citation_id,
                    c.ticket_number,
                    c.status,
                    c.payment_date,
                    p.receipt_number,
                    p.payment_date as payment_recorded_date
                  FROM citations c
                  INNER JOIN payments p ON c.citation_id = p.citation_id
                  WHERE p.status = 'completed'
                  ORDER BY c.citation_id DESC
                  LIMIT 10";

    $verifyStmt = $pdo->query($verifySql);
    $verifyResults = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p class='info'>Showing latest 10 citations with completed payments:</p>";

    echo "<table>";
    echo "<tr>
            <th>Citation ID</th>
            <th>Ticket Number</th>
            <th>Status</th>
            <th>Payment Date</th>
            <th>Receipt Number</th>
            <th>Verification</th>
          </tr>";

    foreach ($verifyResults as $row) {
        $statusClass = $row['status'] === 'paid' ? 'success' : 'error';
        $verifyIcon = $row['status'] === 'paid' ? '✅' : '❌';

        echo "<tr>";
        echo "<td>{$row['citation_id']}</td>";
        echo "<td><strong>{$row['ticket_number']}</strong></td>";
        echo "<td class='{$statusClass}'>{$row['status']}</td>";
        echo "<td>{$row['payment_date']}</td>";
        echo "<td>{$row['receipt_number']}</td>";
        echo "<td class='{$statusClass}'>{$verifyIcon}</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo "<div class='summary-box'>";
    echo "<h3>📋 Next Steps</h3>";
    echo "<p>1. ✅ Run the manual OR entry system for new payments</p>";
    echo "<p>2. ✅ All new payments will automatically update citation status to 'paid'</p>";
    echo "<p>3. ✅ Check the <a href='/tmg/public/audit_log.php'>Audit Log</a> to see all changes</p>";
    echo "<p>4. ✅ This script only needs to be run ONCE to fix historical data</p>";
    echo "</div>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "<div class='summary-box'>";
    echo "<h3 class='error'>❌ Fatal Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<p style='margin-top: 30px;'>";
echo "<a href='/tmg/public/process_payment.php' class='btn'>Go to Process Payments</a>";
echo "<a href='/tmg/public/audit_log.php' class='btn'>View Audit Log</a>";
echo "<a href='/tmg/public/citations.php' class='btn'>View All Citations</a>";
echo "</p>";

echo "</div></body></html>";
?>
