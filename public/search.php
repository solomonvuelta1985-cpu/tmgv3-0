<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login and check session timeout
require_login();
check_session_timeout();

$pdo = getPDO();
$citations = [];
$searched = false;
$total_results = 0;

// Get violation types for dropdown
$violation_types = [];
if ($pdo) {
    $stmt = db_query("SELECT violation_type_id, violation_type, fine_amount_1 FROM violation_types WHERE is_active = 1 ORDER BY violation_type");
    $violation_types = $stmt->fetchAll();
}

// Get officers for dropdown
$officers = [];
if ($pdo) {
    $stmt = db_query("SELECT officer_id, officer_name, badge_number, position FROM apprehending_officers WHERE is_active = 1 ORDER BY officer_name");
    $officers = $stmt->fetchAll();
}

// Process search
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $searched = true;

    $where_clauses = [];
    $params = [];

    // Ticket Number
    if (!empty($_GET['ticket_number'])) {
        $where_clauses[] = "c.ticket_number LIKE ?";
        $params[] = "%" . sanitize($_GET['ticket_number']) . "%";
    }

    // Driver Name
    if (!empty($_GET['driver_name'])) {
        $name = sanitize($_GET['driver_name']);
        $where_clauses[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
        $params[] = "%$name%";
        $params[] = "%$name%";
        $params[] = "%$name%";
    }

    // License Number
    if (!empty($_GET['license_number'])) {
        $where_clauses[] = "c.license_number LIKE ?";
        $params[] = "%" . sanitize($_GET['license_number']) . "%";
    }

    // Plate/MV/Engine/Chassis Number
    if (!empty($_GET['plate_number'])) {
        $where_clauses[] = "c.plate_mv_engine_chassis_no LIKE ?";
        $params[] = "%" . sanitize($_GET['plate_number']) . "%";
    }

    // Status
    if (!empty($_GET['status'])) {
        $where_clauses[] = "c.status = ?";
        $params[] = sanitize($_GET['status']);
    }

    // Date Range
    if (!empty($_GET['date_from'])) {
        $where_clauses[] = "DATE(c.apprehension_datetime) >= ?";
        $params[] = sanitize($_GET['date_from']);
    }

    if (!empty($_GET['date_to'])) {
        $where_clauses[] = "DATE(c.apprehension_datetime) <= ?";
        $params[] = sanitize($_GET['date_to']);
    }

    // Fine Range
    if (!empty($_GET['fine_min'])) {
        $where_clauses[] = "c.total_fine >= ?";
        $params[] = (float)$_GET['fine_min'];
    }

    if (!empty($_GET['fine_max'])) {
        $where_clauses[] = "c.total_fine <= ?";
        $params[] = (float)$_GET['fine_max'];
    }

    // Violation Type
    if (!empty($_GET['violation_type'])) {
        $where_clauses[] = "EXISTS (SELECT 1 FROM violations v WHERE v.citation_id = c.citation_id AND v.violation_type_id = ?)";
        $params[] = (int)$_GET['violation_type'];
    }

    // Apprehending Officer
    if (!empty($_GET['officer_id'])) {
        // Get officer name from ID
        $officer_stmt = db_query("SELECT officer_name FROM apprehending_officers WHERE officer_id = ?", [(int)$_GET['officer_id']]);
        $officer_row = $officer_stmt->fetch();
        if ($officer_row) {
            $where_clauses[] = "c.apprehension_officer = ?";
            $params[] = $officer_row['officer_name'];
        }
    }

    // Place of Apprehension
    if (!empty($_GET['place'])) {
        $where_clauses[] = "c.place_of_apprehension LIKE ?";
        $params[] = "%" . sanitize($_GET['place']) . "%";
    }

    // Age Range
    if (!empty($_GET['age_min'])) {
        $where_clauses[] = "c.age >= ?";
        $params[] = (int)$_GET['age_min'];
    }

    if (!empty($_GET['age_max'])) {
        $where_clauses[] = "c.age <= ?";
        $params[] = (int)$_GET['age_max'];
    }

    // Build the query
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Get total count
    $count_sql = "SELECT COUNT(DISTINCT c.citation_id) as total FROM citations c $where_sql";
    $stmt = db_query($count_sql, $params);
    $total_results = $stmt->fetch()['total'] ?? 0;

    // Limit results
    $limit = isset($_GET['limit']) ? min(500, max(10, (int)$_GET['limit'])) : 100;

    // Get citations
    $sql = "SELECT c.*,
            GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violations
            FROM citations c
            LEFT JOIN violations v ON c.citation_id = v.citation_id
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            $where_sql
            GROUP BY c.citation_id
            ORDER BY c.created_at DESC
            LIMIT $limit";

    if ($pdo && !empty($where_clauses)) {
        $stmt = db_query($sql, $params);
        $citations = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Search - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .search-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .search-header {
            border-bottom: 2px solid #1e3c72;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .search-header h4 {
            color: #1e3c72;
            margin: 0;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        .btn-search {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            color: white;
            padding: 10px 30px;
            font-weight: 600;
        }
        .btn-search:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            color: white;
        }
        .results-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .results-table th {
            background: #1e3c72;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        .results-table td {
            vertical-align: middle;
            font-size: 0.85rem;
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 5px 10px;
        }
        .no-results {
            text-align: center;
            padding: 50px;
            color: #6c757d;
        }
        .search-tips {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
        }
        .export-btn {
            margin-left: 10px;
        }
        .collapsible-section {
            margin-bottom: 15px;
        }
        .section-toggle {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px 15px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-toggle:hover {
            background: #e9ecef;
        }
        .section-content {
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 5px 5px;
            padding: 15px;
            background: white;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-search"></i> Advanced Search</h2>
                <?php if ($searched && count($citations) > 0): ?>
                <button type="button" class="btn btn-success" onclick="exportResults()">
                    <i class="fas fa-file-excel"></i> Export Results
                </button>
                <?php endif; ?>
            </div>

            <!-- Search Tips -->
            <div class="search-tips">
                <strong><i class="fas fa-lightbulb"></i> Search Tips:</strong>
                <ul class="mb-0 mt-2">
                    <li>Use partial matches - you don't need to enter the complete value</li>
                    <li>Combine multiple filters to narrow down results</li>
                    <li>Leave fields empty to ignore them in the search</li>
                    <li>Date range searches are inclusive of both dates</li>
                </ul>
            </div>

            <!-- Search Form -->
            <form method="GET" action="" id="searchForm">
                <div class="search-card">
                    <div class="search-header">
                        <h4><i class="fas fa-filter"></i> Search Filters</h4>
                    </div>

                    <!-- Basic Search -->
                    <div class="collapsible-section">
                        <div class="section-toggle" onclick="toggleSection('basicSearch')">
                            <span><i class="fas fa-user"></i> Basic Information</span>
                            <i class="fas fa-chevron-down" id="basicSearchIcon"></i>
                        </div>
                        <div class="section-content" id="basicSearch">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Ticket Number</label>
                                    <input type="text" class="form-control" name="ticket_number"
                                           value="<?php echo htmlspecialchars($_GET['ticket_number'] ?? ''); ?>"
                                           placeholder="e.g., TCT-2024-001">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Driver Name</label>
                                    <input type="text" class="form-control" name="driver_name"
                                           value="<?php echo htmlspecialchars($_GET['driver_name'] ?? ''); ?>"
                                           placeholder="First, middle, or last name">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">License Number</label>
                                    <input type="text" class="form-control" name="license_number"
                                           value="<?php echo htmlspecialchars($_GET['license_number'] ?? ''); ?>"
                                           placeholder="Driver's license number">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Plate/MV/Engine/Chassis No.</label>
                                    <input type="text" class="form-control" name="plate_number"
                                           value="<?php echo htmlspecialchars($_GET['plate_number'] ?? ''); ?>"
                                           placeholder="Vehicle identification">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Age From</label>
                                    <input type="number" class="form-control" name="age_min" min="0" max="120"
                                           value="<?php echo htmlspecialchars($_GET['age_min'] ?? ''); ?>"
                                           placeholder="Min age">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Age To</label>
                                    <input type="number" class="form-control" name="age_max" min="0" max="120"
                                           value="<?php echo htmlspecialchars($_GET['age_max'] ?? ''); ?>"
                                           placeholder="Max age">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Place of Apprehension</label>
                                    <input type="text" class="form-control" name="place"
                                           value="<?php echo htmlspecialchars($_GET['place'] ?? ''); ?>"
                                           placeholder="Location">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Citation Details -->
                    <div class="collapsible-section">
                        <div class="section-toggle" onclick="toggleSection('citationDetails')">
                            <span><i class="fas fa-file-alt"></i> Citation Details</span>
                            <i class="fas fa-chevron-down" id="citationDetailsIcon"></i>
                        </div>
                        <div class="section-content" id="citationDetails">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="">-- Any Status --</option>
                                        <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?php echo ($_GET['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="contested" <?php echo ($_GET['status'] ?? '') === 'contested' ? 'selected' : ''; ?>>Contested</option>
                                        <option value="dismissed" <?php echo ($_GET['status'] ?? '') === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                                        <option value="void" <?php echo ($_GET['status'] ?? '') === 'void' ? 'selected' : ''; ?>>Void</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Violation Type</label>
                                    <select class="form-select" name="violation_type">
                                        <option value="">-- Any Violation --</option>
                                        <?php foreach ($violation_types as $vt): ?>
                                        <option value="<?php echo $vt['violation_type_id']; ?>"
                                                <?php echo ($_GET['violation_type'] ?? '') == $vt['violation_type_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vt['violation_type']); ?>
                                            (₱<?php echo number_format($vt['fine_amount_1'], 2); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Apprehending Officer</label>
                                    <select class="form-select" name="officer_id">
                                        <option value="">-- Any Officer --</option>
                                        <?php foreach ($officers as $officer): ?>
                                        <option value="<?php echo $officer['officer_id']; ?>"
                                                <?php echo ($_GET['officer_id'] ?? '') == $officer['officer_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($officer['officer_name'] ?? ''); ?>
                                            (<?php echo htmlspecialchars($officer['badge_number'] ?? 'N/A'); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Results Limit</label>
                                    <select class="form-select" name="limit">
                                        <option value="50" <?php echo ($_GET['limit'] ?? 100) == 50 ? 'selected' : ''; ?>>50 results</option>
                                        <option value="100" <?php echo ($_GET['limit'] ?? 100) == 100 ? 'selected' : ''; ?>>100 results</option>
                                        <option value="250" <?php echo ($_GET['limit'] ?? 100) == 250 ? 'selected' : ''; ?>>250 results</option>
                                        <option value="500" <?php echo ($_GET['limit'] ?? 100) == 500 ? 'selected' : ''; ?>>500 results</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date & Fine Range -->
                    <div class="collapsible-section">
                        <div class="section-toggle" onclick="toggleSection('dateRange')">
                            <span><i class="fas fa-calendar-alt"></i> Date & Fine Range</span>
                            <i class="fas fa-chevron-down" id="dateRangeIcon"></i>
                        </div>
                        <div class="section-content" id="dateRange">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Date From</label>
                                    <input type="date" class="form-control" name="date_from"
                                           value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Date To</label>
                                    <input type="date" class="form-control" name="date_to"
                                           value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Min Fine Amount (₱)</label>
                                    <input type="number" class="form-control" name="fine_min" step="0.01" min="0"
                                           value="<?php echo htmlspecialchars($_GET['fine_min'] ?? ''); ?>"
                                           placeholder="0.00">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Max Fine Amount (₱)</label>
                                    <input type="number" class="form-control" name="fine_max" step="0.01" min="0"
                                           value="<?php echo htmlspecialchars($_GET['fine_max'] ?? ''); ?>"
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-search">
                            <i class="fas fa-search"></i> Search Citations
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                            <i class="fas fa-eraser"></i> Clear All
                        </button>
                    </div>
                </div>
            </form>

            <!-- Search Results -->
            <?php if ($searched): ?>
            <div class="search-card">
                <div class="search-header">
                    <h4>
                        <i class="fas fa-list"></i> Search Results
                        <span class="badge bg-primary"><?php echo $total_results; ?> found</span>
                        <?php if (count($citations) < $total_results): ?>
                        <small class="text-muted">(showing <?php echo count($citations); ?>)</small>
                        <?php endif; ?>
                    </h4>
                </div>

                <?php if (count($citations) > 0): ?>
                <div class="table-responsive results-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Date</th>
                                <th>Driver Name</th>
                                <th>License #</th>
                                <th>Violations</th>
                                <th>Total Fine</th>
                                <th>Status</th>
                                <th>Officer</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($citations as $citation): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($citation['ticket_number']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($citation['apprehension_datetime'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($citation['last_name'] . ', ' . $citation['first_name']); ?>
                                    <?php if ($citation['age'] < 18): ?>
                                    <span class="badge bg-warning text-dark">Minor</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($citation['license_number'] ?: 'N/A'); ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars($citation['violations'] ?: 'None'); ?></small>
                                </td>
                                <td><strong>₱<?php echo number_format($citation['total_fine'], 2); ?></strong></td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'pending' => 'bg-warning text-dark',
                                        'paid' => 'bg-success',
                                        'contested' => 'bg-info',
                                        'dismissed' => 'bg-secondary',
                                        'void' => 'bg-danger'
                                    ];
                                    $class = $status_class[$citation['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge badge-status <?php echo $class; ?>">
                                        <?php echo ucfirst(htmlspecialchars($citation['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php if (!empty($citation['apprehension_officer'])): ?>
                                            <?php echo htmlspecialchars($citation['apprehension_officer']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_citation.php?id=<?php echo $citation['citation_id']; ?>"
                                           class="btn btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_citation.php?id=<?php echo $citation['citation_id']; ?>"
                                           class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="print_citation.php?id=<?php echo $citation['citation_id']; ?>"
                                           class="btn btn-outline-secondary" title="Print" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search fa-3x mb-3"></i>
                    <h5>No Citations Found</h5>
                    <p>Try adjusting your search criteria or clearing some filters.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            const icon = document.getElementById(sectionId + 'Icon');

            if (section.style.display === 'none') {
                section.style.display = 'block';
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
            } else {
                section.style.display = 'none';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            }
        }

        function clearForm() {
            document.getElementById('searchForm').reset();
            // Clear URL parameters
            window.location.href = 'search.php';
        }

        function exportResults() {
            // Get table data
            const table = document.querySelector('.results-table table');
            if (!table) return;

            let csv = [];
            const rows = table.querySelectorAll('tr');

            rows.forEach((row, index) => {
                const cols = row.querySelectorAll('th, td');
                const rowData = [];

                cols.forEach((col, colIndex) => {
                    // Skip Actions column
                    if (colIndex < cols.length - 1) {
                        let text = col.innerText.replace(/"/g, '""').trim();
                        rowData.push('"' + text + '"');
                    }
                });

                csv.push(rowData.join(','));
            });

            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);

            link.setAttribute('href', url);
            link.setAttribute('download', 'citation_search_results_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
