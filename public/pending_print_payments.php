<?php
/**
 * Pending Print Payments Management
 *
 * Shows all payments in 'pending_print' status and allows cashier to finalize or void them
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

// Require authentication and check session timeout
require_login();
check_session_timeout();

// Require cashier or admin privileges
if (!can_process_payment()) {
    set_flash('Access denied. Only cashiers can manage pending payments.', 'danger');
    header('Location: /tmg/public/index.php');
    exit;
}

// Page title
$pageTitle = 'Pending Print Payments';

// Get all pending_print payments
$pdo = getPDO();

// Check if database connection failed
if ($pdo === null) {
    // Set error message
    set_flash('Database connection failed. Please check if MySQL is running and try again.', 'danger');
    $pendingPayments = [];
} else {
    try {
        // Explicitly cast payment_id and use STRAIGHT_JOIN to ensure proper execution
        $sql = "SELECT
                    CAST(p.payment_id AS SIGNED) as payment_id,
                    p.receipt_number,
                    p.amount_paid,
                    p.payment_method,
                    p.payment_date,
                    p.status,
                    c.citation_id,
                    c.ticket_number,
                    c.status as citation_status,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    u.full_name as collected_by_name
                FROM payments p
                INNER JOIN citations c ON p.citation_id = c.citation_id
                INNER JOIN users u ON p.collected_by = u.user_id
                WHERE p.status = 'pending_print'
                  AND p.payment_id > 0
                ORDER BY p.payment_date DESC";

        $stmt = $pdo->query($sql);

        if (!$stmt) {
            $errorInfo = $pdo->errorInfo();
            throw new Exception("SQL Error: " . $errorInfo[2]);
        }

        $allPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter out any payments with invalid payment_id (extra safety check)
        $pendingPayments = array_filter($allPayments, function($payment) {
            $isValid = isset($payment['payment_id']) && $payment['payment_id'] > 0;
            if (!$isValid) {
                error_log("CRITICAL: Filtered out invalid payment with ID={$payment['payment_id']}, OR={$payment['receipt_number']}");
            }
            return $isValid;
        });

        // Debug: Log payment IDs for troubleshooting
        $invalidCount = count($allPayments) - count($pendingPayments);
        if ($invalidCount > 0) {
            error_log("WARNING: Filtered out {$invalidCount} invalid payment(s) with payment_id <= 0");
        }

        if (!empty($pendingPayments)) {
            error_log("Pending payments loaded: " . count($pendingPayments) . " valid records");
        } else {
            error_log("No valid pending payments found");
        }

    } catch (Exception $e) {
        error_log("Error fetching pending payments: " . $e->getMessage());
        set_flash('Error loading pending payments: ' . $e->getMessage(), 'danger');
        $pendingPayments = [];
    }
}
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
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/pending-print-payments.css">
    <style>
        /* Page-specific overrides */

        /* Additional page-specific styles */

        /* Action Buttons */
        .btn-group {
            position: static !important;
        }

        .btn-group .btn {
            margin: 0;
        }

        .dropdown-toggle::after {
            margin-left: 0.5em;
        }

        .dropdown-menu {
            border: 1px solid #dee2e6;
            border-radius: 3px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: absolute !important;
            z-index: 1050;
            min-width: 200px;
        }

        .dropdown-item {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            transition: background-color 0.15s;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-item i {
            width: 1.25rem;
            margin-right: 0.5rem;
        }

        /* Fix table overflow for dropdowns */
        .table-responsive {
            overflow: visible !important;
        }

        .card-body {
            overflow: visible !important;
        }

        /* Modal Styles */
        .modern-modal {
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .gradient-header {
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.25rem;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-text {
            flex: 1;
        }

        .header-text .modal-title {
            color: #212529;
            font-size: 1.125rem;
            font-weight: 500;
            margin: 0;
        }

        .header-text .modal-title i {
            color: #6c757d;
        }

        .header-text small {
            display: block;
            font-size: 0.8125rem;
            color: #6c757d;
            margin-top: 0.25rem;
            font-weight: normal;
        }

        /* Receipt Preview */
        .receipt-preview-wrapper {
            background: #f8f9fa;
            min-height: 500px;
        }

        .preview-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
        }

        .toolbar-info {
            display: flex;
            align-items: center;
            font-weight: 400;
            color: #495057;
            font-size: 0.8125rem;
        }

        .toolbar-info i {
            color: #6c757d;
        }

        .receipt-container {
            padding: 1.25rem;
            display: flex;
            justify-content: center;
            background: #f8f9fa;
        }

        .receipt-iframe {
            width: 100%;
            max-width: 800px;
            min-height: 600px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 3px;
        }

        /* Modal Footer */
        .modern-footer {
            background: #ffffff;
            border-top: 1px solid #dee2e6;
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .footer-instructions {
            color: #6c757d;
            font-size: 0.8125rem;
        }

        .footer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .footer-actions .btn {
            padding: 0.5rem 1rem;
            font-weight: 400;
            font-size: 0.8125rem;
            border-radius: 3px;
            border: 1px solid;
            transition: all 0.15s;
        }

        .footer-actions .btn-light {
            background: #ffffff;
            border-color: #dee2e6;
            color: #495057;
        }

        .footer-actions .btn-light:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
        }

        .footer-actions .btn-primary {
            background: #495057;
            border-color: #495057;
            color: white;
        }

        .footer-actions .btn-primary:hover {
            background: #343a40;
            border-color: #343a40;
        }

        .footer-actions .btn-success {
            background: #5a6268;
            border-color: #5a6268;
            color: white;
        }

        .footer-actions .btn-success:hover {
            background: #4e555b;
            border-color: #4e555b;
        }

        .footer-actions .btn-danger {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
        }

        .footer-actions .btn-danger:hover {
            background: #5a6268;
            border-color: #5a6268;
        }

        .footer-actions .btn-warning {
            background: #868e96;
            border-color: #868e96;
            color: white;
        }

        .footer-actions .btn-warning:hover {
            background: #6c757d;
            border-color: #6c757d;
        }

        /* Payment Details Card */
        .payment-details-card {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .payment-details-card h6 {
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.875rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .payment-details-card h6 i {
            color: #6c757d;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.625rem 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 0.8125rem;
            color: #6c757d;
            font-weight: normal;
        }

        .detail-value {
            font-size: 0.8125rem;
            color: #212529;
            font-weight: 400;
        }

        .detail-value.or-number {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: #495057;
            background: #f8f9fa;
            padding: 0.25rem 0.625rem;
            border-radius: 3px;
            border: 1px solid #dee2e6;
        }

        /* Custom Modal */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-modal-content {
            background: white;
            border-radius: 4px;
            width: 90%;
            max-width: 500px;
            border: 1px solid #dee2e6;
        }

        .custom-modal-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
        }

        .custom-modal-header h3 {
            margin: 0;
            font-size: 1.125rem;
            color: #212529;
            font-weight: 500;
        }

        .custom-modal-header h3 i {
            color: #6c757d;
            margin-right: 0.5rem;
        }

        .custom-close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px;
            transition: all 0.15s;
        }

        .custom-close-btn:hover {
            background: #f8f9fa;
            color: #495057;
        }

        .custom-modal-body {
            padding: 1.25rem;
        }

        .current-or-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .warning-box {
            background: #fff8e6;
            border: 1px solid #ffeaa7;
            border-left: 3px solid #856404;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.8125rem;
            border-radius: 3px;
            color: #856404;
        }

        .warning-box i {
            color: #856404;
        }

        .input-group-custom {
            margin-bottom: 1rem;
        }

        .input-group-custom label {
            display: block;
            margin-bottom: 0.5rem;
            color: #495057;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .custom-input {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-family: 'Courier New', monospace;
            font-weight: 500;
            text-align: center;
            border: 1px solid #ced4da;
            border-radius: 3px;
            text-transform: uppercase;
            transition: all 0.15s;
        }

        .custom-input:focus {
            outline: none;
            border-color: #495057;
            box-shadow: 0 0 0 0.2rem rgba(73, 80, 87, 0.1);
        }

        .custom-input::placeholder {
            text-transform: none;
            font-weight: normal;
            opacity: 0.5;
        }

        .custom-modal-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            background: #ffffff;
        }

        .custom-btn {
            padding: 0.5rem 1rem;
            border: 1px solid transparent;
            border-radius: 3px;
            font-size: 0.875rem;
            font-weight: 400;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .custom-btn-secondary {
            background: #ffffff;
            color: #495057;
            border-color: #dee2e6;
        }

        .custom-btn-secondary:hover {
            background: #f8f9fa;
        }

        .custom-btn-primary {
            background: #495057;
            color: white;
            border-color: #495057;
        }

        .custom-btn-primary:hover {
            background: #343a40;
            border-color: #343a40;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                padding: 1rem;
            }

            .modern-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .footer-instructions {
                text-align: center;
                margin-bottom: 0.5rem;
            }

            .footer-actions {
                flex-direction: column;
                width: 100%;
            }

            .footer-actions .btn {
                width: 100%;
            }

            .receipt-container {
                padding: 0.75rem;
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
                <h2>Pending Print Payments</h2>
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

            <!-- Info Box -->
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle"></i>
                <strong>What are Pending Print Payments?</strong><br>
                These are payments that were recorded but never confirmed as printed. This can happen if:
                <ul class="mb-0 mt-2">
                    <li>The page was closed before confirming the print</li>
                    <li>The printer jammed and the cashier didn't complete the process</li>
                    <li>There was a system error during confirmation</li>
                    <li>Wrong amount or OR number was entered</li>
                </ul>
                <strong>You can:</strong> View the receipt, print & finalize, change OR number, or <strong>cancel</strong> the payment (which frees the OR number for reuse).
            </div>

            <!-- Pending Payments Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payments Waiting for Confirmation (<?= count($pendingPayments) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingPayments)): ?>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i> No pending print payments. All payments are finalized!
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>OR Number</th>
                                        <th>Ticket Number</th>
                                        <th>Driver</th>
                                        <th>Amount</th>
                                        <th>Payment Date</th>
                                        <th>Collected By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingPayments as $payment):
                                        // Calculate payment age in hours
                                        $paymentTime = strtotime($payment['payment_date']);
                                        $currentTime = time();
                                        $hoursOld = ($currentTime - $paymentTime) / 3600;

                                        // Determine row class based on age
                                        $rowClass = '';
                                        $ageText = '';
                                        if ($hoursOld > 24) {
                                            $rowClass = 'payment-age-old';
                                            $daysOld = floor($hoursOld / 24);
                                            $ageText = $daysOld . ' day' . ($daysOld > 1 ? 's' : '') . ' old';
                                        } elseif ($hoursOld > 4) {
                                            $rowClass = 'payment-age-warning';
                                            $ageText = floor($hoursOld) . ' hours old';
                                        }
                                    ?>
                                        <tr class="<?= $rowClass ?>"
                                            data-payment-id="<?= $payment['payment_id'] ?>"
                                            data-receipt-number="<?= htmlspecialchars($payment['receipt_number']) ?>"
                                            data-ticket-number="<?= htmlspecialchars($payment['ticket_number']) ?>"
                                            data-driver-name="<?= htmlspecialchars($payment['driver_name']) ?>"
                                            data-amount="<?= $payment['amount_paid'] ?>"
                                            data-payment-method="<?= htmlspecialchars($payment['payment_method']) ?>"
                                            data-payment-date="<?= date('M d, Y H:i', strtotime($payment['payment_date'])) ?>"
                                            data-collected-by="<?= htmlspecialchars($payment['collected_by_name']) ?>"
                                        >
                                            <td>
                                                <strong>#<?= isset($payment['payment_id']) ? $payment['payment_id'] : 'NOT_SET' ?></strong>
                                                <!-- Debug: <?= var_export($payment['payment_id'] ?? 'NULL', true) ?> -->
                                            </td>
                                            <td>
                                                <span style="font-family: 'Courier New', monospace; font-weight: bold;">
                                                    <?= htmlspecialchars($payment['receipt_number']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($payment['ticket_number']) ?></td>
                                            <td><?= htmlspecialchars($payment['driver_name']) ?></td>
                                            <td>
                                                <strong class="text-success">
                                                    ₱<?= number_format($payment['amount_paid'], 2) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?= date('M d, Y H:i', strtotime($payment['payment_date'])) ?>
                                                <?php if ($ageText): ?>
                                                    <br><small class="text-muted"><i class="fas fa-clock"></i> <?= $ageText ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($payment['collected_by_name']) ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button
                                                        class="btn btn-sm btn-primary"
                                                        onclick="viewReceiptModal(this.closest('tr'))"
                                                        title="View Receipt"
                                                    >
                                                        <i class="fas fa-file-invoice"></i> View
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false"
                                                    >
                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); finalizePayment(<?= $payment['payment_id'] ?>, '<?= $payment['receipt_number'] ?>')">
                                                                <i class="fas fa-check text-success"></i> Finalize Payment
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); printReceipt('<?= $payment['receipt_number'] ?>')">
                                                                <i class="fas fa-print text-primary"></i> Print Receipt
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); changeORNumber(<?= $payment['payment_id'] ?>, '<?= $payment['receipt_number'] ?>')">
                                                                <i class="fas fa-edit text-warning"></i> Change OR Number
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" onclick="event.preventDefault(); cancelPayment(<?= $payment['payment_id'] ?>, '<?= $payment['receipt_number'] ?>')">
                                                                <i class="fas fa-ban"></i> Cancel Payment
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CUSTOM VANILLA JS MODAL - NO CONFLICTS! -->
    <div id="customORModal" class="custom-modal-overlay" style="display: none;">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h3><i class="fas fa-edit"></i> Change OR Number</h3>
                <button class="custom-close-btn" onclick="closeCustomORModal()">&times;</button>
            </div>
            <div class="custom-modal-body">
                <div class="current-or-display">
                    <strong>Current OR:</strong>
                    <span id="customCurrentOR" style="font-family: 'Courier New', monospace; color: #2563eb; font-weight: bold; font-size: 1.1rem;"></span>
                </div>
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i> <strong>When to use:</strong><br>
                    Printer jammed • Receipt torn • Need new receipt booklet
                </div>
                <div class="input-group-custom">
                    <label for="customNewOR"><strong>Enter New OR Number:</strong></label>
                    <input
                        type="text"
                        id="customNewOR"
                        class="custom-input"
                        placeholder="e.g., CGVM15320502"
                        autocomplete="off"
                    >
                </div>
            </div>
            <div class="custom-modal-footer">
                <button class="custom-btn custom-btn-secondary" onclick="closeCustomORModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="custom-btn custom-btn-primary" onclick="submitCustomORChange()">
                    <i class="fas fa-check"></i> Update OR Number
                </button>
            </div>
        </div>
    </div>

    <!-- Receipt Preview Modal -->
    <div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-labelledby="receiptPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content modern-modal">
                <!-- Simple Header -->
                <div class="modal-header gradient-header">
                    <div class="header-content">
                        <div class="header-text">
                            <h5 class="modal-title" id="receiptPreviewModalLabel">
                                <i class="fas fa-receipt me-2" style="color: #6c757d;"></i>Receipt Preview
                            </h5>
                            <small>OR #<span id="modal_or_number"></span> | Ticket #<span id="modal_ticket_num"></span></small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Column: Payment Details -->
                        <div class="col-md-4 p-3" style="background: #fafafa; border-right: 1px solid #dee2e6;">
                            <div class="payment-details-card">
                                <h6><i class="fas fa-info-circle" style="color: #6c757d;"></i> Payment Details</h6>
                                <div class="detail-row">
                                    <span class="detail-label">Payment ID</span>
                                    <span class="detail-value">#<span id="detail_payment_id"></span></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">OR Number</span>
                                    <span class="detail-value or-number" id="detail_or_number"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Amount Paid</span>
                                    <span class="detail-value text-success">₱<span id="detail_amount"></span></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Payment Method</span>
                                    <span class="detail-value text-capitalize" id="detail_payment_method"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Payment Date</span>
                                    <span class="detail-value" id="detail_payment_date"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Collected By</span>
                                    <span class="detail-value" id="detail_collected_by"></span>
                                </div>
                            </div>

                            <div class="payment-details-card">
                                <h6><i class="fas fa-user" style="color: #6c757d;"></i> Driver Information</h6>
                                <div class="detail-row">
                                    <span class="detail-label">Ticket Number</span>
                                    <span class="detail-value" id="detail_ticket_number"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Driver Name</span>
                                    <span class="detail-value" id="detail_driver_name"></span>
                                </div>
                            </div>

                            <div class="alert alert-warning mb-0" style="font-size: 0.85rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 12px;">
                                <i class="fas fa-exclamation-circle"></i>
                                <strong>Status:</strong> Pending print confirmation
                            </div>
                        </div>

                        <!-- Right Column: Receipt Preview -->
                        <div class="col-md-8">
                            <div class="receipt-preview-wrapper">
                                <div class="preview-toolbar">
                                    <div class="toolbar-info">
                                        <i class="fas fa-file-invoice me-2"></i>
                                        <span>Official Receipt Preview</span>
                                    </div>
                                </div>
                                <div class="receipt-container">
                                    <iframe id="receiptIframe" class="receipt-iframe"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Simple Footer with Action Buttons -->
                <div class="modal-footer modern-footer">
                    <div class="footer-instructions">
                        Review the payment details and choose an action
                    </div>
                    <div class="footer-actions">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Close
                        </button>
                        <button type="button" class="btn btn-warning" onclick="changeORNumberFromModal()">
                            <i class="fas fa-edit"></i> Change OR
                        </button>
                        <button type="button" class="btn btn-primary" onclick="printReceiptFromModal()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn btn-success" onclick="printAndFinalize()">
                            <i class="fas fa-check-circle"></i> Print & Finalize
                        </button>
                        <button type="button" class="btn btn-danger" onclick="cancelFromModal()">
                            <i class="fas fa-ban"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change OR Number Modal (Bootstrap) -->
    <div class="modal fade" id="changeORModal" tabindex="-1" aria-labelledby="changeORModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: #ffffff; border-bottom: 2px solid #e5e7eb;">
                    <h5 class="modal-title" id="changeORModalLabel">
                        <i class="fas fa-edit text-warning"></i> Change OR Number
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="background: #f8fafc;">
                    <!-- Current OR Display -->
                    <div class="alert alert-info mb-3" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px;">
                        <strong>Current OR:</strong>
                        <span id="currentORDisplay" style="font-family: 'Courier New', monospace; font-size: 1.1rem; color: #2563eb; font-weight: bold;"></span>
                    </div>

                    <!-- Warning Message -->
                    <div class="alert alert-warning mb-3" style="background: #fef3c7; border-left: 4px solid #f59e0b; font-size: 0.9rem;">
                        <i class="fas fa-exclamation-triangle"></i> <strong>When to use:</strong><br>
                        Printer jammed • Receipt torn • Need new receipt booklet
                    </div>

                    <!-- New OR Input -->
                    <form id="changeORForm">
                        <input type="hidden" id="changeOR_paymentId">
                        <input type="hidden" id="changeOR_currentOR">

                        <div class="mb-3">
                            <label for="newORInput" class="form-label fw-bold">
                                <i class="fas fa-receipt text-primary"></i> Enter New OR Number
                            </label>
                            <input
                                type="text"
                                class="form-control form-control-lg"
                                id="newORInput"
                                placeholder="e.g., CGVM15320502"
                                autocomplete="off"
                                autocapitalize="characters"
                                autofocus
                                tabindex="1"
                                style="font-family: 'Courier New', monospace; font-weight: bold; font-size: 1.2rem; text-align: center;"
                                required
                            >
                            <div class="form-text">
                                <i class="fas fa-info-circle text-primary"></i> Enter the new OR number from your receipt booklet
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="background: #ffffff; border-top: 2px solid #e5e7eb;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-warning" onclick="confirmORChange()">
                        <i class="fas fa-check"></i> Update OR Number
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
        let currentPaymentData = {};

        // Auto-uppercase as user types in custom modal
        document.addEventListener('DOMContentLoaded', function() {
            const customInput = document.getElementById('customNewOR');

            // Auto-uppercase
            customInput.addEventListener('input', function() {
                const cursorPos = this.selectionStart;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(cursorPos, cursorPos);
            });

            // Submit on Enter key
            customInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitCustomORChange();
                }
            });

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('customORModal');
                    if (modal.style.display === 'flex') {
                        closeCustomORModal();
                    }
                }
            });

            // Close when clicking overlay (not the modal content)
            document.getElementById('customORModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCustomORModal();
                }
            });
        });

        /**
         * Validate payment ID before any operation
         */
        function validatePaymentId(paymentId, operation) {
            const id = parseInt(paymentId);
            if (isNaN(id) || id <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Payment',
                    html: `<div style="text-align: left;">
                           <p>This payment has an invalid ID: <strong>${paymentId}</strong></p>
                           <div class="alert alert-danger">
                               <i class="fas fa-exclamation-triangle"></i>
                               <strong>Data Integrity Error</strong><br>
                               This payment record is corrupted and cannot be processed.
                           </div>
                           <p>Please contact your system administrator to resolve this issue.</p>
                           </div>`,
                    confirmButtonColor: '#dc2626'
                });
                console.error(`Invalid payment_id=${paymentId} for operation: ${operation}`);
                return false;
            }
            return true;
        }

        /**
         * View receipt in modal with payment details
         */
        function viewReceiptModal(row) {
            // Get data from table row
            currentPaymentData = {
                paymentId: row.dataset.paymentId,
                receiptNumber: row.dataset.receiptNumber,
                ticketNumber: row.dataset.ticketNumber,
                driverName: row.dataset.driverName,
                amount: row.dataset.amount,
                paymentMethod: row.dataset.paymentMethod,
                paymentDate: row.dataset.paymentDate,
                collectedBy: row.dataset.collectedBy
            };

            // Validate payment ID
            if (!validatePaymentId(currentPaymentData.paymentId, 'view receipt')) {
                return;
            }

            // Populate modal header
            document.getElementById('modal_or_number').textContent = currentPaymentData.receiptNumber;
            document.getElementById('modal_ticket_num').textContent = currentPaymentData.ticketNumber;

            // Populate payment details
            document.getElementById('detail_payment_id').textContent = currentPaymentData.paymentId;
            document.getElementById('detail_or_number').textContent = currentPaymentData.receiptNumber;
            document.getElementById('detail_amount').textContent = parseFloat(currentPaymentData.amount).toFixed(2);
            document.getElementById('detail_payment_method').textContent = currentPaymentData.paymentMethod;
            document.getElementById('detail_payment_date').textContent = currentPaymentData.paymentDate;
            document.getElementById('detail_collected_by').textContent = currentPaymentData.collectedBy;

            // Populate driver information
            document.getElementById('detail_ticket_number').textContent = currentPaymentData.ticketNumber;
            document.getElementById('detail_driver_name').textContent = currentPaymentData.driverName;

            // Load receipt in iframe
            const iframe = document.getElementById('receiptIframe');
            iframe.src = '/tmg/public/receipt.php?receipt=' + currentPaymentData.receiptNumber;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
            modal.show();
        }

        /**
         * Print receipt from modal (print only, don't finalize) - NATIVE print dialog
         */
        function printReceiptFromModal() {
            // Use the direct print function for native dialog
            printReceiptDirectly(currentPaymentData.receiptNumber);
        }

        /**
         * Print and finalize payment - NATIVE print dialog
         */
        function printAndFinalize() {
            // Validate payment ID first
            if (!validatePaymentId(currentPaymentData.paymentId, 'print and finalize')) {
                return;
            }

            Swal.fire({
                title: 'Print & Finalize?',
                html: `This will:<br>
                       1. Open <strong>NATIVE print dialog</strong> for OR <strong>${currentPaymentData.receiptNumber}</strong><br>
                       2. Mark payment as completed<br>
                       3. Update citation status to "paid"<br><br>
                       <strong>Please ensure the receipt prints successfully!</strong>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check-circle"></i> Print & Finalize',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#059669',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Print receipt using NATIVE print dialog
                    printReceiptDirectly(currentPaymentData.receiptNumber);

                    // Small delay to allow print dialog to open
                    setTimeout(() => {
                        // Finalize payment
                        finalizePaymentAPI(currentPaymentData.paymentId, currentPaymentData.receiptNumber);
                    }, 1000);
                }
            });
        }

        /**
         * Cancel payment from modal
         */
        function cancelFromModal() {
            // Close the receipt modal first
            const modal = bootstrap.Modal.getInstance(document.getElementById('receiptPreviewModal'));
            modal.hide();

            // Small delay before showing cancel confirmation
            setTimeout(() => {
                cancelPayment(currentPaymentData.paymentId, currentPaymentData.receiptNumber);
            }, 300);
        }

        /**
         * Print receipt (for dropdown menu) - opens in new tab
         */
        function printReceipt(receiptNumber) {
            window.open('/tmg/public/receipt.php?receipt=' + receiptNumber, '_blank');
        }

        /**
         * Print receipt directly - opens NATIVE print dialog
         */
        function printReceiptDirectly(receiptNumber) {
            // Create a hidden iframe
            const iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.top = '-10000px';
            iframe.style.left = '-10000px';
            iframe.style.width = '1px';
            iframe.style.height = '1px';
            iframe.style.border = 'none';

            // Add to document
            document.body.appendChild(iframe);

            // Wait for iframe to load, then trigger print
            iframe.onload = function() {
                setTimeout(() => {
                    try {
                        const iframeWindow = iframe.contentWindow;

                        // Listen for when printing is done (user closes print dialog)
                        iframeWindow.onafterprint = function() {
                            console.log('Print dialog closed - cleaning up iframe');
                            // Remove iframe after user finishes printing
                            setTimeout(() => {
                                if (document.body.contains(iframe)) {
                                    document.body.removeChild(iframe);
                                }
                            }, 500);
                        };

                        // Also listen on the main window (some browsers)
                        const afterPrintHandler = function() {
                            console.log('Print dialog closed (main window)');
                            setTimeout(() => {
                                if (document.body.contains(iframe)) {
                                    document.body.removeChild(iframe);
                                }
                            }, 500);
                            window.removeEventListener('afterprint', afterPrintHandler);
                        };
                        window.addEventListener('afterprint', afterPrintHandler);

                        // Focus the iframe
                        iframeWindow.focus();

                        // Trigger native print dialog
                        iframeWindow.print();

                        console.log('Print dialog opened');

                    } catch (error) {
                        console.error('Print error:', error);
                        // Fallback: open in new tab if print fails
                        window.open('/tmg/public/receipt.php?receipt=' + receiptNumber, '_blank');
                        if (document.body.contains(iframe)) {
                            document.body.removeChild(iframe);
                        }
                    }
                }, 500);
            };

            // Load the receipt
            iframe.src = '/tmg/public/receipt.php?receipt=' + receiptNumber;
        }

        // VANILLA JS - NO LIBRARIES, PURE CONTROL!
        let customModalData = {
            paymentId: null,
            currentOR: null
        };

        /**
         * Open Custom OR Modal - Pure Vanilla JS
         */
        function changeORNumber(paymentId, currentORNumber) {
            // Validate payment ID first
            if (!validatePaymentId(paymentId, 'change OR number')) {
                return;
            }

            // Close receipt preview modal if open
            const receiptModal = bootstrap.Modal.getInstance(document.getElementById('receiptPreviewModal'));
            if (receiptModal) {
                receiptModal.hide();
            }

            // Clean up any Bootstrap remnants
            setTimeout(() => {
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';

                // Store data
                customModalData.paymentId = paymentId;
                customModalData.currentOR = currentORNumber;

                // Update modal content
                document.getElementById('customCurrentOR').textContent = currentORNumber;
                document.getElementById('customNewOR').value = '';

                // Show custom modal
                document.getElementById('customORModal').style.display = 'flex';

                // Focus input after a brief delay
                setTimeout(() => {
                    const input = document.getElementById('customNewOR');
                    input.focus();
                    input.select();
                }, 100);
            }, 300);
        }

        /**
         * Close Custom Modal
         */
        function closeCustomORModal() {
            document.getElementById('customORModal').style.display = 'none';
            document.getElementById('customNewOR').value = '';
            customModalData = { paymentId: null, currentOR: null };
        }

        /**
         * Submit OR Change from Custom Modal
         */
        function submitCustomORChange() {
            const newOR = document.getElementById('customNewOR').value.trim().toUpperCase();
            const currentOR = customModalData.currentOR;
            const paymentId = customModalData.paymentId;

            // Validation
            if (!newOR) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please enter a new OR number'
                });
                document.getElementById('customNewOR').focus();
                return;
            }

            if (newOR === currentOR) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'New OR number must be different from current OR number'
                });
                document.getElementById('customNewOR').focus();
                return;
            }

            if (newOR.length < 5) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warning',
                    text: 'OR number seems too short. Are you sure this is correct?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, continue',
                    cancelButtonText: 'Let me re-enter'
                }).then((result) => {
                    if (result.isConfirmed) {
                        confirmORChangeCustom(paymentId, currentOR, newOR);
                    }
                });
                return;
            }

            // Show confirmation
            confirmORChangeCustom(paymentId, currentOR, newOR);
        }

        /**
         * Confirm OR Change
         */
        function confirmORChangeCustom(paymentId, currentOR, newOR) {
            // Close custom modal
            closeCustomORModal();

            // Show SweetAlert confirmation
            Swal.fire({
                title: 'Confirm Change',
                html: `<div style="text-align: left;">
                       <p>You are about to change the OR number:</p>
                       <table style="width: 100%; margin: 1rem 0; background: #f8fafc; border-radius: 8px;">
                           <tr>
                               <td style="padding: 0.75rem; font-weight: 600; border-bottom: 1px solid #e5e7eb;">Old OR:</td>
                               <td style="padding: 0.75rem; font-family: 'Courier New', monospace; text-decoration: line-through; color: #dc2626; border-bottom: 1px solid #e5e7eb;">${currentOR}</td>
                           </tr>
                           <tr>
                               <td style="padding: 0.75rem; font-weight: 600;">New OR:</td>
                               <td style="padding: 0.75rem; font-family: 'Courier New', monospace; color: #059669; font-weight: bold;">${newOR}</td>
                           </tr>
                       </table>
                       <div class="alert alert-info" style="font-size: 0.9rem; margin-top: 1rem;">
                           <i class="fas fa-info-circle"></i> This change will be logged for audit purposes.
                       </div>
                       </div>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check-circle"></i> Yes, Update It',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#059669',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateORNumberAPI(paymentId, newOR, currentOR);
                }
            });
        }

        /**
         * Change OR Number from modal
         */
        function changeORNumberFromModal() {
            if (!validatePaymentId(currentPaymentData.paymentId, 'change OR number')) {
                return;
            }
            changeORNumber(currentPaymentData.paymentId, currentPaymentData.receiptNumber);
        }

        /**
         * Update OR Number API call
         */
        function updateORNumberAPI(paymentId, newORNumber, oldORNumber) {
            // Show loading
            Swal.fire({
                title: 'Updating OR Number...',
                text: 'Please wait',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send update request
            const formData = new FormData();
            formData.append('payment_id', paymentId);
            formData.append('new_or_number', newORNumber);
            formData.append('reason', `OR number changed from ${oldORNumber} to ${newORNumber} - Physical receipt damaged/printer jam`);
            formData.append('csrf_token', csrfToken);

            fetch('/tmg/api/payments/update_or_number.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'OR Number Updated!',
                        html: `<div style="text-align: left;">
                               <p>The OR number has been successfully updated:</p>
                               <table style="width: 100%; margin: 1rem 0;">
                                   <tr>
                                       <td style="padding: 0.5rem; font-weight: 600;">Old OR:</td>
                                       <td style="padding: 0.5rem; font-family: 'Courier New', monospace; text-decoration: line-through;">${oldORNumber}</td>
                                   </tr>
                                   <tr>
                                       <td style="padding: 0.5rem; font-weight: 600;">New OR:</td>
                                       <td style="padding: 0.5rem; font-family: 'Courier New', monospace; color: #059669; font-weight: bold;">${newORNumber}</td>
                                   </tr>
                               </table>
                               <div class="alert alert-info" style="font-size: 0.9rem; margin-top: 1rem; background: #dbeafe; border: 1px solid #3b82f6; border-radius: 6px; padding: 12px;">
                                   <i class="fas fa-print"></i> <strong>Ready to print!</strong><br>
                                   The receipt will open for printing when you click OK.
                               </div>
                               </div>`,
                        confirmButtonColor: '#059669',
                        confirmButtonText: '<i class="fas fa-print"></i> Print Receipt'
                    }).then(() => {
                        // Open NATIVE print dialog for the NEW receipt
                        printReceiptDirectly(newORNumber);

                        // Listen for when printing is complete, then reload
                        const afterPrintReload = function() {
                            console.log('Printing complete - reloading page');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                            window.removeEventListener('afterprint', afterPrintReload);
                        };
                        window.addEventListener('afterprint', afterPrintReload);

                        // Fallback: reload after 10 seconds if afterprint doesn't fire
                        setTimeout(() => {
                            window.removeEventListener('afterprint', afterPrintReload);
                            location.reload();
                        }, 10000);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to update OR number',
                        confirmButtonColor: '#dc2626'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating OR number: ' + error.message,
                    confirmButtonColor: '#dc2626'
                });
            });
        }

        /**
         * Finalize payment (mark as completed) - API call
         */
        function finalizePaymentAPI(paymentId, receiptNumber) {
            // Show loading
            Swal.fire({
                title: 'Finalizing Payment...',
                text: 'Please wait',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send finalize request
            const formData = new FormData();
            formData.append('payment_id', paymentId);
            formData.append('csrf_token', csrfToken);

            fetch('/tmg/api/payments/finalize_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Finalized!',
                        text: 'Citation status updated to PAID',
                        confirmButtonColor: '#059669'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#dc2626'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error finalizing payment: ' + error.message,
                    confirmButtonColor: '#dc2626'
                });
            });
        }

        /**
         * Finalize payment (mark as completed) - with confirmation
         */
        function finalizePayment(paymentId, receiptNumber) {
            // Validate payment ID first
            if (!validatePaymentId(paymentId, 'finalize payment')) {
                return;
            }

            Swal.fire({
                title: 'Finalize Payment?',
                html: `Mark payment with OR <strong>${receiptNumber}</strong> as completed?<br><br>This will update the citation status to "paid".`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Yes, finalize',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#059669',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    finalizePaymentAPI(paymentId, receiptNumber);
                }
            });
        }

        /**
         * Cancel payment (deletes pending_print payment and frees OR number)
         */
        function cancelPayment(paymentId, receiptNumber) {
            // Validate payment ID first
            if (!validatePaymentId(paymentId, 'cancel payment')) {
                return;
            }

            Swal.fire({
                title: 'Cancel Payment?',
                html: `
                    <div style="text-align: left;">
                        <p>This will <strong>cancel</strong> the payment with OR <strong>${receiptNumber}</strong>.</p>
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 4px; margin: 12px 0; border-left: 3px solid #495057;">
                            <strong>What happens:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li>Payment record is deleted</li>
                                <li>OR number <strong>${receiptNumber}</strong> becomes available for reuse</li>
                                <li>Citation reverts to "pending" status</li>
                            </ul>
                        </div>
                        <p style="font-size: 0.9rem; color: #6c757d;">
                            <i class="fas fa-info-circle"></i> Use this when the receipt was never printed (typo in amount, wrong OR number, etc.)
                        </p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-ban"></i> Yes, cancel payment',
                cancelButtonText: 'No, keep it',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Cancelling Payment...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Send cancel request
                    const formData = new FormData();
                    formData.append('payment_id', paymentId);
                    formData.append('csrf_token', csrfToken);

                    fetch('/tmg/api/payments/cancel_payment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Payment Cancelled',
                                html: `
                                    <div style="text-align: left;">
                                        <p>The payment has been successfully cancelled.</p>
                                        <div style="background: #e8f5e9; padding: 12px; border-radius: 4px; margin: 12px 0; border-left: 3px solid #28a745;">
                                            <strong>OR Number <span style="font-family: 'Courier New', monospace;">${receiptNumber}</span></strong> is now available for reuse.
                                        </div>
                                        <p style="font-size: 0.9rem; color: #6c757d;">
                                            Citation status: <strong>Pending</strong>
                                        </p>
                                    </div>
                                `,
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#dc2626'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error cancelling payment: ' + error.message,
                            confirmButtonColor: '#dc2626'
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>
