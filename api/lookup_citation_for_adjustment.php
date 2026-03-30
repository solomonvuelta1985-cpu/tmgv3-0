<?php
/**
 * API: Lookup Citation for Payment Adjustment
 *
 * Searches for a citation by ticket number and validates eligibility
 * for admin payment adjustment
 *
 * @package TrafficCitationSystem
 * @subpackage API
 */

session_start();

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/payment_adjustment_functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check admin permission
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

// Get database connection
$pdo = getPDO();
if ($pdo === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$ticket_number = trim($input['ticket_number'] ?? '');

// Validate input
if (empty($ticket_number)) {
    echo json_encode([
        'success' => false,
        'message' => 'Ticket number is required'
    ]);
    exit;
}

try {
    // Search for citation by ticket number
    $stmt = $pdo->prepare("
        SELECT
            c.citation_id,
            c.ticket_number,
            c.apprehension_datetime,
            c.total_fine,
            c.status,
            CONCAT(c.first_name, ' ', c.last_name) AS driver_name,
            c.license_number,
            c.plate_mv_engine_chassis_no AS plate_number,
            c.vehicle_description,
            c.place_of_apprehension,
            GROUP_CONCAT(vt.violation_type SEPARATOR ', ') AS violations,
            COUNT(v.violation_id) AS violation_count
        FROM citations c
        LEFT JOIN violations v ON c.citation_id = v.citation_id
        LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
        WHERE c.ticket_number = ? AND c.deleted_at IS NULL
        GROUP BY c.citation_id
        LIMIT 1
    ");

    $stmt->execute([$ticket_number]);
    $citation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citation) {
        echo json_encode([
            'success' => false,
            'message' => 'Citation not found with ticket number: ' . htmlspecialchars($ticket_number)
        ]);
        exit;
    }

    // Validate eligibility for adjustment
    $validation = validate_citation_for_adjustment($citation['citation_id'], $pdo);

    if (!$validation['valid']) {
        echo json_encode([
            'success' => false,
            'message' => $validation['message'],
            'citation' => $citation,
            'eligible' => false
        ]);
        exit;
    }

    // Citation is valid and eligible
    echo json_encode([
        'success' => true,
        'message' => 'Citation found and eligible for adjustment',
        'citation' => [
            'citation_id' => $citation['citation_id'],
            'ticket_number' => $citation['ticket_number'],
            'driver_name' => $citation['driver_name'],
            'license_number' => $citation['license_number'],
            'plate_number' => $citation['plate_number'],
            'vehicle_description' => $citation['vehicle_description'],
            'violations' => $citation['violations'],
            'violation_count' => $citation['violation_count'],
            'apprehension_datetime' => date('F j, Y g:i A', strtotime($citation['apprehension_datetime'])),
            'apprehension_datetime_raw' => $citation['apprehension_datetime'],
            'apprehension_location' => $citation['place_of_apprehension'],
            'total_fine' => $citation['total_fine'],
            'total_fine_formatted' => '₱' . number_format($citation['total_fine'], 2),
            'status' => $citation['status'],
            'status_badge' => get_status_badge($citation['status'])
        ],
        'eligible' => true
    ]);

} catch (PDOException $e) {
    error_log("Database error in lookup_citation_for_adjustment.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}

/**
 * Get status badge HTML
 *
 * @param string $status Citation status
 * @return string HTML badge
 */
function get_status_badge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
        'paid' => '<span class="badge bg-success">Paid</span>',
        'overdue' => '<span class="badge bg-danger">Overdue</span>',
        'waived' => '<span class="badge bg-info">Waived</span>',
        'cancelled' => '<span class="badge bg-secondary">Cancelled</span>'
    ];

    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
}
