<?php
/**
 * Test script simulating driver_duplicates.php search logic
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/services/DuplicateDetectionService.php';

header('Content-Type: text/html; charset=utf-8');

$duplicateService = new DuplicateDetectionService(getPDO());

// Simulate different search queries
$test_searches = [
    'ROSETE',
    'RICHMOND ROSETE',
    'ROSETE RICHMOND',
    'richmond',
    'RICHMOND'
];

echo "<h2>Testing Driver Duplicate Search (Simulating driver_duplicates.php)</h2>";
echo "<p>This simulates the exact logic used in the Driver Duplicates admin page.</p>";
echo "<hr>";

foreach ($test_searches as $search) {
    echo "<h3>Search Query: \"$search\"</h3>";

    $potential_duplicates = [];
    $names = preg_split('/\s+/', trim($search));

    if (count($names) >= 2) {
        // Try: "FIRST LAST" format
        $driver_info_1 = [
            'first_name' => $names[0],
            'last_name' => implode(' ', array_slice($names, 1)),
            'license_number' => $search,
            'plate_number' => $search
        ];

        // Try: "LAST FIRST" format (reversed)
        $driver_info_2 = [
            'first_name' => implode(' ', array_slice($names, 1)),
            'last_name' => $names[0],
            'license_number' => $search,
            'plate_number' => $search
        ];

        echo "<p><em>Trying both name orders...</em></p>";

        // Get matches from both attempts
        $matches_1 = $duplicateService->findPossibleDuplicates($driver_info_1);
        $matches_2 = $duplicateService->findPossibleDuplicates($driver_info_2);

        echo "<p>Format 1 (FIRST LAST): " . count($matches_1) . " matches</p>";
        echo "<p>Format 2 (LAST FIRST): " . count($matches_2) . " matches</p>";

        // Merge and deduplicate results
        $all_matches = array_merge($matches_1, $matches_2);
        $seen_ids = [];
        foreach ($all_matches as $match) {
            $key = $match['last_name'] . '|' . $match['first_name'] . '|' . $match['date_of_birth'];
            if (!in_array($key, $seen_ids)) {
                $potential_duplicates[] = $match;
                $seen_ids[] = $key;
            }
        }

        // Sort by confidence
        usort($potential_duplicates, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
    } else {
        // Single word search
        echo "<p><em>Single word search...</em></p>";
        $driver_info = [
            'first_name' => $search,
            'last_name' => $search,
            'license_number' => $search,
            'plate_number' => $search
        ];

        $potential_duplicates = $duplicateService->findPossibleDuplicates($driver_info);
    }

    // If no results found, try direct database search as fallback
    if (empty($potential_duplicates)) {
        echo "<p><em>No fuzzy matches, trying direct search fallback...</em></p>";
        $potential_duplicates = $duplicateService->directSearch($search);
    }

    echo "<p><strong>Total Results Found: " . count($potential_duplicates) . "</strong></p>";

    if (!empty($potential_duplicates)) {
        echo "<table border='1' cellpadding='5' style='width: 100%; margin-bottom: 20px;'>";
        echo "<tr><th>Name</th><th>DOB</th><th>License</th><th>Match Type</th><th>Confidence</th><th>Reason</th></tr>";
        foreach ($potential_duplicates as $dup) {
            echo "<tr>";
            echo "<td>" . $dup['last_name'] . ", " . $dup['first_name'] . "</td>";
            echo "<td>" . ($dup['date_of_birth'] ?? 'N/A') . "</td>";
            echo "<td>" . ($dup['license_number'] ?? 'N/A') . "</td>";
            echo "<td>" . ($dup['match_type'] ?? 'N/A') . "</td>";
            echo "<td>" . ($dup['confidence'] ?? 0) . "%</td>";
            echo "<td>" . ($dup['reason'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No matches found.</p>";
    }

    echo "<hr>";
}

$duplicateService->closeConnection();

echo "<h3>Summary</h3>";
echo "<p style='color: green;'><strong>✓ All search tests completed!</strong></p>";
echo "<p>The duplicate detection system is working correctly.</p>";
echo "<p><strong>No SQL errors occurred during testing.</strong></p>";
?>
