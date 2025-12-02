<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login
if (!is_logged_in()) {
    header('Location: ../public/login.php');
    exit;
}

try {
    // Build query
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

    $where_clauses = [];
    $params = [];

    if (!empty($search)) {
        $where_clauses[] = "(c.ticket_number LIKE ? OR c.last_name LIKE ? OR c.first_name LIKE ? OR c.license_number LIKE ? OR c.plate_mv_engine_chassis_no LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }

    if (!empty($status_filter)) {
        $where_clauses[] = "c.status = ?";
        $params[] = $status_filter;
    }

    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Get all citations
    $sql = "SELECT c.*,
            GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR '; ') as violations,
            cv.vehicle_type
            FROM citations c
            LEFT JOIN violations v ON c.citation_id = v.citation_id
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            LEFT JOIN citation_vehicles cv ON c.citation_id = cv.citation_id
            $where_sql
            GROUP BY c.citation_id
            ORDER BY c.created_at DESC";

    $stmt = db_query($sql, $params);
    $citations = $stmt->fetchAll();

    // Set headers for CSV download
    $filename = 'citations_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Header
    fputcsv($output, [
        'Ticket Number',
        'Date/Time',
        'Last Name',
        'First Name',
        'Middle Initial',
        'Suffix',
        'Date of Birth',
        'Age',
        'Zone',
        'Barangay',
        'Municipality',
        'Province',
        'License Number',
        'License Type',
        'Plate/MV/Engine/Chassis No.',
        'Vehicle Type',
        'Vehicle Description',
        'Place of Apprehension',
        'Violations',
        'Total Fine',
        'Status',
        'Remarks',
        'Created At'
    ]);

    // CSV Data
    foreach ($citations as $citation) {
        fputcsv($output, [
            $citation['ticket_number'],
            $citation['apprehension_datetime'],
            $citation['last_name'],
            $citation['first_name'],
            $citation['middle_initial'],
            $citation['suffix'],
            $citation['date_of_birth'],
            $citation['age'],
            $citation['zone'],
            $citation['barangay'],
            $citation['municipality'],
            $citation['province'],
            $citation['license_number'],
            $citation['license_type'],
            $citation['plate_mv_engine_chassis_no'],
            $citation['vehicle_type'],
            $citation['vehicle_description'],
            $citation['place_of_apprehension'],
            $citation['violations'],
            $citation['total_fine'],
            $citation['status'],
            $citation['remarks'],
            $citation['created_at']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    header('Location: ../public/citations.php?error=export_failed');
    exit;
}
?>
