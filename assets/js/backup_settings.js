/**
 * TMG - Backup Settings JavaScript
 *
 * Handles the backup settings page functionality.
 */

let currentSettings = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    loadSettings();
    setupEventListeners();
});

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Frequency selection
    document.querySelectorAll('.frequency-option').forEach(option => {
        option.addEventListener('click', function() {
            selectFrequency(this.dataset.frequency);
        });
    });

    // Email notification toggle
    document.getElementById('emailNotification').addEventListener('change', function() {
        document.getElementById('emailSettings').style.display = this.checked ? 'block' : 'none';
    });

    // Form submission
    document.getElementById('backupSettingsForm').addEventListener('submit', handleFormSubmit);
}

/**
 * Load backup settings from API
 */
async function loadSettings() {
    try {
        const response = await fetch('../api/backups/get_settings.php');
        const data = await response.json();

        if (data.success) {
            currentSettings = data.settings;
            populateForm(data.settings);
            updateNextBackupDisplay(data.settings);

            // Hide skeleton, show form
            document.getElementById('loadingSkeleton').style.display = 'none';
            document.getElementById('settingsForm').style.display = 'block';
        } else {
            showAlert('danger', 'Failed to load settings: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading settings:', error);
        showAlert('danger', 'Failed to load settings. Please try again.');
    }
}

/**
 * Populate form with settings
 */
function populateForm(settings) {
    // Backup enabled
    document.getElementById('backupEnabled').checked = settings.backup_enabled == 1;

    // Frequency
    selectFrequency(settings.backup_frequency);

    // Time - HTML5 time input expects HH:MM, so strip seconds if present
    const timeValue = settings.backup_time ? settings.backup_time.substring(0, 5) : '02:00'; // Get HH:MM from HH:MM:SS
    document.getElementById('backupTime').value = timeValue;

    // Storage
    document.getElementById('backupPath').value = settings.backup_path;
    document.getElementById('maxBackups').value = settings.max_backups;

    // Email
    document.getElementById('emailNotification').checked = settings.email_notification == 1;
    document.getElementById('notificationEmail').value = settings.notification_email || '';
    document.getElementById('emailSettings').style.display = settings.email_notification == 1 ? 'block' : 'none';
}

/**
 * Select frequency option
 */
function selectFrequency(frequency) {
    console.log('Selecting frequency:', frequency); // Debug log

    // Remove active class from all
    document.querySelectorAll('.frequency-option').forEach(opt => {
        opt.classList.remove('active');
    });

    // Add active class to selected
    const selected = document.querySelector(`[data-frequency="${frequency}"]`);
    if (selected) {
        selected.classList.add('active');
        document.getElementById('backupFrequency').value = frequency;

        // Add a subtle animation
        selected.style.transform = 'scale(1.02)';
        setTimeout(() => {
            selected.style.transform = '';
        }, 300);

        console.log('Frequency selected:', frequency, 'Element:', selected);
    } else {
        console.warn('No element found for frequency:', frequency);
    }
}

/**
 * Update next backup display
 */
function updateNextBackupDisplay(settings) {
    if (settings.backup_enabled == 1 && settings.next_backup_date) {
        const nextDate = new Date(settings.next_backup_date);
        const formattedDate = nextDate.toLocaleString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        document.getElementById('nextBackupDate').textContent = formattedDate;
        document.getElementById('nextBackupCard').style.display = 'block';
    } else {
        document.getElementById('nextBackupCard').style.display = 'none';
    }
}

/**
 * Handle form submission
 */
async function handleFormSubmit(e) {
    e.preventDefault();

    const submitButton = document.getElementById('saveButton');
    const originalText = submitButton.innerHTML;

    try {
        // Disable button and show loading
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        // Gather form data
        const formData = {
            backup_enabled: document.getElementById('backupEnabled').checked,
            backup_frequency: document.getElementById('backupFrequency').value,
            backup_time: document.getElementById('backupTime').value + ':00', // Add seconds
            backup_path: document.getElementById('backupPath').value,
            max_backups: parseInt(document.getElementById('maxBackups').value),
            email_notification: document.getElementById('emailNotification').checked,
            notification_email: document.getElementById('notificationEmail').value
        };

        // Validate
        if (!formData.backup_frequency) {
            throw new Error('Please select a backup frequency');
        }

        // Send to API
        const response = await fetch('../api/backups/update_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            showAlert('success', 'Backup settings saved successfully!');
            currentSettings = data.settings;
            updateNextBackupDisplay(data.settings);
        } else {
            throw new Error(data.error || 'Failed to save settings');
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        showAlert('danger', error.message);
    } finally {
        // Re-enable button
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
}

/**
 * Run backup now
 */
async function runBackupNow() {
    if (!confirm('This will create a manual backup immediately. Continue?')) {
        return;
    }

    const button = event.target.closest('button');
    const originalText = button.innerHTML;

    try {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating Backup...';

        const response = await fetch('../api/backups/create_backup.php', {
            method: 'POST'
        });

        const data = await response.json();

        if (data.success) {
            showAlert('success', `Backup created successfully! File: ${data.backup.filename} (${data.backup.size_formatted})`);

            // Reload settings to update next backup date
            setTimeout(() => {
                loadSettings();
            }, 1000);
        } else {
            throw new Error(data.error || 'Failed to create backup');
        }
    } catch (error) {
        console.error('Error creating backup:', error);
        showAlert('danger', error.message);
    } finally {
        button.disabled = false;
        button.innerHTML = originalText;
    }
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
