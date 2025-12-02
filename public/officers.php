<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login and check session timeout
require_login();
check_session_timeout();

// Get statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

$pdo = getPDO();
if ($pdo) {
    // Total officers
    $stmt = db_query("SELECT COUNT(*) as count FROM apprehending_officers");
    $stats['total'] = $stmt->fetch()['count'] ?? 0;

    // Active
    $stmt = db_query("SELECT COUNT(*) as count FROM apprehending_officers WHERE is_active = 1");
    $stats['active'] = $stmt->fetch()['count'] ?? 0;

    // Inactive
    $stmt = db_query("SELECT COUNT(*) as count FROM apprehending_officers WHERE is_active = 0");
    $stats['inactive'] = $stmt->fetch()['count'] ?? 0;
}

// Search
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(officer_name LIKE ? OR badge_number LIKE ? OR position LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get officers
$sql = "SELECT * FROM apprehending_officers $where_sql ORDER BY officer_name ASC";
$officers = [];
if ($pdo) {
    $stmt = db_query($sql, $params);
    $officers = $stmt->fetchAll();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apprehending Officers - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #212529;
            font-size: 16px;
            line-height: 1.5;
        }

        .content {
            margin-left: 250px;
            padding: clamp(15px, 3vw, 20px);
            min-height: 100vh;
        }

        .main-card {
            background: #ffffff;
            border-radius: 6px;
            padding: clamp(20px, 4vw, 30px);
            border: 1px solid #dee2e6;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-title {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 600;
            color: #212529;
            margin-bottom: 5px;
        }

        .page-subtitle {
            font-size: clamp(0.95rem, 2.5vw, 1rem);
            color: #6c757d;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: clamp(15px, 3vw, 20px);
            border-left: 4px solid;
            transition: box-shadow 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stat-card.blue { border-left-color: #0d6efd; }
        .stat-card.green { border-left-color: #198754; }
        .stat-card.red { border-left-color: #dc3545; }

        .stat-number {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 600;
            color: #212529;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: clamp(0.85rem, 2.3vw, 0.9rem);
            color: #6c757d;
        }

        /* Action Section */
        .action-section {
            background: #f8f9fa;
            border-radius: 6px;
            padding: clamp(15px, 3vw, 20px);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: clamp(0.85rem, 2.3vw, 0.9rem);
        }

        .search-box input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
            outline: none;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn-custom {
            border-radius: 4px;
            padding: 8px 15px;
            font-size: clamp(0.85rem, 2.3vw, 0.9rem);
            font-weight: 500;
            border: 1px solid;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary-custom {
            background: #0d6efd;
            color: #ffffff;
            border-color: #0d6efd;
        }

        .btn-primary-custom:hover {
            background: #0b5ed7;
            border-color: #0b5ed7;
            color: #ffffff;
        }

        .btn-warning-custom {
            background: #ffc107;
            color: #000000;
            border-color: #ffc107;
        }

        .btn-warning-custom:hover {
            background: #ffca2c;
            border-color: #ffca2c;
            color: #000000;
        }

        .btn-danger-custom {
            background: #dc3545;
            color: #ffffff;
            border-color: #dc3545;
        }

        .btn-danger-custom:hover {
            background: #bb2d3b;
            border-color: #bb2d3b;
            color: #ffffff;
        }

        .btn-success-custom {
            background: #198754;
            color: #ffffff;
            border-color: #198754;
        }

        .btn-success-custom:hover {
            background: #157347;
            border-color: #157347;
            color: #ffffff;
        }

        .btn-secondary-custom {
            background: #6c757d;
            color: #ffffff;
            border-color: #6c757d;
        }

        .btn-secondary-custom:hover {
            background: #5c636a;
            border-color: #5c636a;
            color: #ffffff;
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: clamp(0.75rem, 2vw, 0.8rem);
        }

        /* Table */
        .table-container {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            overflow: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(0.875rem, 2.3vw, 0.9rem);
        }

        .data-table thead th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 12px 15px;
            border-bottom: 2px solid #dee2e6;
            text-align: left;
            white-space: nowrap;
        }

        .data-table tbody td {
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            color: #212529;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: clamp(0.7rem, 2vw, 0.75rem);
            font-weight: 500;
            border: 1px solid;
        }

        .badge-active {
            background: #d1e7dd;
            color: #0f5132;
            border-color: #badbcc;
        }

        .badge-inactive {
            background: #f8d7da;
            color: #842029;
            border-color: #f5c2c7;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Modal */
        .modal-header {
            background: #ffffff;
            border-bottom: 2px solid #dee2e6;
            padding: 15px 20px;
        }

        .modal-title {
            font-size: clamp(1.1rem, 3vw, 1.25rem);
            font-weight: 600;
            color: #212529;
        }

        .modal-body {
            padding: 20px;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: clamp(0.875rem, 2.3vw, 0.9rem);
        }

        .form-control {
            border-radius: 4px;
            border: 1px solid #dee2e6;
            padding: 10px 14px;
            font-size: clamp(0.875rem, 2.3vw, 0.9rem);
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
        }

        .modal-footer {
            border-top: 1px solid #dee2e6;
            padding: 15px 20px;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }

            .action-section {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .action-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="main-card">
            <div class="page-header">
                <h1 class="page-title">Apprehending Officers</h1>
                <p class="page-subtitle">Manage traffic enforcement officers</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label"><i class="fas fa-user-shield me-1"></i> Total Officers</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                    <div class="stat-label"><i class="fas fa-check-circle me-1"></i> Active</div>
                </div>
                <div class="stat-card red">
                    <div class="stat-number"><?php echo number_format($stats['inactive']); ?></div>
                    <div class="stat-label"><i class="fas fa-times-circle me-1"></i> Inactive</div>
                </div>
            </div>

            <!-- Action Section -->
            <div class="action-section">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search officers..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="action-buttons">
                    <button type="button" class="btn-custom btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addOfficerModal">
                        <i class="fas fa-plus"></i> Add Officer
                    </button>
                </div>
            </div>

            <!-- Officers Table -->
            <div class="table-container">
                <?php if (empty($officers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-shield"></i>
                        <p>No officers found.</p>
                        <button type="button" class="btn-custom btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addOfficerModal">
                            <i class="fas fa-plus"></i> Add First Officer
                        </button>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Officer Name</th>
                                <th>Badge Number</th>
                                <th>Position</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($officers as $officer): ?>
                                <tr>
                                    <td><?php echo $officer['officer_id']; ?></td>
                                    <td><?php echo htmlspecialchars($officer['officer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($officer['badge_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($officer['position'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($officer['is_active']): ?>
                                            <span class="badge-status badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($officer['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn-custom btn-warning-custom btn-sm edit-btn"
                                                data-id="<?php echo $officer['officer_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($officer['officer_name']); ?>"
                                                data-badge="<?php echo htmlspecialchars($officer['badge_number'] ?? ''); ?>"
                                                data-position="<?php echo htmlspecialchars($officer['position'] ?? ''); ?>"
                                                data-active="<?php echo $officer['is_active']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-custom btn-danger-custom btn-sm delete-btn"
                                                data-id="<?php echo $officer['officer_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($officer['officer_name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Officer Modal -->
    <div class="modal fade" id="addOfficerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Officer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addOfficerForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                        <div class="mb-3">
                            <label class="form-label">Officer Name *</label>
                            <input type="text" name="officer_name" class="form-control" required placeholder="Enter full name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Badge Number</label>
                            <input type="text" name="badge_number" class="form-control" placeholder="Enter badge number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" placeholder="e.g., Traffic Enforcer">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="addIsActive" checked>
                            <label class="form-check-label" for="addIsActive">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-custom btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-custom btn-primary-custom">Add Officer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Officer Modal -->
    <div class="modal fade" id="editOfficerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Officer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editOfficerForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                        <input type="hidden" name="officer_id" id="editOfficerId">
                        <div class="mb-3">
                            <label class="form-label">Officer Name *</label>
                            <input type="text" name="officer_name" id="editOfficerName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Badge Number</label>
                            <input type="text" name="badge_number" id="editBadgeNumber" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" id="editPosition" class="form-control">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="editIsActive">
                            <label class="form-check-label" for="editIsActive">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-custom btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-custom btn-warning-custom">Update Officer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const search = this.value.trim();
                window.location.href = 'officers.php' + (search ? '?search=' + encodeURIComponent(search) : '');
            }
        });

        // Add Officer
        document.getElementById('addOfficerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('../api/officer_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') {
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding officer');
            });
        });

        // Edit Officer - populate modal
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('editOfficerId').value = this.dataset.id;
                document.getElementById('editOfficerName').value = this.dataset.name;
                document.getElementById('editBadgeNumber').value = this.dataset.badge;
                document.getElementById('editPosition').value = this.dataset.position;
                document.getElementById('editIsActive').checked = this.dataset.active === '1';

                new bootstrap.Modal(document.getElementById('editOfficerModal')).show();
            });
        });

        // Update Officer
        document.getElementById('editOfficerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('../api/officer_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') {
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating officer');
            });
        });

        // Delete Officer
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;

                if (confirm('Are you sure you want to delete officer: ' + name + '?')) {
                    const formData = new FormData();
                    formData.append('officer_id', id);
                    formData.append('csrf_token', '<?php echo generate_token(); ?>');

                    fetch('../api/officer_delete.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.status === 'success') {
                            window.location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error deleting officer');
                    });
                }
            });
        });
    </script>
</body>
</html>
