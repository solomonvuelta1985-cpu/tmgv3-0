<?php
/**
 * LTO Driver Search API Endpoint
 * Provides read-only search access for LTO Gattaran Branch staff
 *
 * Returns driver citation information including unpaid and complete history
 */

session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Require LTO staff or admin access
if (!can_access_lto()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. LTO staff privileges required.'
    ]);
    exit;
}

try {
    $pdo = getPDO();

    // Get and validate search parameters
    $search_term = trim(sanitize($_GET['search_term'] ?? ''));
    $search_type = sanitize($_GET['search_type'] ?? 'all'); // license, name, plate, all

    if (empty($search_term)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Search term is required'
        ]);
        exit;
    }

    // Build WHERE clause based on search type
    $where_clauses = [];
    $params = [];

    switch ($search_type) {
        case 'license':
            $where_clauses[] = "c.license_number LIKE ?";
            $params[] = "%{$search_term}%";
            break;

        case 'name':
            $where_clauses[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            break;

        case 'plate':
            $where_clauses[] = "c.plate_mv_engine_chassis_no LIKE ?";
            $params[] = "%{$search_term}%";
            break;

        case 'all':
        default:
            $where_clauses[] = "(c.license_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.plate_mv_engine_chassis_no LIKE ?)";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            break;
    }

    $where_sql = implode(" AND ", $where_clauses);

    // ========================================================================
    // Query 1: Get UNPAID citations (status = 'pending')
    // ========================================================================
    $unpaid_sql = "
        SELECT
            c.citation_id,
            c.ticket_number,
            c.first_name,
            c.last_name,
            c.middle_initial,
            c.license_number,
            c.date_of_birth,
            c.age,
            c.barangay,
            c.municipality,
            c.province,
            c.plate_mv_engine_chassis_no,
            c.apprehension_datetime,
            c.place_of_apprehension,
            c.status,
            c.total_fine,
            GROUP_CONCAT(vt.violation_type ORDER BY vt.violation_type SEPARATOR ', ') as violations,
            COUNT(v.violation_id) as violation_count
        FROM citations c
        LEFT JOIN violations v ON c.citation_id = v.citation_id
        LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
        WHERE c.status = 'pending' AND {$where_sql}
        GROUP BY c.citation_id
        ORDER BY c.apprehension_datetime DESC
    ";

    $stmt = db_query($unpaid_sql, $params);
    $unpaid_citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========================================================================
    // Query 2: Get ALL citations (complete history)
    // ========================================================================
    $all_sql = "
        SELECT
            c.citation_id,
            c.ticket_number,
            c.first_name,
            c.last_name,
            c.middle_initial,
            c.license_number,
            c.date_of_birth,
            c.age,
            c.barangay,
            c.municipality,
            c.province,
            c.plate_mv_engine_chassis_no,
            c.apprehension_datetime,
            c.place_of_apprehension,
            c.status,
            c.total_fine,
            c.payment_date,
            GROUP_CONCAT(vt.violation_type ORDER BY vt.violation_type SEPARATOR ', ') as violations,
            COUNT(v.violation_id) as violation_count
        FROM citations c
        LEFT JOIN violations v ON c.citation_id = v.citation_id
        LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
        WHERE {$where_sql}
        GROUP BY c.citation_id
        ORDER BY c.apprehension_datetime DESC
    ";

    $stmt = db_query($all_sql, $params);
    $all_citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========================================================================
    // Calculate summary statistics
    // ========================================================================
    $total_citations = count($all_citations);
    $unpaid_count = count($unpaid_citations);
    $total_amount_owed = 0;

    foreach ($unpaid_citations as $citation) {
        $total_amount_owed += (float)$citation['total_fine'];
    }

    // ========================================================================
    // Extract driver information from first citation (if available)
    // ========================================================================
    $driver_info = null;
    if (!empty($all_citations)) {
        $first_citation = $all_citations[0];
        $driver_info = [
            'name' => trim($first_citation['first_name'] . ' ' .
                          ($first_citation['middle_initial'] ? $first_citation['middle_initial'] . '. ' : '') .
                          $first_citation['last_name']),
            'license_number' => $first_citation['license_number'] ?? 'N/A',
            'date_of_birth' => $first_citation['date_of_birth'] ?? null,
            'age' => $first_citation['age'] ?? null,
            'address' => trim(($first_citation['barangay'] ?? '') . ', ' .
                            ($first_citation['municipality'] ?? '') . ', ' .
                            ($first_citation['province'] ?? ''))
        ];
    }

    // ========================================================================
    // Return successful response
    // ========================================================================
    echo json_encode([
        'success' => true,
        'search_term' => $search_term,
        'search_type' => $search_type,
        'driver_info' => $driver_info,
        'unpaid_citations' => $unpaid_citations,
        'all_citations' => $all_citations,
        'summary' => [
            'total_citations' => $total_citations,
            'unpaid_count' => $unpaid_count,
            'total_amount_owed' => $total_amount_owed
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("LTO search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed. Please try again.'
    ]);
}
