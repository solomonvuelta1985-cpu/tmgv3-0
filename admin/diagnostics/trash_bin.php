<?php
/**
 * Trash Bin - Soft Deleted Citations
 *
 * View and restore soft-deleted citations
 * Only admins can access this page
 */

// Define root path (up two levels from admin/diagnostics/)
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Require admin access
require_login();
check_session_timeout();
if (!is_admin()) {
    header('Location: /tmg/public/dashboard.php');
    exit;
}

// Get database connection
$pdo = getPDO();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Get deleted citations count
$stmt = $pdo->query("SELECT COUNT(*) FROM citations WHERE deleted_at IS NOT NULL");
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get deleted citations
$stmt = $pdo->prepare("
    SELECT
        c.citation_id,
        c.ticket_number,
        CONCAT(c.first_name, ' ', c.last_name) as driver_name,
        c.license_number,
        c.plate_mv_engine_chassis_no,
        c.status,
        c.total_fine,
        c.apprehension_datetime,
        c.deleted_at,
        c.deletion_reason,
        u.full_name as deleted_by_name,
        DATEDIFF(NOW(), c.deleted_at) as days_in_trash,
        GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violations
    FROM citations c
    LEFT JOIN users u ON c.deleted_by = u.user_id
    LEFT JOIN violations v ON c.citation_id = v.citation_id
    LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
    WHERE c.deleted_at IS NOT NULL
    GROUP BY c.citation_id
    ORDER BY c.deleted_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute();
$deleted_citations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trash Bin - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .content {
            padding: 20px;
        }
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .trash-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        .stat-box {
            flex: 1;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #6b7280;
            font-size: 0.9em;
        }
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .badge-days {
            font-size: 0.85em;
        }
        .btn-action {
            padding: 6px 12px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <?php include '../../public/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-trash-restore"></i> Trash Bin</h2>
                        <p class="text-muted mb-0">View and restore soft-deleted citations</p>
                    </div>
                    <div>
                        <a href="/tmg/public/citations.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Citations
                        </a>
                        <a href="../index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home"></i> Admin Home
                        </a>
                        <button class="btn btn-danger" onclick="emptyTrash()">
                            <i class="fas fa-trash-alt"></i> Empty Trash
                        </button>
                    </div>
                </div>

                <div class="trash-stats">
                    <div class="stat-box">
                        <div class="stat-number"><?= $total_records ?></div>
                        <div class="stat-label">Deleted Citations</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">
                            <?php
                            $stmt = $pdo->query("SELECT DATEDIFF(NOW(), MIN(deleted_at)) FROM citations WHERE deleted_at IS NOT NULL");
                            echo $stmt->fetchColumn() ?? 0;
                            ?>
                        </div>
                        <div class="stat-label">Days Since First Delete</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM citations WHERE deleted_at IS NOT NULL AND DATEDIFF(NOW(), deleted_at) > 30");
                            echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stat-label">Ready for Permanent Delete (>30 days)</div>
                    </div>
                </div>
            </div>

            <!-- Deleted Citations Table -->
            <div class="table-container">
                <?php if (count($deleted_citations) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Driver Name</th>
                                    <th>License #</th>
                                    <th>Violations</th>
                                    <th>Total Fine</th>
                                    <th>Deleted At</th>
                                    <th>Days in Trash</th>
                                    <th>Deleted By</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deleted_citations as $citation): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($citation['ticket_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($citation['driver_name']) ?></td>
                                        <td><?= htmlspecialchars($citation['license_number'] ?? 'N/A') ?></td>
                                        <td style="font-size: 0.85em; max-width: 200px;">
                                            <?= htmlspecialchars(substr($citation['violations'] ?? 'N/A', 0, 50)) ?>
                                            <?= strlen($citation['violations'] ?? '') > 50 ? '...' : '' ?>
                                        </td>
                                        <td>₱<?= number_format($citation['total_fine'], 2) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($citation['deleted_at'])) ?></td>
                                        <td>
                                            <span class="badge <?= $citation['days_in_trash'] > 30 ? 'bg-danger' : 'bg-warning' ?> badge-days">
                                                <?= $citation['days_in_trash'] ?> days
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($citation['deleted_by_name']) ?></td>
                                        <td style="font-size: 0.85em; max-width: 150px;">
                                            <?= htmlspecialchars(substr($citation['deletion_reason'] ?? '', 0, 30)) ?>
                                            <?= strlen($citation['deletion_reason'] ?? '') > 30 ? '...' : '' ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-success btn-sm btn-action" onclick="restoreCitation(<?= $citation['citation_id'] ?>, '<?= htmlspecialchars($citation['ticket_number'], ENT_QUOTES) ?>')">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Trash pagination">
                            <ul class="pagination justify-content-center mt-4">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <h4>Trash is Empty</h4>
                        <p class="mb-0">No deleted citations found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // CSRF Token
        const csrfToken = '<?= generate_token() ?>';

        /**
         * Restore a citation from trash
         */
        function restoreCitation(citationId, ticketNumber) {
            Swal.fire({
                title: 'Restore Citation?',
                html: `Are you sure you want to restore citation <strong>${ticketNumber}</strong>?<br><br>This will move it back to the active citations list.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-undo"></i> Yes, Restore It',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Restoring...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Send restore request
                    const formData = new FormData();
                    formData.append('citation_id', citationId);
                    formData.append('csrf_token', csrfToken);

                    fetch('/tmg/api/restore_citation.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Restored!',
                                text: data.message,
                                confirmButtonColor: '#10b981'
                            }).then(() => {
                                // Reload page
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Restore Failed',
                                text: data.message,
                                confirmButtonColor: '#dc2626'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while restoring the citation',
                            confirmButtonColor: '#dc2626'
                        });
                    });
                }
            });
        }

        /**
         * Empty trash (permanently delete citations older than 30 days)
         */
        function emptyTrash() {
            Swal.fire({
                title: 'Empty Trash?',
                html: `<div class="text-start">
                    <p class="mb-3"><strong>⚠️ Warning: This action will PERMANENTLY DELETE citations that have been in the trash for more than 30 days.</strong></p>
                    <p class="mb-3">Permanently deleted citations:</p>
                    <ul>
                        <li>Cannot be restored</li>
                        <li>Will lose all violation records</li>
                        <li>Payment records will be orphaned (kept for audit)</li>
                    </ul>
                    <p class="mb-0"><strong>Are you absolutely sure?</strong></p>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash-alt"></i> Yes, Empty Trash',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Feature Coming Soon',
                        text: 'Permanent deletion requires additional safety checks and will be implemented in a future update.',
                        confirmButtonColor: '#3b82f6'
                    });
                }
            });
        }
    </script>
</body>
</html>
