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

    if (!$driver_id && !$license_number && !$plate_number) {
        echo json_encode([
            'success' => false,
            'error' => 'Driver ID, license number, or plate number is required'
        ]);
        exit;
    }

    // Initialize service
    $duplicateService = new DuplicateDetectionService(getPDO());
    $pdo = getPDO();

    // Find driver_id if not provided
    if (!$driver_id && !empty($license_number)) {
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
        }
    }

    // Get all offense counts for this driver
    $offense_counts = [];

    if ($driver_id > 0) {
        // Get all violation types first
        $stmt = $pdo->prepare("
            SELECT violation_type_id
            FROM violation_types
            WHERE is_active = 1
        ");
        $stmt->execute();
        $violation_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each violation type, get the offense count
        foreach ($violation_types as $vt) {
            $violation_type_id = $vt['violation_type_id'];
            $history = $duplicateService->getOffenseHistory($driver_id, $violation_type_id);

            if (!empty($history)) {
                // Get the maximum offense count from history and add 1
                $max_offense = 0;
                foreach ($history as $record) {
                    if ($record['offense_count'] > $max_offense) {
                        $max_offense = $record['offense_count'];
                    }
                }
                $next_offense_count = $max_offense + 1;

                // Cap at 3rd offense
                if ($next_offense_count > 3) {
                    $next_offense_count = 3;
                }

                $offense_counts[$violation_type_id] = $next_offense_count;
            } else {
                $offense_counts[$violation_type_id] = 1; // First offense
            }
        }
    }

    // Get violation types with fine amounts
    $stmt = $pdo->prepare("
        SELECT
            violation_type_id,
            violation_type,
            fine_amount_1,
            fine_amount_2,
            fine_amount_3
        FROM violation_types
        WHERE is_active = 1
    ");
    $stmt->execute();
    $violations_with_fines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build response with violation details
    $violations_data = [];
    foreach ($violations_with_fines as $v) {
        $violation_id = $v['violation_type_id'];
        $offense_count = isset($offense_counts[$violation_id]) ? $offense_counts[$violation_id] : 1;

        $fine_key = "fine_amount_$offense_count";
        $fine_amount = $v[$fine_key];
        $offense_suffix = $offense_count == 1 ? 'st' : ($offense_count == 2 ? 'nd' : 'rd');

        $violations_data[$violation_id] = [
            'violation_type' => $v['violation_type'],
            'offense_count' => $offense_count,
            'offense_label' => "{$offense_count}{$offense_suffix} Offense",
            'fine_amount' => $fine_amount,
            'label' => $v['violation_type'] . " - {$offense_count}{$offense_suffix} Offense (₱" . number_format($fine_amount, 2) . ")"
        ];
    }

    // Close connection
    $duplicateService->closeConnection();

    // Return results
    echo json_encode([
        'success' => true,
        'driver_id' => $driver_id,
        'offense_counts' => $offense_counts,
        'violations' => $violations_data
    ]);

} catch (Exception $e) {
    error_log("Get all offense counts error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while getting offense counts',
        'message' => $e->getMessage()
    ]);
}
