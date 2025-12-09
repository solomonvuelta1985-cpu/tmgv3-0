<?php
/**
 * TMG - Backup Settings Page
 *
 * Allows administrators to configure automatic database backups.
 *
 * @package TMG
 * @subpackage Admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin authentication and check session timeout
require_admin();
check_session_timeout();

$pageTitle = 'Backup Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - TMG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .settings-card {
            border-left: 4px solid #9155fd;
        }
        .frequency-option {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #dee2e6;
            background-color: #fff;
            position: relative;
        }
        .frequency-option:hover {
            background-color: #f8f9fa;
            border-color: #9155fd;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(145, 85, 253, 0.2);
        }
        .frequency-option.active {
            background: linear-gradient(135deg, #9155fd 0%, #764ba2 100%);
            color: white !important;
            border-color: #9155fd;
            box-shadow: 0 4px 12px rgba(145, 85, 253, 0.4);
        }
        .frequency-option.active strong {
            color: white !important;
        }
        .frequency-option.active .text-muted {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        .frequency-option.active i {
            color: white !important;
        }
        .frequency-option.active::after {
            content: "✓";
            position: absolute;
            top: 8px;
            right: 8px;
            background: white;
            color: #9155fd;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .next-backup-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include '../public/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-content-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i data-lucide="database-backup" class="me-2"></i>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="backups.php" class="btn btn-outline-primary">
                            <i data-lucide="list" class="me-1"></i>
                            View Backup History
                        </a>
                    </div>
                </div>

                <!-- Next Backup Info -->
                <div id="nextBackupCard" class="next-backup-info mb-4" style="display: none;">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1">
                                <i data-lucide="clock" class="me-2"></i>
                                Next Scheduled Backup
                            </h5>
                            <p class="mb-0" id="nextBackupDate">Loading...</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-light" onclick="runBackupNow()">
                                <i data-lucide="play" class="me-1"></i>
                                Backup Now
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- Loading Skeleton -->
                <div id="loadingSkeleton">
                    <div class="card settings-card">
                        <div class="card-body">
                            <div class="placeholder-glow">
                                <div class="placeholder col-6 mb-3"></div>
                                <div class="placeholder col-4"></div>
                                <div class="placeholder col-8"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Form -->
                <div id="settingsForm" style="display: none;">
                    <form id="backupSettingsForm">
                        <!-- Backup Status -->
                        <div class="card settings-card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i data-lucide="power" class="me-2"></i>
                                    Backup Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="backupEnabled" name="backup_enabled">
                                    <label class="form-check-label" for="backupEnabled">
                                        Enable Automatic Backups
                                    </label>
                                </div>
                                <small class="text-muted">
                                    When enabled, the system will automatically backup the database according to the schedule below.
                                </small>
                            </div>
                        </div>

                        <!-- Backup Schedule -->
                        <div class="card settings-card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i data-lucide="calendar" class="me-2"></i>
                                    Backup Schedule
                                </h5>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Backup Frequency</label>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6 col-lg-3">
                                        <div class="frequency-option border rounded p-3 text-center" data-frequency="daily">
                                            <i data-lucide="calendar-days" class="d-block mb-2" style="font-size: 2rem;"></i>
                                            <strong>Daily</strong>
                                            <p class="mb-0 small text-muted">Every day</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <div class="frequency-option border rounded p-3 text-center" data-frequency="every_3_days">
                                            <i data-lucide="calendar-range" class="d-block mb-2" style="font-size: 2rem;"></i>
                                            <strong>Every 3 Days</strong>
                                            <p class="mb-0 small text-muted">Twice a week</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <div class="frequency-option border rounded p-3 text-center" data-frequency="weekly">
                                            <i data-lucide="calendar-check" class="d-block mb-2" style="font-size: 2rem;"></i>
                                            <strong>Weekly</strong>
                                            <p class="mb-0 small text-muted">Once a week</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <div class="frequency-option border rounded p-3 text-center" data-frequency="monthly">
                                            <i data-lucide="calendar" class="d-block mb-2" style="font-size: 2rem;"></i>
                                            <strong>Monthly</strong>
                                            <p class="mb-0 small text-muted">Once a month</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="backupFrequency" name="backup_frequency" required>

                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="backupTime" class="form-label">Backup Time</label>
                                        <input type="time" class="form-control" id="backupTime" name="backup_time" required>
                                        <small class="text-muted">Time of day to perform automatic backup</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Storage Settings -->
                        <div class="card settings-card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i data-lucide="hard-drive" class="me-2"></i>
                                    Storage Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="backupPath" class="form-label">Backup Directory</label>
                                        <input type="text" class="form-control" id="backupPath" name="backup_path" required>
                                        <small class="text-muted">Path where backup files will be stored</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="maxBackups" class="form-label">Maximum Backups to Keep</label>
                                        <input type="number" class="form-control" id="maxBackups" name="max_backups" min="1" max="100" required>
                                        <small class="text-muted">Older backups will be automatically deleted</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Notifications -->
                        <div class="card settings-card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i data-lucide="mail" class="me-2"></i>
                                    Email Notifications
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="emailNotification" name="email_notification">
                                    <label class="form-check-label" for="emailNotification">
                                        Send email after each backup
                                    </label>
                                </div>
                                <div id="emailSettings" style="display: none;">
                                    <label for="notificationEmail" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="notificationEmail" name="notification_email">
                                    <small class="text-muted">Email address to receive backup notifications</small>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i data-lucide="x" class="me-1"></i>
                                Reset
                            </button>
                            <button type="submit" class="btn btn-primary" id="saveButton">
                                <i data-lucide="save" class="me-1"></i>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="../assets/js/backup_settings.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
