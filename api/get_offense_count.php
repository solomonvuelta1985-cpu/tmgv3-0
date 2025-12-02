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
    $driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
    $license_number = isset($_GET['license_number']) ? sanitize($_GET['license_number']) : '';
    $plate_number = isset($_GET['plate_number']) ? sanitize($_GET['plate_number']) : '';
    $violation_type_id = isset($_GET['violation_type_id']) ? (int)$_GET['violation_type_id'] : 0;

    if (!$violation_type_id) {
        echo json_encode([
            'success' => false,
            'error' => 'Violation type ID is required'
        ]);
        exit;
    }

    // Initialize service
    $duplicateService = new DuplicateDetectionService(getPDO());

    $offense_count = 1; // Default to first offense
    $history = [];
    $match_method = '';

    // Try to find offense count by different methods
    // Priority 1: Driver ID (from pre-filled data or selected duplicate)
    if ($driver_id > 0) {
        $history = $duplicateService->getOffenseHistory($driver_id, $violation_type_id);
        $match_method = 'driver_id';
    }
    // Priority 2: License number
    elseif (!empty($license_number)) {
        // Find driver by license and get history
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            SELECT DISTINCT driver_id
            FROM citations
            WHERE license_number = :license_number
            LIMIT 1
        ");
        $stmt->execute([':license_number' => $license_number]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $driver_id = $result['driver_id'];
            $history = $duplicateService->getOffenseHistory($driver_id, $violation_type_id);
            $match_method = 'license_number';
        }
    }
    // Priority 3: Plate number (vehicle-based tracking)
    elseif (!empty($plate_number)) {
        $history = $duplicateService->getVehicleOffenseHistory($plate_number, $violation_type_id);
        $match_method = 'plate_number';
    }

    // Calculate offense count
    if (!empty($history)) {
        // Get the maximum offense count from history and add 1
        $max_offense = 0;
        foreach ($history as $record) {
            if ($record['offense_count'] > $max_offense) {
                $max_offense = $record['offense_count'];
            }
        }
        $offense_count = $max_offense + 1;

        // Cap at 3rd offense (or whatever your system supports)
        if ($offense_count > 3) {
            $offense_count = 3;
        }
    }

    // Close connection
    $duplicateService->closeConnection();

    // Return results
    echo json_encode([
        'success' => true,
        'offense_count' => $offense_count,
        'history_count' => count($history),
        'match_method' => $match_method,
        'history' => $history,
        'driver_id' => $driver_id
    ]);

} catch (Exception $e) {
    error_log("Offense count error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while getting offense count',
        'message' => $e->getMessage()
    ]);
}
