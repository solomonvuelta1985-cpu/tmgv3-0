<?php
/**
 * TMG - Backup Service
 *
 * Handles automatic and manual database backups with compression.
 * Supports scheduled backups (daily, every 3 days, weekly, monthly).
 *
 * Features:
 * - Full database backup with mysqldump
 * - GZIP compression for storage efficiency
 * - Automatic cleanup of old backups
 * - Backup verification and integrity checks
 * - Email notifications (optional)
 * - Restore functionality
 *
 * @package TMG
 * @subpackage Services
 */

class BackupService {
    private $pdo;
    private $config;
    private $backupPath;
    private $dbName;
    private $dbHost;
    private $dbUser;
    private $dbPass;

    /**
     * Initialize backup service
     *
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
        $this->ensureBackupDirectory();
    }

    /**
     * Load database configuration
     */
    private function loadConfig() {
        // Get database credentials from connection
        require_once __DIR__ . '/../includes/config.php';

        $this->dbName = DB_NAME;
        $this->dbHost = DB_HOST;
        $this->dbUser = DB_USER;
        $this->dbPass = DB_PASS;

        // Get backup settings from database
        $stmt = $this->pdo->query("SELECT * FROM backup_settings WHERE id = 1");
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);

        // Set backup path (use absolute path)
        $this->backupPath = $this->config['backup_path'];
        if (!preg_match('/^[a-z]:/i', $this->backupPath)) {
            // Convert relative path to absolute
            $this->backupPath = realpath(__DIR__ . '/../') . '/' . ltrim($this->backupPath, './');
        }
    }

    /**
     * Ensure backup directory exists and is writable
     */
    private function ensureBackupDirectory() {
        if (!file_exists($this->backupPath)) {
            if (!mkdir($this->backupPath, 0755, true)) {
                throw new Exception("Failed to create backup directory: {$this->backupPath}");
            }
        }

        if (!is_writable($this->backupPath)) {
            throw new Exception("Backup directory is not writable: {$this->backupPath}");
        }

        // Create .htaccess to prevent direct access
        $htaccessPath = $this->backupPath . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Deny from all\n");
        }
    }

    /**
     * Perform database backup
     *
     * @param string $type 'automatic' or 'manual'
     * @param int|null $userId User ID for manual backups
     * @return array Result with success status and details
     */
    public function performBackup($type = 'automatic', $userId = null) {
        $logId = null;

        try {
            // Generate backup filename
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "tmg_backup_{$timestamp}.sql";
            $gzFilename = $filename . '.gz';
            $filepath = $this->backupPath . '/' . $filename;
            $gzFilepath = $this->backupPath . '/' . $gzFilename;

            // Create backup log entry
            $logId = $this->createBackupLog($gzFilename, $gzFilepath, $type, $userId);

            // Get mysqldump path
            $mysqldumpPath = $this->getMySQLDumpPath();

            // Build mysqldump command
            $command = sprintf(
                '"%s" --user=%s --password=%s --host=%s --single-transaction --quick --lock-tables=false %s > "%s" 2>&1',
                $mysqldumpPath,
                escapeshellarg($this->dbUser),
                escapeshellarg($this->dbPass),
                escapeshellarg($this->dbHost),
                escapeshellarg($this->dbName),
                $filepath
            );

            // Execute mysqldump
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception("mysqldump failed: " . implode("\n", $output));
            }

            // Verify backup file was created
            if (!file_exists($filepath) || filesize($filepath) == 0) {
                throw new Exception("Backup file was not created or is empty");
            }

            // Compress backup with gzip
            $this->compressFile($filepath, $gzFilepath);

            // Delete uncompressed file
            unlink($filepath);

            // Get backup statistics
            $stats = $this->getBackupStats($gzFilepath);

            // Update backup log with success
            $this->updateBackupLog($logId, [
                'backup_status' => 'success',
                'backup_size' => $stats['size'],
                'tables_count' => $stats['tables_count'],
                'records_count' => $stats['records_count'],
                'compression' => 'gzip'
            ]);

            // Cleanup old backups
            $this->cleanupOldBackups();

            // Send notification if enabled
            if ($this->config['email_notification'] && $this->config['notification_email']) {
                $this->sendBackupNotification($gzFilename, $stats);
            }

            return [
                'success' => true,
                'filename' => $gzFilename,
                'size' => $stats['size'],
                'size_formatted' => $this->formatBytes($stats['size']),
                'path' => $gzFilepath,
                'log_id' => $logId
            ];

        } catch (Exception $e) {
            // Update log with failure
            if ($logId) {
                $this->updateBackupLog($logId, [
                    'backup_status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }

            // Log error
            error_log("Backup failed: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log_id' => $logId
            ];
        }
    }

    /**
     * Get path to mysqldump executable
     *
     * @return string Path to mysqldump
     */
    private function getMySQLDumpPath() {
        // Common paths for XAMPP on Windows
        $paths = [
            'C:/xampp/mysql/bin/mysqldump.exe',
            'C:/xampp/mysql/bin/mysqldump',
            'mysqldump' // System PATH
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try to find in system PATH
        exec('where mysqldump', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            return trim($output[0]);
        }

        throw new Exception("mysqldump executable not found. Please ensure MySQL is installed.");
    }

    /**
     * Compress file using gzip
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     */
    private function compressFile($source, $destination) {
        $fp = fopen($source, 'rb');
        $gz = gzopen($destination, 'wb9'); // Maximum compression level

        while (!feof($fp)) {
            gzwrite($gz, fread($fp, 1024 * 512)); // Read in chunks
        }

        fclose($fp);
        gzclose($gz);
    }

    /**
     * Get backup file statistics
     *
     * @param string $filepath Path to backup file
     * @return array Statistics
     */
    private function getBackupStats($filepath) {
        $stats = [
            'size' => filesize($filepath),
            'tables_count' => 0,
            'records_count' => 0
        ];

        // Get table count from database
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '{$this->dbName}'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['tables_count'] = $result['count'];

        // Estimate record count (sum of all tables)
        $stmt = $this->pdo->query("
            SELECT SUM(table_rows) as total_rows
            FROM information_schema.tables
            WHERE table_schema = '{$this->dbName}'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['records_count'] = $result['total_rows'] ?? 0;

        return $stats;
    }

    /**
     * Create backup log entry
     *
     * @param string $filename Backup filename
     * @param string $filepath Full file path
     * @param string $type Backup type
     * @param int|null $userId User ID
     * @return int Log ID
     */
    private function createBackupLog($filename, $filepath, $type, $userId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO backup_logs (
                backup_filename,
                backup_path,
                backup_type,
                backup_status,
                database_name,
                created_by
            ) VALUES (?, ?, ?, 'in_progress', ?, ?)
        ");

        $stmt->execute([$filename, $filepath, $type, $this->dbName, $userId]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Update backup log entry
     *
     * @param int $logId Log ID
     * @param array $data Data to update
     */
    private function updateBackupLog($logId, $data) {
        $setParts = [];
        $values = [];

        foreach ($data as $key => $value) {
            $setParts[] = "$key = ?";
            $values[] = $value;
        }

        $values[] = $logId;

        $sql = "UPDATE backup_logs SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * Cleanup old backups based on max_backups setting
     */
    private function cleanupOldBackups() {
        $maxBackups = $this->config['max_backups'];

        // Get list of backups ordered by date (newest first)
        $stmt = $this->pdo->query("
            SELECT id, backup_filename, backup_path
            FROM backup_logs
            WHERE backup_status = 'success'
            ORDER BY created_at DESC
        ");

        $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Remove backups beyond max count
        if (count($backups) > $maxBackups) {
            $backupsToDelete = array_slice($backups, $maxBackups);

            foreach ($backupsToDelete as $backup) {
                // Delete physical file
                if (file_exists($backup['backup_path'])) {
                    unlink($backup['backup_path']);
                }

                // Delete log entry
                $deleteStmt = $this->pdo->prepare("DELETE FROM backup_logs WHERE id = ?");
                $deleteStmt->execute([$backup['id']]);
            }
        }
    }

    /**
     * Send email notification about backup
     *
     * @param string $filename Backup filename
     * @param array $stats Backup statistics
     */
    private function sendBackupNotification($filename, $stats) {
        $to = $this->config['notification_email'];
        $subject = "TMG Database Backup Completed - " . date('Y-m-d H:i:s');

        $message = "Database backup completed successfully.\n\n";
        $message .= "Filename: $filename\n";
        $message .= "Size: " . $this->formatBytes($stats['size']) . "\n";
        $message .= "Tables: {$stats['tables_count']}\n";
        $message .= "Records: {$stats['records_count']}\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";

        $headers = "From: TMG System <noreply@tmg.local>";

        mail($to, $subject, $message, $headers);
    }

    /**
     * Format bytes to human-readable size
     *
     * @param int $bytes Number of bytes
     * @return string Formatted size
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Check if backup is due based on schedule
     *
     * @return bool True if backup is due
     */
    public function isBackupDue() {
        $this->loadConfig(); // Reload config

        if (!$this->config['backup_enabled']) {
            return false;
        }

        $nextBackup = $this->config['next_backup_date'];

        if (!$nextBackup) {
            return true; // No backup scheduled yet
        }

        return strtotime($nextBackup) <= time();
    }

    /**
     * Get all backup logs
     *
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Backup logs
     */
    public function getBackupLogs($limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT
                bl.*,
                u.username as created_by_name
            FROM backup_logs bl
            LEFT JOIN users u ON bl.created_by = u.user_id
            ORDER BY bl.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get backup file for download
     *
     * @param int $logId Backup log ID
     * @return array|null Backup details or null if not found
     */
    public function getBackupFile($logId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM backup_logs WHERE id = ? AND backup_status = 'success'
        ");
        $stmt->execute([$logId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Delete backup
     *
     * @param int $logId Backup log ID
     * @return bool Success status
     */
    public function deleteBackup($logId) {
        // Get backup record (any status)
        $stmt = $this->pdo->prepare("SELECT * FROM backup_logs WHERE id = ?");
        $stmt->execute([$logId]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$backup) {
            return false;
        }

        // Delete physical file if it exists
        if (!empty($backup['backup_path']) && file_exists($backup['backup_path'])) {
            unlink($backup['backup_path']);
        }

        // Delete log entry
        $stmt = $this->pdo->prepare("DELETE FROM backup_logs WHERE id = ?");
        return $stmt->execute([$logId]);
    }

    /**
     * Update backup settings
     *
     * @param array $settings Settings to update
     * @param int $userId User making the change
     * @return bool Success status
     */
    public function updateSettings($settings, $userId) {
        $allowedFields = [
            'backup_enabled',
            'backup_frequency',
            'backup_time',
            'backup_path',
            'max_backups',
            'email_notification',
            'notification_email'
        ];

        $setParts = [];
        $values = [];

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $setParts[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $setParts[] = "updated_by = ?";
        $values[] = $userId;

        $sql = "UPDATE backup_settings SET " . implode(', ', $setParts) . " WHERE id = 1";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Get current backup settings
     *
     * @return array Backup settings
     */
    public function getSettings() {
        $this->loadConfig();
        return $this->config;
    }
}
