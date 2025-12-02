<?php
/**
 * Admin Tools Dashboard
 *
 * Central hub for all administrative tools and utilities
 * Organized by category: Diagnostics, Database, and Maintenance
 *
 * @package TrafficCitationSystem
 * @subpackage Admin
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_login();
check_session_timeout();
if (!is_admin()) {
    header('Location: /tmg/public/dashboard.php');
    exit;
}

$pdo = getPDO();

// Get quick stats
$stats = [];

// Check for data integrity issues
$stmt = $pdo->query("
    SELECT COUNT(*) FROM citations c
    INNER JOIN payments p ON c.citation_id = p.citation_id
    WHERE c.status = 'pending' AND p.status = 'completed' AND c.deleted_at IS NULL
");
$stats['mismatched_citations'] = $stmt->fetchColumn();

// Check for orphaned payments
$stmt = $pdo->query("
    SELECT COUNT(*) FROM payments p
    LEFT JOIN citations c ON p.citation_id = c.citation_id
    WHERE c.citation_id IS NULL OR c.deleted_at IS NOT NULL
");
$stats['orphaned_payments'] = $stmt->fetchColumn();

// Check for citations in trash
$stmt = $pdo->query("SELECT COUNT(*) FROM citations WHERE deleted_at IS NOT NULL");
$stats['citations_in_trash'] = $stmt->fetchColumn();

// Check for stale pending print
$stmt = $pdo->query("
    SELECT COUNT(*) FROM payments p
    INNER JOIN citations c ON p.citation_id = c.citation_id
    WHERE p.status = 'pending_print'
    AND TIMESTAMPDIFF(HOUR, p.created_at, NOW()) > 24
    AND c.deleted_at IS NULL
");
$stats['stale_pending'] = $stmt->fetchColumn();

// Check if triggers are installed
$stmt = $pdo->query("SHOW TRIGGERS LIKE 'payments'");
$stats['triggers_installed'] = $stmt->rowCount();

// Calculate system health
$total_issues = $stats['mismatched_citations'] + $stats['orphaned_payments'] + $stats['stale_pending'];
$health_score = max(0, 100 - ($total_issues * 2));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tools - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header-card h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .category-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .category-header {
            border-left: 4px solid #667eea;
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .category-header h3 {
            margin: 0;
            color: #1f2937;
        }
        .category-header p {
            margin: 0;
            color: #6b7280;
            font-size: 0.9em;
        }
        .tool-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid transparent;
            transition: all 0.3s;
            cursor: pointer;
        }
        .tool-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }
        .tool-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .tool-content {
            flex-grow: 1;
        }
        .tool-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .tool-description {
            color: #6b7280;
            font-size: 0.9em;
            margin: 0;
        }
        .tool-badge {
            font-size: 0.8em;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: bold;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        .health-indicator {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        .health-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1.2em;
        }
        .health-excellent { background: linear-gradient(135deg, #10b981, #059669); }
        .health-good { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .health-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .health-critical { background: linear-gradient(135deg, #ef4444, #dc2626); }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-tools"></i> Admin Tools Dashboard</h1>
                    <p class="text-muted mb-0">Organized administrative utilities for system management and maintenance</p>
                </div>
                <div>
                    <a href="/tmg/public/dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-home"></i> Back to Main Dashboard
                    </a>
                </div>
            </div>

            <!-- System Health Indicator -->
            <div class="health-indicator">
                <div class="health-circle health-<?php
                    if ($health_score >= 90) echo 'excellent';
                    elseif ($health_score >= 70) echo 'good';
                    elseif ($health_score >= 50) echo 'warning';
                    else echo 'critical';
                ?>">
                    <?= $health_score ?>%
                </div>
                <div>
                    <strong>System Health Score</strong>
                    <p class="text-muted mb-0">
                        <?= $total_issues ?> issue(s) detected
                        <?php if ($total_issues > 0): ?>
                            - <a href="diagnostics/data_integrity_dashboard.php">View Details</a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- DIAGNOSTICS Section -->
        <div class="category-section">
            <div class="category-header">
                <h3><i class="fas fa-stethoscope"></i> Diagnostics Tools</h3>
                <p>Monitor and analyze system health and data integrity</p>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <a href="diagnostics/data_integrity_dashboard.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Data Integrity Dashboard</div>
                                <p class="tool-description">
                                    Comprehensive dashboard showing all data inconsistencies, orphaned records, and system health metrics
                                </p>
                            </div>
                            <?php if ($total_issues > 0): ?>
                                <span class="tool-badge badge-danger"><?= $total_issues ?> issues</span>
                            <?php else: ?>
                                <span class="tool-badge badge-success">Healthy</span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>

                <div class="col-md-6">
                    <a href="diagnostics/automated_consistency_checker.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Automated Consistency Checker</div>
                                <p class="tool-description">
                                    Run comprehensive consistency checks. Can be scheduled with Task Scheduler for daily monitoring
                                </p>
                            </div>
                            <span class="tool-badge badge-info">CLI Ready</span>
                        </div>
                    </a>
                </div>

                <div class="col-md-6">
                    <a href="diagnostics/investigate_citation_payment_inconsistency.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Citation-Payment Investigation Tool</div>
                                <p class="tool-description">
                                    Deep dive analysis of citation and payment inconsistencies with visual reports and fix recommendations
                                </p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-6">
                    <a href="diagnostics/trash_bin.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-trash-restore"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Trash Bin</div>
                                <p class="tool-description">
                                    View and restore soft-deleted citations. Includes audit trail and recovery capabilities
                                </p>
                            </div>
                            <?php if ($stats['citations_in_trash'] > 0): ?>
                                <span class="tool-badge badge-warning"><?= $stats['citations_in_trash'] ?> deleted</span>
                            <?php else: ?>
                                <span class="tool-badge badge-success">Empty</span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- DATABASE Section -->
        <div class="category-section">
            <div class="category-header">
                <h3><i class="fas fa-database"></i> Database Tools</h3>
                <p>Database migrations, schema changes, and configuration</p>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <a href="database/run_consistency_migration.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Consistency Migration Runner</div>
                                <p class="tool-description">
                                    Install database triggers, stored procedures, and error logging tables for data integrity protection
                                </p>
                            </div>
                            <?php if ($stats['triggers_installed'] < 3): ?>
                                <span class="tool-badge badge-warning">Setup Needed</span>
                            <?php else: ?>
                                <span class="tool-badge badge-success">Installed</span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>

                <div class="col-md-6">
                    <a href="database_diagnostics.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Database Diagnostics</div>
                                <p class="tool-description">
                                    View database statistics, table sizes, indexes, and performance metrics
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- MAINTENANCE Section -->
        <div class="category-section">
            <div class="category-header">
                <h3><i class="fas fa-wrench"></i> Maintenance Tools</h3>
                <p>Automated fixes and system maintenance utilities</p>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <a href="maintenance/fix_pending_paid_citations.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-magic"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Fix Pending-Paid Citations</div>
                                <p class="tool-description">
                                    Automatically fix citations marked as "pending" but have completed payment records
                                </p>
                            </div>
                            <?php if ($stats['mismatched_citations'] > 0): ?>
                                <span class="tool-badge badge-danger"><?= $stats['mismatched_citations'] ?> to fix</span>
                            <?php else: ?>
                                <span class="tool-badge badge-success">All Fixed</span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>

                <div class="col-md-6">
                    <div class="tool-card d-flex opacity-50">
                        <div class="tool-icon">
                            <i class="fas fa-broom"></i>
                        </div>
                        <div class="tool-content">
                            <div class="tool-title">Database Cleanup Utility</div>
                            <p class="tool-description">
                                Clean up old audit logs, expired sessions, and temporary data
                            </p>
                        </div>
                        <span class="tool-badge badge-info">Coming Soon</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- OTHER ADMIN TOOLS Section -->
        <div class="category-section">
            <div class="category-header">
                <h3><i class="fas fa-cogs"></i> Other Admin Tools</h3>
                <p>User management, violations, and general administration</p>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <a href="users.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">User Management</div>
                                <p class="tool-description">Manage system users and permissions</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="violations.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Violation Types</div>
                                <p class="tool-description">Configure violation types and fines</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="dashboard.php" class="text-decoration-none">
                        <div class="tool-card d-flex">
                            <div class="tool-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="tool-content">
                                <div class="tool-title">Admin Dashboard</div>
                                <p class="tool-description">View system statistics and reports</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Stats Footer -->
        <div class="category-section">
            <h5 class="mb-3">Quick Statistics</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-3 fw-bold text-<?= $stats['mismatched_citations'] > 0 ? 'danger' : 'success' ?>">
                            <?= $stats['mismatched_citations'] ?>
                        </div>
                        <small class="text-muted">Mismatched Citations</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-3 fw-bold text-<?= $stats['orphaned_payments'] > 0 ? 'warning' : 'success' ?>">
                            <?= $stats['orphaned_payments'] ?>
                        </div>
                        <small class="text-muted">Orphaned Payments</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-3 fw-bold text-<?= $stats['citations_in_trash'] > 0 ? 'warning' : 'success' ?>">
                            <?= $stats['citations_in_trash'] ?>
                        </div>
                        <small class="text-muted">Citations in Trash</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-3 fw-bold text-<?= $stats['stale_pending'] > 0 ? 'warning' : 'success' ?>">
                            <?= $stats['stale_pending'] ?>
                        </div>
                        <small class="text-muted">Stale Pending Payments</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
