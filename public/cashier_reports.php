<?php
/**
 * Cashier Performance Reports
 * Shows statistics for cashier's own work: citations created and payments processed
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require cashier or admin access
require_login();
check_session_timeout();

// Only cashiers and admins can access this page
if (!is_cashier() && !is_admin()) {
    set_flash('Access denied. Cashier privileges required.', 'danger');
    header('Location: /tmg/public/index.php');
    exit;
}

$page_title = "My Performance Report";
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

// Get date filter from query string
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';

// Calculate date range based on filter
$date_condition = '';
$date_params = [$user_id];

switch ($filter) {
    case 'today':
        $date_condition = ' AND DATE(created_at) = CURDATE()';
        $filter_label = 'Today';
        break;
    case 'week':
        $date_condition = ' AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)';
        $filter_label = 'This Week';
        break;
    case 'month':
        $date_condition = ' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())';
        $filter_label = 'This Month';
        break;
    case 'year':
        $date_condition = ' AND YEAR(created_at) = YEAR(CURDATE())';
        $filter_label = 'This Year';
        break;
    default:
        $filter = 'all';
        $filter_label = 'All Time';
}

try {
    $pdo = getPDO();

    // Get citations created by this cashier
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_citations,
            SUM(total_fine) as total_fines,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
        FROM citations
        WHERE created_by = ?
        AND deleted_at IS NULL
        {$date_condition}
    ");
    $stmt->execute($date_params);
    $citation_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get payments processed by this cashier
    $payment_date_condition = str_replace('created_at', 'p.created_at', $date_condition);
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_payments,
            SUM(p.amount_paid) as total_amount,
            COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_payments,
            COUNT(CASE WHEN p.status = 'voided' THEN 1 END) as voided_payments,
            COUNT(CASE WHEN p.status = 'cancelled' THEN 1 END) as cancelled_payments
        FROM payments p
        WHERE p.collected_by = ?
        {$payment_date_condition}
    ");
    $stmt->execute($date_params);
    $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent citations created by cashier
    $stmt = $pdo->prepare("
        SELECT
            citation_id,
            ticket_number,
            CONCAT(last_name, ', ', first_name) as driver_name,
            total_fine,
            status,
            created_at
        FROM citations
        WHERE created_by = ?
        AND deleted_at IS NULL
        {$date_condition}
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute($date_params);
    $recent_citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent payments processed by cashier
    $stmt = $pdo->prepare("
        SELECT
            p.payment_id,
            p.receipt_number,
            c.ticket_number,
            CONCAT(c.last_name, ', ', c.first_name) as driver_name,
            p.amount_paid,
            p.payment_method,
            p.status,
            p.created_at
        FROM payments p
        INNER JOIN citations c ON p.citation_id = c.citation_id
        WHERE p.collected_by = ?
        {$payment_date_condition}
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute($date_params);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Cashier reports error: " . $e->getMessage());
    set_flash('Error loading reports', 'danger');
    $citation_stats = ['total_citations' => 0, 'total_fines' => 0, 'pending_count' => 0, 'paid_count' => 0];
    $payment_stats = ['total_payments' => 0, 'total_amount' => 0, 'completed_payments' => 0, 'voided_payments' => 0, 'cancelled_payments' => 0];
    $recent_citations = [];
    $recent_payments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-card.blue { border-left-color: #3b82f6; }
        .stat-card.green { border-left-color: #10b981; }
        .stat-card.orange { border-left-color: #f59e0b; }
        .stat-card.purple { border-left-color: #8b5cf6; }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-card.blue .stat-icon { color: #3b82f6; }
        .stat-card.green .stat-icon { color: #10b981; }
        .stat-card.orange .stat-icon { color: #f59e0b; }
        .stat-card.purple .stat-icon { color: #8b5cf6; }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1f2937;
            margin: 10px 0;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-buttons {
            margin-bottom: 30px;
        }

        .filter-buttons .btn {
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
        }

        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 30px 0 20px 0;
            color: #1f2937;
        }

        .table-responsive {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #d1d5db;
        }
    </style>

    <!-- Application Configuration -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content" style="margin-left: 260px; padding: 30px;">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-chart-line"></i> My Performance Report</h1>
                <p>Cashier: <strong><?php echo htmlspecialchars($user_name); ?></strong> | Period: <strong><?php echo $filter_label; ?></strong></p>
            </div>

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php
                    echo $_SESSION['flash_message'];
                    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Buttons -->
            <div class="filter-buttons">
                <a href="?filter=today" class="btn <?php echo $filter === 'today' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
                <a href="?filter=week" class="btn <?php echo $filter === 'week' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-calendar-week"></i> This Week
                </a>
                <a href="?filter=month" class="btn <?php echo $filter === 'month' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-calendar-alt"></i> This Month
                </a>
                <a href="?filter=year" class="btn <?php echo $filter === 'year' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-calendar"></i> This Year
                </a>
                <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-infinity"></i> All Time
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <!-- Citations Created -->
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($citation_stats['total_citations'] ?? 0); ?></div>
                    <div class="stat-label">Citations Created</div>
                    <small class="text-muted">
                        Pending: <?php echo number_format($citation_stats['pending_count'] ?? 0); ?> |
                        Paid: <?php echo number_format($citation_stats['paid_count'] ?? 0); ?>
                    </small>
                </div>

                <!-- Total Fines from Citations -->
                <div class="stat-card orange">
                    <div class="stat-icon">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div class="stat-value">₱<?php echo number_format($citation_stats['total_fines'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Fines Issued</div>
                    <small class="text-muted">From citations created</small>
                </div>

                <!-- Payments Processed -->
                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($payment_stats['total_payments'] ?? 0); ?></div>
                    <div class="stat-label">Payments Processed</div>
                    <small class="text-muted">
                        Completed: <?php echo number_format($payment_stats['completed_payments'] ?? 0); ?> |
                        Voided: <?php echo number_format($payment_stats['voided_payments'] ?? 0); ?>
                    </small>
                </div>

                <!-- Total Amount Collected -->
                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-value">₱<?php echo number_format($payment_stats['total_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Amount Collected</div>
                    <small class="text-muted">From payments processed</small>
                </div>
            </div>

            <!-- Recent Citations -->
            <h3 class="section-title"><i class="fas fa-list"></i> Recent Citations Created</h3>
            <div class="table-responsive">
                <?php if (!empty($recent_citations)): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Driver Name</th>
                                <th>Fine Amount</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_citations as $citation): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($citation['ticket_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($citation['driver_name']); ?></td>
                                    <td>₱<?php echo number_format($citation['total_fine'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $citation['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($citation['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($citation['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <p>No citations created in this period</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Payments -->
            <h3 class="section-title"><i class="fas fa-receipt"></i> Recent Payments Processed</h3>
            <div class="table-responsive">
                <?php if (!empty($recent_payments)): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Ticket #</th>
                                <th>Driver Name</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date Processed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($payment['receipt_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($payment['ticket_number']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['driver_name']); ?></td>
                                    <td>₱<?php echo number_format($payment['amount_paid'], 2); ?></td>
                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'voided' ? 'danger' : 'secondary'); ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($payment['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <p>No payments processed in this period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
