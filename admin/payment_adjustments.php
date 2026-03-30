<?php
/**
 * Admin Payment Adjustments
 *
 * Special admin-only page for handling payment adjustments
 * Maintains complete audit trail and financial integrity
 *
 * @package TrafficCitationSystem
 * @subpackage Admin
 */

session_start();

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/payment_adjustment_functions.php';

// Require admin access
require_login();
check_session_timeout();
if (!is_admin()) {
    set_flash('Access denied. Admin privileges required.', 'danger');
    header('Location: /tmg/public/dashboard.php');
    exit;
}

// Get database connection
$pdo = getPDO();

// Check if database connection failed
if ($pdo === null) {
    set_flash('Database connection failed. Please check if MySQL is running and try again.', 'danger');
    $recent_adjustments = [];
} else {
    // Get recent adjustments
    $recent_adjustments = get_recent_adjustments($pdo, 10);
}

// Page title
$pageTitle = 'Payment Adjustments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Traffic Citation System</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/process-payment.css">

    <!-- Application Configuration -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>

    <style>
        /* Base Styles */
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-color: #f8f9fa;
        }

        /* Warning Banner */
        .warning-banner {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe8a1 100%);
            border-left: 5px solid #ffc107;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);
        }

        .warning-banner h5 {
            color: #856404;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-banner ul {
            margin: 0;
            padding-left: 1.5rem;
            color: #856404;
        }

        .warning-banner ul li {
            margin-bottom: 0.5rem;
        }

        /* Citation Details Display */
        .citation-details {
            background: #ffffff;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: none;
        }

        .citation-details.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 0.875rem;
            color: #212529;
            font-weight: 500;
        }

        .amount-highlight {
            background: #e8f4f8;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #0d6efd;
            margin-top: 1rem;
        }

        .amount-highlight .amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0d6efd;
        }

        /* Adjustment Form */
        .adjustment-form {
            display: none;
        }

        .adjustment-form.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Recent Adjustments Table */
        .adjustments-table {
            font-size: 0.875rem;
        }

        .adjustments-table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #495057;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 1rem 0.75rem;
        }

        .adjustments-table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .badge-adjustment-type {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            font-weight: 500;
        }

        /* Password Confirmation Modal */
        .password-modal-header {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
            color: #000;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/loader.php'; ?>
    <?php include __DIR__ . '/../public/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i data-lucide="alert-circle"></i> Payment Adjustments</h2>
                <p class="text-muted">Admin-only special payment processing</p>
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

            <!-- Warning Banner -->
            <div class="warning-banner">
                <h5><i data-lucide="alert-triangle"></i> Important: Use Responsibly</h5>
                <ul>
                    <li><strong>Regular payments</strong> MUST use the standard <a href="/tmg/public/process_payment.php">payment processing system</a></li>
                    <li>This feature is ONLY for special cases: external payments, waivers, corrections, lost paperwork, or court settlements</li>
                    <li>All adjustments are permanently logged with your admin account and IP address</li>
                    <li>Audit notifications are sent to all administrators</li>
                </ul>
            </div>

            <!-- Search Citation -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i data-lucide="search"></i> Search Citation</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i data-lucide="ticket"></i></span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="ticketNumberInput"
                                    placeholder="Enter ticket number (e.g., TMG-2024-001234)"
                                    autocomplete="off"
                                >
                                <button class="btn btn-primary" type="button" id="searchBtn">
                                    <i data-lucide="search"></i> Search
                                </button>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i data-lucide="info"></i> Enter the citation ticket number to begin
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Citation Details (hidden initially) -->
            <div class="citation-details" id="citationDetails">
                <h5 class="mb-3"><i data-lucide="file-text"></i> Citation Details</h5>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Ticket Number</span>
                        <span class="detail-value" id="detail_ticket_number">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Driver Name</span>
                        <span class="detail-value" id="detail_driver_name">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">License Number</span>
                        <span class="detail-value" id="detail_license_number">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Plate Number</span>
                        <span class="detail-value" id="detail_plate_number">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Vehicle</span>
                        <span class="detail-value" id="detail_vehicle">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Apprehension Date</span>
                        <span class="detail-value" id="detail_date">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Location</span>
                        <span class="detail-value" id="detail_location">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Violations</span>
                        <span class="detail-value" id="detail_violations">-</span>
                    </div>
                </div>
                <div class="amount-highlight">
                    <small class="d-block text-muted mb-1">TOTAL FINE AMOUNT</small>
                    <div class="amount" id="detail_amount">₱ 0.00</div>
                </div>
            </div>

            <!-- Adjustment Form (hidden initially) -->
            <div class="adjustment-form" id="adjustmentForm">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i data-lucide="edit"></i> Create Payment Adjustment</h5>
                    </div>
                    <div class="card-body">
                        <form id="paymentAdjustmentForm">
                            <input type="hidden" id="citation_id" name="citation_id">
                            <input type="hidden" id="total_fine" name="total_fine">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">

                            <div class="row g-3">
                                <!-- Adjustment Type -->
                                <div class="col-md-6">
                                    <label for="adjustment_type" class="form-label">
                                        <i data-lucide="tag"></i> Adjustment Type *
                                    </label>
                                    <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                                        <option value="">Select adjustment type...</option>
                                        <?php foreach (get_adjustment_types() as $key => $type): ?>
                                            <option value="<?= $key ?>"
                                                    data-requires-or="<?= $type['requires_or'] ? '1' : '0' ?>"
                                                    data-allows-edit="<?= $type['allows_amount_edit'] ? '1' : '0' ?>"
                                                    data-requires-password="<?= $type['requires_password'] ? '1' : '0' ?>"
                                                    data-target-status="<?= $type['target_status'] ?>">
                                                <?= htmlspecialchars($type['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted" id="adjustment_type_description"></small>
                                </div>

                                <!-- OR Number -->
                                <div class="col-md-6" id="or_number_field">
                                    <label for="or_number" class="form-label">
                                        <i data-lucide="receipt"></i> OR Number <span id="or_required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="or_number"
                                        name="or_number"
                                        placeholder="e.g., 15320501 or CGVM15320501"
                                        style="font-family: 'Courier New', monospace; font-weight: bold;"
                                    >
                                </div>

                                <!-- Payment Date -->
                                <div class="col-md-6">
                                    <label for="payment_date" class="form-label">
                                        <i data-lucide="calendar"></i> Payment Date *
                                    </label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        id="payment_date"
                                        name="payment_date"
                                        value="<?= date('Y-m-d') ?>"
                                        max="<?= date('Y-m-d') ?>"
                                        required
                                    >
                                    <small class="form-text text-muted">Date cannot be in the future</small>
                                </div>

                                <!-- Amount -->
                                <div class="col-md-6" id="amount_field">
                                    <label for="amount" class="form-label">
                                        <i data-lucide="dollar-sign"></i> Amount *
                                    </label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="amount"
                                        name="amount"
                                        step="0.01"
                                        min="0"
                                        required
                                        readonly
                                    >
                                    <small class="form-text text-muted" id="amount_help">Auto-filled from citation</small>
                                </div>

                                <!-- Reason -->
                                <div class="col-md-12">
                                    <label for="reason" class="form-label">
                                        <i data-lucide="message-square"></i> Detailed Reason *
                                    </label>
                                    <textarea
                                        class="form-control"
                                        id="reason"
                                        name="reason"
                                        rows="4"
                                        required
                                        minlength="20"
                                        placeholder="Provide a detailed explanation for this adjustment (minimum 20 characters)..."
                                    ></textarea>
                                    <small class="form-text text-muted">
                                        <span id="reason_count">0</span>/20 characters minimum
                                    </small>
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" id="cancelBtn">
                                    <i data-lucide="x"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-success" id="submitBtn">
                                    <i data-lucide="check-circle"></i> Submit Adjustment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Adjustments -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i data-lucide="history"></i> Recent Adjustments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_adjustments)): ?>
                        <div class="alert alert-info mb-0">
                            <i data-lucide="info"></i> No payment adjustments have been recorded yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover adjustments-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Ticket #</th>
                                        <th>Driver</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>OR Number</th>
                                        <th>Adjusted By</th>
                                        <th>Status Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_adjustments as $adj): ?>
                                        <tr>
                                            <td><?= date('M j, Y g:i A', strtotime($adj['created_at'])) ?></td>
                                            <td><strong><?= htmlspecialchars($adj['ticket_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($adj['driver_name']) ?></td>
                                            <td>
                                                <span class="badge badge-adjustment-type bg-primary">
                                                    <?= htmlspecialchars(str_replace('_', ' ', $adj['adjustment_type'])) ?>
                                                </span>
                                            </td>
                                            <td>₱<?= number_format($adj['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($adj['or_number'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($adj['admin_full_name']) ?></td>
                                            <td>
                                                <small><?= htmlspecialchars($adj['old_status']) ?></small>
                                                <i data-lucide="arrow-right" class="small"></i>
                                                <small><strong><?= htmlspecialchars($adj['new_status']) ?></strong></small>
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

    <!-- Password Confirmation Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header password-modal-header">
                    <h5 class="modal-title" id="passwordModalLabel">
                        <i data-lucide="shield-alert"></i> Confirm Your Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>This adjustment type requires admin password confirmation for security.</p>
                    <div class="mb-3">
                        <label for="admin_password" class="form-label">Your Admin Password</label>
                        <input
                            type="password"
                            class="form-control"
                            id="admin_password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                        >
                    </div>
                    <div class="alert alert-warning mb-0">
                        <small><i data-lucide="info"></i> This action will be logged with your account and IP address.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmPasswordBtn">
                        <i data-lucide="check"></i> Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="../assets/js/admin_payment_adjustments.js"></script>
    <!-- Initialize Lucide Icons -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });

        function reinitLucideIcons() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
