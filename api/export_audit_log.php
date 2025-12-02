<?php
/**
 * Export Audit Log API Endpoint
 *
 * Exports audit logs to CSV format with optional filtering
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AuditService.php';

// Require authentication
require_login();

// Require admin privileges only
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Only administrators can export audit logs.');
}

try {
    // Get filter parameters
    $filters = [];

    if (!empty($_GET['action'])) {
        $filters['action'] = sanitize($_GET['action']);
    }

    if (!empty($_GET['table'])) {
        $filters['table_name'] = sanitize($_GET['table']);
    }

    if (!empty($_GET['user_id'])) {
        $filters['user_id'] = (int)$_GET['user_id'];
    }

    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = sanitize($_GET['date_from']) . ' 00:00:00';
    }

    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = sanitize($_GET['date_to']) . ' 23:59:59';
    }

    $limit = isset($_GET['limit']) ? min(5000, max(10, (int)$_GET['limit'])) : 1000;

    // Initialize AuditService
    $auditService = new AuditService(getPDO());

    // Generate CSV
    $csvContent = $auditService->exportToCSV($filters, $limit);

    // Generate filename
    $filename = 'audit_log_' . date('Y-m-d_His') . '.csv';

    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output CSV
    echo $csvContent;
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Error exporting audit log: ' . $e->getMessage());
}
