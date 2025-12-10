<?php
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Require login and check session timeout
require_login();
check_session_timeout();

// Validate citation ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: citations.php');
    exit;
}

// Check if user has permission to edit citations
if (!can_edit_citation()) {
    set_flash('Access denied. You do not have permission to edit citations.', 'danger');
    header('Location: citations.php');
    exit;
}

$citation_id = (int)$_GET['id'];

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get citation data
    $stmt = $conn->prepare("
        SELECT c.*, cv.vehicle_type
        FROM citations c
        LEFT JOIN citation_vehicles cv ON c.citation_id = cv.citation_id
        WHERE c.citation_id = ?
    ");
    $stmt->execute([$citation_id]);
    $citation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citation) {
        header('Location: citations.php?error=not_found');
        exit;
    }

    // For enforcers, check if they created this citation
    if (is_enforcer() && $citation['created_by'] != $_SESSION['user_id']) {
        set_flash('Access denied. You can only edit citations you created.', 'danger');
        header('Location: citations.php');
        exit;
    }

    // Get violations for this citation
    $stmt = $conn->prepare("
        SELECT v.violation_type_id, v.offense_count
        FROM violations v
        WHERE v.citation_id = ?
    ");
    $stmt->execute([$citation_id]);
    $citation_violations = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get offense counts for this driver (for displaying correct fine amounts)
    $offense_counts = [];
    if ($citation['driver_id']) {
        $stmt = $conn->prepare("
            SELECT vt.violation_type_id, MAX(v.offense_count) AS offense_count
            FROM violations v
            JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            JOIN citations c ON v.citation_id = c.citation_id
            WHERE c.driver_id = ? AND c.citation_id != ?
            GROUP BY vt.violation_type_id
        ");
        $stmt->execute([$citation['driver_id'], $citation_id]);
        $offense_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Cache violation types
    if (!isset($_SESSION['violation_types'])) {
        $stmt = $conn->query("SELECT violation_type_id, violation_type, fine_amount_1, fine_amount_2, fine_amount_3 FROM violation_types WHERE is_active = 1 ORDER BY violation_type");
        $_SESSION['violation_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $violation_types = $_SESSION['violation_types'];

    // Fetch active apprehending officers
    $stmt = $conn->query("SELECT officer_id, officer_name, badge_number, position FROM apprehending_officers WHERE is_active = 1 ORDER BY officer_name");
    $apprehending_officers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("PDOException in edit_citation.php: " . $e->getMessage());
    header('Location: citations.php?error=db_error');
    exit;
}
$conn = null;

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit Citation - <?php echo htmlspecialchars($citation['ticket_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --primary-dark: #0b5ed7;
            --success: #198754;
            --info: #0dcaf0;
            --warning: #ffc107;
            --danger: #dc3545;
            --secondary: #6c757d;
            --white: #ffffff;
            --off-white: #f5f5f5;
            --light-gray: #f8f9fa;
            --medium-gray: #e9ecef;
            --border-gray: #dee2e6;
            --text-dark: #212529;
            --text-muted: #6c757d;
            --text-label: #495057;
        }

        body {
            background-color: var(--off-white);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            font-size: 16px;
        }

        .content {
            flex: 1;
            margin-left: 260px;
            padding: clamp(15px, 3vw, 20px);
            overflow-y: auto;
            height: 100vh;
        }

        .ticket-container {
            background-color: var(--white);
            padding: clamp(20px, 4vw, 30px);
            border-radius: 6px;
            border: 1px solid var(--border-gray);
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
            width: 100%;
        }

        .header {
            background-color: var(--white);
            color: var(--text-dark);
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 25px;
            position: relative;
            border: 1px solid var(--border-gray);
            border-left: 4px solid var(--warning);
        }

        .header h4 {
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }

        .header h1 {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 600;
            letter-spacing: 0.02em;
            margin: 0;
            color: var(--text-dark);
        }

        .ticket-number {
            position: absolute;
            top: 20px;
            right: 20px;
            font-weight: 600;
            background: var(--warning);
            padding: 10px 18px;
            border: 1px solid #e0a800;
            border-radius: 4px;
            font-size: clamp(1rem, 2.5vw, 1.1rem);
            color: #000;
        }

        .section {
            background-color: var(--white);
            padding: 25px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid var(--border-gray);
        }

        .section h5 {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-label);
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-gray);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-label);
            margin-bottom: 8px;
            font-size: clamp(0.95rem, 2.5vw, 1.05rem);
        }

        .form-control, .form-select {
            border-radius: 4px;
            border: 1px solid var(--border-gray);
            padding: 10px 14px;
            font-size: clamp(0.95rem, 2.5vw, 1.05rem);
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
            outline: none;
        }

        .accordion-button {
            font-weight: 600;
            color: var(--text-label);
            background-color: var(--light-gray);
            border: none;
            border-radius: 4px !important;
            padding: 14px 18px;
            font-size: clamp(1rem, 2.5vw, 1.1rem);
        }

        .accordion-button:not(.collapsed) {
            color: var(--text-dark);
            background-color: var(--medium-gray);
            box-shadow: none;
        }

        .btn-custom {
            background-color: var(--warning);
            color: #000;
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: 600;
            font-size: clamp(1rem, 2.5vw, 1.1rem);
            border: 1px solid #e0a800;
            transition: all 0.2s ease;
        }

        .btn-custom:hover {
            background-color: #e0a800;
            transform: translateY(-1px);
        }

        .btn-secondary {
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: 600;
            font-size: clamp(1rem, 2.5vw, 1.1rem);
        }

        .status-section {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .status-section label {
            font-weight: 600;
            color: #664d03;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 260px;
            }
            .ticket-number {
                position: static;
                display: inline-block;
                margin-top: 10px;
            }
        }

        @media (max-width: 576px) {
            .content {
                margin-left: 200px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <form id="editCitationForm">
            <input type="hidden" name="citation_id" value="<?php echo $citation_id; ?>">
            <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo generate_token(); ?>">

            <div class="ticket-container">
                <div class="header">
                    <h4><i class="fas fa-edit"></i> EDIT MODE</h4>
                    <h1>TRAFFIC CITATION TICKET</h1>
                    <input type="hidden" name="ticket_number" value="<?php echo htmlspecialchars($citation['ticket_number']); ?>" pattern="[A-Z0-9\-]{6,8}" minlength="6" maxlength="8">
                    <div class="ticket-number"><?php echo htmlspecialchars($citation['ticket_number']); ?></div>
                </div>

                <!-- Status Section -->
                <div class="status-section">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Citation Status</label>
                            <select name="status" class="form-select">
                                <option value="pending" <?php echo $citation['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $citation['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="contested" <?php echo $citation['status'] === 'contested' ? 'selected' : ''; ?>>Contested</option>
                                <option value="dismissed" <?php echo $citation['status'] === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                                <option value="void" <?php echo $citation['status'] === 'void' ? 'selected' : ''; ?>>Void</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Fine</label>
                            <input type="text" class="form-control" value="₱<?php echo number_format($citation['total_fine'], 2); ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Created</label>
                            <input type="text" class="form-control" value="<?php echo date('M d, Y h:i A', strtotime($citation['created_at'])); ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Driver Info -->
                <div class="section">
                    <h5><i class="fas fa-id-card me-2"></i>Driver Information</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($citation['last_name']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($citation['first_name']); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">M.I.</label>
                            <input type="text" name="middle_initial" class="form-control" value="<?php echo htmlspecialchars($citation['middle_initial'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Suffix</label>
                            <input type="text" name="suffix" class="form-control" value="<?php echo htmlspecialchars($citation['suffix'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" id="dateOfBirth" value="<?php echo htmlspecialchars($citation['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" id="ageField" value="<?php echo htmlspecialchars($citation['age'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Zone</label>
                            <input type="text" name="zone" class="form-control" value="<?php echo htmlspecialchars($citation['zone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Barangay *</label>
                            <input type="text" name="barangay" class="form-control" value="<?php echo htmlspecialchars($citation['barangay']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Municipality</label>
                            <input type="text" name="municipality" class="form-control" value="<?php echo htmlspecialchars($citation['municipality'] ?? 'Baggao'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Province</label>
                            <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($citation['province'] ?? 'Cagayan'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">License Number</label>
                            <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($citation['license_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">License Type</label>
                            <select name="license_type" class="form-select">
                                <option value="">None</option>
                                <option value="Non-Professional" <?php echo ($citation['license_type'] ?? '') === 'Non-Professional' ? 'selected' : ''; ?>>Non-Professional</option>
                                <option value="Professional" <?php echo ($citation['license_type'] ?? '') === 'Professional' ? 'selected' : ''; ?>>Professional</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Info -->
                <div class="section">
                    <h5><i class="fas fa-car me-2"></i>Vehicle Information</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Plate / MV File / Engine / Chassis No. *</label>
                            <input type="text" name="plate_mv_engine_chassis_no" class="form-control" value="<?php echo htmlspecialchars($citation['plate_mv_engine_chassis_no']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Vehicle Type *</label>
                            <?php
                            $vehicle_type = $citation['vehicle_type'] ?? '';
                            $standard_types = ['Motorcycle', 'Tricycle', 'SUV', 'Van', 'Jeep', 'Truck', 'Kulong Kulong'];
                            $is_other = !in_array($vehicle_type, $standard_types) && !empty($vehicle_type);
                            ?>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($standard_types as $type): ?>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="vehicle_type" value="<?php echo $type; ?>" id="<?php echo strtolower(str_replace(' ', '', $type)); ?>" <?php echo $vehicle_type === $type ? 'checked' : ''; ?> required onchange="toggleOtherVehicle(this.value)">
                                    <label class="form-check-label" for="<?php echo strtolower(str_replace(' ', '', $type)); ?>"><?php echo $type; ?></label>
                                </div>
                                <?php endforeach; ?>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="vehicle_type" value="Other" id="othersVehicle" <?php echo $is_other ? 'checked' : ''; ?> onchange="toggleOtherVehicle(this.value)">
                                    <label class="form-check-label" for="othersVehicle">Others</label>
                                </div>
                            </div>
                            <input type="text" name="other_vehicle_input" class="form-control mt-2" id="otherVehicleInput" placeholder="Specify other vehicle type" value="<?php echo $is_other ? htmlspecialchars($vehicle_type) : ''; ?>" style="display: <?php echo $is_other ? 'block' : 'none'; ?>;">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Vehicle Description</label>
                            <input type="text" name="vehicle_description" class="form-control" value="<?php echo htmlspecialchars($citation['vehicle_description'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Apprehension Date & Time *</label>
                            <input type="datetime-local" name="apprehension_datetime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($citation['apprehension_datetime'])); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Place of Apprehension *</label>
                            <input type="text" name="place_of_apprehension" class="form-control" value="<?php echo htmlspecialchars($citation['place_of_apprehension']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Apprehension Officer *</label>
                            <select name="apprehension_officer" class="form-select" required>
                                <option value="" disabled>Select Apprehension Officer</option>
                                <?php
                                $current_officer = $citation['apprehension_officer'] ?? '';
                                $officer_found = false;
                                if (!empty($apprehending_officers)):
                                    foreach ($apprehending_officers as $officer):
                                        $is_selected = ($officer['officer_name'] === $current_officer);
                                        if ($is_selected) $officer_found = true;
                                ?>
                                    <option value="<?php echo htmlspecialchars($officer['officer_name']); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($officer['officer_name']); ?>
                                        <?php if (!empty($officer['badge_number'])): ?>
                                            (<?php echo htmlspecialchars($officer['badge_number']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php
                                    endforeach;
                                endif;
                                // If the current officer is not in the list, add it as an option
                                if (!$officer_found && !empty($current_officer)):
                                ?>
                                    <option value="<?php echo htmlspecialchars($current_officer); ?>" selected>
                                        <?php echo htmlspecialchars($current_officer); ?> (Not in current list)
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Violations -->
                <div class="section">
                    <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Violation(s) *</h5>
                    <div class="accordion violation-list" id="violationsAccordion">
                        <?php
                        $categories = [
                            'Helmet Violations' => ['HELMET'],
                            'License / Registration' => ['LICENSE', 'REGISTRATION', 'OPLAN VISA', 'E-OV MATCH'],
                            'Vehicle Condition' => ['DEFECTIVE', 'MUFFLER', 'MODIFICATION', 'PARTS'],
                            'Reckless / Improper Driving' => ['RECKLESS', 'DRAG RACING', 'DRUNK', 'DRIVING IN SHORT', 'ARROGANT'],
                            'Traffic Rules' => ['TRAFFIC SIGN', 'PARKING', 'OBSTRUCTION', 'PEDESTRIAN', 'LOADING', 'PASSENGER ON TOP'],
                            'Miscellaneous' => ['COLORUM', 'TRASHBIN', 'OVERLOADED', 'CHARGING', 'REFUSAL']
                        ];

                        $displayed_violations = [];

                        foreach ($categories as $category => $keywords) {
                            $category_id = htmlspecialchars(strtolower(str_replace([' ', '/', '(', ')'], '', $category)));
                            echo "<div class='accordion-item'>";
                            echo "<h2 class='accordion-header' id='heading-$category_id'>";
                            echo "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#collapse-$category_id' aria-expanded='false' aria-controls='collapse-$category_id'>$category</button>";
                            echo "</h2>";
                            echo "<div id='collapse-$category_id' class='accordion-collapse collapse' aria-labelledby='heading-$category_id' data-bs-parent='#violationsAccordion'>";
                            echo "<div class='accordion-body p-3'>";

                            foreach ($violation_types as $v) {
                                $matches_category = false;
                                foreach ($keywords as $keyword) {
                                    if (stripos($v['violation_type'], $keyword) !== false) {
                                        $matches_category = true;
                                        break;
                                    }
                                }
                                if ($matches_category && !in_array($v['violation_type_id'], $displayed_violations)) {
                                    $displayed_violations[] = $v['violation_type_id'];
                                    $offense_count = isset($offense_counts[$v['violation_type_id']]) ? min((int)$offense_counts[$v['violation_type_id']] + 1, 3) : 1;
                                    $fine_key = "fine_amount_$offense_count";
                                    $offense_suffix = $offense_count == 1 ? 'st' : ($offense_count == 2 ? 'nd' : 'rd');
                                    $label = $v['violation_type'] . " - {$offense_count}{$offense_suffix} Offense (₱" . number_format($v[$fine_key], 2) . ")";
                                    $input_id = 'violation_' . $v['violation_type_id'];
                                    $is_checked = isset($citation_violations[$v['violation_type_id']]);

                                    echo "<div class='form-check mb-2'>";
                                    echo "<input type='checkbox' class='form-check-input violation-checkbox' name='violations[]' value='" . (int)$v['violation_type_id'] . "' id='$input_id' " . ($is_checked ? 'checked' : '') . ">";
                                    echo "<label class='form-check-label' for='$input_id'>" . htmlspecialchars($label) . "</label>";
                                    echo "</div>";
                                }
                            }
                            echo "</div></div></div>";
                        }

                        // Uncategorized violations
                        $uncategorized = [];
                        foreach ($violation_types as $v) {
                            if (!in_array($v['violation_type_id'], $displayed_violations)) {
                                $uncategorized[] = $v;
                            }
                        }

                        if (!empty($uncategorized)) {
                            echo "<div class='accordion-item'>";
                            echo "<h2 class='accordion-header' id='heading-uncategorized'>";
                            echo "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#collapse-uncategorized' aria-expanded='false' aria-controls='collapse-uncategorized'>Other Violations</button>";
                            echo "</h2>";
                            echo "<div id='collapse-uncategorized' class='accordion-collapse collapse' aria-labelledby='heading-uncategorized' data-bs-parent='#violationsAccordion'>";
                            echo "<div class='accordion-body p-3'>";
                            foreach ($uncategorized as $v) {
                                $offense_count = isset($offense_counts[$v['violation_type_id']]) ? min((int)$offense_counts[$v['violation_type_id']] + 1, 3) : 1;
                                $fine_key = "fine_amount_$offense_count";
                                $offense_suffix = $offense_count == 1 ? 'st' : ($offense_count == 2 ? 'nd' : 'rd');
                                $label = $v['violation_type'] . " - {$offense_count}{$offense_suffix} Offense (₱" . number_format($v[$fine_key], 2) . ")";
                                $input_id = 'violation_' . $v['violation_type_id'];
                                $is_checked = isset($citation_violations[$v['violation_type_id']]);

                                echo "<div class='form-check mb-2'>";
                                echo "<input type='checkbox' class='form-check-input violation-checkbox' name='violations[]' value='" . (int)$v['violation_type_id'] . "' id='$input_id' " . ($is_checked ? 'checked' : '') . ">";
                                echo "<label class='form-check-label' for='$input_id'>" . htmlspecialchars($label) . "</label>";
                                echo "</div>";
                            }
                            echo "</div></div></div>";
                        }
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-other">
                                <button class="accordion-button collapsed" type='button' data-bs-toggle='collapse' data-bs-target='#collapse-other' aria-expanded='false' aria-controls='collapse-other'>
                                    Add New Violation Type
                                </button>
                            </h2>
                            <div id="collapse-other" class="accordion-collapse collapse" aria-labelledby="heading-other" data-bs-parent="#violationsAccordion">
                                <div class="accordion-body p-3">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" name="other_violation" id="other_violation">
                                        <label class="form-check-label" for="other_violation">Other Violation</label>
                                    </div>
                                    <input type="text" name="other_violation_input" class="form-control" id="otherViolationInput" placeholder="Specify other violation" style="display: none;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4"><?php echo htmlspecialchars($citation['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-custom">
                        <i class="fas fa-save me-2"></i>Update Citation
                    </button>
                    <a href="citations.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleOtherVehicle(value) {
        const otherInput = document.getElementById('otherVehicleInput');
        if (value === 'Other') {
            otherInput.style.display = 'block';
            otherInput.required = true;
            otherInput.focus();
        } else {
            otherInput.style.display = 'none';
            otherInput.required = false;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const csrfTokenInput = document.getElementById('csrfToken');
        const otherViolationCheckbox = document.getElementById('other_violation');
        const otherViolationInput = document.getElementById('otherViolationInput');
        const dateOfBirthInput = document.getElementById('dateOfBirth');
        const ageField = document.getElementById('ageField');

        // Age calculation
        function calculateAge(birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            return age;
        }

        dateOfBirthInput.addEventListener('change', () => {
            if (dateOfBirthInput.value) {
                const age = calculateAge(dateOfBirthInput.value);
                if (age >= 0 && age <= 120) {
                    ageField.value = age;
                }
            } else {
                ageField.value = '';
            }
        });

        // Other violation toggle
        if (otherViolationCheckbox && otherViolationInput) {
            otherViolationCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    otherViolationInput.style.display = 'block';
                    otherViolationInput.required = true;
                    otherViolationInput.focus();
                } else {
                    otherViolationInput.style.display = 'none';
                    otherViolationInput.required = false;
                    otherViolationInput.value = '';
                }
            });
        }

        // Form submission
        document.getElementById('editCitationForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate vehicle type
            const selectedVehicleType = document.querySelector('input[name="vehicle_type"]:checked');
            if (!selectedVehicleType) {
                alert('Please select a vehicle type.');
                return;
            }

            const otherVehicleInput = document.getElementById('otherVehicleInput');
            if (selectedVehicleType.value === 'Other' && !otherVehicleInput.value.trim()) {
                alert('Please specify the other vehicle type.');
                otherVehicleInput.focus();
                return;
            }

            // Validate violations
            const violationCheckboxes = document.querySelectorAll('input[name="violations[]"]:checked, input[name="other_violation"]:checked');
            if (violationCheckboxes.length === 0) {
                alert('Please select at least one violation.');
                return;
            }

            const formData = new FormData(this);

            fetch('../api/citation_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    if (data.new_csrf_token) {
                        csrfTokenInput.value = data.new_csrf_token;
                    }
                    window.location.href = 'citations.php';
                } else {
                    alert('Error: ' + data.message);
                    if (data.new_csrf_token) {
                        csrfTokenInput.value = data.new_csrf_token;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating citation: ' + error.message);
            });
        });
    });
    </script>
</body>
</html>
