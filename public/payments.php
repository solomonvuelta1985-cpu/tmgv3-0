<?php
/**
 * Payment Management Page
 *
 * Displays all payments with search/filter capabilities
 * Allows viewing receipts and managing payments
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

// Require authentication and check session timeout
require_login();
check_session_timeout();

// Require cashier or admin privileges
if (!can_process_payment()) {
    set_flash('Access denied. Only cashiers can access payment management.', 'danger');
    header('Location: /tmg/public/index.php');
    exit;
}

// Page title
$pageTitle = 'Payment Management';

// Initialize PaymentService
$pdo = getPDO();

// Check if database connection failed
if ($pdo === null) {
    set_flash('Database connection failed. Please check if MySQL is running and try again.', 'danger');
    $todayStats = ['total_amount' => 0, 'total_payments' => 0];
    $weekStats = ['total_amount' => 0, 'total_payments' => 0];
    $monthStats = ['total_amount' => 0, 'total_payments' => 0];
    $cashiers = [];
} else {
    $paymentService = new PaymentService($pdo);

    // Get today's statistics
    $todayStats = $paymentService->getPaymentStatistics([
        'from' => date('Y-m-d'),
        'to' => date('Y-m-d')
    ]);

    // Get this week's statistics
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekStats = $paymentService->getPaymentStatistics([
        'from' => $weekStart,
        'to' => date('Y-m-d')
    ]);

    // Get this month's statistics
    $monthStats = $paymentService->getPaymentStatistics([
        'from' => date('Y-m-01'),
        'to' => date('Y-m-d')
    ]);

    // Get all cashiers for filter
    $sql = "SELECT DISTINCT user_id, full_name FROM users WHERE role IN ('admin', 'cashier') ORDER BY full_name";
    $stmt = $pdo->query($sql);
    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="../assets/css/payments.css">

    <!-- Application Configuration - MUST be loaded before other JS files -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>
</head>
<body>
    <?php include '../includes/loader.php'; ?>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <main>
                <!-- Page Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Payment Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportPayments('csv')">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card stat-card-today">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Today's Collections</h6>
                                        <h3 class="mb-0">₱<?= number_format($todayStats['total_amount'] ?? 0, 2) ?></h3>
                                        <small class="text-muted"><?= $todayStats['total_payments'] ?? 0 ?> payment(s)</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card stat-card-week">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">This Week's Collections</h6>
                                        <h3 class="mb-0">₱<?= number_format($weekStats['total_amount'] ?? 0, 2) ?></h3>
                                        <small class="text-muted"><?= $weekStats['total_payments'] ?? 0 ?> payment(s)</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-week"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card stat-card-month">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">This Month's Collections</h6>
                                        <h3 class="mb-0">₱<?= number_format($monthStats['total_amount'] ?? 0, 2) ?></h3>
                                        <small class="text-muted"><?= $monthStats['total_payments'] ?? 0 ?> payment(s)</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-header">Search & Filters</div>
                    <div class="card-body">
                        <form id="filterForm" class="row g-3">
                            <div class="col-md-3">
                                <label for="dateFrom" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" name="date_from">
                            </div>
                            <div class="col-md-3">
                                <label for="dateTo" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="dateTo" name="date_to">
                            </div>
                            <div class="col-md-3">
                                <label for="paymentMethod" class="form-label">Payment Method</label>
                                <select class="form-select" id="paymentMethod" name="payment_method">
                                    <option value="">All Methods</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="online">Online Transfer</option>
                                    <option value="gcash">GCash</option>
                                    <option value="paymaya">PayMaya</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="cashier" class="form-label">Cashier</label>
                                <select class="form-select" id="cashier" name="collected_by">
                                    <option value="">All Cashiers</option>
                                    <?php foreach ($cashiers as $cashier): ?>
                                    <option value="<?= $cashier['user_id'] ?>"><?= htmlspecialchars($cashier['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="receiptNumber" class="form-label">
                                    <i class="fas fa-receipt"></i> OR Number
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="receiptNumber" name="receipt_number"
                                           placeholder="e.g., 12345678 or CGVM12345678" maxlength="12"
                                           style="text-transform: uppercase;">
                                    <button class="btn btn-outline-secondary" type="button" onclick="quickSearchOR()" title="Quick Search">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Format: 8 digits or CGVM + 8 digits</small>
                            </div>
                            <div class="col-md-4">
                                <label for="ticketNumber" class="form-label">Ticket Number</label>
                                <input type="text" class="form-control" id="ticketNumber" name="ticket_number" placeholder="TKT-2025-001">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="completed">Completed</option>
                                    <option value="pending">Pending</option>
                                    <option value="refunded">Refunded</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Payments Table -->
                <div class="card">
                    <div class="card-header">Payment Records</div>
                    <div class="card-body">
                        <div id="loadingSpinner" class="text-center py-5" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="paymentsTable">
                                <thead>
                                    <tr>
                                        <th>Date Processed</th>
                                        <th>Citation Date</th>
                                        <th>Receipt No.</th>
                                        <th>Ticket No.</th>
                                        <th>Driver</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Cashier</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentsTableBody">
                                    <tr>
                                        <td colspan="10" class="text-center">
                                            <em>Loading payments...</em>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="Payment list pagination" class="mt-3">
                            <ul class="pagination justify-content-center" id="pagination">
                                <!-- Pagination will be generated by JavaScript -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
                <!-- Modal Header -->
                <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #dee2e6; padding: 1.5rem;">
                    <div class="header-content" style="display: flex; align-items: center; gap: 1rem; width: 100%;">
                        <div class="header-icon" style="width: 56px; height: 56px; background: #d1fae5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; color: #059669;"></i>
                        </div>
                        <div class="header-text" style="flex: 1;">
                            <h5 class="modal-title mb-0" id="paymentDetailsModalLabel" style="font-size: 1.25rem; font-weight: 600; color: #111827;">
                                Payment Record
                            </h5>
                            <small style="color: #6b7280; font-size: 0.875rem;">Transaction details</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body" style="padding: 1.5rem; background: #f9fafb;">
                    <div id="paymentDetailsContent">
                        <!-- Content will be populated via JavaScript -->
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer" style="background: #ffffff; border-top: 1px solid #dee2e6; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 0.5rem 1.25rem;">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-success" onclick="printPaymentReceipt()" style="padding: 0.5rem 1.25rem;">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="../assets/js/payments.js"></script>
</body>
</html>
