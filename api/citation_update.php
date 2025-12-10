<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php'; // SECURITY: Audit logging

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require login
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Rate Limiting
if (!check_rate_limit('citation_update', 20, 300)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Too many update attempts. Please try again later.'
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
    // Validate citation_id
    if (empty($_POST['citation_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Citation ID is required']);
        exit;
    }

    $citation_id = (int)$_POST['citation_id'];

    // Verify citation exists
    $pdo = getPDO();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    $stmt = db_query("SELECT * FROM citations WHERE citation_id = ?", [$citation_id]);
    $existing_citation = $stmt->fetch();

    if (!$existing_citation) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Citation not found']);
        exit;
    }

    // Check permission to edit this citation
    if (!can_edit_citation($citation_id, $existing_citation['created_by'])) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Access denied. You can only edit citations you created.'
        ]);
        exit;
    }

    // Input Validation
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

    // Validate vehicle type
    if (empty($_POST['vehicle_type'])) {
        $errors[] = 'Vehicle type must be selected.';
    } else {
        $valid_vehicle_types = ['Motorcycle', 'Tricycle', 'SUV', 'Van', 'Jeep', 'Truck', 'Kulong Kulong', 'Other'];
        if (!in_array($_POST['vehicle_type'], $valid_vehicle_types)) {
            $errors[] = 'Invalid vehicle type selected.';
        }
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

    // Validate citation/ticket number format and length
    $ticket_number = trim($data['ticket_number']);
    $ticket_length = strlen($ticket_number);

    // Check length (6-8 characters)
    if ($ticket_length < 6 || $ticket_length > 8) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Citation number must be 6 to 8 characters long.'
        ]);
        exit;
    }

    // Check format (alphanumeric and hyphens only)
    if (!preg_match('/^[A-Z0-9\-]{6,8}$/', $ticket_number)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Citation number must contain only uppercase letters, numbers, and hyphens (6-8 characters).'
        ]);
        exit;
    }

    // Check for duplicate citation/ticket number (excluding current citation)
    $duplicate_check = db_query(
        "SELECT ticket_number, created_at FROM citations WHERE ticket_number = ? AND citation_id != ?",
        [$ticket_number, $citation_id]
    );
    $duplicate = $duplicate_check->fetch();

    if ($duplicate) {
        http_response_code(409); // 409 Conflict
        echo json_encode([
            'status' => 'error',
            'error_type' => 'duplicate_citation',
            'message' => "Citation number '{$ticket_number}' is already in use by another citation!",
            'duplicate_info' => [
                'ticket_number' => $duplicate['ticket_number'],
                'created_at' => $duplicate['created_at']
            ]
        ]);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Process date of birth and age
    $dob = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;
    $age = !empty($data['age']) ? (int)$data['age'] : null;

    // SECURITY FIX: Update citation (status field removed to prevent bypassing payment workflow)
    // Status changes should ONLY go through api/update_citation_status.php (admin-only)
    db_query(
        "UPDATE citations SET
            ticket_number = ?,
            last_name = ?,
            first_name = ?,
            middle_initial = ?,
            suffix = ?,
            date_of_birth = ?,
            age = ?,
            zone = ?,
            barangay = ?,
            municipality = ?,
            province = ?,
            license_number = ?,
            license_type = ?,
            plate_mv_engine_chassis_no = ?,
            vehicle_description = ?,
            apprehension_datetime = ?,
            place_of_apprehension = ?,
            apprehension_officer = ?,
            remarks = ?,
            updated_at = NOW()
        WHERE citation_id = ?",
        [
            $data['ticket_number'],
            $data['last_name'],
            $data['first_name'],
            $data['middle_initial'] ?? null,
            $data['suffix'] ?? null,
            $dob,
            $age,
            $data['zone'] ?? null,
            $data['barangay'],
            $data['municipality'] ?? 'Baggao',
            $data['province'] ?? 'Cagayan',
            $data['license_number'] ?? null,
            $data['license_type'] ?? null,
            $data['plate_mv_engine_chassis_no'],
            $data['vehicle_description'] ?? null,
            $data['apprehension_datetime'],
            $data['place_of_apprehension'],
            $data['apprehension_officer'],
            $data['remarks'] ?? null,
            $citation_id
        ]
    );

    // Update vehicle type
    $vehicle_type_value = $data['vehicle_type'];
    if ($vehicle_type_value === 'Other' && !empty($data['other_vehicle_input'])) {
        $vehicle_type_value = $data['other_vehicle_input'];
    }

    // Delete existing vehicle and insert new one
    db_query("DELETE FROM citation_vehicles WHERE citation_id = ?", [$citation_id]);
    db_query(
        "INSERT INTO citation_vehicles (citation_id, vehicle_type) VALUES (?, ?)",
        [$citation_id, $vehicle_type_value]
    );

    // Update violations - delete existing and insert new ones
    db_query("DELETE FROM violations WHERE citation_id = ?", [$citation_id]);

    // Reset total fine (will be recalculated by triggers)
    db_query("UPDATE citations SET total_fine = 0 WHERE citation_id = ?", [$citation_id]);

    // Get driver_id for offense count calculation
    $driver_id = $existing_citation['driver_id'];

    // Handle violations
    $violation_ids = [];
    if (!empty($data['violations'])) {
        $violation_ids = is_array($data['violations']) ? $data['violations'] : [$data['violations']];
    }

    foreach ($violation_ids as $violation_type_id) {
        $violation_type_id = (int)$violation_type_id;

        // Verify violation type exists
        $stmt = db_query(
            "SELECT violation_type_id FROM violation_types WHERE violation_type_id = ?",
            [$violation_type_id]
        );

        if (!$stmt->fetch()) {
            continue;
        }

        // Get offense count for this driver and violation (excluding current citation)
        $offense_count = 1;
        if ($driver_id) {
            $stmt = db_query(
                "SELECT COUNT(*) + 1 as offense_count
                FROM violations v
                JOIN citations c ON v.citation_id = c.citation_id
                WHERE c.driver_id = ? AND v.violation_type_id = ? AND c.citation_id != ?",
                [$driver_id, $violation_type_id, $citation_id]
            );
            $result = $stmt->fetch();
            $offense_count = min($result['offense_count'], 3);
        }

        db_query(
            "INSERT INTO violations (citation_id, violation_type_id, offense_count)
            VALUES (?, ?, ?)",
            [$citation_id, $violation_type_id, $offense_count]
        );
    }

    // Handle "Other" violation
    if (!empty($data['other_violation_input'])) {
        $other_violation = sanitize($data['other_violation_input']);

        $stmt = db_query(
            "SELECT violation_type_id FROM violation_types WHERE violation_type = ?",
            [$other_violation]
        );
        $existing = $stmt->fetch();

        if (!$existing) {
            db_query(
                "INSERT INTO violation_types (violation_type, fine_amount_1, fine_amount_2, fine_amount_3, is_active)
                VALUES (?, 500.00, 1000.00, 1500.00, 1)",
                [$other_violation]
            );
            $other_violation_type_id = $pdo->lastInsertId();
        } else {
            $other_violation_type_id = $existing['violation_type_id'];
        }

        $offense_count = 1;
        if ($driver_id) {
            $stmt = db_query(
                "SELECT COUNT(*) + 1 as offense_count
                FROM violations v
                JOIN citations c ON v.citation_id = c.citation_id
                WHERE c.driver_id = ? AND v.violation_type_id = ? AND c.citation_id != ?",
                [$driver_id, $other_violation_type_id, $citation_id]
            );
            $result = $stmt->fetch();
            $offense_count = min($result['offense_count'], 3);
        }

        db_query(
            "INSERT INTO violations (citation_id, violation_type_id, offense_count)
            VALUES (?, ?, ?)",
            [$citation_id, $other_violation_type_id, $offense_count]
        );

        unset($_SESSION['violation_types']);
    }

    // Commit transaction
    $pdo->commit();

    // SECURITY: Audit logging for citation update
    log_audit(
        $_SESSION['user_id'] ?? null,
        'citation_updated',
        "Ticket #: {$data['ticket_number']}, Driver: {$data['first_name']} {$data['last_name']}, Citation ID: {$citation_id}",
        'success'
    );

    // Get updated citation info
    $stmt = db_query("SELECT total_fine FROM citations WHERE citation_id = ?", [$citation_id]);
    $updated = $stmt->fetch();

    echo json_encode([
        'status' => 'success',
        'message' => 'Citation updated successfully. Ticket #: ' . $data['ticket_number'],
        'new_csrf_token' => generate_token(),
        'citation_id' => $citation_id,
        'total_fine' => $updated['total_fine']
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // SECURITY: Audit logging for failed citation update
    log_audit(
        $_SESSION['user_id'] ?? null,
        'citation_update_failed',
        "Citation ID: " . ($citation_id ?? 'unknown') . ", Error: " . $e->getMessage(),
        'failure'
    );

    error_log("Citation update error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'new_csrf_token' => generate_token()
    ]);
}
?>
