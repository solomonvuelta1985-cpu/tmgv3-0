<?php
/**
 * API: Download Backup File
 *
 * Downloads a backup file by log ID.
 *
 * @method GET
 * @auth Required (Admin only)
 * @query {
 *   id: number (backup log ID)
 * }
 */

session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../services/BackupService.php';

// Require admin authentication
require_admin();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Backup ID is required']);
    exit;
}

try {
    $pdo = getPDO();
    $backupService = new BackupService($pdo);

    $backupId = intval($_GET['id']);
    $backup = $backupService->getBackupFile($backupId);

    if (!$backup) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Backup not found']);
        exit;
    }

    $filepath = $backup['backup_path'];

    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Backup file not found on disk']);
        exit;
    }

    // Set headers for file download
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $backup['backup_filename'] . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    // Clear output buffer
    ob_clean();
    flush();

    // Read file and output
    readfile($filepath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
