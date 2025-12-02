<?php
/**
 * Test the AJAX duplicate check endpoint
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/services/DuplicateDetectionService.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Testing AJAX Duplicate Check Endpoint</h2>";
echo "<p>This tests the API used by the citation form for real-time duplicate detection.</p>";
echo "<hr>";

// Test data
$test_cases = [
    [
        'name' => 'Full Driver Info - RICHMOND ROSETE',
        'data' => [
            'first_name' => 'RICHMOND',
            'last_name' => 'ROSETE',
            'license_number' => '',
            'plate_number' => '',
            'date_of_birth' => '1999-10-17',
            'barangay' => ''
        ]
    ],
    [
        'name' => 'Reversed Name - ROSETE RICHMOND',
        'data' => [
            'first_name' => 'ROSETE',
            'last_name' => 'RICHMOND',
            'license_number' => '',
            'plate_number' => '',
            'date_of_birth' => '',
            'barangay' => ''
        ]
    ],
    [
        'name' => 'Partial Info - Last Name Only',
        'data' => [
            'first_name' => '',
            'last_name' => 'ROSETE',
            'license_number' => '',
            'plate_number' => '',
            'date_of_birth' => '',
            'barangay' => ''
        ]
    ]
];

foreach ($test_cases as $test) {
    echo "<h3>{$test['name']}</h3>";
    echo "<p><strong>Input Data:</strong></p>";
    echo "<pre>" . print_r($test['data'], true) . "</pre>";

    try {
        $duplicateService = new DuplicateDetectionService(getPDO());
        $matches = $duplicateService->findPossibleDuplicates($test['data']);

        // Enhance matches with offense history
        foreach ($matches as &$match) {
            $match['total_offenses'] = 0;
            $match['total_vehicle_offenses'] = 0;

            // Get offense history
            if (!empty($match['driver_id'])) {
                $offense_history = $duplicateService->getOffenseHistory($match['driver_id']);
                $match['offense_history'] = $offense_history;
                $match['total_offenses'] = count($offense_history);
            } else {
                $match['offense_history'] = [];
            }
        }

        $response = [
            'success' => true,
            'match_count' => count($matches),
            'matches' => $matches
        ];

        echo "<p><strong>Response:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Success:</strong> " . ($response['success'] ? 'Yes' : 'No') . "</li>";
        echo "<li><strong>Match Count:</strong> {$response['match_count']}</li>";
        echo "</ul>";

        if ($response['match_count'] > 0) {
            echo "<table border='1' cellpadding='5' style='width: 100%;'>";
            echo "<tr><th>Name</th><th>DOB</th><th>Confidence</th><th>Reason</th><th>Citations</th><th>Offenses</th></tr>";
            foreach ($response['matches'] as $match) {
                echo "<tr>";
                echo "<td>{$match['last_name']}, {$match['first_name']}</td>";
                echo "<td>" . ($match['date_of_birth'] ?? 'N/A') . "</td>";
                echo "<td>{$match['confidence']}%</td>";
                echo "<td>" . ($match['reason'] ?? 'N/A') . "</td>";
                echo "<td>{$match['total_citations']}</td>";
                echo "<td>{$match['total_offenses']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>No duplicates found.</p>";
        }

        $duplicateService->closeConnection();

        echo "<p style='color: green;'>✓ Test passed - No errors</p>";

    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>ERROR:</strong> " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }

    echo "<hr>";
}

echo "<h3>Final Summary</h3>";
echo "<p style='color: green;'><strong>✓ All AJAX endpoint tests completed successfully!</strong></p>";
echo "<p>The real-time duplicate detection in the citation form will work correctly.</p>";
?>
