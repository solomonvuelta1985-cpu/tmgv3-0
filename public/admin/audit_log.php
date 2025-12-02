<?php
/**
 * Audit Log Viewer
 *
 * Comprehensive audit trail viewer with filtering and export
 * Shows all system changes for transparency and accountability
 *
 * @package TrafficCitationSystem
 * @subpackage Public
 */

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/services/AuditService.php';

// Require login and admin access
require_login();
check_session_timeout();

if (!is_admin()) {
    set_flash('Access denied. Only administrators can view audit logs.', 'danger');
    header('Location: /tmg/public/index.php');
    exit;
}

$pdo = getPDO();
$auditService = new AuditService($pdo);

// Get filter parameters
$filters = [];
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$table = isset($_GET['table']) ? sanitize($_GET['table']) : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$limit = isset($_GET['limit']) ? min(500, max(10, (int)$_GET['limit'])) : 100;

if ($action) $filters['action'] = $action;
if ($table) $filters['table_name'] = $table;
if ($userId) $filters['user_id'] = $userId;
if ($dateFrom) $filters['date_from'] = $dateFrom . ' 00:00:00';
if ($dateTo) $filters['date_to'] = $dateTo . ' 23:59:59';

// Get audit logs
$auditLogs = $auditService->getRecentActivity($limit, $filters);

// Get all users for filter dropdown
$sql = "SELECT user_id, username, full_name FROM users ORDER BY full_name";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$sql = "SELECT
            COUNT(*) as total_logs,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT table_name) as tables_affected,
            MIN(created_at) as oldest_log,
            MAX(created_at) as newest_log
        FROM audit_log";
