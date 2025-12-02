<?php
/**
 * Manage Citation Status
 *
 * Admin interface for viewing and changing citation status
 * Includes payment history and audit trail for transparency
 *
 * @package TrafficCitationSystem
 * @subpackage Public
 */

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/services/PaymentService.php';
require_once ROOT_PATH . '/services/AuditService.php';

// Require login and admin access
require_login();
check_session_timeout();

if (!is_admin()) {
    set_flash('Access denied. Only administrators can manage citation status.', 'danger');
    header('Location: /tmg/public/index.php');
    exit;
}

// Validate citation ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash('Invalid citation ID.', 'danger');
    header('Location: /tmg/public/citations.php');
    exit;
}

$citationId = (int)$_GET['id'];
$pdo = getPDO();

// Get citation details
$sql = "SELECT
            c.*,
            CONCAT(c.first_name, ' ', c.last_name) as driver_name,
            u.full_name as created_by_name,
            GROUP_CONCAT(vt.violation_type SEPARATOR ', ') as violations
        FROM citations c
        LEFT JOIN users u ON c.created_by = u.user_id
        LEFT JOIN violations v ON c.citation_id = v.citation_id
        LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
        WHERE c.citation_id = :citation_id
        GROUP BY c.citation_id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':citation_id' => $citationId]);
$citation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$citation) {
    set_flash('Citation not found.', 'danger');
    header('Location: /tmg/public/citations.php');
    exit;
}

// Get payment history
$paymentService = new PaymentService($pdo);
$paymentHistory = $paymentService->getPaymentHistory($citationId);

// Get audit trail (status change history)
$auditService = new AuditService($pdo);
$statusHistory = $auditService->getCitationStatusHistory($citationId);

// Page title
$pageTitle = 'Manage Citation Status';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Traffic Citation System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-size: 16px;
            background-color: #f5f7fa;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
            background-color: #f5f7fa;
            padding: 2.5rem;
            font-size: 1.05rem;
        }

        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e6ed;
        }

        .page-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            background: white;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e0e6ed;
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .card-header h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 500;
        }

        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            border-radius: 8px;
            text-transform: capitalize;
            display: inline-block;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-contested { background: #dbeafe; color: #1e40af; }
        .status-dismissed { background: #e0e7ff; color: #4338ca; }
        .status-void { background: #fee2e2; color: #991b1b; }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
            border-left: 2px solid #e0e6ed;
            padding-left: 1.5rem;
        }

        .timeline-item:last-child {
            border-left-color: transparent;
        }

        .timeline-icon {
            position: absolute;
            left: -0.625rem;
            top: 0;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            background: #2563eb;
            border: 3px solid white;
        }

        .timeline-content {
            font-size: 0.95rem;
        }

        .timeline-date {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h2>
                    <i class="fas fa-tasks"></i> Manage Citation Status
                </h2>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php
                    echo $_SESSION['flash_message'];
                    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Citation Details -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Citation Information</h5>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Ticket Number</span>
                            <span class="info-value"><?= htmlspecialchars($citation['ticket_number']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Current Status</span>
                            <span class="status-badge status-<?= htmlspecialchars($citation['status']) ?>">
                                <?= ucfirst($citation['status']) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Driver Name</span>
                            <span class="info-value"><?= htmlspecialchars($citation['driver_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">License Number</span>
                            <span class="info-value"><?= htmlspecialchars($citation['license_number'] ?: 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Violation(s)</span>
                            <span class="info-value"><?= htmlspecialchars($citation['violations'] ?: 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Fine</span>
                            <span class="info-value">₱<?= number_format($citation['total_fine'], 2) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Apprehension Date</span>
                            <span class="info-value"><?= date('M d, Y h:i A', strtotime($citation['apprehension_datetime'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Created By</span>
                            <span class="info-value"><?= htmlspecialchars($citation['created_by_name'] ?: 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Status Form -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-edit"></i> Change Citation Status</h5>
                </div>
                <div class="card-body">
                    <form id="statusChangeForm">
                        <input type="hidden" name="citation_id" value="<?= $citationId ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">
                                    <i class="fas fa-flag"></i> New Status *
                                </label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="pending" <?= $citation['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="contested" <?= $citation['status'] === 'contested' ? 'selected' : '' ?>>Contested</option>
                                    <option value="dismissed" <?= $citation['status'] === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
                                    <option value="void" <?= $citation['status'] === 'void' ? 'selected' : '' ?>>Void</option>
                                </select>
                                <small class="text-muted">
                                    Note: To mark as "Paid", process payment through the payment system.
                                </small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="reason" class="form-label">
                                    <i class="fas fa-comment"></i> Reason for Change *
                                </label>
                                <input type="text" class="form-control" id="reason" name="reason"
                                       placeholder="Enter reason for status change" required>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                            <a href="citations.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Citations
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payment History -->
            <?php if (!empty($paymentHistory)): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Payment History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Receipt Number</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Collected By</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentHistory as $payment): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($payment['receipt_number']) ?></strong></td>
                                    <td>₱<?= number_format($payment['amount_paid'], 2) ?></td>
                                    <td><?= ucfirst($payment['payment_method']) ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($payment['payment_date'])) ?></td>
                                    <td><?= htmlspecialchars($payment['collector_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'refunded' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($payment['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Audit Trail / Status History -->
            <?php if (!empty($statusHistory)): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-clipboard-list"></i> Status Change History (Audit Trail)</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($statusHistory as $history):
                            $oldValues = json_decode($history['old_values'], true);
                            $newValues = json_decode($history['new_values'], true);
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-icon"></div>
                            <div class="timeline-content">
                                <strong><?= htmlspecialchars($history['user_name'] ?: $history['username'] ?: 'System') ?></strong>
                                changed status from
                                <span class="status-badge status-<?= htmlspecialchars($oldValues['status']) ?>">
                                    <?= ucfirst($oldValues['status']) ?>
                                </span>
                                to
                                <span class="status-badge status-<?= htmlspecialchars($newValues['status']) ?>">
                                    <?= ucfirst($newValues['status']) ?>
                                </span>
                                <?php if (!empty($newValues['reason'])): ?>
                                    <br><small class="text-muted">Reason: <?= htmlspecialchars($newValues['reason']) ?></small>
                                <?php endif; ?>
                                <div class="timeline-date">
                                    <i class="fas fa-clock"></i>
                                    <?= date('M d, Y h:i A', strtotime($history['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle status change form submission
        document.getElementById('statusChangeForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');

            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            // Submit via AJAX
            fetch('/tmg/api/update_citation_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message and reload
                    alert('Success: ' + data.message);
                    location.reload();
                } else {
                    // Show error message
                    alert('Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Status';
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Status';
            });
        });
    </script>
</body>
</html>
