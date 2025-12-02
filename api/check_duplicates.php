<?php
session_start();

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/services/DuplicateDetectionService.php';

// Require login
require_login();

// Set JSON header
header('Content-Type: application/json');

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST; // Fallback to POST data
    }

    // Validate required fields
    $driver_info = [
        'license_number' => $input['license_number'] ?? '',
        'plate_number' => $input['plate_number'] ?? '',
        'first_name' => $input['first_name'] ?? '',
        'last_name' => $input['last_name'] ?? '',
        'date_of_birth' => $input['date_of_birth'] ?? '',
        'barangay' => $input['barangay'] ?? ''
    ];

    // Initialize service
    $duplicateService = new DuplicateDetectionService(getPDO());

    // Find possible duplicates
    $matches = $duplicateService->findPossibleDuplicates($driver_info);

    // For each match, get offense history
    foreach ($matches as &$match) {
        $match['offense_history'] = $duplicateService->getOffenseHistory($match['driver_id']);
        $match['total_offenses'] = count($match['offense_history']);

        // Get vehicle offense history if plate number available
        if (!empty($driver_info['plate_number'])) {
            $match['vehicle_history'] = $duplicateService->getVehicleOffenseHistory(
                $driver_info['plate_number']
            );
            $match['total_vehicle_offenses'] = count($match['vehicle_history']);
        }
    }

    // Close connection
    $duplicateService->closeConnection();

    // Return results
    echo json_encode([
        'success' => true,
        'matches' => $matches,
        'match_count' => count($matches)
    ]);

} catch (Exception $e) {
    error_log("Duplicate check error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while checking for duplicates',
        'message' => $e->getMessage()
    ]);
}
