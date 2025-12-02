<?php
/**
 * Pending Citations API Endpoint
 *
 * High-performance server-side filtering and pagination for process_payment.php
 * Optimized for handling 10,000-20,000+ records
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Require authentication
require_login();

// Require cashier or admin privileges
if (!can_process_payment()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Only GET requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getPDO();

    if ($pdo === null) {
        error_log('getPDO() returned null - check database credentials and MySQL service');
        throw new Exception('Database connection failed. Please check if MySQL is running.');
    }

    // ========================================
    // PAGINATION PARAMETERS
    // ========================================
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 25;

    // Sanitize pagination (prevent abuse)
    $page = max(1, min($page, 10000)); // Max 10,000 pages
    $limit = max(10, min($limit, 100)); // Between 10-100 per page
    $offset = ($page - 1) * $limit;

    // ========================================
    // SEARCH & FILTER PARAMETERS
    // ========================================
    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
    $dateFrom = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
    $dateTo = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
    $minAmount = filter_input(INPUT_GET, 'min_amount', FILTER_VALIDATE_FLOAT) ?: null;
    $maxAmount = filter_input(INPUT_GET, 'max_amount', FILTER_VALIDATE_FLOAT) ?: null;
    $violationType = filter_input(INPUT_GET, 'violation_type', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
    $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'date_desc';

    // ========================================
    // BUILD WHERE CLAUSE
    // ========================================
    $whereConditions = ["c.status = 'pending'", "p.payment_id IS NULL"];
    $params = [];

    // Search across multiple fields
    if (!empty($search)) {
        $searchParam = "%$search%";
        $whereConditions[] = "(
            c.ticket_number LIKE :search1 OR
            CONCAT(c.first_name, ' ', c.last_name) LIKE :search2 OR
            c.license_number LIKE :search3 OR
            c.plate_mv_engine_chassis_no LIKE :search4
        )";
        $params[':search1'] = $searchParam;
        $params[':search2'] = $searchParam;
        $params[':search3'] = $searchParam;
        $params[':search4'] = $searchParam;
    }

    // Date range filter
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(c.apprehension_datetime) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(c.apprehension_datetime) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    // Amount range filter
    if ($minAmount !== null) {
        $whereConditions[] = "c.total_fine >= :min_amount";
        $params[':min_amount'] = $minAmount;
    }
    if ($maxAmount !== null) {
        $whereConditions[] = "c.total_fine <= :max_amount";
        $params[':max_amount'] = $maxAmount;
    }

    // Violation type filter
    if (!empty($violationType)) {
        $whereConditions[] = "vt.violation_type = :violation_type";
        $params[':violation_type'] = $violationType;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // ========================================
    // ORDER BY CLAUSE
    // ========================================
    $orderByMap = [
        'date_desc' => 'c.apprehension_datetime DESC',
        'date_asc' => 'c.apprehension_datetime ASC',
        'amount_desc' => 'c.total_fine DESC',
        'amount_asc' => 'c.total_fine ASC',
        'driver_name' => 'driver_name ASC',
        'ticket_number' => 'c.ticket_number ASC'
    ];
    $orderBy = $orderByMap[$sortBy] ?? 'c.apprehension_datetime DESC';

    // ========================================
    // COUNT TOTAL RECORDS (for pagination)
    // ========================================
    $countSql = "SELECT COUNT(DISTINCT c.citation_id) as total
                 FROM citations c
                 LEFT JOIN violations v ON c.citation_id = v.citation_id
                 LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                 LEFT JOIN payments p ON c.citation_id = p.citation_id
                     AND p.status IN ('pending_print', 'completed')
                 WHERE $whereClause";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // ========================================
    // FETCH PAGINATED RESULTS
    // ========================================
    $dataSql = "SELECT
                    c.citation_id,
                    c.ticket_number,
                    c.apprehension_datetime,
                    c.total_fine,
                    c.status,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.first_name,
                    c.last_name,
                    c.license_number,
                    c.plate_mv_engine_chassis_no as plate_number,
                    c.vehicle_description,
                    GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violations
                FROM citations c
                LEFT JOIN violations v ON c.citation_id = v.citation_id
                LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                LEFT JOIN payments p ON c.citation_id = p.citation_id
                    AND p.status IN ('pending_print', 'completed')
                WHERE $whereClause
                GROUP BY c.citation_id
                ORDER BY $orderBy
                LIMIT :limit OFFSET :offset";

    $dataStmt = $pdo->prepare($dataSql);

    // Bind all filter parameters
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }

    // Bind pagination parameters (must be integers)
    $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $dataStmt->execute();
    $citations = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // ========================================
    // GET STATISTICS FOR FILTERS
    // ========================================
    $statsSql = "SELECT
                    MIN(c.total_fine) as min_fine,
                    MAX(c.total_fine) as max_fine,
                    AVG(c.total_fine) as avg_fine,
                    COUNT(DISTINCT c.citation_id) as total_citations,
                    SUM(c.total_fine) as total_amount
                 FROM citations c
                 LEFT JOIN payments p ON c.citation_id = p.citation_id
                     AND p.status IN ('pending_print', 'completed')
                 WHERE c.status = 'pending' AND p.payment_id IS NULL";

    $statsStmt = $pdo->query($statsSql);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // ========================================
    // GET AVAILABLE VIOLATION TYPES (for filter dropdown)
    // ========================================
    $violationTypesSql = "SELECT DISTINCT vt.violation_type
                         FROM violation_types vt
                         INNER JOIN violations v ON vt.violation_type_id = v.violation_type_id
                         INNER JOIN citations c ON v.citation_id = c.citation_id
                         LEFT JOIN payments p ON c.citation_id = p.citation_id
                             AND p.status IN ('pending_print', 'completed')
                         WHERE c.status = 'pending' AND p.payment_id IS NULL
                         ORDER BY vt.violation_type";

    $violationTypesStmt = $pdo->query($violationTypesSql);
    $availableViolations = $violationTypesStmt->fetchAll(PDO::FETCH_COLUMN);

    // ========================================
    // RETURN JSON RESPONSE
    // ========================================
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $citations,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $totalRecords),
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages
        ],
        'filters' => [
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'violation_type' => $violationType,
            'sort_by' => $sortBy
        ],
        'statistics' => [
            'min_fine' => (float)$stats['min_fine'],
            'max_fine' => (float)$stats['max_fine'],
            'avg_fine' => round((float)$stats['avg_fine'], 2),
            'total_citations' => (int)$stats['total_citations'],
            'total_amount' => (float)$stats['total_amount']
        ],
        'available_violations' => $availableViolations,
        'query_info' => [
            'execution_time' => 0, // Can add microtime() tracking if needed
            'using_indexes' => true
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Pending Citations API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching citations',
        'error' => $e->getMessage() // Remove in production
    ]);
}
