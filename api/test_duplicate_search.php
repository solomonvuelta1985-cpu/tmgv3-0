<?php
/**
 * Test script for duplicate detection
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/services/DuplicateDetectionService.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = getPDO();
    $duplicateService = new DuplicateDetectionService($pdo);

    echo "<h2>Testing Duplicate Detection</h2>";
    echo "<hr>";

    // Test 1: Direct search for "ROSETE"
    echo "<h3>Test 1: Direct Search for 'ROSETE'</h3>";
    $results = $duplicateService->directSearch('ROSETE');
    echo "<p><strong>Results found:</strong> " . count($results) . "</p>";
    if (!empty($results)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Driver ID</th><th>Name</th><th>License</th><th>DOB</th><th>Citations</th></tr>";
        foreach ($results as $r) {
            echo "<tr>";
            echo "<td>" . $r['driver_id'] . "</td>";
            echo "<td>" . $r['last_name'] . ", " . $r['first_name'] . "</td>";
            echo "<td>" . ($r['license_number'] ?? 'N/A') . "</td>";
            echo "<td>" . ($r['date_of_birth'] ?? 'N/A') . "</td>";
            echo "<td>" . $r['total_citations'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "<hr>";

    // Test 2: Fuzzy search for "RICHMOND ROSETE"
    echo "<h3>Test 2: Fuzzy Name Search for 'RICHMOND ROSETE'</h3>";
    $driver_info = [
        'first_name' => 'RICHMOND',
        'last_name' => 'ROSETE'
    ];
    $results = $duplicateService->findPossibleDuplicates($driver_info);
    echo "<p><strong>Results found:</strong> " . count($results) . "</p>";
    if (!empty($results)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Driver ID</th><th>Name</th><th>Match Type</th><th>Confidence</th><th>Reason</th></tr>";
        foreach ($results as $r) {
            echo "<tr>";
            echo "<td>" . $r['driver_id'] . "</td>";
            echo "<td>" . $r['last_name'] . ", " . $r['first_name'] . "</td>";
            echo "<td>" . $r['match_type'] . "</td>";
            echo "<td>" . $r['confidence'] . "%</td>";
            echo "<td>" . $r['reason'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "<hr>";

    // Test 3: Reversed name search "ROSETE RICHMOND"
    echo "<h3>Test 3: Fuzzy Name Search for 'ROSETE RICHMOND' (reversed)</h3>";
    $driver_info = [
        'first_name' => 'ROSETE',
        'last_name' => 'RICHMOND'
    ];
    $results = $duplicateService->findPossibleDuplicates($driver_info);
    echo "<p><strong>Results found:</strong> " . count($results) . "</p>";
    if (!empty($results)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Driver ID</th><th>Name</th><th>Match Type</th><th>Confidence</th><th>Reason</th></tr>";
        foreach ($results as $r) {
            echo "<tr>";
            echo "<td>" . $r['driver_id'] . "</td>";
            echo "<td>" . $r['last_name'] . ", " . $r['first_name'] . "</td>";
            echo "<td>" . $r['match_type'] . "</td>";
            echo "<td>" . $r['confidence'] . "%</td>";
            echo "<td>" . $r['reason'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    $duplicateService->closeConnection();

    echo "<hr>";
    echo "<p style='color: green;'><strong>✓ All tests completed successfully!</strong></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
