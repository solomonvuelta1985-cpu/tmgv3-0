-- =====================================================
-- TMG: Automatic Backup System Migration
-- =====================================================
-- This migration adds support for automatic database backups
-- with configurable schedules (daily, every 3 days, weekly, monthly)
-- =====================================================

-- Create backup_settings table
CREATE TABLE IF NOT EXISTS `backup_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `backup_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is automatic backup enabled',
  `backup_frequency` ENUM('daily', 'every_3_days', 'weekly', 'monthly') NOT NULL DEFAULT 'weekly',
  `backup_time` TIME NOT NULL DEFAULT '02:00:00' COMMENT 'Time of day to run backup (24-hour format)',
  `backup_path` VARCHAR(500) NOT NULL DEFAULT './backups/' COMMENT 'Path to store backups',
  `max_backups` INT(11) NOT NULL DEFAULT 10 COMMENT 'Maximum number of backups to keep',
  `email_notification` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Send email after backup',
  `notification_email` VARCHAR(255) DEFAULT NULL,
  `last_backup_date` DATETIME DEFAULT NULL,
  `next_backup_date` DATETIME DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Automatic backup configuration';

-- Insert default settings
INSERT INTO `backup_settings` (`id`, `backup_enabled`, `backup_frequency`, `backup_time`, `backup_path`, `max_backups`)
VALUES (1, 0, 'weekly', '02:00:00', './backups/', 10)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Create backup_logs table
CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `backup_filename` VARCHAR(255) NOT NULL,
  `backup_path` VARCHAR(500) NOT NULL,
  `backup_size` BIGINT(20) NOT NULL DEFAULT 0 COMMENT 'File size in bytes',
  `backup_type` ENUM('automatic', 'manual') NOT NULL DEFAULT 'automatic',
  `backup_status` ENUM('success', 'failed', 'in_progress') NOT NULL DEFAULT 'in_progress',
  `error_message` TEXT DEFAULT NULL,
  `database_name` VARCHAR(100) NOT NULL,
  `tables_count` INT(11) DEFAULT 0,
  `records_count` INT(11) DEFAULT 0,
  `compression` ENUM('none', 'gzip', 'zip') NOT NULL DEFAULT 'gzip',
  `created_by` INT(11) DEFAULT NULL COMMENT 'User who initiated backup (NULL for automatic)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_backup_status` (`backup_status`),
  KEY `idx_backup_type` (`backup_type`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Backup execution history';

-- Create index for faster queries
CREATE INDEX `idx_backup_filename` ON `backup_logs` (`backup_filename`);

-- =====================================================
-- Stored Procedure: Calculate Next Backup Date
-- =====================================================
DELIMITER $$

DROP PROCEDURE IF EXISTS `calculate_next_backup_date`$$

CREATE PROCEDURE `calculate_next_backup_date`()
BEGIN
    DECLARE v_frequency VARCHAR(20);
    DECLARE v_backup_time TIME;
    DECLARE v_next_date DATETIME;
    DECLARE v_base_date DATETIME;

    -- Get current settings
    SELECT backup_frequency, backup_time
    INTO v_frequency, v_backup_time
    FROM backup_settings
    WHERE id = 1;

    -- Use last backup date as base, or current date if no backup yet
    SELECT COALESCE(last_backup_date, NOW())
    INTO v_base_date
    FROM backup_settings
    WHERE id = 1;

    -- Calculate next backup date based on frequency
    CASE v_frequency
        WHEN 'daily' THEN
            SET v_next_date = DATE_ADD(v_base_date, INTERVAL 1 DAY);
        WHEN 'every_3_days' THEN
            SET v_next_date = DATE_ADD(v_base_date, INTERVAL 3 DAY);
        WHEN 'weekly' THEN
            SET v_next_date = DATE_ADD(v_base_date, INTERVAL 1 WEEK);
        WHEN 'monthly' THEN
            SET v_next_date = DATE_ADD(v_base_date, INTERVAL 1 MONTH);
    END CASE;

    -- Set the time component
    SET v_next_date = DATE_ADD(DATE(v_next_date), INTERVAL HOUR(v_backup_time) HOUR);
    SET v_next_date = DATE_ADD(v_next_date, INTERVAL MINUTE(v_backup_time) MINUTE);

    -- If calculated date is in the past, use current date + interval
    IF v_next_date < NOW() THEN
        CASE v_frequency
            WHEN 'daily' THEN
                SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 DAY);
            WHEN 'every_3_days' THEN
                SET v_next_date = DATE_ADD(NOW(), INTERVAL 3 DAY);
            WHEN 'weekly' THEN
                SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 WEEK);
            WHEN 'monthly' THEN
                SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 MONTH);
        END CASE;
        SET v_next_date = DATE_ADD(DATE(v_next_date), INTERVAL HOUR(v_backup_time) HOUR);
        SET v_next_date = DATE_ADD(v_next_date, INTERVAL MINUTE(v_backup_time) MINUTE);
    END IF;

    -- Update the next backup date
    UPDATE backup_settings
    SET next_backup_date = v_next_date
    WHERE id = 1;

    -- Note: Don't SELECT here as it causes issues when called from triggers
    -- The value is already updated in the table
END$$

DELIMITER ;

-- =====================================================
-- Trigger: Update next backup date after settings change
-- =====================================================
DELIMITER $$

DROP TRIGGER IF EXISTS `after_backup_settings_update`$$

CREATE TRIGGER `after_backup_settings_update`
AFTER UPDATE ON `backup_settings`
FOR EACH ROW
BEGIN
    -- Recalculate next backup date when settings change
    IF NEW.backup_frequency != OLD.backup_frequency
       OR NEW.backup_time != OLD.backup_time
       OR NEW.backup_enabled != OLD.backup_enabled THEN
        CALL calculate_next_backup_date();
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- Trigger: Log backup completion
-- =====================================================
DELIMITER $$

DROP TRIGGER IF EXISTS `after_backup_success`$$

CREATE TRIGGER `after_backup_success`
AFTER UPDATE ON `backup_logs`
FOR EACH ROW
BEGIN
    -- Update last backup date when backup succeeds
    IF NEW.backup_status = 'success' AND OLD.backup_status != 'success' THEN
        UPDATE backup_settings
        SET last_backup_date = NEW.created_at
        WHERE id = 1;

        -- Calculate next backup date
        CALL calculate_next_backup_date();
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- Initialize next backup date
-- =====================================================
CALL calculate_next_backup_date();

-- =====================================================
-- Grant necessary privileges for backup operations
-- =====================================================
-- Note: In production, ensure the database user has appropriate permissions:
-- GRANT SELECT, LOCK TABLES, SHOW VIEW ON tmg_db.* TO 'backup_user'@'localhost';
