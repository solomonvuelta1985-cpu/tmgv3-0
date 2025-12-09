/**
 * TMG - Backup Management JavaScript
 *
 * Handles the backup management page functionality.
 */

let backupsData = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    loadBackups();
});

/**
 * Load backups from API
 */
async function loadBackups() {
    try {
        const response = await fetch('../api/backups/get_logs.php?limit=100');
        const data = await response.json();

        if (data.success) {
            backupsData = data.logs;
            updateStatistics(data.logs);
            displayBackups(data.logs);

            // Hide skeleton, show list
            document.getElementById('loadingSkeleton').style.display = 'none';

            if (data.logs.length === 0) {
                document.getElementById('emptyState').style.display = 'block';
                document.getElementById('backupList').style.display = 'none';
            } else {
                document.getElementById('emptyState').style.display = 'none';
                document.getElementById('backupList').style.display = 'block';
            }
        } else {
            showAlert('danger', 'Failed to load backups: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading backups:', error);
        showAlert('danger', 'Failed to load backups. Please try again.');
    }
}

/**
 * Update statistics
 */
function updateStatistics(backups) {
    // Total backups
    const successfulBackups = backups.filter(b => b.backup_status === 'success');
    document.getElementById('totalBackups').textContent = successfulBackups.length;

    // Total size
    const totalSize = successfulBackups.reduce((sum, b) => sum + parseInt(b.backup_size), 0);
    document.getElementById('totalSize').textContent = formatBytes(totalSize);

    // Last backup
    if (successfulBackups.length > 0) {
        const lastBackup = successfulBackups[0];
        const lastDate = new Date(lastBackup.created_at);
        document.getElementById('lastBackup').textContent = getRelativeTime(lastDate);
    } else {
        document.getElementById('lastBackup').textContent = 'Never';
    }

    // Success rate
    if (backups.length > 0) {
        const successRate = (successfulBackups.length / backups.length) * 100;
        document.getElementById('successRate').textContent = successRate.toFixed(0) + '%';
    } else {
        document.getElementById('successRate').textContent = '100%';
    }
}

/**
 * Display backups
 */
function displayBackups(backups) {
    const container = document.getElementById('backupList');
    container.innerHTML = '';

    backups.forEach(backup => {
        const card = createBackupCard(backup);
        container.appendChild(card);
    });

    // Reinitialize Lucide icons
    lucide.createIcons();
}

/**
 * Create backup card HTML
 */
function createBackupCard(backup) {
    const card = document.createElement('div');
    card.className = `backup-card ${backup.backup_status} card mb-3`;

    const date = new Date(backup.created_at);
    const formattedDate = date.toLocaleString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    const statusBadge = getStatusBadge(backup.backup_status);
    const typeBadge = backup.backup_type === 'automatic'
        ? '<span class="badge bg-info badge-type">Auto</span>'
        : '<span class="badge bg-primary badge-type">Manual</span>';

    const fileExists = backup.file_exists;
    const canDownload = backup.backup_status === 'success' && fileExists;

    card.innerHTML = `
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h6 class="mb-1">
                        <i data-lucide="file-archive" class="me-2"></i>
                        ${backup.backup_filename}
                    </h6>
                    <small class="text-muted">
                        <i data-lucide="calendar" class="me-1"></i>
                        ${formattedDate}
                        ${backup.created_by_name ? `<i data-lucide="user" class="ms-2 me-1"></i>${backup.created_by_name}` : ''}
                    </small>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="small text-muted">Size</div>
                        <strong>${backup.backup_size_formatted}</strong>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    ${statusBadge}
                    ${typeBadge}
                    <div class="btn-group mt-2" role="group">
                        ${canDownload ? `
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadBackup(${backup.id})" title="Download">
                                <i data-lucide="download"></i>
                            </button>
                        ` : ''}
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteBackup(${backup.id})" title="Delete">
                            <i data-lucide="trash-2"></i>
                        </button>
                    </div>
                </div>
            </div>
            ${backup.backup_status === 'failed' && backup.error_message ? `
                <div class="alert alert-danger mt-3 mb-0">
                    <i data-lucide="alert-circle" class="me-2"></i>
                    <strong>Error:</strong> ${backup.error_message}
                </div>
            ` : ''}
            ${backup.backup_status === 'success' && !fileExists ? `
                <div class="alert alert-warning mt-3 mb-0">
                    <i data-lucide="alert-triangle" class="me-2"></i>
                    <strong>Warning:</strong> Backup file not found on disk
                </div>
            ` : ''}
        </div>
    `;

    return card;
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const badges = {
        'success': '<span class="badge bg-success">Success</span>',
        'failed': '<span class="badge bg-danger">Failed</span>',
        'in_progress': '<span class="badge bg-warning">In Progress</span>'
    };

    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

/**
 * Create backup now
 */
async function createBackupNow() {
    if (!confirm('This will create a new database backup. This may take a few moments. Continue?')) {
        return;
    }

    const button = event?.target?.closest('button');
    const originalText = button ? button.innerHTML : '';

    try {
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';
        }

        showAlert('info', 'Creating backup... This may take a few moments.');

        const response = await fetch('../api/backups/create_backup.php', {
            method: 'POST'
        });

        const data = await response.json();

        if (data.success) {
            showAlert('success', `Backup created successfully! File: ${data.backup.filename} (${data.backup.size_formatted})`);

            // Reload backups
            setTimeout(() => {
                loadBackups();
            }, 1000);
        } else {
            throw new Error(data.error || 'Failed to create backup');
        }
    } catch (error) {
        console.error('Error creating backup:', error);
        showAlert('danger', error.message);
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }
}

/**
 * Download backup
 */
function downloadBackup(backupId) {
    // Open download in new window
    window.open(`../api/backups/download_backup.php?id=${backupId}`, '_blank');
    showAlert('info', 'Download started...');
}

/**
 * Delete backup
 */
async function deleteBackup(backupId) {
    if (!confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('../api/backups/delete_backup.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: backupId })
        });

        const data = await response.json();

        if (data.success) {
            showAlert('success', 'Backup deleted successfully');

            // Reload backups
            setTimeout(() => {
                loadBackups();
            }, 500);
        } else {
            throw new Error(data.error || 'Failed to delete backup');
        }
    } catch (error) {
        console.error('Error deleting backup:', error);
        showAlert('danger', error.message);
    }
}

/**
 * Format bytes to human-readable size
 */
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const k = 1024;
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + units[i];
}

/**
 * Get relative time (e.g., "2 hours ago")
 */
function getRelativeTime(date) {
    const seconds = Math.floor((new Date() - date) / 1000);

    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) return interval + ' year' + (interval === 1 ? '' : 's') + ' ago';

    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) return interval + ' month' + (interval === 1 ? '' : 's') + ' ago';

    interval = Math.floor(seconds / 86400);
    if (interval >= 1) return interval + ' day' + (interval === 1 ? '' : 's') + ' ago';

    interval = Math.floor(seconds / 3600);
    if (interval >= 1) return interval + ' hour' + (interval === 1 ? '' : 's') + ' ago';

    interval = Math.floor(seconds / 60);
    if (interval >= 1) return interval + ' minute' + (interval === 1 ? '' : 's') + ' ago';

    return 'Just now';
}

/**
 * Show alert message
 */
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    alertContainer.appendChild(alert);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 150);
    }, 5000);
}
