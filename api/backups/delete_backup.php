<?php
/**
 * API: Delete Backup File
 *
 * Deletes a backup file and its log entry.
 *
 * @method DELETE
 * @auth Required (Admin only)
 * @body {
 *   id: number (backup log ID)
 * }
 */

session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../services/BackupService.php';

header('Content-Type: application/json');

// Require admin authentication
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        throw new Exception('Backup ID is required');
    }

    $pdo = getPDO();
    $backupService = new BackupService($pdo);

    $backupId = intval($data['id']);
    $result = $backupService->deleteBackup($backupId);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Backup deleted successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Backup not found or already deleted'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
