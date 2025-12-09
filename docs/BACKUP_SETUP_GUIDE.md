# TMG - Automatic Database Backup Setup Guide

This guide will help you set up automatic database backups for the TMG (Traffic Management System).

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Database Setup](#database-setup)
4. [Backup Configuration](#backup-configuration)
5. [Windows Task Scheduler Setup](#windows-task-scheduler-setup)
6. [Testing the Backup](#testing-the-backup)
7. [Troubleshooting](#troubleshooting)
8. [Best Practices](#best-practices)

---

## Overview

The TMG Backup System provides:

- **Automatic scheduled backups** (daily, every 3 days, weekly, or monthly)
- **Manual backup capability** via web interface
- **Compressed backups** using GZIP for storage efficiency
- **Backup history tracking** with download and delete functionality
- **Email notifications** for backup completion (optional)
- **Automatic cleanup** of old backups based on retention policy

---

## Prerequisites

Before setting up automatic backups, ensure you have:

1. **XAMPP installed** with MySQL/MariaDB running
2. **PHP 7.4 or higher** with CLI access
3. **Administrator access** to Windows (for Task Scheduler)
4. **Sufficient disk space** for backups (recommended: 10GB+)
5. **Write permissions** on the backup directory

---

## Database Setup

### Step 1: Run Database Migration

Execute the database migration to create the required tables and triggers:

```bash
# Navigate to XAMPP MySQL console
cd C:\xampp\mysql\bin

# Login to MySQL
mysql.exe -u root -p

# Select the TMG database
USE tmg_db;

# Run the migration script
source C:/xampp/htdocs/tmg/database/migrations/add_backup_system.sql;

# Verify tables were created
SHOW TABLES LIKE 'backup%';
```

You should see:
- `backup_settings`
- `backup_logs`

### Step 2: Verify Installation

```sql
-- Check backup settings
SELECT * FROM backup_settings;

-- Check for triggers
SHOW TRIGGERS LIKE 'backup%';

-- Check for stored procedures
SHOW PROCEDURE STATUS WHERE Name = 'calculate_next_backup_date';
```

---

## Backup Configuration

### Step 1: Access Backup Settings

1. Log in to TMG as an **Administrator**
2. Navigate to **System Administration** → **Backup Settings**
3. Configure the following settings:

#### Backup Status
- ☑️ **Enable Automatic Backups** - Turn this ON to enable scheduled backups

#### Backup Schedule
- **Frequency**: Choose from:
  - **Daily** - Backup every day
  - **Every 3 Days** - Backup twice a week
  - **Weekly** - Backup once a week
  - **Monthly** - Backup once a month
- **Backup Time**: Set the time of day (24-hour format, e.g., `02:00:00` for 2 AM)

#### Storage Settings
- **Backup Directory**: Default is `./backups/` (relative to TMG root)
  - Recommended: Use absolute path like `C:/backups/tmg/`
- **Maximum Backups to Keep**: Number of backups to retain (e.g., `10`)
  - Older backups are automatically deleted

#### Email Notifications (Optional)
- ☑️ **Send email after each backup** - Enable email alerts
- **Email Address**: Enter recipient email address

### Step 2: Save Settings

Click **Save Settings** to apply the configuration.

---

## Windows Task Scheduler Setup

### Method 1: Using Task Scheduler GUI

#### Step 1: Open Task Scheduler

1. Press `Win + R` to open Run dialog
2. Type `taskschd.msc` and press Enter
3. Click **Create Task** (not "Create Basic Task")

#### Step 2: General Tab

- **Name**: `TMG Database Backup`
- **Description**: `Automatic database backup for Traffic Management System`
- ☑️ **Run whether user is logged on or not**
- ☑️ **Run with highest privileges**
- **Configure for**: Windows 10

#### Step 3: Triggers Tab

Click **New** and configure:

- **Begin the task**: On a schedule
- **Settings**: Daily
- **Recur every**: 1 days
- **Start**: Today's date
- **Start time**: `01:00:00 AM` (or any time convenient)
- ☑️ **Enabled**

**Note**: The script will check if backup is due based on your settings. Running hourly or daily is fine - it won't create duplicate backups.

Click **OK**.

#### Step 4: Actions Tab

Click **New** and configure:

- **Action**: Start a program
- **Program/script**: `C:\xampp\php\php.exe`
- **Add arguments**: `"C:\xampp\htdocs\tmg\services\backup_scheduler.php"`
- **Start in**: `C:\xampp\htdocs\tmg\services`

Click **OK**.

#### Step 5: Conditions Tab

- ☑️ **Start the task only if the computer is on AC power** (optional - uncheck for laptops)
- ☑️ **Wake the computer to run this task** (optional)

#### Step 6: Settings Tab

- ☑️ **Allow task to be run on demand**
- ☑️ **Run task as soon as possible after a scheduled start is missed**
- **If the task fails, restart every**: 1 hour
- **Attempt to restart up to**: 3 times

Click **OK** to create the task.

#### Step 7: Enter Administrator Password

Enter your Windows administrator password when prompted.

---

### Method 2: Using Command Line

You can also create the task using PowerShell (Run as Administrator):

```powershell
# Create scheduled task
$action = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" -Argument '"C:\xampp\htdocs\tmg\services\backup_scheduler.php"' -WorkingDirectory "C:\xampp\htdocs\tmg\services"

$trigger = New-ScheduledTaskTrigger -Daily -At "01:00AM"

$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName "TMG Database Backup" -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description "Automatic database backup for Traffic Management System"
```

---

## Testing the Backup

### Test 1: Manual Backup via Web Interface

1. Go to **System Administration** → **Backups**
2. Click **Create Backup Now**
3. Wait for the backup to complete
4. Verify the backup appears in the backup list
5. Try downloading the backup file

### Test 2: Run Scheduler Manually

```bash
# Open Command Prompt as Administrator
cd C:\xampp\htdocs\tmg\services

# Run the backup scheduler
php backup_scheduler.php
```

Expected output:
```
=== Backup Scheduler Started ===
[2025-12-09 14:30:00] [INFO] Database connection established
[2025-12-09 14:30:00] [INFO] Backup Settings:
[2025-12-09 14:30:00] [INFO]   - Enabled: Yes
[2025-12-09 14:30:00] [INFO]   - Frequency: weekly
[2025-12-09 14:30:00] [INFO]   - Next Backup: 2025-12-16 02:00:00
[2025-12-09 14:30:00] [INFO] Backup is not due yet. Next backup in 156.5 hours (2025-12-16 02:00:00)
=== Backup Scheduler Finished ===
```

### Test 3: Force a Backup

To test if backup is due, you can temporarily modify the next backup date:

```sql
-- Set next backup to past date
UPDATE backup_settings SET next_backup_date = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = 1;
```

Then run:
```bash
php backup_scheduler.php
```

This should trigger a backup immediately.

### Test 4: Check Task Scheduler History

1. Open Task Scheduler
2. Find **TMG Database Backup** task
3. Click **History** tab (enable if disabled)
4. Verify task execution history

---

## Backup File Structure

Backups are stored in the configured directory with the following format:

```
backups/
├── tmg_backup_2025-12-09_02-00-00.sql.gz
├── tmg_backup_2025-12-10_02-00-00.sql.gz
├── tmg_backup_2025-12-11_02-00-00.sql.gz
└── ...
```

Each backup file contains:
- All database tables
- All data
- Database structure
- Compressed with GZIP (typically 10:1 ratio)

---

## Troubleshooting

### Issue 1: Task Doesn't Run

**Solution**:
1. Check Task Scheduler → Last Run Result
2. Verify PHP path: `C:\xampp\php\php.exe`
3. Verify script path: `C:\xampp\htdocs\tmg\services\backup_scheduler.php`
4. Check Windows Event Viewer for errors
5. Ensure task is set to "Run with highest privileges"

### Issue 2: Backup Fails with "mysqldump not found"

**Solution**:
1. Verify MySQL is installed: `C:\xampp\mysql\bin\mysqldump.exe`
2. Add MySQL to system PATH or update `BackupService.php` with full path

### Issue 3: Permission Denied on Backup Directory

**Solution**:
1. Ensure backup directory exists
2. Grant write permissions to the directory
3. Run as Administrator

### Issue 4: Backup File Size is 0 bytes

**Solution**:
1. Check database credentials in `includes/config.php`
2. Verify database connection
3. Check PHP error log: `C:\xampp\htdocs\tmg\php_errors.log`

### Issue 5: Email Notifications Not Sent

**Solution**:
1. Configure PHP `sendmail` settings in `php.ini`
2. Or use a mail service like SMTP
3. Check email address is valid

---

## Backup Logs

### Application Logs

Backup scheduler logs are stored at:
```
C:\xampp\htdocs\tmg\logs\backup_scheduler.log
```

### Database Logs

All backup attempts are logged in the `backup_logs` table:

```sql
-- View recent backups
SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 10;

-- View failed backups
SELECT * FROM backup_logs WHERE backup_status = 'failed';

-- View backup statistics
SELECT
    backup_status,
    COUNT(*) as count,
    SUM(backup_size) as total_size
FROM backup_logs
GROUP BY backup_status;
```

---

## Best Practices

### 1. Backup Frequency

- **Daily**: For production systems with frequent changes
- **Weekly**: For systems with moderate activity
- **Monthly**: For archival or low-activity systems

### 2. Retention Policy

- Keep at least **7 daily backups** for quick recovery
- Keep at least **4 weekly backups** for point-in-time recovery
- Store critical backups offsite or in cloud storage

### 3. Backup Time

- Schedule backups during **low-traffic hours** (e.g., 2-4 AM)
- Avoid peak usage times

### 4. Storage Location

- **Local storage**: Fast, but vulnerable to hardware failure
- **Network storage**: Better redundancy
- **Cloud storage**: Best for disaster recovery (use sync tools)

### 5. Backup Verification

- **Test restores monthly** to ensure backups are valid
- Monitor backup file sizes for anomalies
- Review backup logs weekly

### 6. Security

- **Restrict access** to backup files (admin only)
- **Encrypt backups** for sensitive data
- **Use HTTPS** when downloading backups

### 7. Monitoring

- Set up **email alerts** for failed backups
- Monitor **disk space** to prevent backup failures
- Review **backup logs** regularly

---

## Restoring from Backup

### Step 1: Download Backup File

1. Go to **System Administration** → **Backups**
2. Find the backup you want to restore
3. Click **Download** icon
4. Save the `.sql.gz` file

### Step 2: Extract Backup

```bash
# Using 7-Zip (Windows)
"C:\Program Files\7-Zip\7z.exe" e tmg_backup_2025-12-09_02-00-00.sql.gz

# Using gunzip (if available)
gunzip tmg_backup_2025-12-09_02-00-00.sql.gz
```

### Step 3: Restore Database

```bash
# Open Command Prompt
cd C:\xampp\mysql\bin

# Restore database
mysql.exe -u root -p tmg_db < C:\path\to\tmg_backup_2025-12-09_02-00-00.sql
```

**WARNING**: This will overwrite your current database. Make a backup first!

---

## Advanced Configuration

### Custom Backup Path

To store backups in a different location:

1. Go to **Backup Settings**
2. Set **Backup Directory** to: `C:/backups/tmg/`
3. Ensure the directory exists and is writable

### Backup to Network Drive

1. Map network drive (e.g., `Z:\`)
2. Set **Backup Directory** to: `Z:/tmg_backups/`
3. Ensure Task Scheduler has network access

### Multiple Backup Schedules

To create multiple backup schedules (e.g., daily + weekly):

1. Create additional scheduled tasks in Windows Task Scheduler
2. Each task calls the same script
3. Configure different retention policies manually

---

## Support

For issues or questions:

1. Check `php_errors.log` for errors
2. Check `logs/backup_scheduler.log` for backup details
3. Review `backup_logs` table in database
4. Contact system administrator

---

**Document Version**: 1.0
**Last Updated**: December 9, 2025
**Author**: TMG Development Team
