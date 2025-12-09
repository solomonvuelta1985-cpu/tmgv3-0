-- Fix the backup triggers to avoid circular reference

-- Drop the problematic trigger
DROP TRIGGER IF EXISTS `after_backup_settings_update`;
DROP TRIGGER IF EXISTS `after_backup_success`;

-- =====================================================
-- BEFORE UPDATE Trigger: Calculate next backup date
-- This runs BEFORE the update, so we can modify NEW values
-- =====================================================
DELIMITER $$

CREATE TRIGGER `before_backup_settings_update`
BEFORE UPDATE ON `backup_settings`
FOR EACH ROW
BEGIN
    DECLARE v_next_date DATETIME;
    DECLARE v_base_date DATETIME;

    -- Only recalculate if frequency, time, or enabled status changed
    IF NEW.backup_frequency != OLD.backup_frequency
       OR NEW.backup_time != OLD.backup_time
       OR NEW.backup_enabled != OLD.backup_enabled THEN

        -- Use last backup date as base, or current date if no backup yet
        SET v_base_date = COALESCE(NEW.last_backup_date, NOW());

        -- Calculate next backup date based on frequency
        CASE NEW.backup_frequency
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
        SET v_next_date = DATE_ADD(DATE(v_next_date), INTERVAL HOUR(NEW.backup_time) HOUR);
        SET v_next_date = DATE_ADD(v_next_date, INTERVAL MINUTE(NEW.backup_time) MINUTE);

        -- If calculated date is in the past, use current date + interval
        IF v_next_date < NOW() THEN
            CASE NEW.backup_frequency
                WHEN 'daily' THEN
                    SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 DAY);
                WHEN 'every_3_days' THEN
                    SET v_next_date = DATE_ADD(NOW(), INTERVAL 3 DAY);
                WHEN 'weekly' THEN
                    SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 WEEK);
                WHEN 'monthly' THEN
                    SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 MONTH);
            END CASE;
            SET v_next_date = DATE_ADD(DATE(v_next_date), INTERVAL HOUR(NEW.backup_time) HOUR);
            SET v_next_date = DATE_ADD(v_next_date, INTERVAL MINUTE(NEW.backup_time) MINUTE);
        END IF;

        -- Set the next backup date in the NEW row (before it's saved)
        SET NEW.next_backup_date = v_next_date;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- AFTER UPDATE Trigger: Update next backup after successful backup
-- =====================================================
DELIMITER $$

CREATE TRIGGER `after_backup_log_success`
AFTER UPDATE ON `backup_logs`
FOR EACH ROW
BEGIN
    DECLARE v_next_date DATETIME;
    DECLARE v_frequency VARCHAR(20);
    DECLARE v_backup_time TIME;

    -- Only proceed if backup just became successful
    IF NEW.backup_status = 'success' AND OLD.backup_status != 'success' THEN

        -- Get current settings
        SELECT backup_frequency, backup_time
        INTO v_frequency, v_backup_time
        FROM backup_settings
        WHERE id = 1;

        -- Calculate next backup date based on frequency
        CASE v_frequency
            WHEN 'daily' THEN
                SET v_next_date = DATE_ADD(NEW.created_at, INTERVAL 1 DAY);
            WHEN 'every_3_days' THEN
                SET v_next_date = DATE_ADD(NEW.created_at, INTERVAL 3 DAY);
            WHEN 'weekly' THEN
                SET v_next_date = DATE_ADD(NEW.created_at, INTERVAL 1 WEEK);
            WHEN 'monthly' THEN
                SET v_next_date = DATE_ADD(NEW.created_at, INTERVAL 1 MONTH);
        END CASE;

        -- Set the time component
        SET v_next_date = DATE_ADD(DATE(v_next_date), INTERVAL HOUR(v_backup_time) HOUR);
        SET v_next_date = DATE_ADD(v_next_date, INTERVAL MINUTE(v_backup_time) MINUTE);

        -- Update backup settings with new dates
        UPDATE backup_settings
        SET last_backup_date = NEW.created_at,
            next_backup_date = v_next_date
        WHERE id = 1;
    END IF;
END$$

DELIMITER ;
