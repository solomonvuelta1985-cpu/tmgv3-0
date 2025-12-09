<?php
/**
 * API: Get Backup Logs
 *
 * Returns list of backup history/logs.
 *
 * @method GET
 * @auth Required (Admin only)
 * @query {
 *   limit: number (default 50),
 *   offset: number (default 0)
 * }
 */

session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../services/BackupService.php';

header('Content-Type: application/json');

// Require admin authentication
require_admin();

try {
    $pdo = getPDO();
    $backupService = new BackupService($pdo);

    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $logs = $backupService->getBackupLogs($limit, $offset);

    // Get total count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM backup_logs");
    $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Format logs with human-readable sizes
    foreach ($logs as &$log) {
        $log['backup_size_formatted'] = formatBytes($log['backup_size']);
        $log['file_exists'] = file_exists($log['backup_path']);
    }

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total' => $totalCount,
        'limit' => $limit,
        'offset' => $offset
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
