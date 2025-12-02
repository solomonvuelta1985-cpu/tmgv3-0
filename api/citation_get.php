<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Require login
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

try {
    if (empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Citation ID is required']);
        exit;
    }

    $citation_id = (int)$_GET['id'];

    // Get citation
    $stmt = db_query(
        "SELECT c.*, cv.vehicle_type
         FROM citations c
         LEFT JOIN citation_vehicles cv ON c.citation_id = cv.citation_id
         WHERE c.citation_id = ?",
        [$citation_id]
    );

    $citation = $stmt->fetch();

    if (!$citation) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Citation not found']);
        exit;
    }

    // Get violations
    $stmt = db_query(
        "SELECT v.*, vt.violation_type
         FROM violations v
         JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
         WHERE v.citation_id = ?",
        [$citation_id]
    );

    $violations = $stmt->fetchAll();
    $citation['violations'] = $violations;

    echo json_encode([
        'status' => 'success',
        'citation' => $citation
    ]);

} catch (Exception $e) {
    error_log("Citation get error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>