$stmt = $pdo->query($sql);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Page title
$pageTitle = 'Audit Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Traffic Citation System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-size: 16px;
            background-color: #f5f7fa;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
            background-color: #f5f7fa;
            padding: 2.5rem;
            font-size: 1.05rem;
        }

        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e6ed;
        }

        .page-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            background: white;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e0e6ed;
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .card-header h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        .filter-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .table {
            font-size: 0.9rem;
        }

        .table thead th {
            font-size: 0.8rem;
            padding: 0.75rem 0.5rem;
            font-weight: 600;
            background-color: #f8fafc;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e6ed;
        }

        .table tbody td {
            padding: 0.75rem 0.5rem;
            vertical-align: middle;
            font-size: 0.85rem;
        }

        .action-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.6rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .action-create { background: #d1fae5; color: #065f46; }
        .action-update { background: #dbeafe; color: #1e40af; }
        .action-delete { background: #fee2e2; color: #991b1b; }
        .action-status_change { background: #fef3c7; color: #92400e; }
        .action-refunded { background: #fce7f3; color: #9f1239; }

        .json-data {
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            background: #f1f5f9;
            padding: 0.5rem;
            border-radius: 6px;
            max-height: 100px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h2>
                    <i class="fas fa-clipboard-list"></i> Audit Log
                </h2>
                <p class="text-muted mb-0">Complete system activity trail for transparency and accountability</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['total_logs']) ?></div>
                    <div class="stat-label"><i class="fas fa-list"></i> Total Log Entries</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['unique_users']) ?></div>
                    <div class="stat-label"><i class="fas fa-users"></i> Unique Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['tables_affected']) ?></div>
                    <div class="stat-label"><i class="fas fa-database"></i> Tables Affected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($auditLogs) ?></div>
                    <div class="stat-label"><i class="fas fa-filter"></i> Filtered Results</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-filter"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="action" class="form-label">Action</label>
                                <select class="form-select form-select-sm" id="action" name="action">
                                    <option value="">All Actions</option>
                                    <option value="create" <?= $action === 'create' ? 'selected' : '' ?>>Create</option>
                                    <option value="update" <?= $action === 'update' ? 'selected' : '' ?>>Update</option>
                                    <option value="delete" <?= $action === 'delete' ? 'selected' : '' ?>>Delete</option>
                                    <option value="status_change" <?= $action === 'status_change' ? 'selected' : '' ?>>Status Change</option>
                                    <option value="refunded" <?= $action === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="table" class="form-label">Table</label>
                                <select class="form-select form-select-sm" id="table" name="table">
                                    <option value="">All Tables</option>
                                    <option value="citations" <?= $table === 'citations' ? 'selected' : '' ?>>Citations</option>
                                    <option value="payments" <?= $table === 'payments' ? 'selected' : '' ?>>Payments</option>
                                    <option value="users" <?= $table === 'users' ? 'selected' : '' ?>>Users</option>
                                    <option value="violations" <?= $table === 'violations' ? 'selected' : '' ?>>Violations</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="user_id" class="form-label">User</label>
                                <select class="form-select form-select-sm" id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['user_id'] ?>" <?= $userId == $user['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="limit" class="form-label">Results Limit</label>
                                <select class="form-select form-select-sm" id="limit" name="limit">
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                    <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                                    <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>

                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="audit_log.php" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                    <button type="button" class="btn btn-success btn-sm" onclick="exportToCSV()">
                                        <i class="fas fa-file-csv"></i> Export CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Log Table -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Audit Trail (<?= count($auditLogs) ?> entries)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($auditLogs)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No audit logs found matching your criteria.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm" id="auditTable">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Record ID</th>
                                        <th>Changes</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditLogs as $log):
                                        $oldValues = json_decode($log['old_values'], true);
                                        $newValues = json_decode($log['new_values'], true);
                                    ?>
                                        <tr>
                                            <td>
                                                <small><?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                                                <?= date('h:i:s A', strtotime($log['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($log['user_name'] ?: $log['username'] ?: 'System') ?></strong>
                                            </td>
                                            <td>
                                                <span class="action-badge action-<?= htmlspecialchars($log['action']) ?>">
                                                    <?= htmlspecialchars($log['action']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($log['table_name']) ?></td>
                                            <td>#<?= htmlspecialchars($log['record_id']) ?></td>
                                            <td>
                                                <?php if ($log['action'] === 'status_change' && $oldValues && $newValues): ?>
                                                    <small>
                                                        <strong>Status:</strong>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($oldValues['status']) ?></span>
                                                        →
                                                        <span class="badge bg-primary"><?= htmlspecialchars($newValues['status']) ?></span>
                                                        <?php if (!empty($newValues['reason'])): ?>
                                                            <br><em><?= htmlspecialchars($newValues['reason']) ?></em>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php elseif ($oldValues || $newValues): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-info"
                                                            onclick="showChanges(<?= htmlspecialchars(json_encode(['old' => $oldValues, 'new' => $newValues])) ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?= htmlspecialchars($log['ip_address'] ?: '-') ?></small></td>
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

    <!-- Changes Detail Modal -->
    <div class="modal fade" id="changesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-code"></i> Change Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Old Values:</h6>
                            <div class="json-data" id="oldValues"></div>
                        </div>
                        <div class="col-md-6">
                            <h6>New Values:</h6>
                            <div class="json-data" id="newValues"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let changesModal;

        document.addEventListener('DOMContentLoaded', function() {
            changesModal = new bootstrap.Modal(document.getElementById('changesModal'));
        });

        function showChanges(data) {
            document.getElementById('oldValues').textContent = JSON.stringify(data.old, null, 2);
            document.getElementById('newValues').textContent = JSON.stringify(data.new, null, 2);
            changesModal.show();
        }

        function exportToCSV() {
            const table = document.getElementById('auditTable');
            let csv = [];

            // Headers
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent);
            csv.push(headers.join(','));

            // Rows
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cols = Array.from(row.querySelectorAll('td')).map(td => {
                    // Clean text content
                    let text = td.textContent.trim().replace(/\s+/g, ' ');
                    // Escape quotes
                    text = text.replace(/"/g, '""');
                    // Wrap in quotes if contains comma
                    return text.includes(',') ? `"${text}"` : text;
                });
                csv.push(cols.join(','));
            });

            // Download
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `audit_log_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
