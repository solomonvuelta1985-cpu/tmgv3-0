<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_login();

header('Content-Type: application/json');

try {
    $driver_id = (int)($_GET['driver_id'] ?? 0);

    if ($driver_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid driver ID']);
        exit;
    }

    // Get driver master record
    $stmt = db_query(
        "SELECT * FROM drivers WHERE driver_id = ?",
        [$driver_id]
    );
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        echo json_encode(['success' => false, 'error' => 'Driver not found']);
        exit;
    }

    // Get ALL citations for this driver (including snapshots)
    $stmt = db_query(
        "SELECT
            c.*,
            GROUP_CONCAT(
                CONCAT(vt.violation_type, ' (', v.offense_count,
                CASE v.offense_count
                    WHEN 1 THEN 'st'
                    WHEN 2 THEN 'nd'
                    WHEN 3 THEN 'rd'
                    ELSE 'th'
                END, ' offense)')
                SEPARATOR ', '
            ) as violations,
            COUNT(v.violation_id) as violation_count
        FROM citations c
        LEFT JOIN violations v ON c.citation_id = v.citation_id
        LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
        WHERE c.driver_id = ? AND c.deleted_at IS NULL
        GROUP BY c.citation_id
        ORDER BY c.apprehension_datetime DESC",
        [$driver_id]
    );

    $citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get offense statistics
    $stmt = db_query(
        "SELECT
            vt.violation_type,
            COUNT(*) as count,
            MAX(v.offense_count) as highest_offense
        FROM violations v
        JOIN citations c ON v.citation_id = c.citation_id
        JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
        WHERE c.driver_id = ? AND c.deleted_at IS NULL
        GROUP BY vt.violation_type_id
        ORDER BY count DESC",
        [$driver_id]
    );

    $offense_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'driver' => $driver,
        'citations' => $citations,
        'offense_stats' => $offense_stats,
        'total_citations' => count($citations)
    ]);

} catch (Exception $e) {
    error_log('Error fetching driver history: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
