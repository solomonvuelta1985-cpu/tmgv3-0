<?php
// Database check script
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';

try {
    $pdo = getPDO();

    echo "<h2>Database Check</h2>";

    // Check citations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM citations");
    $citations_count = $stmt->fetch()['count'];
    echo "<p><strong>Total Citations:</strong> $citations_count</p>";

    // Check violations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM violations");
    $violations_count = $stmt->fetch()['count'];
    echo "<p><strong>Total Violations:</strong> $violations_count</p>";

    // Check violation types
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM violation_types");
    $violation_types_count = $stmt->fetch()['count'];
    echo "<p><strong>Violation Types:</strong> $violation_types_count</p>";

    // Check officers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM apprehending_officers");
    $officers_count = $stmt->fetch()['count'];
    echo "<p><strong>Officers:</strong> $officers_count</p>";

    // Check citation_vehicles
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM citation_vehicles");
    $vehicles_count = $stmt->fetch()['count'];
    echo "<p><strong>Citation Vehicles:</strong> $vehicles_count</p>";

    // Sample citation data
    echo "<h3>Sample Citations:</h3>";
    $stmt = $pdo->query("SELECT citation_id, ticket_number, last_name, first_name, created_at, status FROM citations LIMIT 5");
    $citations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($citations, true) . "</pre>";

    // Sample violations with citation
    echo "<h3>Sample Violations:</h3>";
    $stmt = $pdo->query("
        SELECT v.*, vt.violation_type, c.ticket_number
        FROM violations v
        JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
        JOIN citations c ON v.citation_id = c.citation_id
        LIMIT 5
    ");
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($violations, true) . "</pre>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
