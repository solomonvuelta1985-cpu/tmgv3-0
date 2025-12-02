<?php
/**
 * Fix Pending Paid Citations Tool
 *
 * Automatically fixes citations marked as "pending" but have completed payment records
 * Updates them to "paid" status to maintain data consistency
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
    header('Location: ../../public/dashboard.php');
    exit;
}

$pdo = getPDO();
$fixApplied = false;
$results = [];

// Handle fix submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_fix'])) {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Find citations to fix
        $stmt = $pdo->query("
            SELECT
                c.citation_id,
                c.ticket_number,
                CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                c.status as old_status,
                COUNT(p.payment_id) as payment_count,
                GROUP_CONCAT(p.receipt_number ORDER BY p.created_at SEPARATOR ', ') as receipt_numbers
            FROM citations c
            INNER JOIN payments p ON c.citation_id = p.citation_id
            WHERE c.status = 'pending'
                AND p.status = 'completed'
                AND c.deleted_at IS NULL
            GROUP BY c.citation_id
        ");

        $citations_to_fix = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($citations_to_fix) === 0) {
            $results = [
                'success' => true,
                'message' => 'No citations to fix. All data is consistent!',
                'fixed_count' => 0,
                'citations' => []
            ];
        } else {
            // Update citations to paid
            $citation_ids = array_column($citations_to_fix, 'citation_id');
            $placeholders = implode(',', array_fill(0, count($citation_ids), '?'));

            $stmt = $pdo->prepare("
                UPDATE citations
                SET status = 'paid', updated_at = NOW()
                WHERE citation_id IN ($placeholders)
            ");

            $stmt->execute($citation_ids);

            // Log each fix to audit trail
            $stmt_audit = $pdo->prepare("
                INSERT INTO audit_log (
                    user_id,
                    action,
                    table_name,
                    record_id,
                    old_values,
                    new_values,
                    ip_address,
                    created_at
                ) VALUES (
                    :user_id,
                    'fix_inconsistency',
                    'citations',
                    :citation_id,
                    :old_values,
                    :new_values,
                    :ip_address,
                    NOW()
                )
            ");

            foreach ($citations_to_fix as $citation) {
                $stmt_audit->execute([
                    ':user_id' => $_SESSION['user_id'],
                    ':citation_id' => $citation['citation_id'],
                    ':old_values' => json_encode(['status' => 'pending']),
                    ':new_values' => json_encode(['status' => 'paid', 'reason' => 'Automated fix - had completed payments']),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            }

            // Commit transaction
            $pdo->commit();

            $results = [
                'success' => true,
                'message' => 'Successfully fixed ' . count($citations_to_fix) . ' citation(s)!',
                'fixed_count' => count($citations_to_fix),
                'citations' => $citations_to_fix
            ];

            // Log to error log
            error_log("Fixed pending-paid citations inconsistency: " . count($citations_to_fix) . " citations updated by user ID " . $_SESSION['user_id']);
        }

        $fixApplied = true;

    } catch (Exception $e) {
        $pdo->rollBack();
        $results = [
            'success' => false,
            'message' => 'Error occurred: ' . $e->getMessage(),
            'fixed_count' => 0,
            'citations' => []
        ];
        error_log("Fix pending-paid citations error: " . $e->getMessage());
    }
}

// Get current status (before or after fix)
$stmt = $pdo->query("
    SELECT
        c.citation_id,
        c.ticket_number,
        CONCAT(c.first_name, ' ', c.last_name) as driver_name,
        c.status as citation_status,
        c.total_fine,
        COUNT(p.payment_id) as payment_count,
        GROUP_CONCAT(p.receipt_number ORDER BY p.created_at SEPARATOR ', ') as receipt_numbers,
        SUM(p.amount_paid) as total_paid
    FROM citations c
    INNER JOIN payments p ON c.citation_id = p.citation_id
    WHERE c.status = 'pending'
        AND p.status = 'completed'
        AND c.deleted_at IS NULL
    GROUP BY c.citation_id
");

$affected_citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Pending-Paid Citations - TMG Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .fix-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .step-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }
        .before-after {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin: 0 5px;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="fix-container">
        <!-- Header -->
        <div class="section-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-wrench"></i> Fix Pending-Paid Citations</h2>
                    <p class="text-muted mb-0">Automatically fix citations with status inconsistencies</p>
                </div>
                <div>
                    <a href="../diagnostics/investigate_citation_payment_inconsistency.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search"></i> Investigation Tool
                    </a>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Results (if fix was applied) -->
        <?php if ($fixApplied && isset($results)): ?>
            <div class="section-card">
                <?php if ($results['success']): ?>
                    <div class="alert alert-success">
                        <h4><i class="fas fa-check-circle"></i> <?= htmlspecialchars($results['message']) ?></h4>
                        <?php if ($results['fixed_count'] > 0): ?>
                            <p class="mb-0">
                                The following citations have been updated from
                                <span class="before-after status-pending">PENDING</span>
                                to
                                <span class="before-after status-paid">PAID</span>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($results['fixed_count'] > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Citation ID</th>
                                        <th>Ticket #</th>
                                        <th>Driver Name</th>
                                        <th>Payment Count</th>
                                        <th>Receipt Numbers</th>
                                        <th>Status Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['citations'] as $citation): ?>
                                        <tr>
                                            <td><?= $citation['citation_id'] ?></td>
                                            <td><?= htmlspecialchars($citation['ticket_number']) ?></td>
                                            <td><?= htmlspecialchars($citation['driver_name']) ?></td>
                                            <td><?= $citation['payment_count'] ?></td>
                                            <td style="font-size: 0.85em;"><?= htmlspecialchars($citation['receipt_numbers']) ?></td>
                                            <td>
                                                <span class="before-after status-pending">PENDING</span>
                                                <i class="fas fa-arrow-right"></i>
                                                <span class="before-after status-paid">PAID</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info mt-3">
                        <h5><i class="fas fa-info-circle"></i> What Happened?</h5>
                        <ol class="mb-0">
                            <li>Identified citations with status="pending" but have completed payment records</li>
                            <li>Updated their status to "paid" to match the payment reality</li>
                            <li>Logged all changes to the audit trail for compliance</li>
                            <li>System is now consistent!</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <h4><i class="fas fa-times-circle"></i> Fix Failed</h4>
                        <p class="mb-0"><?= htmlspecialchars($results['message']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Current Status -->
        <div class="section-card">
            <h4 class="mb-4"><i class="fas fa-clipboard-list"></i> Current Status</h4>

            <?php if (count($affected_citations) === 0): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle fa-3x mb-3 d-block"></i>
                    <h4>No Issues Found!</h4>
                    <p class="mb-0">All citations with completed payments have the correct "paid" status. Your system is consistent.</p>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Data Inconsistency Detected</h5>
                    <p class="mb-2">Found <strong><?= count($affected_citations) ?></strong> citation(s) with the following issue:</p>
                    <ul class="mb-0">
                        <li>Citation status is "pending"</li>
                        <li>But citation has completed payment record(s)</li>
                        <li>This creates confusion when trying to delete the citation</li>
                    </ul>
                </div>

                <div class="step-box">
                    <span class="step-number">1</span>
                    <strong>Review the affected citations below</strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Citation ID</th>
                                <th>Ticket Number</th>
                                <th>Driver Name</th>
                                <th>Current Status</th>
                                <th>Total Fine</th>
                                <th>Payment Count</th>
                                <th>Receipt Numbers</th>
                                <th>Total Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($affected_citations as $citation): ?>
                                <tr>
                                    <td><?= $citation['citation_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($citation['ticket_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($citation['driver_name']) ?></td>
                                    <td><span class="before-after status-pending"><?= strtoupper($citation['citation_status']) ?></span></td>
                                    <td>₱<?= number_format($citation['total_fine'], 2) ?></td>
                                    <td><?= $citation['payment_count'] ?></td>
                                    <td style="font-size: 0.85em;"><?= htmlspecialchars($citation['receipt_numbers']) ?></td>
                                    <td>₱<?= number_format($citation['total_paid'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="step-box">
                    <span class="step-number">2</span>
                    <strong>Apply the automatic fix</strong>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-magic"></i> What This Fix Will Do:</h5>
                        <ol>
                            <li>Update citation status from <span class="before-after status-pending">PENDING</span> to <span class="before-after status-paid">PAID</span></li>
                            <li>Update the <code>updated_at</code> timestamp</li>
                            <li>Log the change to the audit trail</li>
                            <li>Preserve all payment records (no data loss)</li>
                        </ol>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to fix these <?= count($affected_citations) ?> citation(s)?');">
                            <input type="hidden" name="csrf_token" value="<?= generate_token() ?>">
                            <div class="d-grid gap-2">
                                <button type="submit" name="apply_fix" class="btn btn-primary btn-lg">
                                    <i class="fas fa-wrench"></i> Apply Fix Now (<?= count($affected_citations) ?> citations)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="step-box">
                    <span class="step-number">3</span>
                    <strong>Verify the fix was successful</strong>
                </div>

                <p class="text-muted">After applying the fix, this page will reload and show the results. You can also verify in the <a href="../diagnostics/data_integrity_dashboard.php">Data Integrity Dashboard</a>.</p>
            <?php endif; ?>
        </div>

        <!-- Information -->
        <div class="section-card">
            <h4 class="mb-3"><i class="fas fa-info-circle"></i> Why This Issue Happens</h4>

            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                            What causes pending citations to have completed payments?
                        </button>
                    </h2>
                    <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>This can happen when:</p>
                            <ul>
                                <li><strong>Payment finalization fails:</strong> The payment completes but the citation status update fails due to a transient database error</li>
                                <li><strong>Race conditions:</strong> Multiple payment processes updating the same citation simultaneously</li>
                                <li><strong>Manual intervention:</strong> Someone manually changes the citation status back to pending after payment</li>
                                <li><strong>System crashes:</strong> Server crashes between payment completion and citation update</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                            Is it safe to run this fix?
                        </button>
                    </h2>
                    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p><strong>Yes, this fix is completely safe because:</strong></p>
                            <ul>
                                <li>It only updates the citation status field</li>
                                <li>All payment records are preserved</li>
                                <li>Changes are logged to the audit trail</li>
                                <li>The fix runs in a database transaction (rolls back on error)</li>
                                <li>No financial data is modified</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                            How can I prevent this in the future?
                        </button>
                    </h2>
                    <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p><strong>Prevention measures:</strong></p>
                            <ol>
                                <li>Run the <a href="../database/run_consistency_migration.php">database migration</a> to install protective triggers</li>
                                <li>The enhanced PaymentProcessor now includes retry logic to handle transient errors</li>
                                <li>Schedule the <a href="../diagnostics/automated_consistency_checker.php">automated checker</a> to run daily</li>
                                <li>Monitor the <a href="../diagnostics/data_integrity_dashboard.php">data integrity dashboard</a> regularly</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
