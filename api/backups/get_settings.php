<?php
/**
 * API: Get Backup Settings
 *
 * Returns current backup configuration settings.
 *
 * @method GET
 * @auth Required (Admin only)
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

    $settings = $backupService->getSettings();

    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
