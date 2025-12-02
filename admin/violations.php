<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();
check_session_timeout();

// Fetch all violation types
$violation_types = [];
try {
    $stmt = db_query(
        "SELECT * FROM violation_types ORDER BY violation_type ASC"
    );
    $violation_types = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching violation types: " . $e->getMessage());
    set_flash('Error loading violation types.', 'danger');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Violation Types - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary-blue: #0d6efd;
            --success-green: #198754;
            --info-cyan: #0dcaf0;
            --warning-yellow: #ffc107;
            --danger-red: #dc3545;
            --secondary-gray: #6c757d;
            --purple: #6f42c1;
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
            background: var(--off-white);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: clamp(0.875rem, 2.3vw, 0.9rem);
            line-height: 1.5;
            color: var(--text-dark);
        }

        .content {
            padding: clamp(15px, 3vw, 20px);
        }

        /* Page Title */
        .page-header h3 {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .page-header p {
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
            color: var(--text-muted);
        }

        .page-header {
            margin-bottom: 30px;
        }

        /* Statistics Card - Flat Design */
        .stat-card {
            background: var(--white);
            border: 1px solid var(--border-gray);
            border-radius: 6px;
            padding: clamp(15px, 3vw, 20px);
            position: relative;
            transition: box-shadow 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 6px 0 0 6px;
            background: var(--purple);
        }

        .stat-value {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Cards */
        .card {
            background: var(--white);
            border: 1px solid var(--border-gray);
            border-radius: 6px;
            overflow: hidden;
        }

        .card-header {
            background: var(--light-gray);
            border-bottom: 2px solid var(--border-gray);
            font-weight: 600;
            color: var(--text-label);
            padding: 15px 20px;
            font-size: clamp(1.1rem, 3vw, 1.25rem);
        }

        /* Tables */
        .table {
            margin-bottom: 0;
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
        }

        .table th {
            background: var(--light-gray);
            font-weight: 600;
            color: var(--text-label);
            border-top: none;
            border-bottom: 2px solid var(--border-gray);
            padding: 12px 15px;
            font-size: 0.875rem;
        }

        .table td {
            padding: 10px 15px;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border-gray);
            vertical-align: middle;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: var(--light-gray);
            transition: background 0.2s;
        }

        /* Badges - Flat Design */
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: clamp(0.7rem, 2vw, 0.75rem);
            font-weight: 500;
            border: 1px solid transparent;
        }

        .badge.bg-success {
            background: #d1e7dd !important;
            color: #0a3622 !important;
            border-color: #a3cfbb;
        }

        .badge.bg-secondary {
            background: #e2e3e5 !important;
            color: #41464b !important;
            border-color: #c4c8cb;
        }

        /* Buttons */
        .btn {
            border-radius: 4px;
            padding: 8px 15px;
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: var(--primary-blue);
            border: 1px solid var(--primary-blue);
        }

        .btn-primary:hover {
            background: #0b5ed7;
            border-color: #0b5ed7;
        }

        .btn-outline-primary {
            color: var(--primary-blue);
            border: 1px solid var(--primary-blue);
        }

        .btn-outline-primary:hover {
            background: var(--primary-blue);
            color: var(--white);
        }

        .btn-outline-danger {
            color: var(--danger-red);
            border: 1px solid var(--danger-red);
        }

        .btn-outline-danger:hover {
            background: var(--danger-red);
            color: var(--white);
        }

        .btn-secondary {
            background: var(--secondary-gray);
            border: 1px solid var(--secondary-gray);
        }

        .btn-danger {
            background: var(--danger-red);
            border: 1px solid var(--danger-red);
        }

        /* Form Controls */
        .form-control {
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            padding: 8px 10px;
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-label);
            margin-bottom: 8px;
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
        }

        /* Modals */
        .modal-title {
            font-size: clamp(1.1rem, 3vw, 1.25rem);
            font-weight: 600;
        }

        .modal-header {
            border-bottom: 2px solid var(--border-gray);
        }

        .modal-footer {
            border-top: 1px solid var(--border-gray);
        }

        /* Fine Amount */
        .fine-amount {
            font-weight: 600;
            color: var(--danger-red);
        }

        .violation-name {
            font-weight: 500;
            color: var(--text-dark);
        }

        .action-buttons .btn {
            margin: 2px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Search Input */
        .card-header .form-control {
            border: 1px solid var(--border-gray);
        }

        /* Focus indicators for accessibility */
        .btn:focus {
            outline: 2px solid var(--primary-blue);
            outline-offset: 2px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }

            .card-header {
                flex-direction: column;
                gap: 10px;
            }

            .card-header .form-control {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../public/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h3><i class="fas fa-exclamation-triangle me-2"></i>Manage Violation Types</h3>
                    <p class="text-muted mb-0">Add, edit, or remove traffic violations and their fines</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addViolationModal">
                    <i class="fas fa-plus me-2"></i>Add New Violation
                </button>
            </div>

            <?php echo show_flash(); ?>

            <!-- Stats Card - Flat Design -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($violation_types); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Total Violation Types</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Violations Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i>All Violation Types</span>
                    <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search violations..." style="width: 250px;">
                </div>
                <div class="card-body p-0">
                    <?php if (empty($violation_types)): ?>
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle d-block"></i>
                            <h5>No Violation Types Found</h5>
                            <p>Click "Add New Violation" to create your first violation type.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="violationsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Violation Type</th>
                                        <th class="text-center">1st Offense</th>
                                        <th class="text-center">2nd Offense</th>
                                        <th class="text-center">3rd+ Offense</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($violation_types as $index => $violation): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td class="violation-name"><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                            <td class="text-center fine-amount">₱<?php echo number_format($violation['fine_amount_1'], 2); ?></td>
                                            <td class="text-center fine-amount">₱<?php echo number_format($violation['fine_amount_2'], 2); ?></td>
                                            <td class="text-center fine-amount">₱<?php echo number_format($violation['fine_amount_3'], 2); ?></td>
                                            <td class="text-center">
                                                <?php if (isset($violation['is_active']) && $violation['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center action-buttons">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editViolation(<?php echo htmlspecialchars(json_encode($violation)); ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteViolation(<?php echo $violation['violation_type_id']; ?>, '<?php echo htmlspecialchars($violation['violation_type']); ?>')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Violation Modal -->
    <div class="modal fade" id="addViolationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Violation Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addViolationForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">

                        <div class="mb-3">
                            <label class="form-label">Violation Type *</label>
                            <input type="text" class="form-control" name="violation_type" required placeholder="e.g., NO HELMET (DRIVER)">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">1st Offense (₱) *</label>
                                <input type="number" class="form-control" name="fine_amount_1" step="0.01" min="0" required placeholder="0.00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">2nd Offense (₱) *</label>
                                <input type="number" class="form-control" name="fine_amount_2" step="0.01" min="0" required placeholder="0.00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">3rd+ Offense (₱) *</label>
                                <input type="number" class="form-control" name="fine_amount_3" step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Additional details about this violation"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Violation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Violation Modal -->
    <div class="modal fade" id="editViolationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Violation Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editViolationForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                        <input type="hidden" name="violation_type_id" id="edit_violation_type_id">

                        <div class="mb-3">
                            <label class="form-label">Violation Type *</label>
                            <input type="text" class="form-control" name="violation_type" id="edit_violation_type" required>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">1st Offense (₱) *</label>
                                <input type="number" class="form-control" name="fine_amount_1" id="edit_fine_1" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">2nd Offense (₱) *</label>
                                <input type="number" class="form-control" name="fine_amount_2" id="edit_fine_2" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">3rd+ Offense (₱) *</label>
                                <input type="number" class="form-control" name="fine_amount_3" id="edit_fine_3" step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Violation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the violation type:</p>
                    <p class="fw-bold fs-5" id="deleteViolationName"></p>
                    <p class="text-danger"><i class="fas fa-warning me-2"></i>This action cannot be undone!</p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="deleteViolationId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash me-2"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.getElementById('violationsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let row of rows) {
                const violationType = row.cells[1].textContent.toLowerCase();
                if (violationType.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Edit violation
        function editViolation(violation) {
            document.getElementById('edit_violation_type_id').value = violation.violation_type_id;
            document.getElementById('edit_violation_type').value = violation.violation_type;
            document.getElementById('edit_fine_1').value = violation.fine_amount_1;
            document.getElementById('edit_fine_2').value = violation.fine_amount_2;
            document.getElementById('edit_fine_3').value = violation.fine_amount_3;
            document.getElementById('edit_description').value = violation.description || '';

            const modal = new bootstrap.Modal(document.getElementById('editViolationModal'));
            modal.show();
        }

        // Delete violation
        function deleteViolation(id, name) {
            document.getElementById('deleteViolationId').value = id;
            document.getElementById('deleteViolationName').textContent = name;

            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // Confirm delete
        function confirmDelete() {
            const id = document.getElementById('deleteViolationId').value;
            const formData = new FormData();
            formData.append('violation_type_id', id);
            formData.append('csrf_token', '<?php echo generate_token(); ?>');

            fetch('../api/violation_delete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        // Add violation form
        document.getElementById('addViolationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('../api/violation_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });

        // Edit violation form
        document.getElementById('editViolationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('../api/violation_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });
    </script>
</body>
</html>
