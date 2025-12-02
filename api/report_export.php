<?php
session_start();

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/services/ReportService.php';

// Require login
require_login();

// Get parameters
$export_type = isset($_GET['export']) ? sanitize($_GET['export']) : 'csv';
$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'financial';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');
$interval = isset($_GET['interval']) ? sanitize($_GET['interval']) : 'day';

// Initialize ReportService
$reportService = new ReportService(getPDO());

// Get data based on report type
$data = [];
$filename = 'report_' . date('Y-m-d_His');

switch ($report_type) {
    case 'financial':
        $data = $reportService->getRevenueTrends($start_date, $end_date, $interval);
        $filename = 'financial_report_' . date('Y-m-d_His');
        break;

    case 'violations':
        $data = $reportService->getViolationStatistics($start_date, $end_date);
        $filename = 'violations_report_' . date('Y-m-d_His');
        break;

    case 'officers':
        $data = $reportService->getOfficerPerformance($start_date, $end_date);
        $filename = 'officers_report_' . date('Y-m-d_His');
        break;

    case 'drivers':
        $data = $reportService->getRepeatOffenders(2, $start_date, $end_date);
        $filename = 'drivers_report_' . date('Y-m-d_His');
        break;

    case 'time':
        $data = $reportService->getDayOfWeekAnalytics($start_date, $end_date);
        $filename = 'time_report_' . date('Y-m-d_His');
        break;

    case 'status':
        $data = $reportService->getStatusDistribution($start_date, $end_date);
        $filename = 'status_report_' . date('Y-m-d_His');
        break;

    case 'vehicles':
        $data = $reportService->getVehicleTypeStatistics($start_date, $end_date);
        $filename = 'vehicles_report_' . date('Y-m-d_His');
        break;
}

// Close connection
$reportService->closeConnection();

// Export based on type
if ($export_type === 'csv' || $export_type === 'excel') {
    exportToCSV($data, $filename);
} elseif ($export_type === 'pdf') {
    exportToPDF($data, $filename, $report_type);
}

/**
 * Export data to CSV
 */
function exportToCSV($data, $filename) {
    if (empty($data)) {
        header('Content-Type: text/plain');
        echo 'No data available for export';
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
}

/**
 * Export data to PDF (simple HTML to PDF)
 */
function exportToPDF($data, $filename, $report_type) {
    if (empty($data)) {
        header('Content-Type: text/plain');
        echo 'No data available for export';
        exit;
    }

    // Simple HTML output that can be printed to PDF
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . htmlspecialchars($filename) . '</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'h1 { color: #333; border-bottom: 3px solid #0d6efd; padding-bottom: 10px; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    echo 'th { background: #f8f9fa; color: #495057; font-weight: 600; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }';
    echo 'td { padding: 10px; border-bottom: 1px solid #dee2e6; }';
    echo 'tr:hover { background: #f8f9fa; }';
    echo '.header-info { margin-bottom: 20px; color: #6c757d; }';
    echo '.footer { margin-top: 30px; padding-top: 20px; border-top: 2px solid #dee2e6; color: #6c757d; font-size: 0.9em; }';
    echo '@media print { button { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';

    echo '<button onclick="window.print()" style="margin-bottom: 20px; padding: 10px 20px; background: #0d6efd; color: white; border: none; border-radius: 5px; cursor: pointer;">Print to PDF</button>';

    echo '<h1>' . ucfirst($report_type) . ' Report</h1>';
    echo '<div class="header-info">';
    echo '<strong>Generated:</strong> ' . date('F d, Y h:i A') . '<br>';
    echo '<strong>Period:</strong> ' . $_GET['start_date'] . ' to ' . $_GET['end_date'];
    echo '</div>';

    echo '<table>';
    echo '<thead><tr>';

    // Headers
    if (!empty($data)) {
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</th>';
        }
        echo '</tr></thead>';

        // Data
        echo '<tbody>';
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $key => $value) {
                // Format currency values
                if (strpos($key, 'fine') !== false || strpos($key, 'amount') !== false) {
                    echo '<td>₱' . number_format($value, 2) . '</td>';
                } elseif (is_numeric($value) && strpos($key, 'count') !== false) {
                    echo '<td>' . number_format($value) . '</td>';
                } else {
                    echo '<td>' . htmlspecialchars($value) . '</td>';
                }
            }
            echo '</tr>';
        }
        echo '</tbody>';
    }

    echo '</table>';

    echo '<div class="footer">';
    echo 'Traffic Citation System - ' . htmlspecialchars($_SESSION['user_name'] ?? 'Administrator');
    echo '</div>';

    echo '</body>';
    echo '</html>';
    exit;
}
