<?php
/**
 * API: Create Manual Backup
 *
 * Performs an immediate manual database backup.
 *
 * @method POST
 * @auth Required (Admin only)
 */

session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../services/BackupService.php';

header('Content-Type: application/json');

// Require admin authentication
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getPDO();
    $backupService = new BackupService($pdo);

    // Perform manual backup
    $result = $backupService->performBackup('manual', $_SESSION['user_id']);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'backup' => [
                'filename' => $result['filename'],
                'size' => $result['size'],
                'size_formatted' => $result['size_formatted'],
                'log_id' => $result['log_id']
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
