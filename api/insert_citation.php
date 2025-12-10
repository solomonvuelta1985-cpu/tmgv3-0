<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php'; // SECURITY: Audit logging

// Security Headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require enforcer or admin privileges to create citations
if (!can_create_citation()) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Access denied. Only enforcers can create citations.'
    ]);
    exit;
}

// Rate Limiting
if (!check_rate_limit('citation_submission', 10, 300)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Too many submission attempts. Please try again later.'
    ]);
    exit;
}

// Validate CSRF Token
if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Security token validation failed.',
        'new_csrf_token' => generate_token()
    ]);
    exit;
}

try {
    // Input Validation and Sanitization
    $required_fields = [
        'ticket_number', 'last_name', 'first_name', 'barangay',
        'plate_mv_engine_chassis_no', 'apprehension_datetime',
        'place_of_apprehension', 'apprehension_officer'
    ];
    
    $errors = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Field '$field' is required.";
        }
    }
    
    // Validate violations
    if (empty($_POST['violations']) && empty($_POST['other_violation_input'])) {
        $errors[] = 'At least one violation must be selected.';
    }

    // Validate vehicle type (single selection radio button)
    if (empty($_POST['vehicle_type'])) {
        $errors[] = 'Vehicle type must be selected.';
    } else {
        $valid_vehicle_types = ['Motorcycle', 'Tricycle', 'SUV', 'Van', 'Jeep', 'Truck', 'Kulong Kulong', 'Other'];
        if (!in_array($_POST['vehicle_type'], $valid_vehicle_types)) {
            $errors[] = 'Invalid vehicle type selected.';
        }
        // If "Other" is selected, validate that other_vehicle_input is provided
        if ($_POST['vehicle_type'] === 'Other' && empty($_POST['other_vehicle_input'])) {
            $errors[] = 'Please specify the other vehicle type.';
        }
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Validation errors: ' . implode(' ', $errors)
        ]);
        exit;
    }
    
    // Sanitize all inputs
    $data = sanitize($_POST);

    // Convert empty strings to NULL for optional fields (prevents unique constraint violations)
    $nullable_fields = ['middle_initial', 'suffix', 'zone', 'license_number', 'license_type',
                        'date_of_birth', 'age', 'vehicle_description', 'remarks'];
    foreach ($nullable_fields as $field) {
        if (isset($data[$field]) && $data[$field] === '') {
            $data[$field] = null;
        }
    }

    // Begin transaction for data consistency
    $pdo = getPDO();
    if (!$pdo) {
        throw new Exception("Database connection failed. Please check your database configuration.");
    }
    $pdo->beginTransaction();
    
    // Process date of birth and age
    $dob = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;
    $age = !empty($data['age']) ? (int)$data['age'] : null;

    // Check if driver exists or create new one (ALWAYS create/link driver_id!)
    $driver_id = null;
    $existing_driver = null;

    // Strategy 1: Try to find by license number (if provided)
    if (!empty($data['license_number'])) {
        $stmt = db_query(
            "SELECT driver_id FROM drivers WHERE license_number = ?",
            [$data['license_number']]
        );
        $existing_driver = $stmt->fetch();
    }

    // Strategy 2: If no license match, try to find by name + DOB (for unlicensed drivers)
    if (!$existing_driver && !empty($data['last_name']) && !empty($data['first_name'])) {
        $sql = "SELECT driver_id FROM drivers
                WHERE last_name = ? AND first_name = ?";
        $params = [$data['last_name'], $data['first_name']];

        // Add DOB to matching if available (more accurate)
        if ($dob) {
            $sql .= " AND date_of_birth = ?";
            $params[] = $dob;
        }

        $sql .= " LIMIT 1";
        $stmt = db_query($sql, $params);
        $existing_driver = $stmt->fetch();
    }

    if ($existing_driver) {
        // Driver exists - update their information
        $driver_id = $existing_driver['driver_id'];
        db_query(
            "UPDATE drivers SET last_name = ?, first_name = ?, middle_initial = ?,
            suffix = ?, date_of_birth = ?, age = ?, zone = ?, barangay = ?, municipality = ?, province = ?,
            license_number = ?, license_type = ? WHERE driver_id = ?",
            [
                $data['last_name'], $data['first_name'], $data['middle_initial'],
                $data['suffix'], $dob, $age, $data['zone'], $data['barangay'],
                $data['municipality'] ?? 'Baggao', $data['province'] ?? 'Cagayan',
                $data['license_number'] ?? null, $data['license_type'] ?? null, $driver_id
            ]
        );
    } else {
        // Driver doesn't exist - create new driver record (even without license!)
        db_query(
            "INSERT INTO drivers (last_name, first_name, middle_initial, suffix,
            date_of_birth, age, zone, barangay, municipality, province, license_number, license_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['last_name'], $data['first_name'], $data['middle_initial'],
                $data['suffix'], $dob, $age, $data['zone'], $data['barangay'],
                $data['municipality'] ?? 'Baggao', $data['province'] ?? 'Cagayan',
                $data['license_number'] ?? null, $data['license_type'] ?? null
            ]
        );
        $driver_id = $pdo->lastInsertId();
    }

    // Insert citation
    db_query(
        "INSERT INTO citations (ticket_number, driver_id, last_name, first_name,
        middle_initial, suffix, date_of_birth, age, zone, barangay, municipality, province, license_number,
        license_type, plate_mv_engine_chassis_no, vehicle_description,
        apprehension_datetime, place_of_apprehension, apprehension_officer, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            $data['ticket_number'], $driver_id, $data['last_name'], $data['first_name'],
            $data['middle_initial'], $data['suffix'], $dob, $age, $data['zone'], $data['barangay'],
            $data['municipality'] ?? 'Baggao', $data['province'] ?? 'Cagayan',
            $data['license_number'] ?? null, $data['license_type'] ?? null,
            $data['plate_mv_engine_chassis_no'], $data['vehicle_description'],
            $data['apprehension_datetime'], $data['place_of_apprehension'],
            $data['apprehension_officer'], $data['remarks'], $_SESSION['user_id']
        ]
    );
    $citation_id = $pdo->lastInsertId();

    // Handle vehicle type (single selection)
    $vehicle_type_value = $data['vehicle_type'];
    if ($vehicle_type_value === 'Other' && !empty($data['other_vehicle_input'])) {
        $vehicle_type_value = $data['other_vehicle_input'];
    }

    db_query(
        "INSERT INTO citation_vehicles (citation_id, vehicle_type) VALUES (?, ?)",
        [$citation_id, $vehicle_type_value]
    );
    
    // Handle violations (now receives violation_type_id integers)
    $violation_ids = [];
    if (!empty($data['violations'])) {
        $violation_ids = is_array($data['violations']) ? $data['violations'] : [$data['violations']];
    }

    // Process each violation by ID
    foreach ($violation_ids as $violation_type_id) {
        $violation_type_id = (int)$violation_type_id;

        // Verify violation type exists
        $stmt = db_query(
            "SELECT violation_type_id FROM violation_types WHERE violation_type_id = ?",
            [$violation_type_id]
        );

        if (!$stmt->fetch()) {
            continue; // Skip invalid violation IDs
        }

        // Get offense count for this driver and violation
        $offense_count = 1;
        if ($driver_id) {
            $stmt = db_query(
                "SELECT COUNT(*) + 1 as offense_count
                FROM violations v
                JOIN citations c ON v.citation_id = c.citation_id
                WHERE c.driver_id = ? AND v.violation_type_id = ?",
                [$driver_id, $violation_type_id]
            );
            $result = $stmt->fetch();
            $offense_count = min($result['offense_count'], 3); // Cap at 3rd offense
        }

        // Insert violation
        db_query(
            "INSERT INTO violations (citation_id, violation_type_id, offense_count)
            VALUES (?, ?, ?)",
            [$citation_id, $violation_type_id, $offense_count]
        );
    }

    // Handle "Other" violation (custom text)
    if (!empty($data['other_violation_input'])) {
        $other_violation = sanitize($data['other_violation_input']);

        // Check if this violation type already exists
        $stmt = db_query(
            "SELECT violation_type_id FROM violation_types WHERE violation_type = ?",
            [$other_violation]
        );
        $existing = $stmt->fetch();

        if (!$existing) {
            // Insert new violation type with default fines
            db_query(
                "INSERT INTO violation_types (violation_type, fine_amount_1, fine_amount_2, fine_amount_3, is_active)
                VALUES (?, 500.00, 1000.00, 1500.00, 1)",
                [$other_violation]
            );
            $other_violation_type_id = $pdo->lastInsertId();
        } else {
            $other_violation_type_id = $existing['violation_type_id'];
        }

        // Get offense count for this driver and violation
        $offense_count = 1;
        if ($driver_id) {
            $stmt = db_query(
                "SELECT COUNT(*) + 1 as offense_count
                FROM violations v
                JOIN citations c ON v.citation_id = c.citation_id
                WHERE c.driver_id = ? AND v.violation_type_id = ?",
                [$driver_id, $other_violation_type_id]
            );
            $result = $stmt->fetch();
            $offense_count = min($result['offense_count'], 3);
        }

        // Insert violation
        db_query(
            "INSERT INTO violations (citation_id, violation_type_id, offense_count)
            VALUES (?, ?, ?)",
            [$citation_id, $other_violation_type_id, $offense_count]
        );

        // Clear violation types cache so new type appears
        unset($_SESSION['violation_types']);
    }
    
    // Commit transaction
    $pdo->commit();

    // SECURITY: Audit logging for citation creation
    log_audit(
        $_SESSION['user_id'] ?? null,
        'citation_created',
        "Ticket #: {$data['ticket_number']}, Driver: {$data['first_name']} {$data['last_name']}, Citation ID: {$citation_id}",
        'success'
    );

    // Generate new CSRF token
    $new_token = generate_token();

    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Citation successfully submitted. Citation No.: ' . $data['ticket_number'],
        'new_csrf_token' => $new_token,
        'ticket_number' => $data['ticket_number']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // SECURITY: Audit logging for failed citation creation
    log_audit(
        $_SESSION['user_id'] ?? null,
        'citation_create_failed',
        "Error: " . $e->getMessage(),
        'failure'
    );

    error_log("Citation submission error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'new_csrf_token' => generate_token()
    ]);
}
?>