<?php
// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/services/ReportService.php';

// Require login and check session timeout
require_login();
check_session_timeout();

// Initialize ReportService
$reportService = new ReportService(getPDO());

// Get filter parameters
// If no dates provided, show records from the beginning of time to today
$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? sanitize($_GET['start_date']) : '2000-01-01'; // Far back start date
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? sanitize($_GET['end_date']) : date('Y-m-d'); // Today
$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'financial';
$interval = isset($_GET['interval']) ? sanitize($_GET['interval']) : 'day';

// Debug: Check if files exist
if (isset($_GET['debug_files'])) {
    echo '<div class="alert alert-info">';
    echo '<strong>File Check:</strong><br>';
    $template_path = ROOT_PATH . '/templates/reports/' . $report_type . '-report.php';
    echo 'Looking for: ' . $template_path . '<br>';
    echo 'File exists: ' . (file_exists($template_path) ? 'YES' : 'NO') . '<br>';
    echo 'ROOT_PATH: ' . ROOT_PATH . '<br>';
    echo '</div>';
}

// Fetch data based on report type
$data = [];
switch ($report_type) {
    case 'financial':
        $data['summary'] = $reportService->getFinancialSummary($start_date, $end_date);
        $data['trends'] = $reportService->getRevenueTrends($start_date, $end_date, $interval);
        $data['outstanding'] = $reportService->getOutstandingFines('pending');
        break;

    case 'violations':
        $data['statistics'] = $reportService->getViolationStatistics($start_date, $end_date);
        $data['trends'] = $reportService->getViolationTrends($start_date, $end_date);
        $data['offense_distribution'] = $reportService->getOffenseCountDistribution($start_date, $end_date);
        break;

    case 'officers':
        $data['performance'] = $reportService->getOfficerPerformance($start_date, $end_date);
        break;

    case 'drivers':
        $data['repeat_offenders'] = $reportService->getRepeatOffenders(2, $start_date, $end_date);
        break;

    case 'time':
        $data['hourly'] = $reportService->getTimeBasedAnalytics($start_date, $end_date);
        $data['day_of_week'] = $reportService->getDayOfWeekAnalytics($start_date, $end_date);
        $data['monthly'] = $reportService->getMonthlyAnalytics($start_date, $end_date);
        break;

    case 'status':
        $data['distribution'] = $reportService->getStatusDistribution($start_date, $end_date);
        $data['contested'] = $reportService->getContestedCitations($start_date, $end_date);
        $data['resolution_time'] = $reportService->getCaseResolutionTime($start_date, $end_date);
        break;

    case 'vehicles':
        $data['vehicle_stats'] = $reportService->getVehicleTypeStatistics($start_date, $end_date);
        break;

    case 'or_audit':
        $data['or_summary'] = $reportService->getOrUsageSummary($start_date, $end_date);
        $data['or_daily'] = $reportService->getOrDailyUsage($start_date, $end_date);
        $data['or_cashier'] = $reportService->getOrUsageByCashier($start_date, $end_date);
        $data['audit_trail'] = $reportService->getOrAuditTrail($start_date, $end_date, 100);
        $data['cancelled_voided'] = $reportService->getCancelledVoidedPayments($start_date, $end_date);
        break;

    case 'cashier':
        // Pagination parameters for recent payments
        $payments_page = isset($_GET['payments_page']) ? max(1, (int)$_GET['payments_page']) : 1;
        $payments_per_page = 20;
        $payments_offset = ($payments_page - 1) * $payments_per_page;

        $data['performance'] = $reportService->getCashierPerformance($start_date, $end_date);
        $data['summary'] = $reportService->getCashierSummary($start_date, $end_date, $_SESSION['user_id']);
        $data['recent_citations'] = $reportService->getCashierRecentCitations($start_date, $end_date, 20);
        $data['recent_payments'] = $reportService->getCashierRecentPayments($start_date, $end_date, $payments_per_page, $payments_offset);
        $data['payments_total_count'] = $reportService->getCashierRecentPaymentsCount($start_date, $end_date);
        $data['payments_page'] = $payments_page;
        $data['payments_per_page'] = $payments_per_page;
        $data['payments_total_pages'] = ceil($data['payments_total_count'] / $payments_per_page);
        break;

    case 'barangay':
        $data['barangay_summary'] = $reportService->getDriversByBarangay($start_date, $end_date);
        // If a specific barangay is selected, get detailed driver list
        if (isset($_GET['barangay_filter']) && $_GET['barangay_filter'] !== '') {
            $data['barangay_drivers'] = $reportService->getDriversBySpecificBarangay($_GET['barangay_filter'], $start_date, $end_date);
        }
        break;

    default:
        $data['summary'] = $reportService->getFinancialSummary($start_date, $end_date);
        break;
}

