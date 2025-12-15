<?php
/**
 * LTO Gattaran Branch - Driver Citation Search
 * Read-only search interface for LTO staff to view driver violation records
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Require LTO staff or admin access
require_lto_staff();
check_session_timeout();

$pdo = getPDO();
$page_title = "LTO Gattaran - Driver Search";

// Initialize variables
$searched = false;
$search_term = '';
$search_type = 'all';
$driver_info = null;
$unpaid_citations = [];
$all_citations = [];
$summary = [
    'total_citations' => 0,
    'unpaid_count' => 0,
    'total_amount_owed' => 0
];

// Process search if form submitted
if (isset($_GET['search']) && !empty($_GET['search_term'])) {
    $searched = true;
    $search_term = sanitize($_GET['search_term']);
    $search_type = sanitize($_GET['search_type'] ?? 'all');

    // Call API endpoint to get results
    $api_url = BASE_PATH . '/api/lto/search_driver.php?' . http_build_query([
        'search_term' => $search_term,
        'search_type' => $search_type
    ]);

    // For server-side search, we'll do it directly here instead of API call
    // Build WHERE clause
    $where_clauses = [];
    $params = [];

    switch ($search_type) {
        case 'license':
            $where_clauses[] = "c.license_number LIKE ?";
            $params[] = "%{$search_term}%";
            break;
        case 'name':
            $where_clauses[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            break;
        case 'plate':
            $where_clauses[] = "c.plate_mv_engine_chassis_no LIKE ?";
            $params[] = "%{$search_term}%";
            break;
        case 'all':
        default:
            $where_clauses[] = "(c.license_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.plate_mv_engine_chassis_no LIKE ?)";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            break;
    }

    $where_sql = implode(" AND ", $where_clauses);

    // Get unpaid citations
    try {
        $unpaid_sql = "
            SELECT
                c.citation_id,
                c.ticket_number,
                c.first_name,
                c.last_name,
                c.middle_initial,
                c.license_number,
                c.date_of_birth,
                c.age,
                c.barangay,
                c.municipality,
                c.province,
                c.plate_mv_engine_chassis_no,
                c.apprehension_datetime,
                c.place_of_apprehension,
                c.status,
                c.total_fine,
                GROUP_CONCAT(vt.violation_type ORDER BY vt.violation_type SEPARATOR ', ') as violations,
                COUNT(v.violation_id) as violation_count
            FROM citations c
            LEFT JOIN violations v ON c.citation_id = v.citation_id
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            WHERE c.status = 'pending' AND {$where_sql}
            GROUP BY c.citation_id
            ORDER BY c.apprehension_datetime DESC
        ";
        $stmt = db_query($unpaid_sql, $params);
        $unpaid_citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all citations
        $all_sql = "
            SELECT
                c.citation_id,
                c.ticket_number,
                c.first_name,
                c.last_name,
                c.middle_initial,
                c.license_number,
                c.date_of_birth,
                c.age,
                c.barangay,
                c.municipality,
                c.province,
                c.plate_mv_engine_chassis_no,
                c.apprehension_datetime,
                c.place_of_apprehension,
                c.status,
                c.total_fine,
                c.payment_date,
                GROUP_CONCAT(vt.violation_type ORDER BY vt.violation_type SEPARATOR ', ') as violations,
                COUNT(v.violation_id) as violation_count
            FROM citations c
            LEFT JOIN violations v ON c.citation_id = v.citation_id
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            WHERE {$where_sql}
            GROUP BY c.citation_id
            ORDER BY c.apprehension_datetime DESC
        ";
        $stmt = db_query($all_sql, $params);
        $all_citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary
        $summary['total_citations'] = count($all_citations);
        $summary['unpaid_count'] = count($unpaid_citations);
        $summary['total_amount_owed'] = array_sum(array_column($unpaid_citations, 'total_fine'));

        // Get driver info
        if (!empty($all_citations)) {
            $first = $all_citations[0];
            $driver_info = [
                'first_name' => $first['first_name'] ?? '',
                'last_name' => $first['last_name'] ?? '',
                'middle_initial' => $first['middle_initial'] ?? '',
                'name' => trim($first['first_name'] . ' ' . ($first['middle_initial'] ? $first['middle_initial'] . '. ' : '') . $first['last_name']),
                'license_number' => $first['license_number'] ?? 'N/A',
                'date_of_birth' => $first['date_of_birth'] ?? null,
                'age' => $first['age'] ?? null,
                'barangay' => $first['barangay'] ?? '',
                'municipality' => $first['municipality'] ?? '',
                'province' => $first['province'] ?? '',
                'address' => trim(($first['barangay'] ?? '') . ', ' . ($first['municipality'] ?? '') . ', ' . ($first['province'] ?? '')),
                'plate_number' => $first['plate_mv_engine_chassis_no'] ?? 'N/A',
                'total_citations' => count($all_citations),
                'unpaid_count' => count($unpaid_citations),
                'total_amount_owed' => array_sum(array_column($unpaid_citations, 'total_fine'))
            ];
        }
    } catch (Exception $e) {
        set_flash('Search error: ' . $e->getMessage(), 'danger');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Traffic Citation System</title>

    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Custom Styles -->
    <style>
        /* ===== ENHANCED DESIGN SYSTEM: MODERN, FLAT, ELEVATED ===== */

        :root {
            /* Color Palette - Vibrant & Professional */
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --success: #10b981;
            --success-dark: #059669;
            --info: #06b6d4;
            --info-dark: #0891b2;
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --secondary: #64748b;
            --secondary-dark: #475569;

            /* Neutral Palette */
            --white: #ffffff;
            --off-white: #f8fafc;
            --light-gray: #f1f5f9;
            --medium-gray: #e2e8f0;
            --border-gray: #cbd5e1;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --text-label: #334155;

            /* Shadows for depth */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--off-white);
            color: var(--text-dark);
            font-size: clamp(0.875rem, 2.3vw, 0.9rem);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ===== HEADER WITH LOGOS ===== */
        .header-section {
            background: var(--white);
            padding: 20px 25px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .header-logos {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 52px;
            height: 52px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .logo-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            line-height: 1.3;
        }

        .header-title {
            text-align: center;
            flex: 1;
        }

        .header-title h1 {
            font-size: clamp(1.25rem, 3vw, 1.5rem);
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .header-title p {
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            color: var(--text-muted);
            font-weight: 400;
        }

        .header-datetime {
            text-align: right;
            min-width: 150px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        .date-display {
            font-size: clamp(0.75rem, 2vw, 0.85rem);
            font-weight: 600;
            color: var(--text-dark);
        }

        .time-display {
            font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            font-weight: 700;
            color: var(--primary);
            font-variant-numeric: tabular-nums;
        }

        /* ===== MAIN CONTAINER ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 30px;
        }

        /* ===== SEARCH CARD ===== */
        .search-card {
            background: var(--white);
            border: 1px solid var(--border-gray);
            border-radius: 8px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
        }

        .search-card h5 {
            font-size: clamp(1rem, 2.8vw, 1.15rem);
            font-weight: 600;
            color: var(--text-label);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== FORM ELEMENTS ===== */
        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-label);
            margin-bottom: 6px;
            font-size: clamp(0.8rem, 2vw, 0.85rem);
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border-gray);
            border-radius: 6px;
            font-size: clamp(0.85rem, 2.3vw, 0.9rem);
            font-family: 'Inter', sans-serif;
            background: var(--white);
            color: var(--text-dark);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        /* ===== BUTTONS - MODERN FLAT DESIGN ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 6px;
            font-size: clamp(0.85rem, 2.3vw, 0.9rem);
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--white);
            border-color: var(--secondary);
        }

        .btn-secondary:hover {
            background: var(--secondary-dark);
            border-color: var(--secondary-dark);
        }

        .btn-info {
            background: var(--info);
            color: var(--white);
            border-color: var(--info);
        }

        .btn-info:hover {
            background: var(--info-dark);
            border-color: var(--info-dark);
        }

        /* ===== DRIVER INFO CARD - ENHANCED ===== */
        .driver-info-card {
            background: var(--white);
            border: 1px solid var(--border-gray);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: clamp(22px, 4vw, 28px);
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
        }

        .driver-info-card h4 {
            font-size: clamp(1.25rem, 3.5vw, 1.4rem);
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--light-gray);
        }

        .driver-info-card p {
            font-size: clamp(0.88rem, 2.3vw, 0.95rem);
            color: var(--text-dark);
            margin: 10px 0;
            line-height: 1.6;
        }

        .driver-info-card strong {
            color: var(--text-label);
            font-weight: 600;
            min-width: 130px;
            display: inline-block;
        }

        /* ===== DRIVER INFO GRID ===== */
        .driver-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px 25px;
            margin-top: 15px;
        }

        .driver-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .driver-info-label {
            font-size: clamp(0.75rem, 2vw, 0.8rem);
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .driver-info-value {
            font-size: clamp(0.9rem, 2.3vw, 1rem);
            color: var(--text-dark);
            font-weight: 600;
        }

        /* ===== STATS BAR ===== */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            padding: 15px;
            background: var(--light-gray);
            border-radius: 6px;
            margin-top: 15px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-label {
            font-size: clamp(0.7rem, 1.8vw, 0.75rem);
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: clamp(1.25rem, 3vw, 1.5rem);
            font-weight: 700;
        }

        .stat-value.primary { color: var(--primary); }
        .stat-value.danger { color: var(--danger); }
        .stat-value.warning { color: var(--warning-dark); }

        /* ===== RESULTS CARD - ENHANCED ===== */
        .results-card {
            background: var(--white);
            border: 1px solid var(--border-gray);
            border-radius: 8px;
            padding: clamp(22px, 4vw, 28px);
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
        }

        .results-card h5 {
            font-size: clamp(1.1rem, 3vw, 1.25rem);
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--light-gray);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* ===== TABLE DESIGN - MODERN & CLEAN ===== */
        .table-container {
            overflow-x: auto;
            border: 1px solid var(--border-gray);
            border-radius: 6px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(0.8rem, 2.2vw, 0.875rem);
            background: var(--white);
        }

        .table thead th {
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
            padding: 13px 15px;
            text-align: left;
            border-bottom: 2px solid var(--primary-dark);
            white-space: nowrap;
            text-transform: uppercase;
            font-size: clamp(0.75rem, 2vw, 0.78rem);
            letter-spacing: 0.3px;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--medium-gray);
        }

        .table tbody tr:last-child {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: var(--light-gray);
        }

        .table tbody td {
            padding: 12px 15px;
            color: var(--text-dark);
            vertical-align: middle;
        }

        .table tbody td:first-child {
            font-weight: 600;
        }

        /* ===== BADGES - CLEAN FLAT DESIGN ===== */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: clamp(0.7rem, 1.8vw, 0.75rem);
            font-weight: 600;
            border: 1px solid;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge.bg-danger {
            background: var(--danger);
            color: var(--white);
            border-color: var(--danger);
        }

        .badge.bg-success {
            background: var(--success);
            color: var(--white);
            border-color: var(--success);
        }

        .badge.bg-warning {
            background: var(--warning);
            color: var(--text-dark);
            border-color: var(--warning);
        }

        .badge.bg-secondary {
            background: var(--secondary);
            color: var(--white);
            border-color: var(--secondary);
        }

        .badge.bg-dark {
            background: var(--text-dark);
            color: var(--white);
            border-color: var(--text-dark);
        }

        /* ===== ALERT MESSAGES ===== */
        .alert {
            padding: 14px 18px;
            border-radius: 6px;
            border: 1px solid;
            margin-bottom: 18px;
            font-size: clamp(0.85rem, 2.3vw, 0.9rem);
            font-weight: 500;
        }

        .alert-success {
            background: #d1e7dd;
            border-color: var(--success);
            color: #0a5028;
            border-left-width: 4px;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: var(--danger);
            color: #7a1f1f;
            border-left-width: 4px;
        }

        /* ===== NO RESULTS STATE ===== */
        .no-results {
            text-align: center;
            padding: 60px 25px;
            color: var(--text-muted);
        }

        .no-results svg {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-results h4 {
            font-size: clamp(1.15rem, 3vw, 1.3rem);
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 12px;
        }

        .no-results p {
            font-size: clamp(0.85rem, 2.3vw, 0.95rem);
            color: var(--text-muted);
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 992px) {
            #searchForm > div {
                grid-template-columns: 1fr !important;
            }

            #searchForm button {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .header-logos {
                flex-direction: column;
                gap: 15px;
                width: 100%;
            }

            .header-title {
                text-align: center;
            }

            .header-datetime {
                text-align: center;
                width: 100%;
                align-items: center;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .results-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .table {
                font-size: 0.8rem;
            }

            .table thead th,
            .table tbody td {
                padding: 8px 10px;
            }
        }

        /* ===== PRINT STYLES ===== */
        @media print {
            .search-card,
            .btn {
                display: none !important;
            }

            body {
                background: white;
            }

            .header-section {
                border-bottom: 2px solid #000;
            }
        }

        /* ===== UTILITY CLASSES ===== */
        .mt-4 { margin-top: 1.5rem; }
        .mb-3 { margin-bottom: 1rem; }
        .me-2 { margin-right: 0.5rem; }
        .gap-3 { gap: 1rem; }
        .text-muted { color: var(--text-muted); }
        .text-dark { color: var(--text-dark); }
        .fw-bold { font-weight: 600; }
        .d-flex { display: flex; }
        .align-items-center { align-items: center; }
        .justify-content-between { justify-content: space-between; }


        /* ===== ROW UTILITY (GRID FORM LAYOUT) ===== */
        .row {
            display: grid;
            gap: 15px;
        }

        .row.g-3 {
            gap: 1rem;
        }

        .col-md-6 {
            grid-column: span 1;
        }

        @media (min-width: 768px) {
            .row:not(.single-col) {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Header Section with Logos -->
    <header class="header-section">
        <div class="header-content">
            <div class="header-logos">
                <!-- LTO Logo -->
                <div class="logo-container">
                    <img src="../assets/img/LOGO1.png" alt="LTO Logo" class="logo">
                    <!-- <div class="logo-text">Land Transportation Office<br>Gattaran Branch</div> -->
                </div>

                <!-- LGU Logo -->
                <div class="logo-container">
                    <img src="../assets/img/TMG PNG.png" alt="LGU Logo" class="logo">
                    <!-- <div class="logo-text">Local Government Unit<br>Municipality</div> -->
                </div>
            </div>

            <div class="header-title">
                <h1>Driver Citation Search System</h1>
                <p>Search and view driver violation records</p>
            </div>

            <div class="header-datetime">
                <div id="currentDate" class="date-display"></div>
                <div id="currentTime" class="time-display"></div>
                <a href="../public/logout.php" class="btn btn-secondary" style="margin-top: 10px; font-size: 0.8rem; padding: 8px 14px;">
                    <i data-lucide="log-out"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($_SESSION['flash']['message']); ?>
                <?php unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="search-card">
            <form method="GET" action="" id="searchForm">
                <div style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 12px; align-items: end;">
                    <div>
                        <label for="search_term" class="form-label">Search Driver Records</label>
                        <input
                            type="text"
                            class="form-control"
                            id="search_term"
                            name="search_term"
                            placeholder="Enter license number, name, or plate number..."
                            value="<?php echo htmlspecialchars($search_term); ?>"
                            required>
                    </div>
                    <div>
                        <label for="search_type" class="form-label">Filter By</label>
                        <select class="form-select" id="search_type" name="search_type">
                            <option value="all" <?php echo ($search_type === 'all') ? 'selected' : ''; ?>>All Fields</option>
                            <option value="license" <?php echo ($search_type === 'license') ? 'selected' : ''; ?>>License Number</option>
                            <option value="name" <?php echo ($search_type === 'name') ? 'selected' : ''; ?>>Driver Name</option>
                            <option value="plate" <?php echo ($search_type === 'plate') ? 'selected' : ''; ?>>Plate Number</option>
                        </select>
                    </div>
                    <button type="submit" name="search" class="btn btn-primary">
                        <i data-lucide="search"></i>
                        Search
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearForm()">
                        <i data-lucide="x"></i>
                        Clear
                    </button>
                </div>
            </form>
        </div>

        <?php if ($searched): ?>
            <?php if (!empty($driver_info)): ?>
                <!-- Driver Information -->
                <div class="driver-info-card">
                    <h4>
                        <i data-lucide="user"></i>
                        Driver Information & Citation Summary
                    </h4>

                    <div class="driver-info-grid">
                        <div class="driver-info-item">
                            <div class="driver-info-label">Full Name</div>
                            <div class="driver-info-value"><?php echo htmlspecialchars($driver_info['name']); ?></div>
                        </div>

                        <div class="driver-info-item">
                            <div class="driver-info-label">License Number</div>
                            <div class="driver-info-value"><?php echo htmlspecialchars($driver_info['license_number']); ?></div>
                        </div>

                        <div class="driver-info-item">
                            <div class="driver-info-label">Plate/MV Number</div>
                            <div class="driver-info-value"><?php echo htmlspecialchars($driver_info['plate_number']); ?></div>
                        </div>

                        <?php if ($driver_info['date_of_birth']): ?>
                        <div class="driver-info-item">
                            <div class="driver-info-label">Date of Birth</div>
                            <div class="driver-info-value"><?php echo date('F d, Y', strtotime($driver_info['date_of_birth'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($driver_info['age']): ?>
                        <div class="driver-info-item">
                            <div class="driver-info-label">Age</div>
                            <div class="driver-info-value"><?php echo htmlspecialchars($driver_info['age']); ?> years old</div>
                        </div>
                        <?php endif; ?>

                        <div class="driver-info-item">
                            <div class="driver-info-label">Barangay</div>
                            <div class="driver-info-value"><?php echo htmlspecialchars($driver_info['barangay'] ?: 'N/A'); ?></div>
                        </div>

                        <div class="driver-info-item">
                            <div class="driver-info-label">Municipality</div>
                            <div class="driver-info-value"><?php echo htmlspecialchars($driver_info['municipality'] ?: 'N/A'); ?></div>
                        </div>

                        <div class="driver-info-item">
                            <div class="driver-info-label">Province</div>
                            <div class="driver-info-value"><?php echo htmlspecialchars($driver_info['province'] ?: 'N/A'); ?></div>
                        </div>
                    </div>

                    <!-- Stats Bar -->
                    <div class="stats-bar">
                        <div class="stat-item">
                            <div class="stat-label">Total Citations</div>
                            <div class="stat-value primary"><?php echo number_format($driver_info['total_citations']); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Unpaid Citations</div>
                            <div class="stat-value danger"><?php echo number_format($driver_info['unpaid_count']); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Total Amount Owed</div>
                            <div class="stat-value warning">₱<?php echo number_format($driver_info['total_amount_owed'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Unpaid Citations -->
                <div class="results-card">
                    <div class="results-header">
                        <h5>
                            <i data-lucide="alert-circle"></i>
                            Unpaid Citations (<?php echo count($unpaid_citations); ?>)
                        </h5>
                        <?php if (!empty($unpaid_citations)): ?>
                            <button onclick="window.print()" class="btn btn-info">
                                <i data-lucide="printer"></i>
                                Print
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($unpaid_citations)): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Date</th>
                                        <th>Violations</th>
                                        <th>Place</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unpaid_citations as $citation): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($citation['ticket_number']); ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($citation['apprehension_datetime'])); ?></td>
                                            <td><?php echo htmlspecialchars($citation['violations'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($citation['place_of_apprehension']); ?></td>
                                            <td><strong>₱<?php echo number_format($citation['total_fine'], 2); ?></strong></td>
                                            <td><span class="badge bg-danger">Pending</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            No unpaid citations found. Driver has no pending violations.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Complete History -->
                <div class="results-card">
                    <h5>
                        <i data-lucide="history"></i>
                        Complete Citation History (<?php echo count($all_citations); ?>)
                    </h5>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Date</th>
                                    <th>Violations</th>
                                    <th>Place</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Paid Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_citations as $citation): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($citation['ticket_number']); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($citation['apprehension_datetime'])); ?></td>
                                        <td><?php echo htmlspecialchars($citation['violations'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($citation['place_of_apprehension']); ?></td>
                                        <td><strong>₱<?php echo number_format($citation['total_fine'], 2); ?></strong></td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => '<span class="badge bg-danger">Pending</span>',
                                                'paid' => '<span class="badge bg-success">Paid</span>',
                                                'contested' => '<span class="badge bg-warning">Contested</span>',
                                                'dismissed' => '<span class="badge bg-secondary">Dismissed</span>',
                                                'void' => '<span class="badge bg-dark">Void</span>'
                                            ];
                                            echo $status_badges[$citation['status']] ?? '<span class="badge bg-secondary">' . htmlspecialchars($citation['status']) . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($citation['payment_date']) {
                                                echo date('M d, Y', strtotime($citation['payment_date']));
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Results -->
                <div class="results-card">
                    <div class="no-results">
                        <i data-lucide="search-x"></i>
                        <h4>No Results Found</h4>
                        <p>No citations found for "<?php echo htmlspecialchars($search_term); ?>"</p>
                        <p class="text-muted">Try adjusting your search criteria or search type</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Initialize Lucide Icons -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            updateDateTime();
            setInterval(updateDateTime, 1000); // Update every second
        });

        function updateDateTime() {
            const now = new Date();

            // Format date: December 11, 2025
            const dateOptions = {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            const dateString = now.toLocaleDateString('en-US', dateOptions);

            // Format time: 02:30:45 PM
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const timeString = now.toLocaleTimeString('en-US', timeOptions);

            document.getElementById('currentDate').textContent = dateString;
            document.getElementById('currentTime').textContent = timeString;
        }

        function clearForm() {
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
