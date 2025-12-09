<?php
/**
 * TMG - Backup Management Page
 *
 * View, download, and manage database backups.
 *
 * @package TMG
 * @subpackage Admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin authentication and check session timeout
require_admin();
check_session_timeout();

$pageTitle = 'Backup Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - TMG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .backup-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .backup-card:hover {
            border-left-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .backup-card.success {
            border-left-color: #28a745;
        }
        .backup-card.failed {
            border-left-color: #dc3545;
        }
        .backup-card.in_progress {
            border-left-color: #ffc107;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
        }
        .badge-type {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
        }
    </style>
</head>
<body>
    <?php include '../public/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i data-lucide="database" class="me-2"></i>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary me-2" onclick="createBackupNow()">
                            <i data-lucide="download" class="me-1"></i>
                            Create Backup Now
                        </button>
                        <a href="backup_settings.php" class="btn btn-outline-secondary">
                            <i data-lucide="settings" class="me-1"></i>
                            Settings
                        </a>
                    </div>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0" id="totalBackups">--</h3>
                                    <p class="mb-0">Total Backups</p>
                                </div>
                                <i data-lucide="database" style="font-size: 2.5rem; opacity: 0.8;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0" id="totalSize">--</h3>
                                    <p class="mb-0">Total Size</p>
                                </div>
                                <i data-lucide="hard-drive" style="font-size: 2.5rem; opacity: 0.8;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0" id="lastBackup">--</h3>
                                    <p class="mb-0">Last Backup</p>
                                </div>
                                <i data-lucide="clock" style="font-size: 2.5rem; opacity: 0.8;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0" id="successRate">--</h3>
                                    <p class="mb-0">Success Rate</p>
                                </div>
                                <i data-lucide="check-circle" style="font-size: 2.5rem; opacity: 0.8;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup List -->
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i data-lucide="list" class="me-2"></i>
                            Backup History
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="loadBackups()">
                            <i data-lucide="refresh-cw" class="me-1"></i>
                            Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Loading Skeleton -->
                        <div id="loadingSkeleton">
                            <div class="placeholder-glow">
                                <div class="placeholder col-12 mb-2"></div>
                                <div class="placeholder col-10 mb-2"></div>
                                <div class="placeholder col-8"></div>
                            </div>
                        </div>

                        <!-- Backup List Container -->
                        <div id="backupList" style="display: none;"></div>

                        <!-- Empty State -->
                        <div id="emptyState" style="display: none;" class="text-center py-5">
                            <i data-lucide="database" style="font-size: 4rem; opacity: 0.3;"></i>
                            <h5 class="mt-3 text-muted">No backups found</h5>
                            <p class="text-muted">Create your first backup to get started</p>
                            <button class="btn btn-primary" onclick="createBackupNow()">
                                <i data-lucide="plus" class="me-1"></i>
                                Create Backup
                            </button>
                        </div>
                    </div>
                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="../assets/js/backups.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
