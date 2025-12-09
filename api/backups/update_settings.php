<?php
/**
 * API: Update Backup Settings
 *
 * Updates backup configuration settings.
 *
 * @method POST
 * @auth Required (Admin only)
 * @body {
 *   backup_enabled: boolean,
 *   backup_frequency: 'daily'|'every_3_days'|'weekly'|'monthly',
 *   backup_time: 'HH:MM:SS',
 *   backup_path: string,
 *   max_backups: number,
 *   email_notification: boolean,
 *   notification_email: string
 * }
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
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Invalid request data');
    }

    $pdo = getPDO();
    $backupService = new BackupService($pdo);

    // Prepare settings array
    $settings = [];

    if (isset($data['backup_enabled'])) {
        $settings['backup_enabled'] = $data['backup_enabled'] ? 1 : 0;
    }

    if (isset($data['backup_frequency'])) {
        $validFrequencies = ['daily', 'every_3_days', 'weekly', 'monthly'];
        if (!in_array($data['backup_frequency'], $validFrequencies)) {
            throw new Exception('Invalid backup frequency');
        }
        $settings['backup_frequency'] = $data['backup_frequency'];
    }

    if (isset($data['backup_time'])) {
        // Validate time format
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/', $data['backup_time'])) {
            throw new Exception('Invalid time format. Use HH:MM:SS (24-hour format)');
        }
        $settings['backup_time'] = $data['backup_time'];
    }

    if (isset($data['backup_path'])) {
        $settings['backup_path'] = $data['backup_path'];
    }

    if (isset($data['max_backups'])) {
        $maxBackups = intval($data['max_backups']);
        if ($maxBackups < 1 || $maxBackups > 100) {
            throw new Exception('Max backups must be between 1 and 100');
        }
        $settings['max_backups'] = $maxBackups;
    }

    if (isset($data['email_notification'])) {
        $settings['email_notification'] = $data['email_notification'] ? 1 : 0;
    }

    if (isset($data['notification_email'])) {
        if (!empty($data['notification_email']) && !filter_var($data['notification_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        $settings['notification_email'] = $data['notification_email'];
    }

    // Update settings
    $result = $backupService->updateSettings($settings, $_SESSION['user_id']);

    if ($result) {
        // Get updated settings
        $updatedSettings = $backupService->getSettings();

        echo json_encode([
            'success' => true,
            'message' => 'Backup settings updated successfully',
            'settings' => $updatedSettings
        ]);
    } else {
        throw new Exception('Failed to update settings');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
