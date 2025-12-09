<?php
/**
 * TMG - Automated Backup Scheduler (CLI Script)
 *
 * This script is designed to be run by Windows Task Scheduler.
 * It checks if a backup is due and performs it automatically.
 *
 * Usage:
 *   php backup_scheduler.php
 *
 * Windows Task Scheduler Setup:
 *   Program: C:\xampp\php\php.exe
 *   Arguments: "C:\xampp\htdocs\tmg\services\backup_scheduler.php"
 *   Schedule: Run every hour (the script will check if backup is due)
 *
 * @package TMG
 * @subpackage Services
 */

// Ensure script is run from command line only
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line.\n");
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set time limit (no limit for CLI)
set_time_limit(0);

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/BackupService.php';

/**
 * Log message to console and file
 */
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";

    // Output to console
    echo $logMessage;

    // Log to file
    $logFile = __DIR__ . '/../logs/backup_scheduler.log';
    $logDir = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Main execution
 */
try {
    log_message("=== Backup Scheduler Started ===");

    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    log_message("Database connection established");

    // Initialize backup service
    $backupService = new BackupService($pdo);

    // Get current settings
    $settings = $backupService->getSettings();

    log_message("Backup Settings:");
    log_message("  - Enabled: " . ($settings['backup_enabled'] ? 'Yes' : 'No'));
    log_message("  - Frequency: " . $settings['backup_frequency']);
    log_message("  - Next Backup: " . ($settings['next_backup_date'] ?? 'Not scheduled'));

    // Check if backup is enabled
    if (!$settings['backup_enabled']) {
        log_message("Automatic backup is disabled. Exiting.", 'INFO');
        exit(0);
    }

    // Check if backup is due
    if (!$backupService->isBackupDue()) {
        $nextBackup = $settings['next_backup_date'];
        $timeUntil = strtotime($nextBackup) - time();
        $hoursUntil = round($timeUntil / 3600, 1);

        log_message("Backup is not due yet. Next backup in $hoursUntil hours ($nextBackup)", 'INFO');
        exit(0);
    }

    log_message("Backup is due. Starting backup process...", 'INFO');

    // Perform backup
    $result = $backupService->performBackup('automatic', null);

    if ($result['success']) {
        log_message("Backup completed successfully!", 'SUCCESS');
        log_message("  - Filename: " . $result['filename']);
        log_message("  - Size: " . $result['size_formatted']);
        log_message("  - Path: " . $result['path']);

        // Get next backup date
        $newSettings = $backupService->getSettings();
        log_message("  - Next Backup: " . ($newSettings['next_backup_date'] ?? 'Not scheduled'));

        exit(0); // Success
    } else {
        log_message("Backup failed: " . $result['error'], 'ERROR');
        exit(1); // Failure
    }

} catch (PDOException $e) {
    log_message("Database error: " . $e->getMessage(), 'ERROR');
    exit(1);
} catch (Exception $e) {
    log_message("Error: " . $e->getMessage(), 'ERROR');
    exit(1);
} finally {
    log_message("=== Backup Scheduler Finished ===\n");
}