// Close connection
$reportService->closeConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/reports.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i data-lucide="bar-chart-3"></i>
                    </div>
                    <div>
                        <h3>Reports & Analytics</h3>
                        <p class="text-muted mb-0">Comprehensive reporting and data insights</p>
                    </div>
                </div>
            </div>

            <?php echo show_flash(); ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" id="reportFilterForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select" id="reportTypeSelect">
                            <option value="financial" <?php echo $report_type === 'financial' ? 'selected' : ''; ?>>Financial Reports</option>
                            <option value="or_audit" <?php echo $report_type === 'or_audit' ? 'selected' : ''; ?>>OR Usage & Audit Trail</option>
                            <option value="violations" <?php echo $report_type === 'violations' ? 'selected' : ''; ?>>Violation Analytics</option>
                            <option value="officers" <?php echo $report_type === 'officers' ? 'selected' : ''; ?>>Officer Performance</option>
                            <option value="cashier" <?php echo $report_type === 'cashier' ? 'selected' : ''; ?>>Cashier Performance</option>
                            <option value="drivers" <?php echo $report_type === 'drivers' ? 'selected' : ''; ?>>Driver Reports</option>
                            <option value="barangay" <?php echo $report_type === 'barangay' ? 'selected' : ''; ?>>Barangay Reports</option>
                            <option value="time" <?php echo $report_type === 'time' ? 'selected' : ''; ?>>Time-Based Analytics</option>
                            <option value="status" <?php echo $report_type === 'status' ? 'selected' : ''; ?>>Status & Operations</option>
                            <option value="vehicles" <?php echo $report_type === 'vehicles' ? 'selected' : ''; ?>>Vehicle Reports</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <?php if ($report_type === 'financial'): ?>
                    <div class="col-md-2">
                        <label class="form-label">Interval</label>
                        <select name="interval" class="form-select">
                            <option value="day" <?php echo $interval === 'day' ? 'selected' : ''; ?>>Daily</option>
                            <option value="week" <?php echo $interval === 'week' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="month" <?php echo $interval === 'month' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="year" <?php echo $interval === 'year' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="filter" style="width: 16px; height: 16px;"></i>
                            <span>Apply Filters</span>
                        </button>
                        <button type="button" class="btn btn-success" id="exportBtn">
                            <i data-lucide="download" style="width: 16px; height: 16px;"></i>
                            <span>Export</span>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.print()">
                            <i data-lucide="printer" style="width: 16px; height: 16px;"></i>
                            <span>Print</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Content -->
            <div class="report-content">
                <?php if ($report_type === 'financial'): ?>
                    <?php include ROOT_PATH . '/templates/reports/financial-report.php'; ?>
                <?php elseif ($report_type === 'or_audit'): ?>
                    <?php include ROOT_PATH . '/templates/reports/or-audit-report.php'; ?>
                <?php elseif ($report_type === 'violations'): ?>
                    <?php include ROOT_PATH . '/templates/reports/violations-report.php'; ?>
                <?php elseif ($report_type === 'officers'): ?>
                    <?php include ROOT_PATH . '/templates/reports/officers-report.php'; ?>
                <?php elseif ($report_type === 'cashier'): ?>
                    <?php include ROOT_PATH . '/templates/reports/cashier-report.php'; ?>
                <?php elseif ($report_type === 'drivers'): ?>
                    <?php include ROOT_PATH . '/templates/reports/drivers-report.php'; ?>
                <?php elseif ($report_type === 'barangay'): ?>
                    <?php include ROOT_PATH . '/templates/reports/barangay-report.php'; ?>
                <?php elseif ($report_type === 'time'): ?>
                    <?php include ROOT_PATH . '/templates/reports/time-report.php'; ?>
                <?php elseif ($report_type === 'status'): ?>
                    <?php include ROOT_PATH . '/templates/reports/status-report.php'; ?>
                <?php elseif ($report_type === 'vehicles'): ?>
                    <?php include ROOT_PATH . '/templates/reports/vehicles-report.php'; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="../assets/js/reports.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
