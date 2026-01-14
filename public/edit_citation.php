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
        SELECT c.*
        FROM citations c
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

    // Fetch active violation categories (with fallback)
    $violation_categories = [];
    try {
        $stmt = $conn->query("SELECT * FROM violation_categories WHERE is_active = 1 ORDER BY display_order ASC, category_name ASC");
        $violation_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback categories if table doesn't exist
        $violation_categories = [
            ['category_id' => 1, 'category_name' => 'License / Registration', 'category_icon' => 'file-text'],
            ['category_id' => 2, 'category_name' => 'Helmet Violations', 'category_icon' => 'shield'],
            ['category_id' => 3, 'category_name' => 'Vehicle Condition', 'category_icon' => 'wrench'],
            ['category_id' => 4, 'category_name' => 'Traffic Rules', 'category_icon' => 'traffic-cone'],
            ['category_id' => 5, 'category_name' => 'Reckless Driving', 'category_icon' => 'alert-octagon'],
            ['category_id' => 6, 'category_name' => 'Other', 'category_icon' => 'more-horizontal']
        ];
    }

    // Clear cached violations to fetch fresh data with category_id
    unset($_SESSION['violation_types']);

    // Cache violation types with category_id
    if (!isset($_SESSION['violation_types'])) {
        try {
            $stmt = $conn->query("SELECT violation_type_id, violation_type, fine_amount_1, fine_amount_2, fine_amount_3, category_id FROM violation_types WHERE is_active = 1 ORDER BY violation_type");
            $_SESSION['violation_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback without category_id
            $stmt = $conn->query("SELECT violation_type_id, violation_type, fine_amount_1, fine_amount_2, fine_amount_3 FROM violation_types WHERE is_active = 1 ORDER BY violation_type");
            $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Auto-assign categories based on keywords
            foreach ($violations as &$v) {
                $name = strtoupper($v['violation_type']);
                if (strpos($name, 'LICENSE') !== false || strpos($name, 'REGISTRATION') !== false || strpos($name, 'OPLAN') !== false) {
                    $v['category_id'] = 1;
                } elseif (strpos($name, 'HELMET') !== false) {
                    $v['category_id'] = 2;
                } elseif (strpos($name, 'DEFECTIVE') !== false || strpos($name, 'MUFFLER') !== false || strpos($name, 'MODIFICATION') !== false) {
                    $v['category_id'] = 3;
                } elseif (strpos($name, 'TRAFFIC') !== false || strpos($name, 'PARKING') !== false || strpos($name, 'OBSTRUCTION') !== false) {
                    $v['category_id'] = 4;
                } elseif (strpos($name, 'RECKLESS') !== false || strpos($name, 'DRAG') !== false || strpos($name, 'DRUNK') !== false) {
                    $v['category_id'] = 5;
                } else {
                    $v['category_id'] = 6; // Other
                }
            }
            $_SESSION['violation_types'] = $violations;
        }
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/citation-form.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-size: 16px;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: clamp(20px, 3vw, 30px);
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: var(--sidebar-collapsed-width);
            }
        }

        .ticket-container {
            background-color: var(--white);
            padding: clamp(20px, 4vw, 30px);
            border-radius: 6px;
            border: 1px solid var(--border-gray);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 1400px;
            margin: 0 auto 24px auto;
        }

        .header {
            background: var(--white);
            padding: clamp(18px, 2.5vw, 24px);
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid var(--border-gray);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 0;
        }

        .edit-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff3cd;
            color: #856404;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #ffc107;
            white-space: nowrap;
        }

        .edit-mode-badge i {
            width: 18px;
            height: 18px;
        }

        .header-title {
            flex: 1;
            min-width: 0;
        }

        .header-title h4 {
            font-size: 0.75rem;
            font-weight: 400;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #9ca3af;
            margin: 0 0 4px 0;
            line-height: 1.3;
        }

        .header-title h1 {
            font-size: clamp(1.15rem, 2.8vw, 1.5rem);
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .citation-number-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            padding: 10px 18px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .citation-number-badge .badge-label {
            color: #64748b;
            font-weight: 500;
            font-size: 0.875rem;
            letter-spacing: 0.3px;
        }

        .citation-number-badge .badge-value {
            color: #0f172a;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            letter-spacing: 0.5px;
        }

        .citation-number-badge i {
            width: 18px;
            height: 18px;
            color: #3b82f6;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-left {
                width: 100%;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .citation-number-badge {
                width: 100%;
                justify-content: center;
            }
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
            background: var(--primary);
            color: var(--white);
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: clamp(0.9rem, 2.2vw, 1rem);
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-custom:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
            color: var(--white);
        }

        .btn-custom i {
            width: 18px;
            height: 18px;
        }

        .btn-secondary {
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: clamp(0.9rem, 2.2vw, 1rem);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.12);
        }

        .btn-secondary i {
            width: 18px;
            height: 18px;
        }

        .btn-outline-secondary {
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: clamp(0.9rem, 2.2vw, 1rem);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-gray);
            background: white;
            color: var(--text-dark);
        }

        .btn-outline-secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.12);
            background: var(--light-gray);
            color: var(--text-dark);
        }

        .btn-outline-secondary i {
            width: 18px;
            height: 18px;
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

        /* ==========================================
           CITATION NUMBER INPUT - MODERN STYLING
           ========================================== */
        .citation-number-input-group {
            background: white;
            padding: 20px 25px;
            border-radius: 8px;
            border: 2px solid #3b82f6;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
        }

        .citation-number-input-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .citation-number-input-group label i {
            color: #3b82f6;
        }

        #ticket_number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            transition: all 0.2s ease;
            text-transform: uppercase;
        }

        #ticket_number:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        #ticket_number.is-valid {
            border-color: #10b981;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2310b981' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px 20px;
            padding-right: 40px;
        }

        #ticket_number.is-invalid {
            border-color: #ef4444;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23ef4444'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23ef4444' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px 20px;
            padding-right: 40px;
        }

        .citation-help-text {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 8px;
        }

        .citation-help-text i {
            width: 14px;
            height: 14px;
        }

        .citation-validation-feedback {
            display: none;
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .citation-validation-feedback.valid {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .citation-validation-feedback.invalid {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .citation-validation-feedback.checking {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .spinner {
            animation: spin 1s linear infinite;
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
                    <div class="header-left">
                        <div class="edit-mode-badge">
                            <i data-lucide="pencil"></i>
                            <span>Edit Mode</span>
                        </div>
                        <div class="header-title">
                            <h4>Municipality of Baggao</h4>
                            <h1>Traffic Citation Ticket</h1>
                        </div>
                    </div>
                    <div class="citation-number-badge">
                        <i data-lucide="hash"></i>
                        <span class="badge-label">Citation</span>
                        <span class="badge-value"><?php echo htmlspecialchars($citation['ticket_number']); ?></span>
                    </div>
                </div>

                <!-- Editable Citation Number Section -->
                <div class="citation-number-input-group">
                    <label for="ticket_number" class="mb-0">
                        <i data-lucide="hash"></i>
                        Citation Number
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="ticket_number"
                        class="form-control"
                        id="ticket_number"
                        value="<?php echo htmlspecialchars($citation['ticket_number']); ?>"
                        required
                        pattern="[A-Z0-9\-]{6,8}"
                        minlength="6"
                        maxlength="8"
                        title="Citation number must be 6 to 8 characters (letters, numbers, or hyphens)"
                        autocomplete="off"
                        data-original-value="<?php echo htmlspecialchars($citation['ticket_number']); ?>"
                    >

                    <div class="citation-help-text" id="citationHelpText">
                        <i data-lucide="info"></i>
                        <span>Edit the citation number if needed. Must be 6-8 characters (letters, numbers, hyphens only).</span>
                    </div>

                    <div class="citation-validation-feedback" id="citationFeedback" role="alert"></div>
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
                            <?php
                            $barangays = [
                                'Adaoag', 'Agaman (Proper)', 'Agaman Norte', 'Agaman Sur', 'Alba', 'Annayatan',
                                'Asassi', 'Asinga-Via', 'Awallan', 'Bacagan', 'Bagunot', 'Barsat East',
                                'Barsat West', 'Bitag Grande', 'Bitag Pequeño', 'Bunugan', 'C. Verzosa (Valley Cove)',
                                'Canagatan', 'Carupian', 'Catugay', 'Dabbac Grande', 'Dalin', 'Dalla',
                                'Hacienda Intal', 'Ibulo', 'Immurung', 'J. Pallagao', 'Lasilat', 'Mabini',
                                'Masical', 'Mocag', 'Nangalinan', 'Poblacion (Centro)', 'Remus', 'San Antonio',
                                'San Francisco', 'San Isidro', 'San Jose', 'San Miguel', 'San Vicente',
                                'Santa Margarita', 'Santor', 'Taguing', 'Taguntungan', 'Tallang', 'Taytay',
                                'Temblique', 'Tungel'
                            ];
                            $current_barangay = $citation['barangay'] ?? '';
                            $is_other_barangay = !in_array($current_barangay, $barangays) && !empty($current_barangay);
                            ?>
                            <select name="barangay" class="form-select" id="barangaySelect" required>
                                <option value="" disabled>Select Barangay</option>
                                <?php
                                foreach ($barangays as $barangay) {
                                    $selected = ($current_barangay === $barangay) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($barangay) . "\" $selected>" . htmlspecialchars($barangay) . "</option>";
                                }
                                ?>
                                <option value="Other" <?php echo $is_other_barangay ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="otherBarangayDiv" style="display: <?php echo $is_other_barangay ? 'block' : 'none'; ?>;">
                            <label class="form-label">Specify Other Barangay *</label>
                            <input type="text" name="other_barangay" class="form-control" id="otherBarangayInput" placeholder="Enter other barangay" value="<?php echo $is_other_barangay ? htmlspecialchars($current_barangay) : ''; ?>">
                        </div>
                        <div class="col-md-3" id="municipalityDiv" style="display: <?php echo !empty($current_barangay) ? 'block' : 'none'; ?>;">
                            <label class="form-label">Municipality</label>
                            <input type="text" name="municipality" class="form-control" id="municipalityInput" value="<?php echo htmlspecialchars($citation['municipality'] ?? 'Baggao'); ?>" <?php echo !$is_other_barangay ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3" id="provinceDiv" style="display: <?php echo !empty($current_barangay) ? 'block' : 'none'; ?>;">
                            <label class="form-label">Province</label>
                            <input type="text" name="province" class="form-control" id="provinceInput" value="<?php echo htmlspecialchars($citation['province'] ?? 'Cagayan'); ?>" <?php echo !$is_other_barangay ? 'readonly' : ''; ?>>
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

                            // Case-insensitive matching
                            $vehicle_type_upper = strtoupper($vehicle_type);
                            $standard_types_upper = array_map('strtoupper', $standard_types);
                            $is_other = !in_array($vehicle_type_upper, $standard_types_upper) && !empty($vehicle_type);
                            ?>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($standard_types as $type): ?>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="vehicle_type" value="<?php echo $type; ?>" id="<?php echo strtolower(str_replace(' ', '', $type)); ?>" <?php echo strcasecmp($vehicle_type, $type) === 0 ? 'checked' : ''; ?> required onchange="toggleOtherVehicle(this.value)">
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

                <!-- Violations (Tabbed Interface) -->
                <div class="section">
                    <h5 class="text-danger"><i data-lucide="alert-triangle" style="width: 20px; height: 20px; margin-right: 8px;"></i>Violation(s) *</h5>

                    <!-- Search Box -->
                    <div class="violation-search-box mb-3">
                        <div class="search-wrapper">
                            <i data-lucide="search" class="search-icon"></i>
                            <input type="text" class="search-input" id="violationSearch" placeholder="Search all violations...">
                            <button class="search-clear" type="button" id="clearSearch" title="Clear search">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                        <small class="search-hint">
                            <i data-lucide="info" style="width: 14px; height: 14px;"></i> Search works across all categories
                        </small>
                    </div>

                    <!-- Tabs Navigation - Modern Pills -->
                    <div class="violation-tabs-wrapper">
                        <div class="violation-tabs" id="violationTabs">
                            <?php
                            $tab_index = 0;
                            foreach ($violation_categories as $cat) {
                                $category_slug = strtolower(str_replace(' ', '-', $cat['category_name']));
                                $active_class = $tab_index === 0 ? ' active' : '';

                                echo "<button class='tab-pill$active_class' data-tab='$category_slug' data-category-id='" . $cat['category_id'] . "' type='button'>";
                                echo "<i data-lucide='" . htmlspecialchars($cat['category_icon']) . "' class='tab-icon'></i>";
                                echo "<span class='tab-label'>" . htmlspecialchars($cat['category_name']) . "</span>";
                                echo "<span class='tab-badge' data-tab='$category_slug'>0</span>";
                                echo "</button>";
                                $tab_index++;
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="violation-tab-content" id="violationTabsContent">
                        <?php
                        $tab_index = 0;

                        foreach ($violation_categories as $cat) {
                            $category_slug = strtolower(str_replace(' ', '-', $cat['category_name']));
                            $active_class = $tab_index === 0 ? ' active' : '';

                            echo "<div class='tab-pane$active_class' data-pane='$category_slug'>";
                            echo "<div class='violations-list'>";

                            // Check if this is "Other" category for custom violation input
                            $isOtherCategory = ($cat['category_name'] === 'Other');

                            if ($isOtherCategory) {
                                // Custom violation
                                echo "<div class='violation-item'>";
                                echo "<div class='custom-checkbox'>";
                                echo "<input type='checkbox' class='checkbox-input' name='other_violation' id='other_violation'>";
                                echo "<label class='checkbox-label' for='other_violation'>";
                                echo "<span class='checkbox-box'></span>";
                                echo "<span class='checkbox-text'>Other Violation (Specify below)</span>";
                                echo "</label>";
                                echo "</div></div>";
                                echo "<input type='text' name='other_violation_input' class='form-control mt-2' id='otherViolationInput' placeholder='Specify other violation' style='display: none;'>";
                            }

                            // Find violations matching this category
                            $violations_found = false;
                            foreach ($violation_types as $v) {
                                // Match violations by category_id
                                if (isset($v['category_id']) && $v['category_id'] == $cat['category_id']) {
                                    $violations_found = true;
                                    $offense_count = isset($offense_counts[$v['violation_type_id']]) ? min((int)$offense_counts[$v['violation_type_id']] + 1, 3) : 1;
                                    $fine_key = "fine_amount_$offense_count";
                                    $offense_suffix = $offense_count == 1 ? 'st' : ($offense_count == 2 ? 'nd' : 'rd');
                                    $label = $v['violation_type'] . " - {$offense_count}{$offense_suffix} Offense (₱" . number_format($v[$fine_key], 2) . ")";
                                    $input_id = 'violation_' . $v['violation_type_id'];
                                    $is_checked = isset($citation_violations[$v['violation_type_id']]) ? 'checked' : '';

                                    echo "<div class='violation-item' data-violation-text='" . htmlspecialchars(strtolower($v['violation_type'])) . "'>";
                                    echo "<div class='custom-checkbox'>";
                                    echo "<input type='checkbox' class='checkbox-input violation-checkbox' name='violations[]' value='" . (int)$v['violation_type_id'] . "' id='$input_id' data-offense='$offense_count' data-tab='$category_slug' $is_checked>";
                                    echo "<label class='checkbox-label' for='$input_id'>";
                                    echo "<span class='checkbox-box'></span>";
                                    echo "<span class='checkbox-text'>" . htmlspecialchars($label) . "</span>";
                                    echo "</label>";
                                    echo "</div></div>";
                                }
                            }

                            if (!$violations_found && !$isOtherCategory) {
                                echo "<div class='empty-state'>";
                                echo "<i data-lucide='inbox' style='width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 8px;'></i>";
                                echo "<p>No violations available in this category.</p>";
                                echo "</div>";
                            }

                            echo "</div></div>";
                            $tab_index++;
                        }
                        ?>
                    </div>

                    <!-- No Results Message -->
                    <div class="no-results" id="noResultsAlert" style="display: none;">
                        <i data-lucide="search" class="no-results-icon"></i>
                        <div class="no-results-text">
                            No violations found matching "<strong id="searchQuery"></strong>"
                            <br><small>Try different keywords</small>
                        </div>
                    </div>

                    <div class="mt-3 remarks">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4"><?php echo htmlspecialchars($citation['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-custom">
                        <i data-lucide="save"></i>
                        <span>Update Citation</span>
                    </button>
                    <a href="citations.php" class="btn btn-outline-secondary">
                        <i data-lucide="x"></i>
                        <span>Cancel</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

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

        // Citation Number Real-Time Validation
        const citationInput = document.getElementById('ticket_number');
        const citationFeedback = document.getElementById('citationFeedback');
        const citationHelpText = document.getElementById('citationHelpText');
        const originalValue = citationInput.getAttribute('data-original-value');
        let checkTimeout;
        let isValidCitation = true;

        function resetValidation() {
            citationInput.classList.remove('is-valid', 'is-invalid');
            citationFeedback.className = 'citation-validation-feedback';
            citationFeedback.innerHTML = '';
        }

        function showValidation(type, message) {
            citationFeedback.className = `citation-validation-feedback ${type}`;

            if (type === 'checking') {
                citationFeedback.innerHTML = `
                    <i data-lucide="loader-2" class="spinner"></i>
                    <span>${message}</span>
                `;
            } else if (type === 'valid') {
                citationInput.classList.remove('is-invalid');
                citationInput.classList.add('is-valid');
                citationFeedback.innerHTML = `
                    <i data-lucide="check-circle"></i>
                    <span>${message}</span>
                `;
            } else if (type === 'invalid') {
                citationInput.classList.remove('is-valid');
                citationInput.classList.add('is-invalid');
                citationFeedback.innerHTML = `
                    <i data-lucide="alert-circle"></i>
                    <span>${message}</span>
                `;
            }

            // Reinitialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function checkCitationDuplicate() {
            const citationNo = citationInput.value.trim().toUpperCase();

            // If value is the same as original, skip validation
            if (citationNo === originalValue) {
                resetValidation();
                citationInput.classList.add('is-valid');
                showValidation('valid', 'Citation number unchanged.');
                isValidCitation = true;
                return;
            }

            // Check length
            if (citationNo.length < 6 || citationNo.length > 8) {
                showValidation('invalid', 'Citation number must be 6 to 8 characters long.');
                isValidCitation = false;
                return;
            }

            // Check format
            if (!/^[A-Z0-9\-]{6,8}$/.test(citationNo)) {
                showValidation('invalid', 'Invalid format. Use only uppercase letters, numbers, and hyphens.');
                isValidCitation = false;
                return;
            }

            // Show checking state
            showValidation('checking', 'Checking availability...');

            // Perform AJAX check
            fetch('../api/check_citation_duplicate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'citation_no=' + encodeURIComponent(citationNo)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.exists) {
                        showValidation('invalid', 'This citation number already exists in the database.');
                        isValidCitation = false;
                    } else {
                        showValidation('valid', 'Citation number is available.');
                        isValidCitation = true;
                    }
                } else {
                    showValidation('invalid', data.error || 'Validation error');
                    isValidCitation = false;
                }
            })
            .catch(error => {
                console.error('Validation error:', error);
                showValidation('invalid', 'Could not verify citation number. Please try again.');
                isValidCitation = false;
            });
        }

        // Auto-uppercase and debounced validation
        if (citationInput && citationFeedback) {
            citationInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();

                clearTimeout(checkTimeout);
                resetValidation();

                if (this.value.trim()) {
                    checkTimeout = setTimeout(() => {
                        checkCitationDuplicate();
                    }, 500);
                } else {
                    isValidCitation = false;
                }
            });

            citationInput.addEventListener('blur', function() {
                if (this.value.trim()) {
                    checkCitationDuplicate();
                }
            });
        }

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

        // ==========================================
        // BARANGAY DROPDOWN HANDLING
        // ==========================================
        const barangaySelect = document.getElementById('barangaySelect');
        const otherBarangayDiv = document.getElementById('otherBarangayDiv');
        const otherBarangayInput = document.getElementById('otherBarangayInput');
        const municipalityDiv = document.getElementById('municipalityDiv');
        const provinceDiv = document.getElementById('provinceDiv');
        const municipalityInput = document.getElementById('municipalityInput');
        const provinceInput = document.getElementById('provinceInput');

        if (barangaySelect && otherBarangayDiv && otherBarangayInput && municipalityDiv && provinceDiv) {
            barangaySelect.addEventListener('change', () => {
                const isOther = barangaySelect.value === 'Other';
                if (isOther) {
                    otherBarangayDiv.style.display = 'block';
                    otherBarangayInput.required = true;
                    otherBarangayInput.focus();
                    municipalityDiv.style.display = 'block';
                    provinceDiv.style.display = 'block';
                    if (municipalityInput) {
                        municipalityInput.value = '';
                        municipalityInput.removeAttribute('readonly');
                    }
                    if (provinceInput) {
                        provinceInput.value = '';
                        provinceInput.removeAttribute('readonly');
                    }
                } else {
                    otherBarangayDiv.style.display = 'none';
                    otherBarangayInput.required = false;
                    otherBarangayInput.value = '';
                    if (barangaySelect.value) {
                        municipalityDiv.style.display = 'block';
                        provinceDiv.style.display = 'block';
                        if (municipalityInput) {
                            municipalityInput.value = 'Baggao';
                            municipalityInput.setAttribute('readonly', true);
                        }
                        if (provinceInput) {
                            provinceInput.value = 'Cagayan';
                            provinceInput.setAttribute('readonly', true);
                        }
                    } else {
                        municipalityDiv.style.display = 'none';
                        provinceDiv.style.display = 'none';
                        if (municipalityInput) municipalityInput.value = '';
                        if (provinceInput) provinceInput.value = '';
                    }
                }
            });
        }

        // ==========================================
        // VIOLATION TABS AND SEARCH FUNCTIONALITY
        // ==========================================
        function initViolationTabs() {
            const searchInput = document.getElementById('violationSearch');
            const clearSearchBtn = document.getElementById('clearSearch');
            const tabContent = document.getElementById('violationTabsContent');
            const noResultsAlert = document.getElementById('noResultsAlert');
            const searchQuerySpan = document.getElementById('searchQuery');
            const tabButtons = document.querySelectorAll('.tab-pill');
            const tabPanes = document.querySelectorAll('.tab-pane');

            if (!searchInput || !tabContent) {
                return;
            }

            // Tab switching
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // Remove active from all tabs
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanes.forEach(pane => pane.classList.remove('active'));

                    // Add active to clicked tab
                    this.classList.add('active');
                    const targetPane = document.querySelector(`[data-pane="${targetTab}"]`);
                    if (targetPane) {
                        targetPane.classList.add('active');
                    }

                    // Reinitialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            });

            // Search functionality
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                filterViolations(query);
            });

            // Clear search button
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    filterViolations('');
                    searchInput.focus();
                });
            }

            function filterViolations(query) {
                const allItems = tabContent.querySelectorAll('.violation-item');
                let totalVisible = 0;
                let visiblePerTab = {};

                // Initialize counters for each tab
                tabPanes.forEach(pane => {
                    const tabId = pane.getAttribute('data-pane');
                    visiblePerTab[tabId] = 0;
                });

                allItems.forEach(item => {
                    const violationText = item.getAttribute('data-violation-text') || '';
                    const label = item.querySelector('.checkbox-text');
                    const labelText = label ? label.textContent.toLowerCase() : '';

                    // Check if item matches search query
                    const matches = query === '' || violationText.includes(query) || labelText.includes(query);

                    if (matches) {
                        item.classList.remove('hidden');
                        totalVisible++;

                        // Count visible items per tab
                        const parentPane = item.closest('.tab-pane');
                        if (parentPane) {
                            const tabId = parentPane.getAttribute('data-pane');
                            visiblePerTab[tabId]++;
                        }
                    } else {
                        item.classList.add('hidden');
                    }
                });

                // Show/hide no results message
                if (totalVisible === 0 && query !== '') {
                    noResultsAlert.style.display = 'flex';
                    if (searchQuerySpan) {
                        searchQuerySpan.textContent = query;
                    }
                } else {
                    noResultsAlert.style.display = 'none';
                }

                // If searching, switch to first tab with results
                if (query !== '' && totalVisible > 0) {
                    for (let tabId in visiblePerTab) {
                        if (visiblePerTab[tabId] > 0) {
                            const firstTabWithResults = document.querySelector(`[data-tab="${tabId}"]`);
                            if (firstTabWithResults && !firstTabWithResults.classList.contains('active')) {
                                firstTabWithResults.click();
                            }
                            break;
                        }
                    }
                }
            }

            // Tab badge counts
            function updateTabCounts() {
                const badges = document.querySelectorAll('.tab-badge');

                badges.forEach(badge => {
                    const tabName = badge.getAttribute('data-tab');
                    const checkboxes = tabContent.querySelectorAll(`.violation-checkbox[data-tab="${tabName}"]:checked`);
                    const count = checkboxes.length;

                    badge.textContent = count;

                    if (count > 0) {
                        badge.classList.add('has-selections');
                    } else {
                        badge.classList.remove('has-selections');
                    }
                });
            }

            // Listen for checkbox changes to update counts
            const allCheckboxes = tabContent.querySelectorAll('.violation-checkbox');
            allCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateTabCounts);
            });

            // Initialize counts on page load
            updateTabCounts();

            // Other violation checkbox
            const otherViolationCheckbox = document.getElementById('other_violation');
            const otherViolationInput = document.getElementById('otherViolationInput');
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
        }

        // Initialize violation tabs
        initViolationTabs();

        // Form submission
        document.getElementById('editCitationForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate citation number
            if (!isValidCitation) {
                Swal.fire({
                    title: 'Invalid Citation Number',
                    text: 'Please enter a valid and unique citation number before submitting.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
                citationInput.focus();
                return;
            }

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
            .then(response => {
                const status = response.status;
                return response.json().then(data => ({status, data}));
            })
            .then(({status, data}) => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        if (data.new_csrf_token) {
                            csrfTokenInput.value = data.new_csrf_token;
                        }
                        window.location.href = 'citations.php';
                    });
                } else if (status === 409 && data.error_type === 'duplicate_citation') {
                    // Show detailed duplicate citation error
                    const duplicateInfo = data.duplicate_info || {};
                    const createdDate = duplicateInfo.created_at ?
                        new Date(duplicateInfo.created_at).toLocaleString() : 'Unknown';

                    Swal.fire({
                        title: 'Citation Already Exists!',
                        html: `
                            <div style="text-align: left; padding: 15px;">
                                <p style="font-size: 1.1rem; margin-bottom: 15px;">
                                    <strong>Citation Number:</strong>
                                    <span style="color: #dc3545; font-family: monospace; font-size: 1.2rem;">
                                        ${duplicateInfo.ticket_number || 'Unknown'}
                                    </span>
                                </p>
                                <p style="color: #6c757d;">
                                    <strong>Previously created:</strong> ${createdDate}
                                </p>
                                <hr style="margin: 15px 0;">
                                <p style="font-size: 0.95rem; color: #495057;">
                                    This citation number has already been used in the system.
                                    Please use a different citation number.
                                </p>
                            </div>
                        `,
                        icon: 'warning',
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: 'OK, I\'ll Change It',
                        width: '600px'
                    }).then(() => {
                        const citationInput = document.getElementById('ticket_number');
                        if (citationInput) {
                            citationInput.focus();
                            citationInput.select();
                        }
                    });
                    if (data.new_csrf_token) {
                        csrfTokenInput.value = data.new_csrf_token;
                    }
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'An error occurred while updating the citation.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                    if (data.new_csrf_token) {
                        csrfTokenInput.value = data.new_csrf_token;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Error updating citation: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            });
        });
    });
    </script>
</body>
</html>
